<?php

namespace App\Services;

use App\Models\{DeliveryArea, HubTransfer, LogisticsHub, Shipment, User, Vehicle};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HubTransferService
{
    public function create(Shipment $bound, User $actor, int $destinationHubId): HubTransfer
    {
        $provider = app(\App\Support\LogisticsProviderContext::class)->requireFor($actor);

        return DB::transaction(function () use ($bound, $actor, $destinationHubId, $provider): HubTransfer {
            $shipment = Shipment::forProvider($provider->id)->whereKey($bound->id)->lockForUpdate()->firstOrFail();
            if ($shipment->status !== 'sorted' || ! $shipment->current_hub_id) {
                throw ValidationException::withMessages(['shipment_id' => 'Only a sorted Parcel at a verified Sorting Center can enter a Hub Transfer.']);
            }
            if (HubTransfer::forProvider($provider->id)->where('shipment_id', $shipment->id)->whereNotIn('status', ['completed', 'exception'])->exists()) {
                throw ValidationException::withMessages(['shipment_id' => 'This Parcel already has an active Hub Transfer.']);
            }

            $destination = LogisticsHub::forProvider($provider->id)->whereKey($destinationHubId)->where('status', 'active')->firstOrFail();
            if ((int) $destination->id === (int) $shipment->current_hub_id) {
                throw ValidationException::withMessages(['destination_hub_id' => 'Choose a different active destination Sorting Center for a transfer.']);
            }
            if ($shipment->destination_hub_id && (int) $shipment->destination_hub_id !== (int) $destination->id) {
                throw ValidationException::withMessages(['destination_hub_id' => 'The transfer destination must match the Parcel route destination.']);
            }
            if ($shipment->delivery_area_id) {
                $covered = DeliveryArea::forProvider($provider->id)
                    ->whereKey($shipment->delivery_area_id)
                    ->where('is_active', true)
                    ->whereHas('sortingCenters', fn ($query) => $query->whereKey($destination->id)->where('status', 'active'))
                    ->exists();
                if (! $covered) {
                    throw ValidationException::withMessages(['destination_hub_id' => 'The selected destination Sorting Center is not configured for this Parcel delivery area.']);
                }
            }

            $reference = 'HT-'.now()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            $transfer = HubTransfer::create([
                'logistics_provider_id' => $provider->id,
                'shipment_id' => $shipment->id,
                'origin_hub_id' => $shipment->current_hub_id,
                'destination_hub_id' => $destination->id,
                'transfer_reference' => $reference,
                'created_by' => $actor->id,
                'status' => 'pending',
            ]);

            $shipment->update(['destination_hub_id' => $destination->id, 'next_hub_id' => $destination->id, 'status' => 'hub_transfer']);
            $shipment->events()->create([
                'actor_id' => $actor->id,
                'status' => 'hub_transfer',
                'event_type' => 'hub_transfer_scheduled',
                'event_key' => 'hub-transfer-scheduled:'.$transfer->id,
                'sorting_center_id' => $shipment->current_hub_id,
                'location' => $destination->name,
                'note' => 'Hub Transfer '.$reference.' scheduled from the verified origin Sorting Center.',
                'metadata' => ['provider_id' => $provider->id, 'transfer_id' => $transfer->id],
            ]);
            DB::table('hub_movements')->insert([
                'shipment_id' => $shipment->id,
                'from_hub_id' => $transfer->origin_hub_id,
                'to_hub_id' => $transfer->destination_hub_id,
                'recorded_by' => $actor->id,
                'type' => 'transfer',
                'note' => 'Hub Transfer '.$reference.' scheduled.',
                'moved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $transfer;
        }, 3);
    }

    public function assignDriver(HubTransfer $bound, User $actor, int $driverId, ?int $vehicleId = null): HubTransfer
    {
        $provider = app(\App\Support\LogisticsProviderContext::class)->requireFor($actor);

        return DB::transaction(function () use ($bound, $actor, $driverId, $vehicleId, $provider): HubTransfer {
            $transfer = HubTransfer::forProvider($provider->id)->whereKey($bound->id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'A transfer driver can only be assigned while the transfer is pending.']);
            }
            $driver = User::query()->whereKey($driverId)->where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', 'rider'))->whereHas('riderProfile', fn ($query) => $query->where('logistics_provider_id', $provider->id)->assignmentEligible())->first();
            if (! $driver) throw ValidationException::withMessages(['transfer_driver_id' => 'Choose an active, eligible Rider belonging to this Logistics Provider.']);
            $busy = Shipment::forProvider($provider->id)->activeNonTerminal()->where(function ($query) use ($driver) {
                $query->where('rider_id', $driver->id)->orWhere('pickup_rider_id', $driver->id);
            })->exists();
            if ($busy) throw ValidationException::withMessages(['transfer_driver_id' => 'This Rider already has an active non-terminal Parcel assignment.']);
            if ($this->hasActiveTransferAssignment($driver->id)) {
                throw ValidationException::withMessages(['transfer_driver_id' => 'This Rider already has an active Hub Transfer assignment.']);
            }
            $vehicle = null;
            if ($vehicleId) {
                $vehicle = Vehicle::query()->whereKey($vehicleId)->where('logistics_provider_id', $provider->id)->whereIn('status', ['available', 'in_use'])->first();
                if (! $vehicle) throw ValidationException::withMessages(['vehicle_id' => 'Choose a vehicle owned by this Logistics Provider.']);
            }

            $transfer->update(['transfer_driver_id' => $driver->id, 'vehicle_id' => $vehicle?->id, 'status' => 'assigned']);
            $driver->riderProfile->update(['availability' => 'assigned']);
            $transfer->shipment->assignments()->create([
                'rider_id' => $driver->id,
                'vehicle_id' => $vehicle?->id,
                'assigned_by' => $actor->id,
                'assignment_type' => 'transfer',
                'status' => 'assigned',
                'assigned_at' => now(),
                'reason' => 'Hub Transfer '.$transfer->transfer_reference.' assignment.',
            ]);
            $transfer->shipment->events()->create([
                'actor_id' => $actor->id,
                'status' => 'hub_transfer',
                'event_type' => 'hub_transfer_driver_assigned',
                'event_key' => 'hub-transfer-driver:'.$transfer->id,
                'location' => $transfer->originHub->name,
                'note' => $driver->name.' assigned to Hub Transfer '.$transfer->transfer_reference.'.',
                'metadata' => ['provider_id' => $provider->id, 'transfer_id' => $transfer->id, 'driver_id' => $driver->id],
            ]);

            return $transfer->fresh(['driver', 'vehicle', 'originHub', 'destinationHub']);
        }, 3);
    }

    public function dispatch(HubTransfer $bound, User $actor): HubTransfer
    {
        return $this->transition($bound, $actor, 'assigned', 'in_transit', function (HubTransfer $transfer): array {
            return ['dispatched_at' => now()];
        }, 'hub_transfer_dispatched', 'Hub Transfer dispatched from the origin Sorting Center.');
    }

    public function arrive(HubTransfer $bound, User $actor): HubTransfer
    {
        return $this->transition($bound, $actor, 'in_transit', 'arrived', function (): array {
            return ['arrived_at' => now()];
        }, 'hub_transfer_arrived', 'Transfer vehicle arrived at the destination Sorting Center; parcel receipt scan is still required.');
    }

    public function receive(HubTransfer $bound, User $actor, string $parcelCode): HubTransfer
    {
        $provider = app(\App\Support\LogisticsProviderContext::class)->requireFor($actor);

        return DB::transaction(function () use ($bound, $actor, $parcelCode, $provider): HubTransfer {
            $transfer = HubTransfer::forProvider($provider->id)->whereKey($bound->id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== 'arrived') throw ValidationException::withMessages(['status' => 'Verify transfer arrival before recording destination receipt.']);
            $shipment = Shipment::forProvider($provider->id)->whereKey($transfer->shipment_id)->lockForUpdate()->firstOrFail();
            if (! hash_equals((string) ($shipment->parcel_code ?: $shipment->shipment_number), trim($parcelCode))) throw ValidationException::withMessages(['parcel_code' => 'The scanned Parcel code does not match this Hub Transfer.']);

            $now = now();
            $transfer->update(['status' => 'completed', 'received_at' => $now]);
            $shipment->update(['current_hub_id' => $transfer->destination_hub_id, 'next_hub_id' => null, 'status' => 'at_sorting_center', 'received_by' => $actor->id, 'received_at' => $now, 'parcel_scanned_at' => null]);
            $shipment->events()->create([
                'actor_id' => $actor->id,
                'sorting_center_id' => $transfer->destination_hub_id,
                'status' => 'at_sorting_center',
                'event_type' => 'destination_receipt_verified',
                'event_key' => 'hub-transfer-receipt:'.$transfer->id,
                'location' => $transfer->destinationHub->name,
                'note' => 'Destination receipt verified for Hub Transfer '.$transfer->transfer_reference.'. Confirm the Parcel code before final sorting.',
                'metadata' => ['provider_id' => $provider->id, 'transfer_id' => $transfer->id, 'parcel_code' => $parcelCode],
            ]);
            DB::table('hub_movements')->insert([
                'shipment_id' => $shipment->id,
                'from_hub_id' => $transfer->origin_hub_id,
                'to_hub_id' => $transfer->destination_hub_id,
                'recorded_by' => $actor->id,
                'type' => 'inbound',
                'note' => 'Destination receipt verified for '.$transfer->transfer_reference.'.',
                'moved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $shipment->assignments()->where('assignment_type', 'transfer')->whereNull('ended_at')->update(['status' => 'ended', 'ended_at' => $now]);
            $this->releaseDriverIfIdle($transfer->transfer_driver_id, $provider->id, $shipment->id);

            return $transfer->fresh(['shipment', 'originHub', 'destinationHub', 'driver', 'vehicle']);
        }, 3);
    }

    public function exception(HubTransfer $bound, User $actor, string $reason): HubTransfer
    {
        return $this->transition($bound, $actor, ['pending', 'assigned', 'in_transit', 'arrived'], 'exception', function (HubTransfer $transfer) use ($reason): array {
            return ['exception_reason' => $reason];
        }, 'hub_transfer_exception', 'Hub Transfer exception reported: '.$reason);
    }

    private function transition(HubTransfer $bound, User $actor, string|array $from, string $to, callable $values, string $eventType, string $note): HubTransfer
    {
        $provider = app(\App\Support\LogisticsProviderContext::class)->requireFor($actor);
        $from = (array) $from;

        return DB::transaction(function () use ($bound, $actor, $provider, $from, $to, $values, $eventType, $note): HubTransfer {
            $transfer = HubTransfer::forProvider($provider->id)->whereKey($bound->id)->lockForUpdate()->firstOrFail();
            if (! in_array($transfer->status, $from, true)) throw ValidationException::withMessages(['status' => 'This Hub Transfer transition is not allowed.']);
            $transfer->update(array_merge(['status' => $to], $values($transfer)));
            $transfer->shipment->events()->create([
                'actor_id' => $actor->id,
                'status' => $to === 'exception' ? 'exception' : 'hub_transfer',
                'event_type' => $eventType,
                'event_key' => $eventType.':'.$transfer->id,
                'sorting_center_id' => $to === 'arrived' ? $transfer->destination_hub_id : $transfer->origin_hub_id,
                'location' => $to === 'arrived' ? $transfer->destinationHub->name : $transfer->originHub->name,
                'note' => $note,
                'metadata' => ['provider_id' => $provider->id, 'transfer_id' => $transfer->id],
            ]);

            if ($to === 'exception') {
                $transfer->shipment->update(['status' => 'exception']);
                $transfer->shipment->assignments()->where('assignment_type', 'transfer')->whereNull('ended_at')->update(['status' => 'ended', 'ended_at' => now()]);
                $this->releaseDriverIfIdle($transfer->transfer_driver_id, $provider->id, $transfer->shipment_id);
            }
            if ($to === 'in_transit') $transfer->shipment->update(['status' => 'hub_transfer']);

            return $transfer->fresh(['shipment', 'originHub', 'destinationHub', 'driver', 'vehicle']);
        }, 3);
    }

    private function hasActiveTransferAssignment(int $riderId): bool
    {
        return \App\Models\ShipmentAssignment::query()
            ->where('rider_id', $riderId)
            ->where('assignment_type', 'transfer')
            ->whereNull('ended_at')
            ->whereNotIn('status', ['declined', 'ended'])
            ->exists();
    }

    private function releaseDriverIfIdle(?int $riderId, int $providerId, int $shipmentId): void
    {
        if (! $riderId) return;
        $hasShipmentWork = Shipment::forProvider($providerId)->whereKeyNot($shipmentId)->activeNonTerminal()
            ->where(fn ($query) => $query->where('rider_id', $riderId)->orWhere('pickup_rider_id', $riderId))->exists();
        if (! $hasShipmentWork && ! $this->hasActiveTransferAssignment($riderId)) {
            User::find($riderId)?->riderProfile?->update(['availability' => 'available']);
        }
    }
}
