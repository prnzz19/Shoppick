<?php

namespace App\Services;

use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Support\LogisticsProviderContext;

class LogisticsSidebarCounts
{
    public const PICKUP_REQUEST_STATUSES = ['ready_for_pickup'];
    public const INCOMING_STATUSES = ['pickup_assigned', 'pickup_accepted', 'picked_up'];
    public const SORTING_STATUSES = ['at_sorting_center'];
    public const DELIVERY_MONITORING_STATUSES = [
        'assigned_to_rider', 'in_transit', 'out_for_delivery',
        'delivery_attempted', 'delivery_failed', 'exception',
    ];

    public function for(User $user): array
    {
        $provider = app(LogisticsProviderContext::class)->forUser($user);

        if (! $provider) {
            return [
                'pickup_requests' => 0,
                'incoming_parcels' => 0,
                'sorting' => 0,
                'delivery_assignment' => 0,
                'awaiting_assignment' => 0,
                'delivery_monitoring' => 0,
                'available_riders' => 0,
                'rider_attention' => 0,
                'rider_applications' => 0,
                'pending_riders' => 0,
                'unread_messages' => $user->notificationsData()->unread()->count(),
            ];
        }

        $shipmentCounts = Shipment::forProvider($provider->id)->selectRaw(
            "SUM(CASE WHEN status IN ('ready_for_pickup') THEN 1 ELSE 0 END) AS pickup_requests,
             SUM(CASE WHEN status IN ('pickup_assigned','pickup_accepted','picked_up') THEN 1 ELSE 0 END) AS incoming_parcels,
             SUM(CASE WHEN status = 'at_sorting_center' THEN 1 ELSE 0 END) AS sorting,
             SUM(CASE WHEN status = 'sorted' AND rider_id IS NULL THEN 1 ELSE 0 END) AS delivery_assignment,
             SUM(CASE WHEN (status = 'ready_for_pickup' AND pickup_rider_id IS NULL)
                           OR (status IN ('at_sorting_center','sorted') AND rider_id IS NULL)
                      THEN 1 ELSE 0 END) AS awaiting_assignment,
             SUM(CASE WHEN status IN ('assigned_to_rider','in_transit','out_for_delivery','delivery_attempted','delivery_failed','exception') THEN 1 ELSE 0 END) AS delivery_monitoring"
        )->first();

        $riders = RiderProfile::forProvider($provider->id)
            ->whereHas('user', fn ($query) => $query
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('registration_status', 'approved')->orWhereNull('registration_status')));
        $eligibleRiders = (clone $riders)->assignmentEligible();

        return [
            'pickup_requests' => (int) ($shipmentCounts->pickup_requests ?? 0),
            'incoming_parcels' => (int) ($shipmentCounts->incoming_parcels ?? 0),
            'sorting' => (int) ($shipmentCounts->sorting ?? 0),
            'delivery_assignment' => (int) ($shipmentCounts->delivery_assignment ?? 0),
            'awaiting_assignment' => (int) ($shipmentCounts->awaiting_assignment ?? 0),
            'delivery_monitoring' => (int) ($shipmentCounts->delivery_monitoring ?? 0),
            'available_riders' => (int) (clone $eligibleRiders)->where('availability', 'available')->count(),
            'rider_attention' => (clone $riders)->where(fn($q)=>$q->whereIn('driver_license_status',['pending_review','rejected'])->orWhereDate('driver_license_expires_at','<=',now()->addDays(config('riders.license_expiry_warning_days',30))))->count(),
            'rider_applications' => 0,
            'pending_riders' => (clone $riders)->where('account_status','pending_activation')->whereHas('user', fn ($q) => $q->where('is_active', false)->where('registration_status', 'pending'))->count(),
            'unread_messages' => $user->notificationsData()->unread()->count(),
        ];
    }
}
