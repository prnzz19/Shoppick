<?php

namespace App\Services;

use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Models\User;

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
        $shipmentCounts = Shipment::query()->selectRaw(
            "SUM(CASE WHEN status IN ('ready_for_pickup') THEN 1 ELSE 0 END) AS pickup_requests,
             SUM(CASE WHEN status IN ('pickup_assigned','pickup_accepted','picked_up') THEN 1 ELSE 0 END) AS incoming_parcels,
             SUM(CASE WHEN status = 'at_sorting_center' THEN 1 ELSE 0 END) AS sorting,
             SUM(CASE WHEN status = 'sorted' AND rider_id IS NULL THEN 1 ELSE 0 END) AS delivery_assignment,
             SUM(CASE WHEN status IN ('assigned_to_rider','in_transit','out_for_delivery','delivery_attempted','delivery_failed','exception') THEN 1 ELSE 0 END) AS delivery_monitoring"
        )->first();

        return [
            'pickup_requests' => (int) ($shipmentCounts->pickup_requests ?? 0),
            'incoming_parcels' => (int) ($shipmentCounts->incoming_parcels ?? 0),
            'sorting' => (int) ($shipmentCounts->sorting ?? 0),
            'delivery_assignment' => (int) ($shipmentCounts->delivery_assignment ?? 0),
            'delivery_monitoring' => (int) ($shipmentCounts->delivery_monitoring ?? 0),
            'rider_attention' => RiderProfile::whereHas('user',fn($q)=>$q->where(fn($u)=>$u->where('registration_status','approved')->orWhereNull('registration_status')))->where(fn($q)=>$q->whereIn('driver_license_status',['pending_review','rejected'])->orWhereDate('driver_license_expires_at','<=',now()->addDays(config('riders.license_expiry_warning_days',30))))->count(),
            'rider_applications' => RiderProfile::whereHas('user',fn($q)=>$q->where('registration_type','rider')->whereIn('registration_status',['pending','needs_resubmission']))->count(),
            'pending_riders' => RiderProfile::whereHas('user',fn($q)=>$q->where('registration_type','rider')->whereIn('registration_status',['pending','needs_resubmission']))->count(),
            'unread_messages' => $user->notificationsData()->unread()->count(),
        ];
    }
}
