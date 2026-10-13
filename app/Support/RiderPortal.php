<?php

namespace App\Support;

use App\Models\{Shipment, User};
use Illuminate\Database\Eloquent\Builder;

/** Web portal rules; mobile API workflows continue to use their existing services. */
class RiderPortal
{
    public const HISTORY = ['delivered', 'completed', 'returned', 'delivery_failed', 'delivery_attempted', 'exception'];
    public const PICKUP = ['pickup_assigned', 'pickup_accepted', 'picked_up'];
    public const DELIVERY = ['assigned', 'pickup_scheduled', 'picked_up', 'at_hub', 'hub_transfer', 'in_transit', 'assigned_to_rider', 'out_for_delivery'];

    public static function hasRole(User $user): bool
    {
        return $user->hasRole('rider') && !$user->hasRole(['admin', 'super_admin', 'logistics', 'seller']);
    }

    public static function active(User $user): bool
    {
        return self::hasRole($user) && $user->is_active && !$user->trashed()
            && !$user->getAttribute('archived_at') && $user->riderProfile?->account_status === 'active'
            && (!$user->registration_status || $user->registration_status === 'approved');
    }

    public static function awaitingReview(User $user): bool
    {
        return self::hasRole($user)&&!$user->trashed()&&!$user->getAttribute('archived_at')&&$user->registration_type==='rider'
            &&in_array($user->registration_status,['pending','rejected','needs_resubmission','incomplete'],true);
    }

    public static function owned(int $id): Builder
    {
        return Shipment::query()->where(fn ($q) => $q->where('pickup_rider_id', $id)->orWhere('rider_id', $id));
    }

    public static function actionable(int $id): Builder
    {
        return self::owned($id)->where(fn ($q) => $q
            ->where(fn ($p) => $p->where('pickup_rider_id', $id)->whereIn('status', self::PICKUP)->whereNull('pickup_arrived_at'))
            ->orWhere(fn ($d) => $d->where('rider_id', $id)->whereIn('status', self::DELIVERY)));
    }

    public static function actions(Shipment $shipment, int $id): array
    {
        $actions = [];
        if ($shipment->pickup_rider_id === $id) {
            $action = match ($shipment->status) {
                'pickup_assigned' => 'accept_pickup',
                'pickup_accepted' => $shipment->pickup_accepted_at ? 'confirm_pickup' : null,
                'picked_up' => !$shipment->pickup_arrived_at ? 'arrive_sorting' : null,
                default => null,
            };
            if ($action) $actions[] = $action;
        }
        if ($shipment->rider_id === $id) {
            $action = match ($shipment->status) {
                'assigned_to_rider' => $shipment->delivery_accepted_at ? 'start_delivery' : 'accept_delivery',
                'assigned', 'pickup_scheduled' => 'legacy_pickup',
                'picked_up' => !$shipment->pickup_rider_id ? 'legacy_transit' : null,
                'at_hub', 'hub_transfer' => 'legacy_transit',
                'in_transit', 'delivery_attempted' => 'legacy_out',
                'out_for_delivery' => 'delivered',
                default => null,
            };
            if ($action) $actions[] = $action;
            if ($shipment->status === 'out_for_delivery') $actions[] = 'failed';
        }
        return $actions;
    }

    public static function version(Shipment $shipment): string
    {
        // Events also distinguish updates made within the same timestamp second.
        return hash('sha256', json_encode([$shipment->getAttributes(), $shipment->events()->max('id'), $shipment->proofOfDelivery()->first()?->getAttributes()]));
    }

    public static function address(?array $address, bool $brief = false): string
    {
        $keys = $brief ? ['city', 'municipality', 'province'] : ['address', 'address_line', 'address_line_1', 'street', 'barangay', 'city', 'municipality', 'province', 'postal_code'];
        return collect($address ?? [])->only($keys)->filter(fn ($value) => is_string($value) && filled($value))->unique()->implode(', ');
    }
}
