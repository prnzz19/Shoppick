<?php

namespace App\Http\Controllers;

use App\Models\DeliveryArea;
use App\Models\LogisticsProvider;
use App\Models\ProofOfDelivery;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CodCollectionService;
use App\Services\NotificationService;
use App\Services\ParcelWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LogisticsMobileController extends Controller
{
    private const FINISHED = ['delivered', 'completed', 'returned'];

    private const RELATIONS = ['order', 'sellerOrder.items', 'store', 'rider', 'pickupRider', 'provider', 'proofOfDelivery'];

    private function manager(Request $r): bool
    {
        return $r->attributes->get('logistics_role') === 'logistics';
    }

    private function permit(Request $r, string $permission): void
    {
        if ($this->manager($r)) {
            abort_unless($r->user()->hasPermissionTo($permission), 403, 'You do not have permission for this action.');
        }
    }

    private function scoped(Request $r)
    {
        return Shipment::query()->when(! $this->manager($r), fn ($q) => $q->where(fn ($q) => $q->where('rider_id', $r->user()->id)->orWhere('pickup_rider_id', $r->user()->id)));
    }

    private function shipment(Request $r, int $id, bool $lock = false): Shipment
    {
        $this->permit($r, 'view_shipments');

        return $this->scoped($r)->with(self::RELATIONS)->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
    }

    public function dashboard(Request $r)
    {
        $this->permit($r, 'view_logistics_dashboard');
        $q = $this->scoped($r);
        $summary = $this->manager($r) ? [
            'Pending pickups' => (clone $q)->whereIn('status', ['ready_for_pickup', 'pickup_assigned', 'pickup_accepted'])->count(),
            'In transit' => (clone $q)->whereIn('status', ['picked_up', 'in_transit', 'hub_transfer'])->count(),
            'Out for delivery' => (clone $q)->where('status', 'out_for_delivery')->count(),
            'Delivered today' => (clone $q)->whereDate('delivered_at', today())->count(),
            'Failed deliveries' => (clone $q)->whereIn('status', ['delivery_failed', 'exception', 'delivery_attempted'])->count(),
        ] : [
            'Assigned' => (clone $q)->whereIn('status', ['pickup_assigned', 'pickup_accepted', 'assigned_to_rider', 'assigned'])->count(),
            'Picked up' => (clone $q)->where('status', 'picked_up')->count(),
            'Out for delivery' => (clone $q)->where('status', 'out_for_delivery')->count(),
            'Completed today' => (clone $q)->whereDate('delivered_at', today())->count(),
        ];

        return response()->json(['summary' => $summary,
            'deliveries' => (clone $q)->with(self::RELATIONS)->whereNotIn('status', self::FINISHED)->oldest('assigned_at')->limit(8)->get()->map(fn ($s) => $this->data($s, $r)),
            'today' => (clone $q)->where(fn ($q) => $q->whereDate('assigned_at', today())->orWhereDate('estimated_delivery_at', today()))->count(),
            'unread_notifications' => $r->user()->notificationsData()->unread()->count()]);
    }

    public function deliveries(Request $r)
    {
        $this->permit($r, 'view_shipments');
        $d = $r->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(Shipment::STATUSES)], 'history' => 'nullable|boolean', 'rider_id' => 'nullable|integer', 'provider_id' => 'nullable|integer']);
        $q = $this->scoped($r)->with(self::RELATIONS)
            ->when($d['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($r->has('history'), fn ($q) => $r->boolean('history') ? $q->whereIn('status', self::FINISHED) : $q->whereNotIn('status', self::FINISHED))
            ->when($d['rider_id'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('rider_id', $v)->orWhere('pickup_rider_id', $v)))
            ->when($d['provider_id'] ?? null, fn ($q, $v) => $q->where('logistics_provider_id', $v))
            ->when($d['q'] ?? null, function ($q, $v) {
                $q->where(function ($q) use ($v) {
                    $term = '%'.$v.'%';
                    $q->where('shipment_number', 'like', $term)->orWhere('parcel_code', 'like', $term)
                        ->orWhereHas('order', fn ($q) => $q->where('order_number', 'like', $term)->orWhere('buyer_name', 'like', $term))
                        ->orWhereHas('order.user', fn ($q) => $q->where('name', 'like', $term));
                    foreach (['store', 'rider', 'pickupRider', 'provider'] as $relation) {
                        $q->orWhereHas($relation, fn ($q) => $q->where('name', 'like', $term));
                    }
                });
            });

        return response()->json(['deliveries' => $q->latest('updated_at')->latest('id')->paginate(20)->through(fn ($s) => $this->data($s, $r)), 'statuses' => Shipment::STATUSES]);
    }

    public function show(Request $r, int $delivery)
    {
        return response()->json(['delivery' => $this->data($this->shipment($r, $delivery), $r, true)]);
    }

    private function trackingRider(Shipment $s): ?int
    {
        if (in_array($s->status, ['pickup_accepted', 'picked_up'], true) && ! $s->pickup_arrived_at) {
            return $s->pickup_rider_id;
        }
        if ($s->status === 'out_for_delivery') {
            return $s->rider_id;
        }

        return null;
    }

    private function coordinates(?array $value): ?array
    {
        $lat = $value['latitude'] ?? null;
        $lng = $value['longitude'] ?? null;
        if (! is_numeric($lat) || ! is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return null;
        }

        return ['latitude' => (float) $lat, 'longitude' => (float) $lng];
    }

    public function tracking(Request $r, int $delivery)
    {
        $s = $this->shipment($r, $delivery);
        $activeRider = $this->trackingRider($s);
        $points = DB::table('shipment_tracking_points')->where('shipment_id', $s->id)
            ->orderByDesc('recorded_at')->orderByDesc('id')->limit(500)->get()->reverse()->values()
            ->map(fn ($p) => ['id' => $p->id, 'rider_id' => $p->rider_id, 'latitude' => (float) $p->latitude,
                'longitude' => (float) $p->longitude, 'accuracy' => $p->accuracy === null ? null : (float) $p->accuracy,
                'source' => $p->source, 'recorded_at' => $p->recorded_at]);
        $current = $activeRider ? $points->last(fn ($p) => $p['rider_id'] === $activeRider && $p['source'] === 'device' && \Illuminate\Support\Carbon::parse($p['recorded_at'])->greaterThan(now()->subMinutes(2))) : null;

        return response()->json(['delivery' => $this->data($s, $r, true),
            'origin' => $this->coordinates($s->pickup_address), 'destination' => $this->coordinates($s->delivery_address),
            'events' => $s->events()->oldest('id')->get()->map(fn ($e) => ['id' => $e->id, 'status' => $e->status,
                'note' => $e->note, 'location' => $e->location, 'created_at' => $e->created_at?->toIso8601String(),
                'coordinates' => $this->coordinates($e->metadata)]),
            'points' => $points, 'current_rider_location' => $current,
            'can_share_location' => ! $this->manager($r) && $activeRider === $r->user()->id,
            'active' => $activeRider !== null]);
    }

    public function location(Request $r, int $delivery)
    {
        abort_if($this->manager($r), 403);
        $d = $r->validate(['latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|between:0,999999', 'recorded_at' => 'required|date|after_or_equal:'.now()->subMinutes(2)->toIso8601String().'|before_or_equal:'.now()->addSeconds(30)->toIso8601String()]);
        DB::transaction(function () use ($r, $delivery, $d) {
            $s = $this->shipment($r, $delivery, true);
            abort_unless($this->trackingRider($s) === $r->user()->id, 422, 'Location sharing is only available during your active pickup or delivery.');
            $last = DB::table('shipment_tracking_points')->where('shipment_id', $s->id)->where('rider_id', $r->user()->id)->where('source', 'device')->latest('recorded_at')->first();
            abort_if($last && \Illuminate\Support\Carbon::parse($d['recorded_at'])->lessThanOrEqualTo(\Illuminate\Support\Carbon::parse($last->recorded_at)), 422, 'This location is older than the last recorded update.');
            DB::table('shipment_tracking_points')->insert($d + ['shipment_id' => $s->id, 'rider_id' => $r->user()->id, 'source' => 'device', 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['message' => 'Location recorded.'], 201);
    }

    private function actions(Shipment $s, Request $r): array
    {
        if ($this->manager($r)) {
            $actions = [];
            if ($r->user()->hasPermissionTo('assign_shipments') && in_array($s->status, ['ready_for_pickup', 'sorted'])) {
                $actions[] = 'assign';
            }
            if ($r->user()->hasPermissionTo('manage_shipments')) {
                if ($s->status === 'picked_up' && $s->pickup_arrived_at) {
                    $actions[] = 'receive';
                }
                if ($s->status === 'at_sorting_center') {
                    $actions[] = $s->parcel_scanned_at ? 'sort' : 'scan';
                }
                if ($s->status === 'delivery_failed') {
                    $actions = [...$actions, 'reschedule', 'return'];
                }
            }

            return $actions;
        }
        $id = $r->user()->id;
        if ($s->pickup_rider_id === $id) {
            if ($s->status === 'pickup_assigned') {
                return ['accept_pickup'];
            }
            if ($s->status === 'pickup_accepted') {
                return ['confirm_pickup'];
            }
            if ($s->status === 'picked_up' && ! $s->pickup_arrived_at) {
                return ['arrive_sorting'];
            }
        }
        if ($s->rider_id !== $id) {
            return [];
        }
        if ($s->status === 'assigned_to_rider') {
            return [$s->delivery_accepted_at ? 'start_delivery' : 'accept_delivery'];
        }
        if ($s->status === 'out_for_delivery') {
            $actions = ['proof', 'failed'];
            if ($s->order->payment_method === 'cod' && ! in_array($s->order->payment_status, ['cod_collected', 'paid'])) {
                $actions[] = 'collect_cod';
            }
            if ($s->proofOfDelivery && in_array($s->proofOfDelivery->status, ['pending', 'approved']) && ($s->order->payment_method !== 'cod' || in_array($s->order->payment_status, ['cod_collected', 'paid']))) {
                $actions[] = 'delivered';
            }

            return $actions;
        }

        return [];
    }

    private function data(Shipment $s, Request $r, bool $detail = false): array
    {
        $data = ['id' => $s->id, 'tracking_number' => $s->shipment_number, 'parcel_code' => $s->parcel_code,
            'order_number' => $s->order?->order_number, 'buyer' => $s->order?->buyer_name ?: $s->order?->user?->name,
            'shop' => $s->store?->name, 'provider' => $s->provider?->name, 'provider_id' => $s->logistics_provider_id,
            'rider' => $s->rider?->name, 'pickup_rider' => $s->pickupRider?->name, 'status' => $s->status,
            'destination' => $this->address($s->delivery_address), 'pickup_address' => $this->address($s->pickup_address),
            'updated_at' => $s->updated_at?->toIso8601String(), 'actions' => $this->actions($s, $r),
            'failure_reason' => $s->failure_reason, 'payment_type' => $s->order?->payment_method,
            'cod_amount' => $s->order?->payment_method === 'cod' ? (float) $s->order->payments()->where('method', 'cod')->latest('id')->value('amount') : null,
            'payment_status' => $s->order?->payment_status];
        if ($detail) {
            $data += ['phone' => $s->order?->buyer_phone, 'instructions' => $s->order?->note,
                'products' => $s->sellerOrder?->items->map(fn ($i) => ['name' => $i->product_name, 'quantity' => $i->quantity]),
                'proof' => $s->proofOfDelivery ? ['recipient_name' => $s->proofOfDelivery->recipient_name, 'notes' => $s->proofOfDelivery->notes, 'submitted_at' => $s->proofOfDelivery->submitted_at, 'status' => $s->proofOfDelivery->status] : null,
                'events' => $s->events()->oldest('id')->get(['status', 'note', 'created_at'])];
        }

        return $data;
    }

    private function address(?array $address): string
    {
        return collect($address)->only(['address', 'address_line', 'street', 'barangay', 'city', 'municipality', 'province', 'postal_code'])->filter(fn ($v) => is_scalar($v) && filled($v))->unique()->implode(', ');
    }

    public function assign(Request $r, int $delivery, ParcelWorkflowService $workflow)
    {
        $this->permit($r, 'assign_shipments');
        $d = $r->validate(['rider_id' => 'required|integer|exists:users,id']);
        DB::transaction(function () use ($r, $delivery, $d, $workflow) {
            $s = $this->shipment($r, $delivery, true);
            $rider = User::findOrFail($d['rider_id']);
            $profile = RiderProfile::where('user_id', $rider->id)->lockForUpdate()->firstOrFail();
            abort_if($s->logistics_provider_id && $s->logistics_provider_id !== $profile->logistics_provider_id, 422, 'Choose a Rider from this provider.');
            abort_if($s->provider && $s->provider->status !== 'active', 422, 'This provider is inactive.');
            match ($s->status) {
                'ready_for_pickup' => $workflow->assignPickup($s, $r->user(), $rider),
                'sorted' => $workflow->assignDelivery($s, $r->user(), $rider),
                default => abort(422, 'This delivery cannot be assigned at its current stage.'),
            };
        });

        return $this->show($r, $delivery);
    }

    public function status(Request $r, int $delivery, ParcelWorkflowService $workflow)
    {
        $this->permit($r, 'manage_shipments');
        $d = $r->validate(['action' => 'required|string', 'parcel_code' => 'nullable|string|max:100', 'delivery_area_id' => 'nullable|integer|exists:delivery_areas,id',
            'reason' => 'nullable|string|max:100', 'details' => 'nullable|string|max:500']);
        DB::transaction(function () use ($r, $delivery, $workflow, $d) {
            $s = $this->shipment($r, $delivery, true);
            abort_unless(in_array($d['action'], $this->actions($s, $r), true), 422, 'This action is no longer available. Refresh the delivery.');
            $actor = $r->user();
            if ($d['action'] === 'failed') {
                $r->validate(['reason' => 'required|in:recipient_unavailable,incorrect_address,reschedule_requested,parcel_issue,payment_issue,other', 'details' => 'nullable|required_if:reason,other|string|max:500']);
            }
            if ($d['action'] === 'return') {
                $r->validate(['details' => 'required|string|max:500']);
            }
            $reason = str($d['reason'] ?? '')->replace('_', ' ')->headline().(filled($d['details'] ?? null) ? ': '.$d['details'] : '');
            match ($d['action']) {
                'accept_pickup' => $workflow->acceptPickup($s, $actor),
                'confirm_pickup' => $workflow->confirmPickup($s, $actor, $d['parcel_code'] ?? ''),
                'arrive_sorting' => $workflow->arriveSortingCenter($s, $actor),
                'accept_delivery' => $workflow->acceptDelivery($s, $actor),
                'start_delivery' => $workflow->startDelivery($s, $actor),
                'delivered' => $workflow->deliver($s, $actor),
                'failed' => $workflow->fail($s, $actor, $reason),
                'collect_cod' => app(CodCollectionService::class)->collect($s, $actor),
                'receive' => $workflow->receive($s, $actor),
                'scan' => $workflow->confirmScan($s, $actor, $d['parcel_code'] ?? ''),
                'sort' => $workflow->sort($s, $actor, DeliveryArea::findOrFail($d['delivery_area_id'] ?? 0)),
                'reschedule' => $workflow->reschedule($s, $actor),
                'return' => $workflow->return($s, $actor, $d['details']),
                default => abort(422, 'Use the proof upload form for this action.'),
            };
        });

        return $this->show($r, $delivery);
    }

    public function proof(Request $r, int $delivery)
    {
        $r->validate(['recipient_name' => 'required|string|max:100', 'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=6000,max_height=6000', 'notes' => 'nullable|string|max:1000']);
        $path = null;
        try {
            DB::transaction(function () use ($r, $delivery, &$path) {
                $s = $this->shipment($r, $delivery, true);
                abort_unless($s->rider_id === $r->user()->id && $s->status === 'out_for_delivery', 403);
                $path = $r->file('photo')->store('pod', 'local');
                ProofOfDelivery::updateOrCreate(['shipment_id' => $s->id], ['submitted_by' => $r->user()->id, 'recipient_name' => $r->input('recipient_name'), 'photo_path' => $path, 'notes' => $r->input('notes'), 'status' => 'pending', 'submitted_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null, 'review_notes' => null]);
                foreach (User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('slug', 'logistics'))->get() as $manager) {
                    NotificationService::send($manager->id, 'Proof of Delivery submitted', $s->shipment_number.' requires review.', 'logistics', route('logistics.pod'), ['shipment_id' => $s->id], 'package');
                }
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }

        return $this->show($r, $delivery);
    }

    public function riders(Request $r)
    {
        $this->permit($r, 'manage_riders');
        $r->validate(['filter' => 'nullable|in:active,available,on_delivery,inactive', 'delivery_id' => 'nullable|integer']);
        $s = $r->filled('delivery_id') ? $this->shipment($r, $r->integer('delivery_id')) : null;
        $q = User::with('riderProfile.provider')->whereHas('roles', fn ($q) => $q->where('slug', 'rider'))->whereHas('riderProfile');
        if ($r->filter === 'available' || $s) {
            $q->where('is_active', true)->whereHas('riderProfile', fn ($q) => $q->assignmentEligible())
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('shipments')->where(fn ($q) => $q->whereColumn('rider_id', 'users.id')->orWhereColumn('pickup_rider_id', 'users.id'))->whereNotIn('status', self::FINISHED));
        } elseif ($r->filter === 'inactive') {
            $q->where(fn ($q) => $q->where('is_active', false)->orWhereHas('riderProfile', fn ($q) => $q->where('account_status', '!=', 'active')));
        } elseif ($r->filter === 'on_delivery') {
            $q->whereHas('riderProfile', fn ($q) => $q->whereIn('availability', ['assigned', 'on_delivery', 'on_pickup']));
        } else {
            $q->where('is_active', true)->whereHas('riderProfile', fn ($q) => $q->where('account_status', 'active'));
        }
        if ($s?->logistics_provider_id) {
            $q->whereHas('riderProfile', fn ($q) => $q->where('logistics_provider_id', $s->logistics_provider_id));
        }
        if ($s?->status === 'sorted') {
            $q->whereHas('riderProfile.deliveryAreas', fn ($q) => $q->where('delivery_areas.id', $s->delivery_area_id));
        }

        return response()->json(['riders' => $q->orderBy('name')->paginate(20)->through(function ($u) {
            $active = Shipment::where(fn ($q) => $q->where('rider_id', $u->id)->orWhere('pickup_rider_id', $u->id))->whereNotIn('status', self::FINISHED)->count();

            return ['id' => $u->id, 'name' => $u->name, 'phone' => $u->phone, 'avatar' => $u->avatar_url, 'provider' => $u->riderProfile->provider?->name, 'provider_id' => $u->riderProfile->logistics_provider_id, 'status' => $u->is_active ? $u->riderProfile->account_status : 'inactive', 'availability' => $u->riderProfile->availability, 'active_deliveries' => $active, 'completed_deliveries' => $u->assignedShipments()->whereIn('status', ['delivered', 'completed'])->count()];
        })]);
    }

    public function providers(Request $r)
    {
        $this->permit($r, 'view_shipments');

        return response()->json(['providers' => LogisticsProvider::withCount([
            'riders as active_riders' => fn ($q) => $q->where('account_status', 'active')->whereHas('user', fn ($q) => $q->where('is_active', true)),
            'shipments as active_deliveries' => fn ($q) => $q->whereNotIn('status', self::FINISHED),
            'shipments as completed_deliveries' => fn ($q) => $q->whereIn('status', ['delivered', 'completed']),
        ])->orderBy('name')->paginate(20)->through(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'code' => $p->code, 'status' => $p->status, 'logo' => $p->logo ? Storage::disk('public')->url($p->logo) : null, 'contact_email' => $p->contact_email, 'contact_phone' => $p->contact_phone, 'active_riders' => $p->active_riders, 'active_deliveries' => $p->active_deliveries, 'completed_deliveries' => $p->completed_deliveries])]);
    }

    public function saveProvider(Request $r)
    {
        $this->permit($r, 'manage_logistics_settings');
        $d = $r->validate(['name' => 'required|string|max:100', 'code' => 'required|alpha_dash|max:30|unique:logistics_providers,code', 'contact_email' => 'nullable|email|max:255', 'contact_phone' => 'nullable|string|max:30']);

        return response()->json(['provider' => LogisticsProvider::create($d)], 201);
    }

    public function linkProvider(Request $r, int $delivery)
    {
        $this->permit($r, 'assign_shipments');
        $d = $r->validate(['provider_id' => 'required|exists:logistics_providers,id']);
        DB::transaction(function () use ($r, $delivery, $d) {
            $s = $this->shipment($r, $delivery, true);
            abort_unless(in_array($s->status, ['ready_for_pickup', 'sorted']), 422, 'Set the provider before assigning a Rider.');
            $p = LogisticsProvider::findOrFail($d['provider_id']);
            abort_unless($p->status === 'active', 422, 'Provider is inactive.');
            $s->forceFill(['logistics_provider_id' => $p->id])->save();
        });

        return $this->show($r, $delivery);
    }

    public function riderProvider(Request $r, int $rider)
    {
        $this->permit($r, 'manage_riders');
        $d = $r->validate(['provider_id' => 'nullable|exists:logistics_providers,id']);
        DB::transaction(function () use ($rider, $d) {
            $u = User::findOrFail($rider);
            abort_unless($u->hasRole('rider'), 404);
            $p = $u->riderProfile()->lockForUpdate()->firstOrFail();
            abort_if(Shipment::where(fn ($q) => $q->where('rider_id', $rider)->orWhere('pickup_rider_id', $rider))->whereNotIn('status', self::FINISHED)->exists(), 422, 'Finish active assignments before changing provider.');
            $p->forceFill(['logistics_provider_id' => $d['provider_id'] ?? null])->save();
        });

        return response()->json(['message' => 'Provider updated.']);
    }

    public function areas(Request $r)
    {
        $this->permit($r, 'manage_shipments');

        return response()->json(['areas' => DeliveryArea::where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function notifications(Request $r)
    {
        return response()->json(['notifications' => $r->user()->notificationsData()->latest()->paginate(20)->through(fn ($n) => ['id' => $n->id, 'title' => $n->title, 'body' => $n->body, 'read_at' => $n->read_at, 'created_at' => $n->created_at, 'shipment_id' => data_get($n->data, 'shipment_id')])]);
    }

    public function readNotifications(Request $r)
    {
        $r->user()->notificationsData()->unread()->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifications marked as read.']);
    }
}
