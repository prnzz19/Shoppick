<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LogisticsMonitoringController extends Controller
{
    private const FINISHED = ['delivered', 'completed', 'returned'];
    private const PICKUP_ACTIVE = ['pickup_assigned', 'pickup_accepted', 'picked_up'];

    public function overview()
    {
        $statuses = Shipment::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->orderBy('status')->pluck('total', 'status');
        $summary = [
            'Logistics Staff' => User::whereHas('roles', fn ($q) => $q->where('slug', 'logistics'))->count(),
            'Riders' => $this->riderQuery()->count(),
            'Active Shipments' => Shipment::whereNotIn('status', [...self::FINISHED, 'delivery_failed', 'exception'])->count(),
            'Out for Delivery' => Shipment::where('status', 'out_for_delivery')->count(),
            'Delivered' => Shipment::whereIn('status', ['delivered', 'completed'])->count(),
            'Failed / Returned' => Shipment::whereIn('status', ['delivery_failed', 'returned'])->count(),
        ];
        $shipments = Shipment::with(['order', 'store', 'rider', 'pickupRider'])->latest('updated_at')->latest('id')->limit(8)->get();

        return view('admin.logistics.overview', compact('summary', 'statuses', 'shipments'));
    }

    public function deliveries(Request $request)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|string|max:50']);
        $statuses = Shipment::distinct()->orderBy('status')->pluck('status');
        $shipments = Shipment::with(['order.user', 'sellerOrder', 'store', 'rider', 'pickupRider'])
            ->when($filters['q'] ?? null, function ($query, $term) {
                $query->where(fn ($q) => $q->where('shipment_number', 'like', '%'.$term.'%')
                    ->orWhere('parcel_code', 'like', '%'.$term.'%')
                    ->orWhere('order_id', $term)
                    ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', '%'.$term.'%'))
                    ->orWhereHas('sellerOrder', fn ($order) => $order->where('seller_order_number', 'like', '%'.$term.'%')));
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('updated_at')->latest('id')->paginate(15)->withQueryString();

        return view('admin.logistics.deliveries', compact('shipments', 'statuses'));
    }

    public function delivery(Shipment $shipment)
    {
        $shipment->load(['order.user', 'sellerOrder', 'store.user', 'rider', 'pickupRider', 'currentHub', 'proofOfDelivery.submitter',
            'events' => fn ($q) => $q->with('actor')->orderBy('created_at')->orderBy('id')]);

        return view('admin.logistics.delivery', compact('shipment'));
    }

    public function proof(Shipment $shipment)
    {
        $proof = $shipment->proofOfDelivery;
        abort_unless($proof?->photo_path && Storage::disk('local')->exists($proof->photo_path), 404);

        return Storage::disk('local')->response($proof->photo_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function riders()
    {
        $riders = $this->withActivity($this->riderQuery())->with('riderProfile')->orderBy('name')->paginate(15);

        return view('admin.logistics.riders', compact('riders'));
    }

    public function rider(User $rider)
    {
        abort_unless($rider->hasRole('rider'), 404);
        $rider = $this->withActivity($this->riderQuery())->with('riderProfile')->findOrFail($rider->id);
        $shipments = Shipment::with(['order', 'store', 'rider', 'pickupRider'])
            ->where(fn ($q) => $q->where('rider_id', $rider->id)->orWhere('pickup_rider_id', $rider->id))
            ->latest('updated_at')->latest('id')->paginate(15);

        $currentDelivery = Shipment::where('rider_id', $rider->id)->whereNotIn('status', self::FINISHED)->oldest('assigned_at')->first();
        $events = ShipmentEvent::with('shipment')->where('actor_id', $rider->id)
            ->latest('created_at')->latest('id')->paginate(10, ['*'], 'activity_page');

        return view('admin.logistics.rider', compact('rider', 'shipments', 'currentDelivery', 'events'));
    }

    private function riderQuery(): Builder
    {
        return User::whereHas('roles', fn ($q) => $q->where('slug', 'rider'));
    }

    private function withActivity(Builder $query): Builder
    {
        // Pickup work ends when the parcel reaches sorting. Count a parcel once even
        // when the same rider handles both pickup and delivery.
        return $query->addSelect([
            'last_activity_at' => ShipmentEvent::selectRaw('MAX(created_at)')->whereColumn('actor_id', 'users.id'),
            'current_assignments' => Shipment::selectRaw('COUNT(*)')
                ->whereNotIn('status', self::FINISHED)
                ->where(fn ($q) => $q->whereColumn('rider_id', 'users.id')
                    ->orWhere(fn ($pickup) => $pickup->whereColumn('pickup_rider_id', 'users.id')->whereIn('status', self::PICKUP_ACTIVE))),
            'completed_deliveries' => Shipment::selectRaw('COUNT(*)')->whereColumn('rider_id', 'users.id')->whereIn('status', ['delivered', 'completed']),
        ]);
    }
}
