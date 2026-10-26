<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{LogisticsHub, LogisticsProvider, Shipment, ShipmentEvent, User};
use App\Support\LogisticsProviderContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SortingCenterScannerController extends Controller
{
    public function context(Request $request)
    {
        [$user, $provider, $center] = $this->scannerContext($request->user());

        return response()->json([
            'success' => true,
            'user' => ['id' => $user->id, 'name' => $user->name],
            'provider' => ['id' => $provider->id, 'name' => $provider->trade_name ?: $provider->company_name],
            'sorting_center' => $this->centerData($center),
            'scanner_ready' => true,
            'allowed_actions' => ['scan_waybill'],
        ]);
    }

    public function scan(Request $request)
    {
        $data = $request->validate(['waybill' => ['required', 'string', 'max:100']]);
        [$user, $provider, $center] = $this->scannerContext($request->user());
        $waybill = trim($data['waybill']);

        return DB::transaction(function () use ($user, $provider, $center, $waybill) {
            $provider = LogisticsProvider::query()->whereKey($provider->id)->lockForUpdate()->firstOrFail();
            $center = LogisticsHub::query()->whereKey($center->id)->lockForUpdate()->firstOrFail();
            abort_unless($provider->isOperational(), 403, 'The Logistics Provider is not operational.');
            abort_unless($center->logistics_provider_id === $provider->id && $center->status === 'active', 403, 'The assigned Sorting Center is unavailable.');

            $shipment = Shipment::query()
                ->where('logistics_provider_id', $provider->id)
                ->where(fn ($query) => $query->where('parcel_code', $waybill)->orWhere('shipment_number', $waybill))
                ->lockForUpdate()
                ->first();
            abort_unless($shipment, 404, 'Waybill not found.');

            $eventKey = "physical-arrival:{$shipment->id}:{$center->id}";
            $existing = ShipmentEvent::query()->where('event_key', $eventKey)->first();
            if ($existing) {
                return response()->json($this->scanResponse($shipment->fresh(['currentHub', 'nextHub', 'destinationHub']), $provider, $center, $existing, true));
            }

            if (in_array($shipment->status, Shipment::TERMINAL_STATUSES, true)) {
                throw ValidationException::withMessages(['waybill' => 'This Shipment is already terminal.']);
            }

            $expectedCenterId = $shipment->current_hub_id
                ? ($shipment->next_hub_id ?: $shipment->destination_hub_id)
                : ($shipment->origin_hub_id ?: $shipment->next_hub_id ?: $shipment->destination_hub_id);
            if (! $expectedCenterId) {
                throw ValidationException::withMessages(['waybill' => 'This Shipment has no expected Sorting Center. Logistics must assign its route first.']);
            }
            if ((int) $expectedCenterId !== (int) $center->id) {
                return response()->json(['success' => false, 'message' => 'This Waybill is not expected at the assigned Sorting Center.'], 409);
            }

            if (! in_array($shipment->status, ['picked_up', 'at_hub', 'hub_transfer', 'in_transit'], true)) {
                throw ValidationException::withMessages(['waybill' => 'This Shipment is not in a state that permits physical arrival.']);
            }

            $now = now();
            $shipment->update([
                'current_hub_id' => $center->id,
                'status' => 'at_sorting_center',
                'received_by' => $user->id,
                'received_at' => $now,
            ]);
            $event = $shipment->events()->create([
                'actor_id' => $user->id,
                'sorting_center_id' => $center->id,
                'status' => 'at_sorting_center',
                'event_type' => 'physical_arrival',
                'event_key' => $eventKey,
                'location' => $center->name,
                'note' => 'Parcel physically arrived at '.$center->name.'.',
                'metadata' => ['arrival_stage' => 'sorting_center', 'waybill' => $waybill, 'provider_id' => $provider->id],
            ]);

            return response()->json($this->scanResponse($shipment->fresh(['currentHub', 'nextHub', 'destinationHub']), $provider, $center, $event, false), 201);
        });
    }

    public function recent(Request $request)
    {
        [, $provider, $center] = $this->scannerContext($request->user());
        $limit = min(max((int) $request->input('per_page', 20), 1), 50);
        $scans = ShipmentEvent::query()
            ->where('sorting_center_id', $center->id)
            ->where('event_type', 'physical_arrival')
            ->whereHas('shipment', fn ($query) => $query->where('logistics_provider_id', $provider->id))
            ->with(['shipment.currentHub', 'shipment.nextHub', 'shipment.destinationHub'])
            ->latest()
            ->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $scans->getCollection()->map(fn (ShipmentEvent $event) => $this->scanResponse($event->shipment, $provider, $center, $event, false))->values(),
            'meta' => ['current_page' => $scans->currentPage(), 'last_page' => $scans->lastPage(), 'per_page' => $scans->perPage(), 'total' => $scans->total()],
        ]);
    }

    private function scannerContext(User $user): array
    {
        abort_unless($user->hasRole('logistics') && $user->is_active, 403, 'Logistics scanner access is required.');
        $provider = app(LogisticsProviderContext::class)->forUser($user);
        abort_unless($provider && $provider->isOperational(), 403, 'An approved, active Logistics Provider is required.');
        $center = $user->sortingCenter;
        abort_unless($center && (int) $center->logistics_provider_id === (int) $provider->id, 403, 'A Sorting Center assignment is required.');
        abort_unless($center->status === 'active', 403, 'The assigned Sorting Center is inactive.');

        return [$user, $provider, $center];
    }

    private function centerData(LogisticsHub $center): array
    {
        return [
            'id' => $center->id,
            'code' => $center->code,
            'name' => $center->name,
            'address' => $center->address,
            'street' => $center->street,
            'barangay' => $center->barangay,
            'municipality' => $center->municipality,
            'province' => $center->province,
            'postal_code' => $center->postal_code,
            'latitude' => $center->latitude,
            'longitude' => $center->longitude,
            'contact_phone' => $center->contact_phone,
            'operating_hours' => $center->operating_hours,
        ];
    }

    private function scanResponse(Shipment $shipment, LogisticsProvider $provider, LogisticsHub $center, ShipmentEvent $event, bool $duplicate): array
    {
        return [
            'success' => true,
            'duplicate' => $duplicate,
            'message' => $duplicate ? 'Waybill arrival was already recorded at this Sorting Center.' : 'Waybill arrival recorded.',
            'shipment_id' => $shipment->id,
            'waybill' => $shipment->parcel_code ?: $shipment->shipment_number,
            'tracking_number' => $shipment->shipment_number,
            'provider' => ['id' => $provider->id, 'name' => $provider->trade_name ?: $provider->company_name],
            'sorting_center' => $this->centerData($center),
            'arrival_timestamp' => $event->created_at?->toIso8601String(),
            'shipment_current_status' => $shipment->status,
            'current_center' => $shipment->currentHub ? $this->centerData($shipment->currentHub) : null,
            'next_center' => $shipment->nextHub ? $this->centerData($shipment->nextHub) : null,
            'destination_center' => $shipment->destinationHub ? $this->centerData($shipment->destinationHub) : null,
            'allowed_actions' => ['view_scan_result'],
        ];
    }
}
