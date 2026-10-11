<?php

namespace App\Services;

use App\Models\{Shipment, User};
use App\Support\RiderPortal;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiderPortalActionService
{
    public function locked(Shipment $bound, User $actor, ?string $version, Closure $callback): mixed
    {
        return DB::transaction(function () use ($bound, $actor, $version, $callback) {
            $shipment = Shipment::whereKey($bound->id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $user->setRelation('riderProfile', $user->riderProfile()->lockForUpdate()->first());
            abort_unless(RiderPortal::active($user), 403);
            abort_unless(in_array($user->id, [$shipment->pickup_rider_id, $shipment->rider_id], true), 403);
            if ($version && !hash_equals(RiderPortal::version($shipment), $version)) $this->stale();
            return $callback($shipment, $user);
        }, 3);
    }

    public function perform(Shipment $shipment, User $rider, array $data): void
    {
        $this->locked($shipment, $rider, $data['version'] ?? null, function ($shipment, $rider) use ($data) {
            if (!in_array($data['action'], RiderPortal::actions($shipment, $rider->id), true)) $this->stale();
            $workflow = app(ParcelWorkflowService::class);
            $reason = isset($data['reason']) ? str($data['reason'])->replace('_', ' ')->headline().(filled($data['details'] ?? null) ? ': '.$data['details'] : '') : '';
            match ($data['action']) {
                'accept_pickup' => $workflow->acceptPickup($shipment, $rider),
                'confirm_pickup' => $workflow->confirmPickup($shipment, $rider, $data['parcel_code'] ?? ''),
                'arrive_sorting' => $workflow->arriveSortingCenter($shipment, $rider),
                'accept_delivery' => $workflow->acceptDelivery($shipment, $rider),
                'start_delivery' => $workflow->startDelivery($shipment, $rider),
                'delivered' => $workflow->deliver($shipment, $rider),
                'failed' => $workflow->fail($shipment, $rider, $reason),
                'legacy_pickup' => app(ShipmentService::class)->transition($shipment, $rider, 'picked_up'),
                'legacy_transit' => app(ShipmentService::class)->transition($shipment, $rider, 'in_transit'),
                'legacy_out' => app(ShipmentService::class)->transition($shipment, $rider, 'out_for_delivery'),
            };
        });
    }

    public function stale(): never
    {
        throw ValidationException::withMessages(['status' => 'This delivery was updated elsewhere. Refresh the page to view its latest status.']);
    }
}
