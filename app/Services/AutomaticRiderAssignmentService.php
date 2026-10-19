<?php

namespace App\Services;

use App\Models\{DeliveryArea, LogisticsHub, LogisticsProvider, RiderProfile, Shipment, ShipmentAssignment, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Selects and assigns local Riders for the two automatic dispatch triggers.
 *
 * This service deliberately uses the existing Shipment and ShipmentAssignment
 * records. A shipment row lock makes repeated ready-to-ship and scanner calls
 * idempotent, while declined assignment rows provide the current-cycle
 * exclusion list.
 */
class AutomaticRiderAssignmentService
{
    public function assignPickup(Shipment $shipment, ?int $providerId = null, array $excludeRiderIds = []): ?Shipment
    {
        return $this->assign($shipment, 'pickup', $providerId, $excludeRiderIds);
    }

    public function assignDelivery(Shipment $shipment, ?int $providerId = null, array $excludeRiderIds = []): ?Shipment
    {
        return $this->assign($shipment, 'delivery', $providerId, $excludeRiderIds);
    }

    public function assign(Shipment $bound, string $type, ?int $providerId = null, array $excludeRiderIds = []): ?Shipment
    {
        abort_unless(in_array($type, ['pickup', 'delivery'], true), 500, 'Unsupported automatic assignment type.');

        return DB::transaction(function () use ($bound, $type, $providerId, $excludeRiderIds): ?Shipment {
            $shipment = Shipment::query()->whereKey($bound->id)->lockForUpdate()->firstOrFail();

            if (in_array($shipment->status, Shipment::TERMINAL_STATUSES, true)) {
                return null;
            }

            $provider = LogisticsProvider::query()->whereKey($providerId ?: $shipment->logistics_provider_id)->first();
            if (! $provider || ! $provider->isOperational()) {
                return null;
            }

            $this->prepareRoute($shipment, $provider->id);

            if ($type === 'pickup') {
                if ($shipment->pickup_rider_id || $this->hasActiveAssignment($shipment, 'pickup')) {
                    return $shipment;
                }
                if ($shipment->status !== 'ready_for_pickup' || ! $shipment->origin_hub_id) {
                    $this->awaiting($shipment, $type, $provider);
                    return $shipment;
                }
            } else {
                if ($shipment->rider_id || $this->hasActiveAssignment($shipment, 'delivery')) {
                    return $shipment;
                }
                if (! $shipment->destination_hub_id || (int) $shipment->current_hub_id !== (int) $shipment->destination_hub_id) {
                    return $shipment;
                }
                if (! in_array($shipment->status, ['at_sorting_center', 'sorted'], true) || ! $shipment->delivery_area_id) {
                    $this->awaiting($shipment, $type, $provider);
                    return $shipment;
                }
            }

            $excluded = collect($excludeRiderIds)->map(fn ($id) => (int) $id)->filter()->values();
            $excluded = $excluded->merge(
                $shipment->assignments()
                    ->where('assignment_type', $type)
                    ->where('status', 'declined')
                    ->pluck('rider_id')
            )->unique()->values();

            $candidate = $this->candidates($shipment, $provider->id, $type, $excluded)->first();
            if (! $candidate) {
                $this->awaiting($shipment, $type, $provider);
                return $shipment;
            }

            $actor = User::query()->find($provider->owner_user_id);
            if (! $actor) {
                $this->awaiting($shipment, $type, $provider);
                return $shipment;
            }

            $this->recordAssignment($shipment, $candidate, $actor, $provider, $type);

            return $shipment->fresh();
        }, 3);
    }

    /**
     * Resolve only information that can be derived from existing structured
     * address/coverage data. No geographic guess is made when data is absent.
     */
    public function prepareRoute(Shipment $shipment, int $providerId): Shipment
    {
        $areas = DeliveryArea::query()->where('logistics_provider_id', $providerId)->where('is_active', true)
            ->with(['sortingCenters' => fn ($query) => $query->where('status', 'active')])->get();

        if (! $shipment->delivery_area_id) {
            $area = $areas->first(fn (DeliveryArea $candidate) => $this->addressMatchesArea($shipment->delivery_address, $candidate));
            if ($area) {
                $shipment->delivery_area_id = $area->id;
            }
        }

        if (! $shipment->destination_hub_id && $shipment->delivery_area_id) {
            $area = $areas->firstWhere('id', (int) $shipment->delivery_area_id);
            $hub = $area?->sortingCenters->first();
            if ($hub) {
                $shipment->destination_hub_id = $hub->id;
            }
        }

        if (! $shipment->origin_hub_id) {
            $hubs = LogisticsHub::query()->where('logistics_provider_id', $providerId)->where('status', 'active')->get();
            $address = $this->addressText($shipment->pickup_address);
            $matching = $hubs->filter(function (LogisticsHub $hub) use ($address): bool {
                if (! $address) return false;
                return collect([$hub->name, $hub->code, $hub->address, $hub->street, $hub->barangay, $hub->municipality, $hub->province])
                    ->filter()->map(fn ($value) => mb_strtolower((string) $value))->contains(fn ($value) => str_contains(mb_strtolower($address), $value));
            });
            $hub = $matching->count() === 1 ? $matching->first() : ($hubs->count() === 1 ? $hubs->first() : null);
            if ($hub) {
                $shipment->origin_hub_id = $hub->id;
            }
        }

        if ($shipment->isDirty(['delivery_area_id', 'origin_hub_id', 'destination_hub_id'])) {
            $shipment->save();
        }

        return $shipment;
    }

    public function validateManualCandidate(Shipment $shipment, User $rider, string $type): void
    {
        $profile = $rider->riderProfile;
        if (! $profile) return;

        $hubId = $type === 'pickup' ? $shipment->origin_hub_id : $shipment->destination_hub_id;
        if ($hubId && (int) $profile->hub_id !== (int) $hubId) {
            throw validation_error('rider_id', 'This Rider is not assigned to the Shipment Sorting Center.');
        }
        if ($profile->rider_type && ! in_array($profile->rider_type, [$type, 'both'], true)) {
            throw validation_error('rider_id', "This Rider is not approved for {$type} assignments.");
        }
        if ($type === 'pickup' && $profile->approved_service_area && ! $this->serviceAreaTextMatches($profile->approved_service_area, $shipment->pickup_address)) {
            throw validation_error('rider_id', 'This Rider is not approved for the pickup service area.');
        }
        if ($type === 'delivery' && $shipment->delivery_area_id && ! $profile->deliveryAreas()->whereKey($shipment->delivery_area_id)->exists()) {
            throw validation_error('rider_id', 'This Rider is not assigned to the Parcel delivery area.');
        }
    }

    private function candidates(Shipment $shipment, int $providerId, string $type, Collection $excluded): Collection
    {
        return User::query()->with(['riderProfile.hub', 'riderProfile.deliveryAreas'])
            ->where('is_active', true)->where(function ($query) {
                $query->where('registration_status', 'approved')->orWhereNull('registration_status');
            })
            ->whereHas('roles', fn ($query) => $query->where('slug', 'rider'))
            ->whereHas('riderProfile', function ($query) use ($providerId) {
                $query->where('logistics_provider_id', $providerId)->assignmentEligible();
            })->get()->filter(function (User $rider) use ($shipment, $type, $excluded): bool {
                if ($excluded->contains((int) $rider->id)) return false;
                $profile = $rider->riderProfile;
                // The existing SHOPPICK workflow marks a Rider assigned and
                // rejects a second active job. Keep that one-job capacity
                // invariant for automatic dispatch as well.
                if ($this->activeWorkload($rider->id, $shipment->id, (int) $shipment->logistics_provider_id) > 0) return false;
                $hubId = $type === 'pickup' ? $shipment->origin_hub_id : $shipment->destination_hub_id;
                if (! $profile || ! $hubId || (int) $profile->hub_id !== (int) $hubId) return false;
                if (! in_array($profile->rider_type, [$type, 'both'], true)) return false;
                if ($type === 'pickup') {
                    return (bool) $profile->approved_service_area
                        && $this->serviceAreaTextMatches($profile->approved_service_area, $shipment->pickup_address);
                }
                return (bool) $shipment->delivery_area_id
                    && $profile->deliveryAreas->contains('id', (int) $shipment->delivery_area_id);
            })->sort(function (User $left, User $right) use ($shipment, $type): int {
                $leftCount = $this->activeWorkload($left->id, $shipment->id, (int) $shipment->logistics_provider_id);
                $rightCount = $this->activeWorkload($right->id, $shipment->id, (int) $shipment->logistics_provider_id);
                if ($leftCount !== $rightCount) return $leftCount <=> $rightCount;
                $leftLast = $this->lastAssignedAt($left->id, $type);
                $rightLast = $this->lastAssignedAt($right->id, $type);
                if ($leftLast !== $rightLast) return $leftLast <=> $rightLast;
                return $left->id <=> $right->id;
            })->values();
    }

    private function recordAssignment(Shipment $shipment, User $rider, User $actor, LogisticsProvider $provider, string $type): void
    {
        $now = now();
        $cycle = $type.':'.$shipment->id.':'.($shipment->ready_at?->timestamp ?: $shipment->created_at?->timestamp ?: $shipment->id);
        $values = [
            'rider_id' => $rider->id,
            'assigned_by' => $actor->id,
            'assignment_type' => $type,
            'status' => 'assigned',
            'assigned_at' => $now,
            'assignment_cycle' => $cycle,
            'reason' => 'Automatic local Rider assignment.',
        ];
        if ($type === 'pickup') {
            $shipment->update(['pickup_rider_id' => $rider->id, 'pickup_assigned_by' => $actor->id, 'assigned_at' => $now, 'status' => 'pickup_assigned']);
            $eventStatus = 'pickup_assigned';
            $eventType = 'automatic_pickup_assignment';
            $title = 'New parcel pickup assignment';
            $body = "Collect {$shipment->parcel_code} from {$shipment->store?->name}.";
        } else {
            $shipment->update(['rider_id' => $rider->id, 'delivery_assigned_by' => $actor->id, 'delivery_assigned_at' => $now, 'assigned_at' => $now, 'status' => 'assigned_to_rider']);
            $eventStatus = 'assigned_to_rider';
            $eventType = 'automatic_delivery_assignment';
            $title = 'New parcel delivery assignment';
            $body = "Deliver {$shipment->parcel_code} in {$shipment->deliveryArea?->name}.";
        }
        $shipment->assignments()->create($values);
        $key = "{$eventType}:{$shipment->id}:{$rider->id}";
        $shipment->events()->firstOrCreate(['event_key' => $key], [
            'actor_id' => $actor->id, 'status' => $eventStatus, 'event_type' => $eventType,
            'location' => $type === 'delivery' ? $shipment->deliveryArea?->name : $shipment->originHub?->name,
            'note' => 'Automatically assigned to the nearest eligible local Rider.',
            'metadata' => ['provider_id' => $provider->id, 'assignment_type' => $type, 'rider_id' => $rider->id, 'distance_used' => false],
        ]);
        $rider->riderProfile->update(['availability' => 'assigned']);
        NotificationService::send($rider->id, $title, $body, 'logistics', route('rider.shipments.show', $shipment), ['shipment_id' => $shipment->id, 'assignment_type' => $type], 'package');
    }

    private function awaiting(Shipment $shipment, string $type, LogisticsProvider $provider): void
    {
        $eventType = "automatic_{$type}_assignment_awaiting";
        $key = "{$eventType}:{$shipment->id}";
        $shipment->events()->firstOrCreate(['event_key' => $key], [
            'actor_id' => $provider->owner_user_id, 'status' => 'awaiting_rider_assignment', 'event_type' => $eventType,
            'note' => 'No eligible local Rider was available; Logistics manual assignment remains available.',
            'metadata' => ['provider_id' => $provider->id, 'assignment_type' => $type],
        ]);
    }

    private function hasActiveAssignment(Shipment $shipment, string $type): bool
    {
        return $shipment->assignments()->where('assignment_type', $type)->whereNull('ended_at')->whereNotIn('status', ['declined', 'ended'])->exists();
    }

    private function activeWorkload(int $riderId, int $shipmentId, int $providerId): int
    {
        return Shipment::query()->forProvider($providerId)->whereKeyNot($shipmentId)->activeNonTerminal()->where(fn ($query) => $query->where('pickup_rider_id', $riderId)->orWhere('rider_id', $riderId))->count();
    }

    private function lastAssignedAt(int $riderId, string $type): int
    {
        $assignedAt = ShipmentAssignment::query()->where('rider_id', $riderId)
            ->where(function ($query) use ($type) { $query->where('assignment_type', $type)->orWhereNull('assignment_type'); })
            ->latest('assigned_at')->value('assigned_at');
        return $assignedAt ? (int) now()->parse($assignedAt)->timestamp : 0;
    }

    private function addressMatchesArea(?array $address, DeliveryArea $area): bool
    {
        $province = mb_strtolower((string) data_get($address, 'province'));
        $municipality = mb_strtolower((string) (data_get($address, 'municipality') ?: data_get($address, 'city')));
        $barangay = mb_strtolower((string) data_get($address, 'barangay'));
        if (! $province || mb_strtolower((string) $area->province) !== $province) return false;
        if ($area->municipality && mb_strtolower((string) $area->municipality) !== $municipality) return false;
        $covered = collect($area->barangays ?: [])->map(fn ($value) => mb_strtolower((string) $value));
        return $covered->isEmpty() || ($barangay && $covered->contains($barangay));
    }

    private function serviceAreaTextMatches(string $approved, ?array $address): bool
    {
        $approved = mb_strtolower(trim($approved));
        $text = mb_strtolower($this->addressText($address));
        if (! $approved || ! $text) return false;
        return str_contains($text, $approved) || str_contains($approved, $text)
            || collect(preg_split('/[,|\/]+/', $approved))->map('trim')->filter(fn ($part) => mb_strlen($part) > 2)->every(fn ($part) => str_contains($text, $part));
    }

    private function addressText(?array $address): string
    {
        return collect($address ?: [])->flatten()->filter(fn ($value) => is_scalar($value) && filled($value))->implode(', ');
    }
}

function validation_error(string $field, string $message): \Illuminate\Validation\ValidationException
{
    return \Illuminate\Validation\ValidationException::withMessages([$field => $message]);
}
