<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{HubTransfer, HubTransferTrip, LogisticsHub, RiderProfile, Shipment, Vehicle};
use App\Services\{HubTransferService, HubTransferTripService};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HubTransferController extends Controller
{
    public function index(Request $request)
    {
        $providerId = (int) $request->attributes->get('logistics_provider')->id;
        $data = $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:pending,assigned,in_transit,arrived,completed,exception']);
        $transfers = HubTransfer::forProvider($providerId)
            ->with(['shipment.store', 'originHub', 'destinationHub', 'driver', 'vehicle', 'tripItem'])
            ->when($data['q'] ?? null, function ($query, $term) {
                $query->where(function ($query) use ($term) {
                    $query->where('transfer_reference', 'like', "%{$term}%")
                        ->orWhereHas('shipment', fn ($shipment) => $shipment->where('parcel_code', 'like', "%{$term}%")->orWhere('shipment_number', 'like', "%{$term}%"));
                });
            })
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('updated_at')->paginate(20)->withQueryString();
        // The trip layer is additive. Keep the existing page healthy until its
        // reviewed migration is applied in the target environment.
        $trips = Schema::hasTable('hub_transfer_trips')
            ? HubTransferTrip::forProvider($providerId)
                ->with(['originHub', 'destinationHub', 'driver', 'vehicle', 'items.shipment'])
                ->when($data['q'] ?? null, function ($query, $term) {
                    $query->where(function ($query) use ($term) {
                        $query->where('trip_reference', 'like', "%{$term}%")
                            ->orWhereHas('items.shipment', fn ($shipment) => $shipment->where('parcel_code', 'like', "%{$term}%")->orWhere('shipment_number', 'like', "%{$term}%"));
                    });
                })
                ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest('updated_at')->paginate(10, ['*'], 'trips')->withQueryString()
            : collect();

        $summary = collect(HubTransfer::forProvider($providerId)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'));
        $hubs = LogisticsHub::forProvider($providerId)->where('status', 'active')->orderBy('name')->get();
        $drivers = RiderProfile::forProvider($providerId)->with('user')->assignmentEligible()->get()->filter(fn ($profile) => $profile->user?->is_active)->values();
        $vehicles = Vehicle::query()->where('logistics_provider_id', $providerId)->whereIn('status', ['available', 'in_use'])->orderBy('code')->get();
        $transferableShipments = Shipment::forProvider($providerId)
            ->with(['currentHub', 'destinationHub', 'store'])
            ->where('status', 'sorted')
            ->whereNotNull('current_hub_id')
            ->whereNotNull('received_at')
            ->whereNotNull('parcel_scanned_at')
            ->whereNotNull('sorted_at')
            ->whereDoesntHave('hubTransfers', fn ($query) => $query->whereNotIn('status', ['completed', 'exception']))
            ->latest('updated_at')->get();

        $transportDrivers = $drivers->filter(fn ($profile) => in_array($profile->rider_type, ['transport', 'both'], true))->values();
        return view('logistics.hub-transfers', compact('transfers', 'trips', 'summary', 'hubs', 'drivers', 'transportDrivers', 'vehicles', 'transferableShipments', 'data'));
    }

    public function store(Request $request, HubTransferService $service)
    {
        $data = $request->validate([
            'shipment_id' => 'nullable|integer|exists:shipments,id',
            'shipment_ids' => 'nullable|array',
            'shipment_ids.*' => 'integer|distinct|exists:shipments,id',
            'transfer_ids' => 'nullable|array',
            'transfer_ids.*' => 'integer|distinct|exists:hub_transfers,id',
            'destination_hub_id' => 'nullable|integer|exists:logistics_hubs,id',
        ]);
        $shipmentIds = collect($data['shipment_ids'] ?? [])->push($data['shipment_id'] ?? null)->filter()->unique()->values();
        $transferIds = collect($data['transfer_ids'] ?? [])->filter()->unique()->values();
        if ($shipmentIds->isNotEmpty() && $transferIds->isNotEmpty()) {
            throw ValidationException::withMessages(['shipment_ids' => 'Choose sorted Parcels or pending Transfers, not both.']);
        }
        if ($transferIds->isNotEmpty()) {
            abort_unless(Schema::hasTable('hub_transfer_trips'), 503, 'Multi-parcel transfer trips are not enabled in this environment.');
            if ($transferIds->count() < 2) throw ValidationException::withMessages(['transfer_ids' => 'Select at least two pending Transfers for one trip.']);
            app(HubTransferTripService::class)->create($transferIds->all(), $request->user());
            return back()->with('success', 'Transfer Trip created. Assign a Transport Driver and vehicle before dispatch.');
        }
        if ($shipmentIds->isEmpty()) throw ValidationException::withMessages(['shipment_ids' => 'Select at least one eligible sorted Parcel.']);
        if (empty($data['destination_hub_id'])) throw ValidationException::withMessages(['destination_hub_id' => 'Choose an active destination Sorting Center.']);
        if ($shipmentIds->count() > 1) abort_unless(Schema::hasTable('hub_transfer_trips'), 503, 'Multi-parcel transfer trips are not enabled in this environment.');
        $transfers = DB::transaction(function () use ($shipmentIds, $data, $request, $service) {
            $transfers = $shipmentIds->map(function ($shipmentId) use ($data, $request, $service) {
                $shipment = Shipment::query()->whereKey($shipmentId)->firstOrFail();
                return $service->create($shipment, $request->user(), (int) $data['destination_hub_id']);
            });
            if ($transfers->count() > 1) {
                app(HubTransferTripService::class)->create($transfers->pluck('id')->all(), $request->user());
            }
            return $transfers;
        });
        if ($transfers->count() > 1) {
            return back()->with('success', 'Transfer Trip created. Assign a Transport Driver and vehicle before dispatch.');
        }
        return back()->with('success', 'Hub Transfer created and awaiting driver assignment.');
    }

    public function assign(Request $request, HubTransfer $transfer, HubTransferService $service)
    {
        $data = $request->validate(['transfer_driver_id' => 'required|integer|exists:users,id', 'vehicle_id' => 'nullable|integer|exists:vehicles,id']);
        $service->assignDriver($transfer, $request->user(), (int) $data['transfer_driver_id'], isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null);
        return back()->with('success', 'Hub Transfer driver assigned.');
    }

    public function dispatch(HubTransfer $transfer, Request $request, HubTransferService $service)
    {
        $service->dispatch($transfer, $request->user());
        return back()->with('success', 'Hub Transfer dispatched.');
    }

    public function arrive(HubTransfer $transfer, Request $request, HubTransferService $service)
    {
        $service->arrive($transfer, $request->user());
        return back()->with('success', 'Transfer arrival recorded. Destination receipt scan is still required.');
    }

    public function receive(HubTransfer $transfer, Request $request, HubTransferService $service)
    {
        $data = $request->validate(['parcel_code' => 'required|string|max:100']);
        $service->receive($transfer, $request->user(), $data['parcel_code']);
        return back()->with('success', 'Destination receipt verified. The Parcel is ready for destination scanning and sorting.');
    }

    public function exception(HubTransfer $transfer, Request $request, HubTransferService $service)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $service->exception($transfer, $request->user(), $data['reason']);
        return back()->with('success', 'Hub Transfer exception recorded.');
    }

    public function createTrip(Request $request, HubTransferTripService $service)
    {
        abort_unless(Schema::hasTable('hub_transfer_trips'), 503, 'Multi-parcel transfer trips are not enabled in this environment.');
        $data = $request->validate(['transfer_ids' => 'required|array|min:2', 'transfer_ids.*' => 'integer|exists:hub_transfers,id']);
        $service->create($data['transfer_ids'], $request->user());
        return back()->with('success', 'Multi-parcel Transfer Trip created. Assign a Transport Driver and vehicle before dispatch.');
    }

    public function assignTrip(Request $request, int $trip, HubTransferTripService $service)
    {
        $trip = $this->tripOr503($trip);
        $data = $request->validate(['transport_driver_id' => 'required|integer|exists:users,id', 'vehicle_id' => 'nullable|integer|exists:vehicles,id']);
        $service->assign($trip, $request->user(), (int) $data['transport_driver_id'], isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null);
        return back()->with('success', 'Transport Driver assigned to the Transfer Trip.');
    }

    public function dispatchTrip(int $trip, Request $request, HubTransferTripService $service) { $trip = $this->tripOr503($trip); $service->dispatch($trip, $request->user()); return back()->with('success', 'Transfer Trip dispatched.'); }
    public function arriveTrip(int $trip, Request $request, HubTransferTripService $service) { $trip = $this->tripOr503($trip); $service->arrive($trip, $request->user()); return back()->with('success', 'Transfer Trip arrival recorded. Verify each parcel receipt.'); }
    public function receiveTripItem(int $trip, int $item, Request $request, HubTransferTripService $service) { $trip = $this->tripOr503($trip); $data = $request->validate(['parcel_code' => 'required|string|max:100']); $service->receive($trip, $request->user(), $item, $data['parcel_code']); return back()->with('success', 'Parcel receipt verified.'); }
    public function exceptionTripItem(int $trip, int $item, Request $request, HubTransferTripService $service) { $trip = $this->tripOr503($trip); $data = $request->validate(['reason' => 'required|string|max:500']); $service->exceptionItem($trip, $request->user(), $item, $data['reason']); return back()->with('success', 'Manifest parcel exception recorded.'); }
    public function exceptionTrip(int $trip, Request $request, HubTransferTripService $service) { $trip = $this->tripOr503($trip); $data = $request->validate(['reason' => 'required|string|max:500']); $service->exception($trip, $request->user(), $data['reason']); return back()->with('success', 'Transfer Trip exception recorded.'); }

    private function tripOr503(int $tripId): HubTransferTrip
    {
        abort_unless(Schema::hasTable('hub_transfer_trips'), 503, 'Multi-parcel transfer trips are not enabled in this environment.');
        return HubTransferTrip::query()->whereKey($tripId)->firstOrFail();
    }
}
