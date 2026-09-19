<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{DeliveryArea, RiderProfile, Shipment, User};
use App\Services\ParcelWorkflowService;
use Illuminate\Http\Request;

class SortingCenterController extends Controller
{
    public function account(){return view('logistics.account');}
    public function pickupRequests(Request $request)
    {
        $shipments = $this->parcels($request, ['ready_for_pickup', 'pickup_assigned', 'pickup_accepted', 'picked_up']);
        $riders = $this->availableRiders();
        return view('logistics.pickup-requests', compact('shipments', 'riders'));
    }

    public function assignPickup(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $data = $request->validate(['rider_id' => 'required|exists:users,id']);
        $workflow->assignPickup($shipment, $request->user(), User::findOrFail($data['rider_id']));
        return back()->with('success', 'Pickup Rider assigned.');
    }

    public function incoming(Request $request)
    {
        $shipments = $this->parcels($request, ['picked_up','at_sorting_center']);
        return view('logistics.incoming-parcels', compact('shipments'));
    }

    public function receive(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $workflow->receive($shipment, $request->user());
        return back()->with('success', 'Parcel received at the Sorting Center.');
    }

    public function scan(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $data = $request->validate(['parcel_code' => 'required|string|max:100']);
        $workflow->confirmScan($shipment, $request->user(), $data['parcel_code']);
        return back()->with('success', 'Parcel code confirmed.');
    }

    public function sorting(Request $request)
    {
        $shipments = $this->parcels($request, ['at_sorting_center', 'sorted']);
        $areas = DeliveryArea::orderByDesc('is_active')->orderBy('name')->get();
        $detectedAreas = $shipments->getCollection()->mapWithKeys(function($shipment) use ($areas) {
            $province = strtolower((string)data_get($shipment->delivery_address,'province'));
            $municipality = strtolower((string)(data_get($shipment->delivery_address,'municipality') ?: data_get($shipment->delivery_address,'city')));
            $barangay = strtolower((string)data_get($shipment->delivery_address,'barangay'));
            $match = $areas->where('is_active',true)->first(function($area) use ($province,$municipality,$barangay) {
                if ($province && strtolower($area->province) !== $province) return false;
                if ($area->municipality && strtolower($area->municipality) !== $municipality) return false;
                $covered = collect($area->barangays)->map(fn($value)=>strtolower($value));
                return $covered->isEmpty() || $covered->contains($barangay);
            });
            return [$shipment->id => $match];
        });
        return view('logistics.sorting', compact('shipments', 'areas', 'detectedAreas'));
    }

    public function storeArea(Request $request)
    {
        $data = $request->validate(['code'=>'required|string|max:30|unique:delivery_areas,code','name'=>'required|string|max:100','province'=>'required|string|max:100','municipality'=>'nullable|string|max:100','barangays'=>'nullable|string|max:1000']);
        $data['barangays'] = collect(explode(',', $data['barangays'] ?? ''))->map(fn($v)=>trim($v))->filter()->values()->all();
        DeliveryArea::create($data);
        return back()->with('success', 'Delivery Area created.');
    }

    public function sort(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $data = $request->validate(['delivery_area_id' => 'required|exists:delivery_areas,id']);
        $workflow->sort($shipment, $request->user(), DeliveryArea::findOrFail($data['delivery_area_id']));
        return back()->with('success', 'Parcel sorted into its Delivery Area.');
    }

    public function assignments(Request $request)
    {
        $shipments = $this->parcels($request, ['sorted', 'assigned_to_rider']);
        $areas = DeliveryArea::with(['riders.user'])->where('is_active', true)->orderBy('name')->get();
        $riderProfiles = RiderProfile::with(['user','deliveryAreas'])->assignmentEligible()->get();
        return view('logistics.delivery-assignment', compact('shipments', 'areas', 'riderProfiles'));
    }

    public function reports(Request $request)
    {
        $data = $request->validate(['from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from']);
        $from = isset($data['from']) ? now()->parse($data['from'])->startOfDay() : now()->subDays(29)->startOfDay();
        $to = isset($data['to']) ? now()->parse($data['to'])->endOfDay() : now()->endOfDay();
        $query = Shipment::with(['deliveryArea','rider'])->whereBetween('updated_at',[$from,$to]);
        $parcels = $query->get();
        $metrics = ['Received'=>$parcels->whereNotNull('received_at')->count(),'Sorted'=>$parcels->whereNotNull('sorted_at')->count(),'Assigned'=>$parcels->whereNotNull('delivery_assigned_at')->count(),'Delivered'=>$parcels->whereIn('status',['delivered','completed'])->count(),'Failed'=>$parcels->where('status','delivery_failed')->count(),'Returned'=>$parcels->where('status','returned')->count()];
        $byArea = $parcels->whereNotNull('delivery_area_id')->groupBy('delivery_area_id')->map(fn($set)=>['name'=>$set->first()->deliveryArea?->name,'total'=>$set->count(),'delivered'=>$set->whereIn('status',['delivered','completed'])->count(),'failed'=>$set->whereIn('status',['delivery_failed','returned'])->count()])->values();
        $byRider = $parcels->whereNotNull('rider_id')->groupBy('rider_id')->map(fn($set)=>['name'=>$set->first()->rider?->name,'assigned'=>$set->count(),'delivered'=>$set->whereIn('status',['delivered','completed'])->count(),'failed'=>$set->whereIn('status',['delivery_failed','returned'])->count()])->values();
        return view('logistics.sorting-reports',compact('metrics','byArea','byRider','from','to'));
    }

    public function assignDelivery(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $data = $request->validate(['rider_id' => 'required|exists:users,id']);
        $workflow->assignDelivery($shipment, $request->user(), User::findOrFail($data['rider_id']));
        return back()->with('success', 'Delivery Rider assigned.');
    }

    public function reschedule(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $workflow->reschedule($shipment, $request->user());
        return back()->with('success', 'Delivery rescheduled to the assigned Rider.');
    }

    public function markReturned(Request $request, Shipment $shipment, ParcelWorkflowService $workflow)
    {
        $data = $request->validate(['reason'=>'required|string|max:500']);
        $workflow->return($shipment, $request->user(), $data['reason']);
        return back()->with('success', 'Parcel marked as returned.');
    }

    public function syncRiderAreas(Request $request, RiderProfile $rider)
    {
        $data = $request->validate(['delivery_area_ids'=>'nullable|array','delivery_area_ids.*'=>'exists:delivery_areas,id']);
        $rider->deliveryAreas()->sync($data['delivery_area_ids'] ?? []);
        return back()->with('success', 'Rider Delivery Areas updated.');
    }

    private function parcels(Request $request, array $statuses)
    {
        return Shipment::with(['store','order.user','sellerOrder.items','pickupRider.riderProfile','rider.riderProfile','deliveryArea'])
            ->whereIn('status', $statuses)
            ->when($request->q, function($query, $value) {
                $query->where(function($query) use ($value) {
                    $query->where('parcel_code','like',"%{$value}%")->orWhere('shipment_number','like',"%{$value}%")
                        ->orWhereHas('store', fn($store)=>$store->where('name','like',"%{$value}%"));
                });
            })->latest('updated_at')->paginate(20)->withQueryString();
    }

    private function availableRiders()
    {
        return User::with('riderProfile')->where('is_active', true)->whereHas('roles', fn($q)=>$q->where('slug','rider'))
            ->whereHas('riderProfile', fn($q)=>$q->assignmentEligible())->orderBy('name')->get();
    }
}
