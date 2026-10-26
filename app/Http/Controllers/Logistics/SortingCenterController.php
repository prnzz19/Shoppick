<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{DeliveryArea, LogisticsHub, RiderProfile, Shipment, User};
use App\Services\ParcelWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class SortingCenterController extends Controller
{
    private function providerId(Request $request): int { return $request->attributes->get('logistics_provider')->id; }
    private function assertOwnedShipment(Request $request, Shipment $shipment): void { abort_unless($shipment->logistics_provider_id === $this->providerId($request), 404); }
    public function account(){return view('logistics.account');}
    public function pickupRequests(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:ready_for_pickup,pickup_assigned,pickup_accepted,picked_up',
        ]);
        $providerId = $this->providerId($request);
        $statuses = ['ready_for_pickup', 'pickup_assigned', 'pickup_accepted', 'picked_up'];
        $baseQuery = Shipment::forProvider($providerId)->whereIn('status', $statuses);
        $query = (clone $baseQuery)
            ->with([
                'store', 'order', 'sellerOrder.items', 'pickupRider.riderProfile',
                'events' => fn ($events) => $events->whereIn('event_type', ['automatic_pickup_assignment', 'pickup_assigned', 'pickup_accepted', 'picked_up'])->latest('created_at'),
            ])
            ->when($data['q'] ?? null, function ($query, $value) {
                $query->where(function ($query) use ($value) {
                    $query->where('parcel_code', 'like', "%{$value}%")
                        ->orWhere('shipment_number', 'like', "%{$value}%")
                        ->orWhereHas('store', fn ($store) => $store->where('name', 'like', "%{$value}%"));
                });
            })
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
        $shipments = $query->latest('updated_at')->paginate(20)->withQueryString();
        $summary = [
            'waiting' => (clone $baseQuery)->where('status', 'ready_for_pickup')->count(),
            'assigned' => (clone $baseQuery)->whereIn('status', ['pickup_assigned', 'pickup_accepted'])->count(),
            'picked_up' => (clone $baseQuery)->where('status', 'picked_up')->count(),
            'exceptions' => Shipment::forProvider($providerId)->whereIn('status', ['exception', 'delivery_failed', 'returned'])->count(),
        ];
        $riders = $this->availableRiders($request->user());
        return view('logistics.pickup-requests', compact('shipments', 'riders', 'summary', 'data'));
    }
    public function assignPickup(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $this->assertOwnedShipment($request, $shipment);
        $data = $request->validate(['rider_id' => 'required|exists:users,id']);
        $workflow->assignPickup($shipment, $request->user(), User::findOrFail($data['rider_id']));
        return back()->with('success', 'Pickup Rider assigned.');
    }

    public function incoming(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'arrival' => 'nullable|in:in_transit,ready,received',
            'rider_id' => 'nullable|integer',
            'hub_id' => 'nullable|integer',
        ]);
        $providerId = $this->providerId($request);
        $hubs = LogisticsHub::forProvider($providerId)->orderByDesc('status')->orderBy('name')->get();
        $riders = RiderProfile::forProvider($providerId)
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->get();
        $allowedRiderIds = $riders->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $allowedHubIds = $hubs->pluck('id')->map(fn ($id) => (int) $id)->all();
        $baseQuery = Shipment::forProvider($providerId)->whereIn('status', ['picked_up', 'at_sorting_center']);
        $query = (clone $baseQuery)
            ->with([
                'store',
                'order',
                'pickupRider.riderProfile.vehicle',
                'currentHub',
                'destinationHub',
                'events' => fn ($events) => $events->latest('created_at'),
            ])
            ->when($data['q'] ?? null, function ($query, $value) {
                $query->where(function ($query) use ($value) {
                    $query->where('parcel_code', 'like', "%{$value}%")
                        ->orWhere('shipment_number', 'like', "%{$value}%")
                        ->orWhereHas('store', fn ($store) => $store->where('name', 'like', "%{$value}%"))
                        ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', "%{$value}%"));
                });
            })
            ->when($data['rider_id'] ?? null, function ($query, $riderId) use ($allowedRiderIds) {
                $query->whereIn('pickup_rider_id', $allowedRiderIds)->where('pickup_rider_id', (int) $riderId);
            })
            ->when($data['hub_id'] ?? null, function ($query, $hubId) use ($allowedHubIds) {
                $query->whereIn('current_hub_id', $allowedHubIds)->where('current_hub_id', (int) $hubId);
            });
        match ($data['arrival'] ?? null) {
            'in_transit' => $query->where('status', 'picked_up')->whereNull('pickup_arrived_at'),
            'ready' => $query->where('status', 'picked_up')->whereNotNull('pickup_arrived_at'),
            'received' => $query->where('status', 'at_sorting_center'),
            default => null,
        };
        $shipments = $query->latest('updated_at')->paginate(20)->withQueryString();
        $summary = [
            'expected' => (clone $baseQuery)->count(),
            'in_transit' => (clone $baseQuery)->where('status', 'picked_up')->whereNull('pickup_arrived_at')->count(),
            'ready' => (clone $baseQuery)->where('status', 'picked_up')->whereNotNull('pickup_arrived_at')->count(),
            'received' => (clone $baseQuery)->where('status', 'at_sorting_center')->count(),
        ];
        return view('logistics.incoming-parcels', compact('shipments', 'summary', 'riders', 'hubs', 'data'));
    }
    public function receive(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $this->assertOwnedShipment($request, $shipment);
        $workflow->receive($shipment, $request->user());
        return back()->with('success', 'Parcel received at the Sorting Center.');
    }

    public function scan(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $this->assertOwnedShipment($request, $shipment);
        $data = $request->validate(['parcel_code' => 'required|string|max:100']);
        $workflow->confirmScan($shipment, $request->user(), $data['parcel_code']);
        return back()->with('success', 'Parcel code confirmed.');
    }

    public function sorting(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:awaiting_scan,ready_to_sort,sorted',
            'area_id' => 'nullable|integer',
            'hub_id' => 'nullable|integer',
            'region_code' => 'nullable|string|max:20',
            'province_code' => 'nullable|string|max:20',
            'municipality_code' => 'nullable|string|max:20',
            'barangay_code' => 'nullable|string|max:20',
        ]);
        $providerId = $this->providerId($request);
        $hubs = $this->authorizedSortingCenters($request);
        $hub = $this->selectedSortingCenter($request, $hubs);
        if ($hub && ! empty($data['area_id'])) $this->assertSortingAreaAllowed($request, $hub, (int) $data['area_id']);
        $areaQuery = $hub ? $this->sortingAreasQuery($request, $hub) : DeliveryArea::forProvider($providerId)->whereRaw('1 = 0');
        $areaQuery->orderByDesc('is_active')->orderBy('name');
        if (Schema::hasTable('delivery_area_barangay') && Schema::hasTable('psgc_barangays')) $areaQuery->with('psgcBarangays');
        $areas = $areaQuery->get();
        $barangayCatalog = Schema::hasTable('psgc_barangays') ? $this->officialBarangaysQuery()->orderBy('municipality')->orderBy('name')->get() : collect();
        $configuredMunicipalities = $hub ? $this->sortingMunicipalities($request, $hub) : collect();
        // Sorting filters are operationally scoped. The national PSGC catalog
        // remains available through Hub Service Coverage configuration.
        $sortingMunicipalities = $configuredMunicipalities;
        $sortingGeography = $this->sortingGeography($configuredMunicipalities);
        if ($hub) $this->applyReadOnlyGeographySelection($request, $hub, $data);
        $selectedMunicipalityCode = (string) ($data['municipality_code'] ?? ($sortingMunicipalities->count() === 1 ? $sortingMunicipalities->first()['code'] : ''));
        if ($selectedMunicipalityCode !== '' && ! $sortingMunicipalities->contains('code', $selectedMunicipalityCode)) abort(422, 'The selected municipality is not present in the verified PSGC catalog.');
        $sortingBarangays = $hub ? $this->sortingBarangayRows($request, $hub, $selectedMunicipalityCode ?: null) : collect();
        $coveredBarangayIds = $hub ? $this->coveredBarangayIds($request, $hub) : collect();
        if (! empty($data['barangay_code'])) {
            $selectedBarangay = $sortingBarangays->firstWhere('code', $data['barangay_code']);
            abort_unless($selectedBarangay, 422, 'The selected barangay is not present in the verified PSGC catalog.');
            $data['barangay_name'] = $selectedBarangay->name;
        }
        if ($selectedMunicipalityCode !== '') {
            $data['municipality_name'] = $sortingMunicipalities->firstWhere('code', $selectedMunicipalityCode)['name'] ?? null;
        }
        $data['municipality_code'] = $selectedMunicipalityCode ?: null;
        $baseQuery = $hub
            ? Shipment::forProvider($providerId)->where('current_hub_id', $hub->id)->whereIn('status', ['at_sorting_center', 'sorted'])
            : Shipment::forProvider($providerId)->whereRaw('1 = 0');
        $query = $this->applySortingSearch(
            (clone $baseQuery)->with(['store', 'order.user', 'sellerOrder.items', 'pickupRider.riderProfile', 'rider.riderProfile', 'deliveryArea', 'currentHub', 'destinationHub']),
            $data
        );
        match ($data['status'] ?? null) {
            'awaiting_scan' => $query->where('status', 'at_sorting_center')->whereNull('parcel_scanned_at'),
            'ready_to_sort' => $query->where('status', 'at_sorting_center')->whereNotNull('parcel_scanned_at'),
            'sorted' => $query->where('status', 'sorted'),
            default => null,
        };
        $shipments = $query->latest('updated_at')->paginate(20)->withQueryString();
        $geoCatalog = Schema::hasTable('psgc_barangays') ? $this->officialBarangaysQuery()->get() : collect();
        $geoDetails = $shipments->getCollection()->mapWithKeys(function ($shipment) use ($geoCatalog) {
            $address = (array) $shipment->delivery_address;
            $municipality = trim((string) ($address['municipality'] ?? $address['city'] ?? ''));
            $barangay = trim((string) ($address['barangay'] ?? ''));
            $code = trim((string) ($address['barangay_code'] ?? ''));
            $match = $code !== '' ? $geoCatalog->first(fn ($row) => (string) $row->code === $code) : null;
            if (! $match && $barangay !== '' && $municipality !== '') $match = $geoCatalog->first(fn ($row) => mb_strtolower($row->name) === mb_strtolower($barangay) && mb_strtolower($row->municipality) === mb_strtolower($municipality));
            return [$shipment->id => ['municipality' => $municipality ?: 'Not recorded', 'barangay' => $barangay ?: 'Not recorded', 'verified' => (bool) $match, 'psgc' => $match?->code]];
        });
        $detectedAreas = $shipments->getCollection()->mapWithKeys(function ($shipment) use ($areas) {
            $province = strtolower((string) data_get($shipment->delivery_address, 'province'));
            $municipality = strtolower((string) (data_get($shipment->delivery_address, 'municipality') ?: data_get($shipment->delivery_address, 'city')));
            $barangay = strtolower((string) data_get($shipment->delivery_address, 'barangay'));
            $barangayCode = (string) data_get($shipment->delivery_address, 'barangay_code');
            $match = $areas->where('is_active', true)->first(function ($area) use ($province, $municipality, $barangay, $barangayCode) {
                if ($province && strtolower($area->province) !== $province) return false;
                if ($area->municipality && strtolower($area->municipality) !== $municipality) return false;
                $covered = collect($area->barangays)->map(fn ($value) => strtolower($value));
                $official = $area->relationLoaded('psgcBarangays') ? $area->psgcBarangays : collect();
                if ($official->isNotEmpty()) return $official->contains(fn ($row) => strtolower($row->name) === $barangay || (string) $row->code === $barangayCode);
                return $covered->isEmpty() || $covered->contains($barangay);
            });
            return [$shipment->id => $match];
        });
        $summary = $this->sortingSummaryFor($this->applySortingSearch(clone $baseQuery, $data));
        $data['hub_id'] = $hub?->id;
        $sortingRegions = $sortingGeography['regions'];
        $sortingProvinces = $sortingGeography['provinces'];
        return view('logistics.sorting', compact('shipments', 'areas', 'hubs', 'hub', 'barangayCatalog', 'sortingRegions', 'sortingProvinces', 'sortingMunicipalities', 'sortingBarangays', 'coveredBarangayIds', 'detectedAreas', 'geoDetails', 'summary', 'data'));
    }

    public function sortingCenters(Request $request)
    {
        $hubs = $this->authorizedSortingCenters($request);
        return response()->json(['data' => $hubs->map(fn ($hub) => [
            'id' => $hub->id,
            'code' => $hub->code,
            'name' => $hub->name,
            'municipality' => $hub->municipality,
            'province' => $hub->province,
        ])->values()]);
    }

    public function sortingDeliveryAreas(Request $request)
    {
        $data = $request->validate(['hub_id' => 'required|integer']);
        $hubs = $this->authorizedSortingCenters($request);
        $hub = $this->selectedSortingCenter($request, $hubs, (int) $data['hub_id']);
        $areas = $this->sortingAreasQuery($request, $hub)
            ->orderBy('name')->get(['id', 'code', 'name', 'municipality', 'province']);
        return response()->json(['hub_id' => $hub->id, 'data' => $areas->map(fn ($area) => [
            'id' => $area->id, 'code' => $area->code, 'name' => $area->name,
            'municipality' => $area->municipality, 'province' => $area->province,
        ])->values()]);
    }

    public function sortingBarangays(Request $request)
    {
        $data = $request->validate(['hub_id' => 'required|integer', 'municipality_code' => 'nullable|string|max:20']);
        abort_unless(Schema::hasTable('psgc_barangays'), 503, 'The verified PSGC barangay catalog is not available in this environment.');
        $hubs = $this->authorizedSortingCenters($request);
        $hub = $this->selectedSortingCenter($request, $hubs, (int) $data['hub_id']);
        $configuredMunicipalities = $this->sortingMunicipalities($request, $hub);
        // Sorting filters may only expose municipalities configured for the
        // selected provider-owned hub. The nationwide PSGC catalog remains a
        // reference source in Service Coverage, not an operational queue.
        $municipalities = $configuredMunicipalities->map(fn ($row) => [
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'province' => (string) ($row['province'] ?? ''),
            'province_code' => $row['province_code'] ?? null,
            'region_code' => $row['region_code'] ?? null,
            'coverage_status' => 'covered',
        ])->values();
        abort_unless($municipalities->isNotEmpty(), 503, 'Geographic data has not been loaded.');
        $municipalityCode = $data['municipality_code'] ?? ($municipalities->count() === 1 ? $municipalities->first()['code'] : null);
        if ($municipalityCode !== null) abort_unless($municipalities->contains('code', $municipalityCode), 422, 'The selected municipality is not present in the verified PSGC catalog.');
        $barangays = $this->sortingBarangayRows($request, $hub, $municipalityCode);
        if ($municipalityCode !== null) abort_unless($barangays->isNotEmpty(), 503, 'No verified PSGC rows are available for this municipality.');
        $coveredIds = $this->coveredBarangayIds($request, $hub);
        $sortingGeography = $this->sortingGeography($configuredMunicipalities);
        return response()->json([
            'hub' => ['id' => $hub->id, 'code' => $hub->code, 'name' => $hub->name],
            'regions' => $sortingGeography['regions']->values(),
            'provinces' => $sortingGeography['provinces']->values(),
            'municipalities' => $municipalities->values(),
            'selected_municipality_code' => $municipalityCode,
            'data' => $barangays->map(fn ($barangay) => [
                'code' => $barangay->code, 'name' => $barangay->name,
                'municipality_code' => $barangay->municipality_code, 'municipality' => $barangay->municipality,
                'province' => $barangay->province,
                'province_code' => $municipalities->firstWhere('code', (string) $barangay->municipality_code)['province_code'] ?? null,
                'region_code' => $municipalities->firstWhere('code', (string) $barangay->municipality_code)['region_code'] ?? null,
                'coverage_status' => $coveredIds->contains((int) $barangay->id) ? 'covered' : 'unconfigured',
            ])->values(),
        ]);
    }

    public function sortingSummary(Request $request)
    {
        $data = $request->validate([
            'hub_id' => 'required|integer', 'q' => 'nullable|string|max:100', 'area_id' => 'nullable|integer',
            'region_code' => 'nullable|string|max:20', 'province_code' => 'nullable|string|max:20',
            'municipality_code' => 'nullable|string|max:20', 'barangay_code' => 'nullable|string|max:20',
        ]);
        $hubs = $this->authorizedSortingCenters($request);
        $hub = $this->selectedSortingCenter($request, $hubs, (int) $data['hub_id']);
        if (! empty($data['area_id'])) $this->assertSortingAreaAllowed($request, $hub, (int) $data['area_id']);
        $this->applyReadOnlyGeographySelection($request, $hub, $data);
        $this->applyReadOnlyBarangaySelection($request, $hub, $data);
        $base = $this->sortingQueueQuery($request, (int) $hub->id);
        $base = $this->applySortingSearch($base, $data);
        return response()->json(['hub_id' => $hub->id, 'summary' => $this->sortingSummaryFor($base)]);
    }

    public function sortingQueue(Request $request)
    {
        $data = $request->validate([
            'hub_id' => 'required|integer', 'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:awaiting_scan,ready_to_sort,sorted', 'area_id' => 'nullable|integer',
            'region_code' => 'nullable|string|max:20', 'province_code' => 'nullable|string|max:20',
            'municipality_code' => 'nullable|string|max:20', 'barangay_code' => 'nullable|string|max:20',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $hubs = $this->authorizedSortingCenters($request);
        $hub = $this->selectedSortingCenter($request, $hubs, (int) $data['hub_id']);
        if (! empty($data['area_id'])) $this->assertSortingAreaAllowed($request, $hub, (int) $data['area_id']);
        $this->applyReadOnlyGeographySelection($request, $hub, $data);
        $this->applyReadOnlyBarangaySelection($request, $hub, $data);
        $query = $this->applySortingSearch($this->sortingQueueQuery($request, (int) $hub->id), $data);
        match ($data['status'] ?? null) {
            'awaiting_scan' => $query->where('status', 'at_sorting_center')->whereNull('parcel_scanned_at'),
            'ready_to_sort' => $query->where('status', 'at_sorting_center')->whereNotNull('parcel_scanned_at'),
            'sorted' => $query->where('status', 'sorted'),
            default => null,
        };
        $shipments = $query->latest('updated_at')->paginate($data['per_page'] ?? 20)->withQueryString();
        return response()->json([
            'hub' => ['id' => $hub->id, 'code' => $hub->code, 'name' => $hub->name],
            'summary' => $this->sortingSummaryFor($this->applySortingSearch($this->sortingQueueQuery($request, (int) $hub->id), $data)),
            'data' => $shipments->through(fn ($shipment) => $this->sortingQueuePayload($shipment)),
        ]);
    }
    public function storeArea(Request $request)
    {
        $data = $request->validate(['code'=>'required|string|max:30|unique:delivery_areas,code','name'=>'required|string|max:100','province'=>'required|string|max:100','municipality'=>'nullable|string|max:100','barangays'=>'nullable|string|max:1000','barangay_codes'=>'nullable|array','barangay_codes.*'=>'string|max:20']);
        $data['barangays'] = collect(explode(',', $data['barangays'] ?? ''))->map(fn($v)=>trim($v))->filter()->values()->all();
        $codes = collect($data['barangay_codes'] ?? [])->unique()->values(); unset($data['barangay_codes']);
        DB::transaction(function () use ($data, $codes, $request): void {
            $rows = collect();
            if ($codes->isNotEmpty()) {
                abort_unless(Schema::hasTable('delivery_area_barangay') && Schema::hasTable('psgc_barangays'), 503, 'Official barangay coverage is not enabled until the reviewed migration is applied.');
                $rows = $this->officialBarangaysQuery()->whereIn('code', $codes)->where('province', $data['province'])->when($data['municipality'] ?? null, fn ($q, $municipality) => $q->where('municipality', $municipality))->get();
                abort_unless($rows->count() === $codes->count(), 422, 'Select only official barangays in the chosen municipality.');
            }
            $area = DeliveryArea::create(['logistics_provider_id'=>$this->providerId($request)]+$data);
            if ($rows->isNotEmpty()) { $area->psgcBarangays()->sync($rows->pluck('id')); $area->update(['barangays' => $rows->pluck('name')->values()->all()]); }
        });
        return back()->with('success', 'Delivery Area created.');
    }

    public function sort(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $this->assertOwnedShipment($request, $shipment);
        $data = $request->validate(['delivery_area_id' => 'required|exists:delivery_areas,id', 'hub_id' => 'nullable|integer']);
        if ($request->filled('hub_id') || $request->user()?->sorting_center_id) {
            $hub = $this->selectedSortingCenter($request, $this->authorizedSortingCenters($request));
            abort_unless($hub && (int) $shipment->current_hub_id === (int) $hub->id, 403, 'This parcel is not currently at the selected Sorting Center.');
        }
        $areaQuery = DeliveryArea::forProvider($this->providerId($request))->with('sortingCenters');
        $officialCatalogEnabled = Schema::hasTable('delivery_area_barangay') && Schema::hasTable('psgc_barangays');
        if ($officialCatalogEnabled) {
            $areaQuery->with('psgcBarangays');
        }
        $area = $areaQuery->findOrFail($data['delivery_area_id']);
        abort_unless($area->is_active, 422, 'The selected Delivery Area is inactive.');

        // Destination sorting must use a zone configured for that hub. Origin
        // sorting remains available for direct-delivery and transfer routes.
        if ($shipment->destination_hub_id && (int) $shipment->current_hub_id === (int) $shipment->destination_hub_id) {
            abort_unless($area->sortingCenters->contains('id', (int) $shipment->current_hub_id), 422, 'The Delivery Area is not configured for this destination Sorting Center.');
        }

        if ($officialCatalogEnabled && $area->psgcBarangays->isNotEmpty()) {
            $address = (array) $shipment->delivery_address;
            $municipality = mb_strtolower(trim((string) ($address['municipality'] ?? $address['city'] ?? '')));
            $barangay = mb_strtolower(trim((string) ($address['barangay'] ?? '')));
            $barangayCode = trim((string) ($address['barangay_code'] ?? ''));
            $covered = $municipality !== '' && ($barangay !== '' || $barangayCode !== '') && $area->psgcBarangays->contains(function ($row) use ($municipality, $barangay, $barangayCode) {
                return mb_strtolower((string) $row->municipality) === $municipality
                    && (($barangayCode !== '' && (string) $row->code === $barangayCode)
                        || ($barangay !== '' && mb_strtolower((string) $row->name) === $barangay));
            });
            abort_unless($covered, 422, 'Confirm a valid municipality and barangay covered by this Delivery Area before sorting.');
        }

        $workflow->sort($shipment, $request->user(), $area);
        return back()->with('success', 'Parcel sorted into its Delivery Area.');
    }

    public function assignments(Request $request)
    {
        $shipments = $this->parcels($request, ['sorted', 'assigned_to_rider']);
        $providerId=$this->providerId($request);
        $areas = DeliveryArea::forProvider($providerId)->with(['riders.user'])->where('is_active', true)->orderBy('name')->get();
        $riderProfiles = RiderProfile::forProvider($providerId)->with(['user','deliveryAreas'])->assignmentEligible()->get();
        $areas->each(fn($area)=>$area->setRelation('riders',$area->riders->where('logistics_provider_id',$providerId)->values()));
        return view('logistics.delivery-assignment', compact('shipments', 'areas', 'riderProfiles'));
    }

    public function reports(Request $request)
    {
        $data = $request->validate(['from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from']);
        $from = isset($data['from']) ? now()->parse($data['from'])->startOfDay() : now()->subDays(29)->startOfDay();
        $to = isset($data['to']) ? now()->parse($data['to'])->endOfDay() : now()->endOfDay();
        $query = Shipment::forProvider($this->providerId($request))->with(['deliveryArea','rider'])->whereBetween('updated_at',[$from,$to]);
        $parcels = $query->get();
        $metrics = ['Received'=>$parcels->whereNotNull('received_at')->count(),'Sorted'=>$parcels->whereNotNull('sorted_at')->count(),'Assigned'=>$parcels->whereNotNull('delivery_assigned_at')->count(),'Delivered'=>$parcels->whereIn('status',['delivered','completed'])->count(),'Failed'=>$parcels->where('status','delivery_failed')->count(),'Returned'=>$parcels->where('status','returned')->count()];
        $byArea = $parcels->whereNotNull('delivery_area_id')->groupBy('delivery_area_id')->map(fn($set)=>['name'=>$set->first()->deliveryArea?->name,'total'=>$set->count(),'delivered'=>$set->whereIn('status',['delivered','completed'])->count(),'failed'=>$set->whereIn('status',['delivery_failed','returned'])->count()])->values();
        $byRider = $parcels->whereNotNull('rider_id')->groupBy('rider_id')->map(fn($set)=>['name'=>$set->first()->rider?->name,'assigned'=>$set->count(),'delivered'=>$set->whereIn('status',['delivered','completed'])->count(),'failed'=>$set->whereIn('status',['delivery_failed','returned'])->count()])->values();
        return view('logistics.sorting-reports',compact('metrics','byArea','byRider','from','to'));
    }

    public function assignDelivery(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $this->assertOwnedShipment($request, $shipment);
        $data = $request->validate(['rider_id' => 'required|exists:users,id']);
        $workflow->assignDelivery($shipment, $request->user(), User::findOrFail($data['rider_id']));
        return back()->with('success', 'Delivery Rider assigned.');
    }

    public function reschedule(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $this->assertOwnedShipment($request, $shipment);
        $workflow->reschedule($shipment, $request->user());
        return back()->with('success', 'Delivery rescheduled to the assigned Rider.');
    }

    public function markReturned(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $this->assertOwnedShipment($request, $shipment);
        $data = $request->validate(['reason'=>'required|string|max:500']);
        $workflow->return($shipment, $request->user(), $data['reason']);
        return back()->with('success', 'Parcel marked as returned.');
    }

    public function syncRiderAreas(Request $request, RiderProfile $rider)
    {
        $providerId=$this->providerId($request);
        abort_unless($rider->logistics_provider_id===$providerId,404);
        $data = $request->validate(['delivery_area_ids'=>'nullable|array','delivery_area_ids.*'=>'exists:delivery_areas,id']);
        $areaIds=collect($data['delivery_area_ids'] ?? [])->map(fn($id)=>(int)$id)->unique();
        abort_unless(DeliveryArea::forProvider($providerId)->whereIn('id',$areaIds)->count()===$areaIds->count(),404);
        $rider->deliveryAreas()->sync($data['delivery_area_ids'] ?? []);
        return back()->with('success', 'Rider Delivery Areas updated.');
    }

    private function authorizedSortingCenters(Request $request)
    {
        $provider = $request->attributes->get('logistics_provider');
        $user = $request->user();
        $membership = $provider?->users()->whereKey($user?->id)->wherePivot('is_active', true)->first();
        $membershipRole = strtolower((string) ($membership?->pivot?->membership_role ?? ''));
        $isManager = $provider && ((int) $provider->owner_user_id === (int) $user?->id
            || in_array($membershipRole, ['owner', 'manager', 'admin'], true));
        $query = LogisticsHub::forProvider($this->providerId($request))->where('status', 'active')->orderBy('name');
        if (! $isManager) {
            if (! $user?->sorting_center_id) return collect();
            $query->whereKey($user->sorting_center_id);
        }
        return $query->get();
    }

    private function selectedSortingCenter(Request $request, $hubs, ?int $requestedId = null)
    {
        if ($hubs->isEmpty()) return null;
        $requestedId ??= ($request->filled('hub_id') ? (int) $request->input('hub_id') : null);
        if ($requestedId !== null) {
            $hub = $hubs->firstWhere('id', $requestedId);
            abort_unless($hub, 403, 'You are not authorized to use this Sorting Center.');
            return $hub;
        }
        $assigned = $request->user()?->sorting_center_id
            ? $hubs->firstWhere('id', (int) $request->user()->sorting_center_id)
            : null;
        return $assigned ?: $hubs->first();
    }

    private function sortingQueueQuery(Request $request, int $hubId)
    {
        return Shipment::forProvider($this->providerId($request))
            ->where('current_hub_id', $hubId)
            ->whereIn('status', ['at_sorting_center', 'sorted'])
            ->with(['store', 'order.user', 'sellerOrder.items', 'pickupRider.riderProfile', 'rider.riderProfile', 'deliveryArea', 'currentHub', 'destinationHub']);
    }

    private function sortingAreasQuery(Request $request, LogisticsHub $hub)
    {
        $destinationHubIds = Shipment::forProvider($this->providerId($request))
            ->where('current_hub_id', $hub->id)
            ->whereNotNull('destination_hub_id')
            ->distinct()->pluck('destination_hub_id');
        $hubHasCoverage = DeliveryArea::forProvider($this->providerId($request))
            ->whereHas('sortingCenters', fn ($query) => $query->whereKey($hub->id))->exists();
        return DeliveryArea::forProvider($this->providerId($request))
            ->where('is_active', true)
            ->when($destinationHubIds->isNotEmpty(), fn ($query) => $query->whereHas('sortingCenters', fn ($centers) => $centers->whereIn('logistics_hubs.id', $destinationHubIds)))
            ->when($destinationHubIds->isEmpty() && $hubHasCoverage, fn ($query) => $query->whereHas('sortingCenters', fn ($centers) => $centers->whereKey($hub->id)))
            ->when(Schema::hasTable('delivery_area_barangay') && Schema::hasTable('psgc_barangays'), fn ($query) => $query->with('psgcBarangays'));
    }

    private function sortingMunicipalities(Request $request, LogisticsHub $hub)
    {
        if (! Schema::hasTable('psgc_barangays')) return collect();
        $configured = collect();
        if (trim((string) $hub->municipality) !== '') {
            $configured->push(['municipality' => trim((string) $hub->municipality), 'province' => trim((string) $hub->province)]);
        }
        $hub->coverageAreas()->where('is_active', true)->get(['municipality', 'province'])->each(function ($area) use ($configured) {
            if (trim((string) $area->municipality) !== '') $configured->push(['municipality' => trim((string) $area->municipality), 'province' => trim((string) $area->province)]);
        });
        if ($configured->isEmpty()) return collect();
        $locationMap = Schema::hasTable('psgc_locations')
            ? \App\Models\PsgcLocation::query()->where('level', 'city_municipality')->get()->keyBy('code')
            : collect();
        return $this->officialBarangaysQuery()->get()->filter(function ($row) use ($configured) {
            return $configured->contains(function ($item) use ($row) {
                return mb_strtolower($item['municipality']) === mb_strtolower((string) $row->municipality)
                    && ($item['province'] === '' || mb_strtolower($item['province']) === mb_strtolower((string) $row->province));
            });
        })->groupBy('municipality_code')->map(function ($rows) use ($locationMap) {
            $first = $rows->first();
            $location = $locationMap->get((string) $first->municipality_code);
            return [
                'code' => (string) $first->municipality_code,
                'name' => (string) $first->municipality,
                'province' => (string) $first->province,
                'province_code' => $location?->province_code,
                'region_code' => $location?->region_code,
            ];
        })->values();
    }

    /**
     * Return the national reference catalog for filters. Operational hub
     * coverage is deliberately kept separate and is only used to label rows
     * or authorize sorting actions.
     */
    private function catalogMunicipalities($configured = null)
    {
        if (Schema::hasTable('psgc_locations')) {
            $catalog = \App\Models\PsgcLocation::query()->active()
                ->where('level', 'city_municipality')
                ->orderBy('name')
                ->get(['code', 'name', 'province_code', 'region_code'])
                ->map(fn ($row) => [
                    'code' => (string) $row->code,
                    'name' => (string) $row->name,
                    'province' => '',
                    'province_code' => $row->province_code,
                    'region_code' => $row->region_code,
                    'coverage_status' => 'unconfigured',
                ])->values();
            if ($catalog->isNotEmpty()) {
                $configuredCodes = collect($configured ?? [])->pluck('code')->map(fn ($code) => (string) $code)->flip();
                return $catalog->map(function ($row) use ($configuredCodes) {
                    $row['coverage_status'] = $configuredCodes->has((string) $row['code']) ? 'covered' : 'unconfigured';
                    return $row;
                })->values();
            }
        }

        return collect($configured ?? [])->map(fn ($row) => [
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'province' => (string) ($row['province'] ?? ''),
            'province_code' => $row['province_code'] ?? null,
            'region_code' => $row['region_code'] ?? null,
            'coverage_status' => 'covered',
        ])->values();
    }

    private function sortingGeography($municipalities): array
    {
        $regions = collect();
        $provinces = collect();
        if (Schema::hasTable('psgc_locations')) {
            $configuredCodes = collect($municipalities)->pluck('code')->map(fn ($code) => (string) $code)->flip();
            $configuredLocations = \App\Models\PsgcLocation::query()->active()->where('level', 'city_municipality')->whereIn('code', $configuredCodes->keys())->get(['province_code', 'region_code']);
            $coveredProvinceCodes = $configuredLocations->pluck('province_code')->filter()->unique()->flip();
            $coveredRegionCodes = $configuredLocations->pluck('region_code')->filter()->unique()->flip();
            $provinces = \App\Models\PsgcLocation::query()->active()->where('level', 'province')->orderBy('name')->get(['code', 'name', 'region_code'])->map(function ($row) use ($coveredProvinceCodes) {
                $covered = $coveredProvinceCodes->has((string) $row->code);
                return ['code' => (string) $row->code, 'name' => (string) $row->name, 'region_code' => $row->region_code, 'coverage_status' => $covered ? 'covered' : 'unconfigured'];
            });
            $regions = \App\Models\PsgcLocation::query()->active()->where('level', 'region')->orderBy('name')->get(['code', 'name'])->map(function ($row) use ($coveredRegionCodes) {
                $covered = $coveredRegionCodes->has((string) $row->code);
                return ['code' => (string) $row->code, 'name' => (string) $row->name, 'coverage_status' => $covered ? 'covered' : 'unconfigured'];
            });
        }
        return ['regions' => $regions->values(), 'provinces' => $provinces->values()];
    }

    private function sortingBarangayRows(Request $request, LogisticsHub $hub, ?string $municipalityCode = null)
    {
        $municipalities = $this->sortingMunicipalities($request, $hub);
        $query = $this->officialBarangaysQuery();
        if ($municipalityCode !== null && $municipalityCode !== '') $query->where('municipality_code', $municipalityCode);
        elseif ($municipalities->isNotEmpty()) $query->whereIn('municipality_code', $municipalities->pluck('code'));
        else return collect();
        return $query->orderBy('name')->get();
    }

    private function coveredBarangayIds(Request $request, LogisticsHub $hub)
    {
        if (! Schema::hasTable('delivery_area_barangay')) return collect();
        return DB::table('delivery_area_barangay')
            ->join('delivery_area_hub', 'delivery_area_hub.delivery_area_id', '=', 'delivery_area_barangay.delivery_area_id')
            ->join('delivery_areas', 'delivery_areas.id', '=', 'delivery_area_barangay.delivery_area_id')
            ->where('delivery_area_hub.logistics_hub_id', $hub->id)
            ->where('delivery_areas.logistics_provider_id', $this->providerId($request))
            ->where('delivery_areas.is_active', true)
            ->pluck('delivery_area_barangay.psgc_barangay_id')->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function officialBarangaysQuery()
    {
        return \App\Models\PsgcBarangay::query()
            ->when(Schema::hasColumn('psgc_barangays', 'is_active'), fn ($query) => $query->where('is_active', true));
    }

    /**
     * Read-only queue filters are still constrained to the selected hub's
     * configured geography and never authorize a sort or change coverage.
     */
    private function applyReadOnlyBarangaySelection(Request $request, LogisticsHub $hub, array &$data): void
    {
        if (empty($data['barangay_code'])) return;
        abort_unless(Schema::hasTable('psgc_barangays'), 503, 'Geographic data has not been loaded.');
        $barangay = $this->sortingBarangayRows($request, $hub, $data['municipality_code'] ?? null)->firstWhere('code', $data['barangay_code']);
        abort_unless($barangay, 422, 'The selected barangay is not configured for this Sorting Center.');
        if (! empty($data['municipality_code'])) {
            abort_unless((string) $barangay->municipality_code === (string) $data['municipality_code'], 422, 'The selected barangay is outside the selected municipality.');
        }
        $data['barangay_name'] = $barangay->name;
        $data['municipality_code'] = (string) $barangay->municipality_code;
    }

    private function applyReadOnlyGeographySelection(Request $request, LogisticsHub $hub, array &$data): void
    {
        $municipalities = $this->sortingMunicipalities($request, $hub);
        $geography = $this->sortingGeography($this->sortingMunicipalities($request, $hub));
        if (! empty($data['region_code'])) {
            abort_unless($geography['regions']->contains('code', $data['region_code']), 422, 'The selected region is not present in the verified PSGC catalog.');
        }
        if (! empty($data['province_code'])) {
            abort_unless($geography['provinces']->contains('code', $data['province_code']), 422, 'The selected province is not present in the verified PSGC catalog.');
        }
        if (! empty($data['municipality_code'])) {
            $municipality = $municipalities->firstWhere('code', (string) $data['municipality_code']);
            abort_unless($municipality, 422, 'The selected municipality is not present in the verified PSGC catalog.');
            if (! empty($data['province_code'])) abort_unless((string) $municipality['province_code'] === (string) $data['province_code'], 422, 'The selected municipality is outside the selected province.');
            if (! empty($data['region_code'])) abort_unless((string) $municipality['region_code'] === (string) $data['region_code'], 422, 'The selected municipality is outside the selected region.');
            $data['municipality_name'] = $municipality['name'];
        }
    }

    private function applyBarangaySelection(Request $request, LogisticsHub $hub, array &$data): void
    {
        if (empty($data['barangay_code'])) return;
        abort_unless(Schema::hasTable('psgc_barangays'), 503, 'The verified PSGC barangay catalog is not available in this environment.');
        $municipalities = $this->sortingMunicipalities($request, $hub);
        $barangay = $this->sortingBarangayRows($request, $hub, $data['municipality_code'] ?? null)->firstWhere('code', $data['barangay_code']);
        abort_unless($barangay && $municipalities->contains('code', (string) $barangay->municipality_code), 422, 'The selected barangay is not configured for this Sorting Center.');
        $data['barangay_name'] = $barangay->name;
        $data['municipality_code'] = (string) $barangay->municipality_code;
    }

    private function applyGeographySelection(Request $request, LogisticsHub $hub, array &$data): void
    {
        $municipalities = $this->sortingMunicipalities($request, $hub);
        $geography = $this->sortingGeography($municipalities);
        if (! empty($data['region_code'])) {
            abort_unless($geography['regions']->contains('code', $data['region_code']), 422, 'The selected region is not configured for this Sorting Center.');
        }
        if (! empty($data['province_code'])) {
            abort_unless($geography['provinces']->contains('code', $data['province_code']), 422, 'The selected province is not configured for this Sorting Center.');
        }
        if (! empty($data['municipality_code'])) {
            $municipality = $municipalities->firstWhere('code', (string) $data['municipality_code']);
            abort_unless($municipality, 422, 'The selected municipality is not configured for this Sorting Center.');
            if (! empty($data['province_code'])) abort_unless((string) $municipality['province_code'] === (string) $data['province_code'], 422, 'The selected municipality is outside the selected province.');
            if (! empty($data['region_code'])) abort_unless((string) $municipality['region_code'] === (string) $data['region_code'], 422, 'The selected municipality is outside the selected region.');
            $data['municipality_name'] = $municipality['name'];
        }
    }

    private function assertSortingAreaAllowed(Request $request, LogisticsHub $hub, int $areaId): void
    {
        abort_unless($this->sortingAreasQuery($request, $hub)->whereKey($areaId)->exists(), 422, 'The Delivery Area is not available for this Sorting Center.');
    }

    private function applySortingSearch($query, array $data)
    {
        return $query
            ->when($data['q'] ?? null, function ($query, $value) {
                $query->where(function ($query) use ($value) {
                    $query->where('parcel_code', 'like', "%{$value}%")
                        ->orWhere('shipment_number', 'like', "%{$value}%")
                        ->orWhereHas('store', fn ($store) => $store->where('name', 'like', "%{$value}%"));
                });
            })
            ->when($data['region_code'] ?? null, function ($query, $regionCode) {
                $codes = Schema::hasTable('psgc_locations')
                    ? \App\Models\PsgcLocation::query()->active()->where('level', 'city_municipality')->where('region_code', $regionCode)->pluck('code')
                    : collect();
                $query->where(function ($query) use ($regionCode, $codes) {
                    $query->whereJsonContains('delivery_address->region_code', $regionCode);
                    foreach ($codes as $code) $query->orWhereJsonContains('delivery_address->city_code', $code);
                });
            })
            ->when($data['province_code'] ?? null, function ($query, $provinceCode) {
                $codes = Schema::hasTable('psgc_locations')
                    ? \App\Models\PsgcLocation::query()->active()->where('level', 'city_municipality')->where('province_code', $provinceCode)->pluck('code')
                    : collect();
                $query->where(function ($query) use ($provinceCode, $codes) {
                    $query->whereJsonContains('delivery_address->province_code', $provinceCode);
                    foreach ($codes as $code) $query->orWhereJsonContains('delivery_address->city_code', $code);
                });
            })
            ->when($data['municipality_code'] ?? null, function ($query, $municipalityCode) use ($data) {
                $query->where(function ($query) use ($municipalityCode, $data) {
                    $query->whereJsonContains('delivery_address->city_code', $municipalityCode)
                        ->orWhereJsonContains('delivery_address->municipality_code', $municipalityCode)
                        ->orWhereJsonContains('delivery_address->municipality', $data['municipality_name'] ?? '');
                });
            })
            ->when($data['area_id'] ?? null, fn ($query, $areaId) => $query->where('delivery_area_id', $areaId))
            ->when($data['barangay_code'] ?? null, function ($query) use ($data) {
                $query->where(function ($query) use ($data) {
                    $query->whereJsonContains('delivery_address->barangay_code', $data['barangay_code'])
                        ->orWhereJsonContains('delivery_address->barangay', $data['barangay_name'] ?? '');
                });
            });
    }

    private function sortingSummaryFor($query): array
    {
        return [
            'total' => (clone $query)->count(),
            'awaiting_scan' => (clone $query)->where('status', 'at_sorting_center')->whereNull('parcel_scanned_at')->count(),
            'ready_to_sort' => (clone $query)->where('status', 'at_sorting_center')->whereNotNull('parcel_scanned_at')->count(),
            'sorted' => (clone $query)->where('status', 'sorted')->count(),
        ];
    }

    private function sortingQueuePayload(Shipment $shipment): array
    {
        $address = (array) $shipment->delivery_address;
        $destination = trim((string) ($address['municipality'] ?? $address['city'] ?? ''));
        $barangay = trim((string) ($address['barangay'] ?? ''));
        $nextAction = $shipment->status === 'sorted'
            ? ($shipment->destination_hub_id && (int) $shipment->destination_hub_id !== (int) $shipment->current_hub_id ? 'Awaiting hub transfer' : 'Ready for delivery assignment')
            : ($shipment->parcel_scanned_at ? 'Confirm sorting' : 'Confirm parcel code');
        return [
            'id' => $shipment->id,
            'parcel_code' => $shipment->parcel_code ?: $shipment->shipment_number,
            'shipment_number' => $shipment->shipment_number,
            'status' => $shipment->status,
            'current_hub' => $shipment->currentHub?->name,
            'destination_hub' => $shipment->destinationHub?->name,
            'municipality' => $destination ?: null,
            'barangay' => $barangay ?: null,
            'delivery_area' => $shipment->deliveryArea?->only(['id', 'name']),
            'parcel_scanned' => (bool) $shipment->parcel_scanned_at,
            'phase' => $shipment->status === 'sorted' ? 'sorted' : 'at_sorting_center',
            'next_action' => $nextAction,
        ];
    }

    private function parcels(Request $request, array $statuses)
    {
        return Shipment::forProvider($this->providerId($request))->with(['store','order.user','sellerOrder.items','pickupRider.riderProfile','rider.riderProfile','deliveryArea'])
            ->whereIn('status', $statuses)
            ->when($request->q, function($query, $value) {
                $query->where(function($query) use ($value) {
                    $query->where('parcel_code','like',"%{$value}%")->orWhere('shipment_number','like',"%{$value}%")
                        ->orWhereHas('store', fn($store)=>$store->where('name','like',"%{$value}%"));
                });
            })->latest('updated_at')->paginate(20)->withQueryString();
    }

    private function availableRiders(User $owner)
    {
        return User::with('riderProfile')->where('is_active', true)->whereHas('roles', fn($q)=>$q->where('slug','rider'))
            ->whereHas('riderProfile', fn($q)=>$q->where('logistics_provider_id',$owner->logisticsProviders()->wherePivot('is_active',true)->value('logistics_providers.id'))->assignmentEligible())->orderBy('name')->get();
    }
}
