<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{DeliveryArea, LogisticsHub, Shipment, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SortingCenterManagementController extends Controller
{
    public function update(Request $request, LogisticsHub $hub)
    {
        $providerId = $this->providerId($request);
        $hub = LogisticsHub::forProvider($providerId)->whereKey($hub->id)->firstOrFail();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:logistics_hubs,code,'.$hub->id],
            'name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'street' => ['nullable', 'string', 'max:150'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'operating_hours' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'in:active,inactive,maintenance'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $hub->update($data);

        return back()->with('success', 'Sorting Center details saved.');
    }

    public function assignScanner(Request $request, LogisticsHub $hub)
    {
        $providerId = $this->providerId($request);
        $hub = LogisticsHub::forProvider($providerId)->whereKey($hub->id)->firstOrFail();
        $data = $request->validate(['user_id' => ['nullable', 'integer', 'exists:users,id']]);

        DB::transaction(function () use ($data, $hub, $providerId): void {
            if ($data['user_id'] ?? null) {
                $user = User::query()->whereKey($data['user_id'])->where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', 'logistics'))->whereHas('logisticsProviders', fn ($query) => $query->whereKey($providerId)->where('logistics_provider_user.is_active', true))->firstOrFail();
                $user->update(['sorting_center_id' => $hub->id]);
            }
        });

        return back()->with('success', 'Sorting Center scanner assignment saved.');
    }

    public function syncCoverage(Request $request, LogisticsHub $hub)
    {
        $providerId = $this->providerId($request);
        $hub = LogisticsHub::forProvider($providerId)->whereKey($hub->id)->firstOrFail();
        $data = $request->validate(['delivery_area_ids' => ['nullable', 'array'], 'delivery_area_ids.*' => ['integer', 'exists:delivery_areas,id']]);
        $areaIds = collect($data['delivery_area_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        abort_unless(DeliveryArea::forProvider($providerId)->whereIn('id', $areaIds)->count() === $areaIds->count(), 404);
        $hub->coverageAreas()->sync($areaIds);

        return back()->with('success', 'Sorting Center coverage saved.');
    }

    public function updateRoute(Request $request, Shipment $shipment)
    {
        $providerId = $this->providerId($request);
        $shipment = Shipment::forProvider($providerId)->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
        $data = $request->validate([
            'origin_hub_id' => ['nullable', 'integer', 'exists:logistics_hubs,id'],
            'next_hub_id' => ['nullable', 'integer', 'exists:logistics_hubs,id'],
            'destination_hub_id' => ['nullable', 'integer', 'exists:logistics_hubs,id'],
        ]);
        $ids = collect($data)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        abort_unless(LogisticsHub::forProvider($providerId)->whereIn('id', $ids)->where('status', 'active')->count() === $ids->count(), 404);
        $shipment->update($data);
        $shipment->events()->create(['actor_id' => $request->user()->id, 'status' => $shipment->status, 'event_type' => 'center_route_planned', 'note' => 'Sorting Center route updated by Logistics.']);

        return back()->with('success', 'Sorting Center route saved.');
    }

    private function providerId(Request $request): int
    {
        return (int) $request->attributes->get('logistics_provider')->id;
    }
}
