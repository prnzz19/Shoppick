<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Payment, Shipment, User};
use App\Services\{CodCollectionService, RiderAssignmentDecisionService, RiderPortalActionService, RiderProofOfDeliveryService};
use App\Support\RiderPortal;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RiderMobileApiController extends Controller
{
    public function jobs(Request $request)
    {
        $rider = $this->rider($request);
        $query = RiderPortal::owned($rider->id)->with(['provider', 'store', 'order.payments', 'sellerOrder', 'currentHub', 'originHub', 'nextHub', 'destinationHub', 'deliveryArea', 'proofOfDelivery', 'assignments']);
        if ($rider->riderProfile?->logistics_provider_id) $query->where('logistics_provider_id', $rider->riderProfile->logistics_provider_id);
        $query->whereNotIn('status', Shipment::TERMINAL_STATUSES);
        if ($request->filled('type')) $query->where($request->validate(['type' => 'in:pickup,delivery'])['type'] === 'pickup' ? 'pickup_rider_id' : 'rider_id', $rider->id);
        if ($request->filled('status')) $query->where('status', $request->validate(['status' => 'string|max:50'])['status']);
        if ($request->filled('q')) {
            $q = $request->validate(['q' => 'string|max:100'])['q'];
            $query->where(fn ($builder) => $builder->where('shipment_number', 'like', "%{$q}%")->orWhere('parcel_code', 'like', "%{$q}%"));
        }
        $page = $query->latest('updated_at')->paginate(min(max((int) $request->input('per_page', 20), 1), 50));
        return $this->paginated($page, $rider);
    }

    public function history(Request $request)
    {
        $rider = $this->rider($request);
        $query = RiderPortal::owned($rider->id)->with(['provider', 'store', 'order.payments', 'sellerOrder', 'currentHub', 'originHub', 'nextHub', 'destinationHub', 'deliveryArea', 'proofOfDelivery', 'assignments'])
            ->where(fn ($q) => $q->whereIn('status', RiderPortal::HISTORY)->orWhere(fn ($p) => $p->where('pickup_rider_id', $rider->id)->whereNotNull('pickup_arrived_at')));
        if ($rider->riderProfile?->logistics_provider_id) $query->where('logistics_provider_id', $rider->riderProfile->logistics_provider_id);
        $page = $query->latest('updated_at')->paginate(min(max((int) $request->input('per_page', 20), 1), 50));
        return $this->paginated($page, $rider);
    }

    public function show(Request $request, Shipment $shipment)
    {
        $rider = $this->rider($request);
        $this->ownedBy($shipment, $rider);
        $shipment->load(['provider', 'store', 'order.payments', 'sellerOrder.items', 'vehicle', 'currentHub', 'originHub', 'nextHub', 'destinationHub', 'deliveryArea', 'proofOfDelivery', 'assignments']);
        return response()->json(['success' => true, 'data' => $this->shipmentData($shipment, $rider)]);
    }

    public function availability(Request $request)
    {
        $rider = $this->rider($request);
        $data = $request->validate(['availability' => 'required|in:available,off_duty']);
        if (RiderPortal::actionable($rider->id)->exists()) throw ValidationException::withMessages(['availability' => 'Finish your current assignment before changing availability.']);
        $rider->riderProfile->update($data);
        return response()->json(['success' => true, 'message' => 'Availability updated.', 'data' => ['availability' => $rider->riderProfile->fresh()->availability]]);
    }

    public function accept(Request $request, Shipment $shipment, RiderPortalActionService $actions)
    {
        $rider = $this->rider($request);
        $this->ownedBy($shipment, $rider);
        $available = array_values(array_filter(RiderPortal::actions($shipment, $rider->id), fn ($action) => in_array($action, ['accept_pickup', 'accept_delivery'], true)));
        if (count($available) !== 1) throw ValidationException::withMessages(['action' => 'This assignment is not awaiting acceptance.']);
        $actions->perform($shipment, $rider, ['action' => $available[0], 'version' => $request->input('version')]);
        return $this->updated($shipment->fresh(['provider', 'store', 'order.payments', 'proofOfDelivery', 'assignments']), $rider, 'Assignment accepted.');
    }

    public function action(Request $request, Shipment $shipment, RiderPortalActionService $actions)
    {
        $rider = $this->rider($request);
        $this->ownedBy($shipment, $rider);
        $data = $request->validate([
            'action' => 'required|in:accept_pickup,confirm_pickup,arrive_sorting,accept_delivery,start_delivery,delivered,failed,legacy_pickup,legacy_transit,legacy_out',
            'version' => 'nullable|string|size:64', 'parcel_code' => 'nullable|required_if:action,confirm_pickup|string|max:100',
            'reason' => 'nullable|required_if:action,failed|in:recipient_unavailable,incorrect_address,reschedule_requested,parcel_issue,payment_issue,other',
            'details' => 'nullable|required_if:reason,other|string|max:500',
        ]);
        if (! in_array($data['action'], $this->allowedActions($shipment->load(['order.payments', 'proofOfDelivery']), $rider), true)) throw ValidationException::withMessages(['action' => 'This action is not currently available for this Shipment.']);
        $actions->perform($shipment, $rider, $data);
        return $this->updated($shipment->fresh(['provider', 'store', 'order.payments', 'proofOfDelivery', 'assignments']), $rider, 'Shipment action completed.');
    }

    public function decline(Request $request, Shipment $shipment, RiderAssignmentDecisionService $decision)
    {
        $rider = $this->rider($request);
        $this->ownedBy($shipment, $rider);
        $data = $request->validate(['version' => 'nullable|string|size:64', 'reason' => 'required|in:vehicle_issue,unavailable,emergency,other', 'details' => 'nullable|required_if:reason,other|string|max:500']);
        $result = $decision->decline($shipment, $rider, $data);
        return response()->json(['success' => true, 'message' => 'Assignment declined and returned for reassignment.', 'data' => $this->shipmentData($result->load(['provider', 'assignments']), $rider)]);
    }

    public function pod(Request $request, Shipment $shipment, RiderProofOfDeliveryService $proof)
    {
        $rider = $this->rider($request);
        $this->ownedBy($shipment, $rider);
        $result = $proof->submit($request, $shipment, $rider, $request->input('version'));
        return response()->json(['success' => true, 'message' => 'Proof of Delivery submitted.', 'data' => ['id' => $result->id, 'shipment_id' => $result->shipment_id, 'recipient_name' => $result->recipient_name, 'status' => $result->status, 'submitted_at' => $result->submitted_at?->toIso8601String(), 'allowed_actions' => $this->allowedActions($shipment->fresh(['order.payments', 'proofOfDelivery']), $rider)]]);
    }

    public function cod(Request $request, Shipment $shipment, CodCollectionService $cod, RiderPortalActionService $portal)
    {
        $rider = $this->rider($request);
        $this->ownedBy($shipment, $rider);
        $request->validate(['version' => 'nullable|string|size:64']);
        $payment = null;
        $portal->locked($shipment, $rider, $request->input('version'), function ($fresh, $user) use ($cod, &$payment) { $payment = $cod->collect($fresh, $user); });
        return response()->json(['success' => true, 'message' => 'COD collection recorded.', 'data' => ['payment_id' => $payment?->id, 'status' => $payment?->status, 'amount' => $payment?->amount, 'shipment_id' => $shipment->id, 'allowed_actions' => $this->allowedActions($shipment->fresh(['order.payments', 'proofOfDelivery']), $rider)]]);
    }

    private function rider(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && RiderPortal::active($user->load('riderProfile')), 403, 'Rider access is required.');
        return $user;
    }

    private function ownedBy(Shipment $shipment, User $rider): void
    {
        abort_unless(in_array($rider->id, [$shipment->pickup_rider_id, $shipment->rider_id], true), 404, 'Shipment not found.');
        $providerId = $rider->riderProfile?->logistics_provider_id;
        abort_unless(! $providerId || ! $shipment->logistics_provider_id || (int) $providerId === (int) $shipment->logistics_provider_id, 404, 'Shipment not found.');
    }

    private function paginated($page, User $rider)
    {
        return response()->json(['success' => true, 'data' => $page->getCollection()->map(fn ($shipment) => $this->shipmentData($shipment, $rider))->values(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    private function updated(Shipment $shipment, User $rider, string $message)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $this->shipmentData($shipment, $rider)]);
    }

    private function shipmentData(Shipment $shipment, User $rider): array
    {
        $assignment = $shipment->assignments?->where('rider_id', $rider->id)->whereNull('ended_at')->sortByDesc('id')->first();
        return [
            'id' => $shipment->id, 'shipment_id' => $shipment->id, 'assignment_id' => $assignment?->id,
            'assignment_type' => $assignment?->assignment_type ?: ($shipment->rider_id === $rider->id ? 'delivery' : 'pickup'),
            'assignment_status' => $assignment?->status, 'assignment_cycle' => $assignment?->assignment_cycle,
            'shipment_number' => $shipment->shipment_number, 'tracking_number' => $shipment->shipment_number,
            'parcel_code' => $shipment->parcel_code ?: $shipment->shipment_number, 'waybill' => $shipment->parcel_code ?: $shipment->shipment_number,
            'status' => $shipment->status, 'priority' => $shipment->priority, 'assigned_at' => $shipment->assigned_at?->toIso8601String(),
            'provider' => $shipment->provider ? ['id' => $shipment->provider->id, 'name' => $shipment->provider->trade_name ?: $shipment->provider->company_name] : null,
            'pickup' => ['address' => RiderPortal::address($shipment->pickup_address), 'snapshot' => $shipment->pickup_address],
            'dropoff' => ['address' => RiderPortal::address($shipment->delivery_address), 'snapshot' => $shipment->delivery_address],
            'center' => $shipment->currentHub ? ['id' => $shipment->currentHub->id, 'code' => $shipment->currentHub->code, 'name' => $shipment->currentHub->name] : null,
            'origin_center' => $shipment->originHub?->only(['id', 'code', 'name']), 'next_center' => $shipment->nextHub?->only(['id', 'code', 'name']), 'destination_center' => $shipment->destinationHub?->only(['id', 'code', 'name']),
            'delivery_area' => $shipment->deliveryArea?->only(['id', 'code', 'name']), 'store' => $shipment->store?->only(['id', 'name']),
            'seller_order_id' => $shipment->seller_order_id, 'order_id' => $shipment->order_id,
            'cod' => $this->codData($shipment), 'allowed_actions' => $this->allowedActions($shipment, $rider),
            'version' => RiderPortal::version($shipment),
        ];
    }

    private function codData(Shipment $shipment): ?array
    {
        $order = $shipment->order;
        if (! $order || $order->payment_method !== 'cod') return null;
        $payment = $order->payments?->where('method', 'cod')->sortByDesc('id')->first();
        return ['required' => true, 'amount' => $payment?->amount, 'status' => $payment?->status, 'payment_id' => $payment?->id];
    }

    private function allowedActions(Shipment $shipment, User $rider): array
    {
        $actions = RiderPortal::actions($shipment, $rider->id);
        if ($shipment->rider_id === $rider->id && $shipment->status === 'out_for_delivery') {
            array_unshift($actions, 'submit_pod');
            $cod = $this->codData($shipment);
            if ($cod && in_array($cod['status'], ['pending', 'unpaid', 'cod'], true)) array_splice($actions, 1, 0, 'collect_cod');
            if (! $shipment->proofOfDelivery || ($cod && ! in_array($cod['status'], ['cod_collected', 'paid'], true))) $actions = array_values(array_diff($actions, ['delivered']));
        }
        return array_values(array_unique($actions));
    }
}
