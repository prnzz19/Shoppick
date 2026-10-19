<?php

namespace App\Services;

use App\Models\{Shipment, User};
use Illuminate\Validation\ValidationException;

class RiderAssignmentDecisionService
{
    public function decline(Shipment $bound, User $rider, array $data): Shipment
    {
        return app(RiderPortalActionService::class)->locked($bound, $rider, $data['version'] ?? null,
            function (Shipment $shipment, User $user) use ($data): Shipment {
                $pickup = $shipment->pickup_rider_id === $user->id && $shipment->status === 'pickup_assigned';
                $delivery = $shipment->rider_id === $user->id && $shipment->status === 'assigned_to_rider' && ! $shipment->delivery_accepted_at;
                if (! $pickup && ! $delivery) {
                    throw ValidationException::withMessages(['reason' => 'This assignment can no longer be declined.']);
                }
                $reason = str($data['reason'])->replace('_', ' ')->headline().(isset($data['details']) ? ': '.$data['details'] : '');
                $type = $pickup ? 'pickup' : 'delivery';
                $assignment = $shipment->assignments()->where('rider_id', $user->id)->where('assignment_type', $type)->whereNull('ended_at')->latest('id')->first();
                if ($assignment) {
                    $assignment->update(['status' => 'declined', 'declined_at' => now(), 'decline_reason' => $reason, 'ended_at' => now()]);
                }
                $restoreStatus = $pickup ? 'ready_for_pickup' : ($shipment->events()->where('status', 'sorted')->exists() ? 'sorted' : 'at_sorting_center');
                $shipment->update($pickup
                    ? ['pickup_rider_id' => null, 'pickup_assigned_by' => null, 'assigned_at' => null, 'status' => $restoreStatus]
                    : ['rider_id' => null, 'delivery_assigned_by' => null, 'delivery_assigned_at' => null, 'assigned_at' => null, 'status' => $restoreStatus]);
                $shipment->events()->create(['actor_id' => $user->id, 'status' => 'assignment_declined', 'event_type' => 'rider_assignment_declined', 'note' => $reason, 'metadata' => ['assignment_type' => $type, 'rider_id' => $user->id]]);
                $user->riderProfile?->update(['availability' => 'available']);
                foreach (User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('slug', 'logistics'))->get() as $manager) {
                    NotificationService::send($manager->id, 'Rider declined assignment', "{$shipment->parcel_code}: {$reason}", 'logistics', route($pickup ? 'logistics.pickup-requests' : 'logistics.delivery-assignment'), ['shipment_id' => $shipment->id], 'package');
                }
                app(AutomaticRiderAssignmentService::class)->assign($shipment, $type, $shipment->logistics_provider_id, [$user->id]);
                return $shipment->fresh();
            });
    }
}
