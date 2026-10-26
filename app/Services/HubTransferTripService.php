<?php

namespace App\Services;

use App\Models\{HubTransfer, HubTransferTrip, LogisticsHub, Shipment, User, Vehicle};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Groups existing one-parcel HubTransfer records into a safe multi-parcel
 * manifest. The original HubTransfer rows remain the source of parcel history.
 */
class HubTransferTripService
{
    public function create(array $transferIds, User $actor): HubTransferTrip
    {
        $provider = app(\App\Support\LogisticsProviderContext::class)->requireFor($actor);
        $ids = collect($transferIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->count() < 2) throw ValidationException::withMessages(['transfer_ids' => 'Select at least two parcels for a multi-parcel trip.']);

        return DB::transaction(function () use ($provider, $actor, $ids): HubTransferTrip {
            $transfers = HubTransfer::forProvider($provider->id)->whereIn('id', $ids)->with('shipment')->lockForUpdate()->get();
            if ($transfers->count() !== $ids->count()) throw ValidationException::withMessages(['transfer_ids' => 'One or more transfers are outside this provider.']);
            if ($transfers->contains(fn ($transfer) => $transfer->status !== 'pending')) throw ValidationException::withMessages(['transfer_ids' => 'Only pending transfers may be added to a new manifest.']);
            if ($transfers->contains(fn ($transfer) => $transfer->tripItem()->exists())) throw ValidationException::withMessages(['transfer_ids' => 'A selected parcel is already on a transfer manifest.']);
            $first = $transfers->first();
            if ($transfers->contains(fn ($transfer) => (int) $transfer->origin_hub_id !== (int) $first->origin_hub_id || (int) $transfer->destination_hub_id !== (int) $first->destination_hub_id)) {
                throw ValidationException::withMessages(['transfer_ids' => 'All parcels must share the same origin and destination Sorting Centers.']);
            }
            $reference = 'TRIP-'.now()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            $trip = HubTransferTrip::create([
                'logistics_provider_id' => $provider->id,
                'origin_hub_id' => $first->origin_hub_id,
                'destination_hub_id' => $first->destination_hub_id,
                'trip_reference' => $reference,
                'created_by' => $actor->id,
                'status' => 'pending',
            ]);
            foreach ($transfers as $transfer) $trip->items()->create(['hub_transfer_id' => $transfer->id, 'shipment_id' => $transfer->shipment_id]);
            return $trip->load('items.transfer', 'originHub', 'destinationHub');
        }, 3);
    }

    public function assign(HubTransferTrip $bound, User $actor, int $driverId, ?int $vehicleId = null): HubTransferTrip
    {
        $provider = app(\App\Support\LogisticsProviderContext::class)->requireFor($actor);
        return DB::transaction(function () use ($bound, $actor, $driverId, $vehicleId, $provider): HubTransferTrip {
            $trip = HubTransferTrip::forProvider($provider->id)->whereKey($bound->id)->with(['items.transfer.shipment', 'vehicle'])->lockForUpdate()->firstOrFail();
            if ($trip->status !== 'pending') throw ValidationException::withMessages(['status' => 'A transport driver can only be assigned to a pending trip.']);
            $driver = User::query()->whereKey($driverId)->where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', 'rider'))->whereHas('riderProfile', fn ($query) => $query->where('logistics_provider_id', $provider->id)->whereIn('rider_type', ['transport', 'both'])->assignmentEligible())->first();
            if (! $driver) throw ValidationException::withMessages(['transport_driver_id' => 'Choose an eligible Transport Driver owned by this provider.']);
            $busy = Shipment::forProvider($provider->id)->activeNonTerminal()->where(fn ($q) => $q->where('rider_id', $driver->id)->orWhere('pickup_rider_id', $driver->id))->exists();
            if ($busy || $this->hasActiveTrip($driver->id)) throw ValidationException::withMessages(['transport_driver_id' => 'This Transport Driver has active parcel work.']);
            $vehicle = null;
            if ($vehicleId) {
                $vehicle = Vehicle::query()->whereKey($vehicleId)->where('logistics_provider_id', $provider->id)->where('status', 'available')->first();
                if (! $vehicle) throw ValidationException::withMessages(['vehicle_id' => 'Choose a vehicle owned by this provider.']);
            }
            $weights = $trip->items->map(fn ($item) => data_get($item->shipment, 'weight_kg'))->filter(fn ($weight) => is_numeric($weight));
            if ($vehicle && $vehicle->capacity_kg !== null && $weights->count() === $trip->items->count() && $weights->sum() > (float) $vehicle->capacity_kg) throw ValidationException::withMessages(['vehicle_id' => 'The selected vehicle capacity is insufficient for this manifest.']);
            $trip->update(['transport_driver_id' => $driver->id, 'vehicle_id' => $vehicle?->id, 'status' => 'assigned']);
            foreach ($trip->items as $item) {
                $item->transfer()->update(['transfer_driver_id' => $driver->id, 'vehicle_id' => $vehicle?->id, 'status' => 'assigned']);
                $item->shipment->assignments()->create(['rider_id' => $driver->id, 'vehicle_id' => $vehicle?->id, 'assigned_by' => $actor->id, 'assignment_type' => 'transfer', 'status' => 'assigned', 'assigned_at' => now(), 'reason' => 'Transfer Trip '.$trip->trip_reference.' assignment.']);
                $item->shipment->events()->create(['actor_id' => $actor->id, 'status' => 'hub_transfer', 'event_type' => 'hub_transfer_driver_assigned', 'event_key' => 'hub-transfer-trip-driver:'.$trip->id.':'.$item->shipment_id, 'sorting_center_id' => $trip->origin_hub_id, 'location' => $trip->originHub->name, 'note' => 'Transport Driver assigned to Transfer Trip '.$trip->trip_reference.'.', 'metadata' => ['provider_id' => $provider->id, 'trip_id' => $trip->id, 'driver_id' => $driver->id]]);
            }
            $driver->riderProfile?->update(['availability' => 'assigned']);
            $vehicle?->update(['status' => 'in_use']);
            return $trip->fresh(['items.transfer', 'driver', 'vehicle', 'originHub', 'destinationHub']);
        }, 3);
    }

    public function dispatch(HubTransferTrip $bound, User $actor): HubTransferTrip
    {
        return $this->transition($bound, $actor, 'assigned', 'in_transit', ['dispatched_at' => now()], 'hub_transfer_trip_dispatched');
    }

    public function arrive(HubTransferTrip $bound, User $actor): HubTransferTrip
    {
        return $this->transition($bound, $actor, 'in_transit', 'arrived', ['arrived_at' => now()], 'hub_transfer_trip_arrived');
    }

    public function receive(HubTransferTrip $bound, User $actor, int $itemId, string $parcelCode): HubTransferTrip
    {
        $provider = app(\App\Support\LogisticsProviderContext::class)->requireFor($actor);
        return DB::transaction(function () use ($bound, $actor, $itemId, $parcelCode, $provider): HubTransferTrip {
            $trip = HubTransferTrip::forProvider($provider->id)->whereKey($bound->id)->with(['items.transfer', 'destinationHub'])->lockForUpdate()->firstOrFail();
            if ($trip->status !== 'arrived') throw ValidationException::withMessages(['status' => 'Record trip arrival before verifying parcel receipts.']);
            $item = $trip->items()->whereKey($itemId)->with('shipment', 'transfer')->lockForUpdate()->firstOrFail();
            if ($item->status === 'received') throw ValidationException::withMessages(['parcel_code' => 'This parcel was already received.']);
            $shipment = Shipment::forProvider($provider->id)->whereKey($item->shipment_id)->lockForUpdate()->firstOrFail();
            $expected = (string) ($shipment->parcel_code ?: $shipment->shipment_number);
            if (! hash_equals($expected, trim($parcelCode))) throw ValidationException::withMessages(['parcel_code' => 'The parcel code does not match this manifest item.']);
            $now = now();
            $item->update(['status' => 'received', 'received_by' => $actor->id, 'received_at' => $now]);
            $item->transfer->update(['status' => 'completed', 'received_at' => $now]);
            $shipment->update(['current_hub_id' => $trip->destination_hub_id, 'next_hub_id' => null, 'status' => 'at_sorting_center', 'received_by' => $actor->id, 'received_at' => $now, 'parcel_scanned_at' => null]);
            $shipment->events()->create(['actor_id' => $actor->id, 'sorting_center_id' => $trip->destination_hub_id, 'status' => 'at_sorting_center', 'event_type' => 'destination_receipt_verified', 'event_key' => 'hub-transfer-trip-receipt:'.$trip->id.':'.$item->id, 'location' => $trip->destinationHub->name, 'note' => 'Manual destination receipt verified for '.$trip->trip_reference.'.', 'metadata' => ['provider_id' => $provider->id, 'trip_id' => $trip->id, 'trip_item_id' => $item->id, 'parcel_code' => $parcelCode, 'verification_method' => 'manual']]);
            DB::table('hub_movements')->insert(['shipment_id' => $shipment->id, 'from_hub_id' => $trip->origin_hub_id, 'to_hub_id' => $trip->destination_hub_id, 'recorded_by' => $actor->id, 'type' => 'inbound', 'note' => 'Manual receipt verified for Transfer Trip '.$trip->trip_reference.'.', 'moved_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            $shipment->assignments()->where('assignment_type', 'transfer')->whereNull('ended_at')->update(['status' => 'ended', 'ended_at' => $now]);
            $this->finalizeIfResolved($trip->fresh(['items', 'driver', 'vehicle']), $provider->id);
            return $trip->fresh(['items.shipment', 'driver', 'vehicle', 'originHub', 'destinationHub']);
        }, 3);
    }

    public function exception(HubTransferTrip $bound, User $actor, string $reason): HubTransferTrip
    {
        return $this->transition($bound, $actor, ['pending', 'assigned', 'in_transit', 'arrived'], 'exception', ['exception_reason' => $reason], 'hub_transfer_trip_exception');
    }

    public function exceptionItem(HubTransferTrip $bound, User $actor, int $itemId, string $reason): HubTransferTrip
    {
        $provider = app(\App\Support\LogisticsProviderContext::class)->requireFor($actor);
        return DB::transaction(function () use ($bound, $actor, $itemId, $reason, $provider): HubTransferTrip {
            $trip = HubTransferTrip::forProvider($provider->id)->whereKey($bound->id)->with(['items.transfer', 'destinationHub', 'driver', 'vehicle'])->lockForUpdate()->firstOrFail();
            if (! in_array($trip->status, ['arrived', 'in_transit'], true)) throw ValidationException::withMessages(['status' => 'A parcel exception can only be recorded for an active or arrived trip.']);
            $item = $trip->items()->whereKey($itemId)->with('shipment', 'transfer')->lockForUpdate()->firstOrFail();
            if (in_array($item->status, ['received', 'exception'], true)) throw ValidationException::withMessages(['reason' => 'This manifest parcel is already resolved.']);
            $item->update(['status' => 'exception', 'exception_reason' => $reason]);
            $item->transfer->update(['status' => 'exception', 'exception_reason' => $reason]);
            $item->shipment->update(['status' => 'exception']);
            $item->shipment->assignments()->where('assignment_type', 'transfer')->whereNull('ended_at')->update(['status' => 'ended', 'ended_at' => now()]);
            $item->shipment->events()->create(['actor_id' => $actor->id, 'sorting_center_id' => $trip->destination_hub_id, 'status' => 'exception', 'event_type' => 'hub_transfer_parcel_exception', 'event_key' => 'hub-transfer-trip-item-exception:'.$trip->id.':'.$item->id, 'location' => $trip->destinationHub->name, 'note' => 'Parcel exception recorded during Transfer Trip '.$trip->trip_reference.': '.$reason, 'metadata' => ['provider_id' => $provider->id, 'trip_id' => $trip->id, 'trip_item_id' => $item->id, 'verification_method' => 'manual']]);
            $this->finalizeIfResolved($trip->fresh(['items', 'driver', 'vehicle']), $provider->id);
            return $trip->fresh(['items.shipment', 'driver', 'vehicle', 'originHub', 'destinationHub']);
        }, 3);
    }

    private function transition(HubTransferTrip $bound, User $actor, string|array $from, string $to, array $values, string $event): HubTransferTrip
    {
        $provider = app(\App\Support\LogisticsProviderContext::class)->requireFor($actor);
        return DB::transaction(function () use ($bound, $actor, $provider, $from, $to, $values, $event): HubTransferTrip {
            $trip = HubTransferTrip::forProvider($provider->id)->whereKey($bound->id)->with('items.transfer.shipment')->lockForUpdate()->firstOrFail();
            if (! in_array($trip->status, (array) $from, true)) throw ValidationException::withMessages(['status' => 'This Transfer Trip transition is not allowed.']);
            $trip->update(array_merge(['status' => $to], $values));
            foreach ($trip->items as $item) {
                $transfer = $item->transfer;
                $shipment = $item->shipment;
                if ($to === 'in_transit') { $transfer->update(['status' => 'in_transit', 'dispatched_at' => $values['dispatched_at'] ?? now()]); $shipment->update(['status' => 'hub_transfer']); }
                if ($to === 'arrived') $transfer->update(['status' => 'arrived', 'arrived_at' => $values['arrived_at'] ?? now()]);
                $shipment->events()->create(['actor_id' => $actor->id, 'status' => $to === 'exception' ? 'exception' : 'hub_transfer', 'event_type' => $event, 'event_key' => $event.':'.$trip->id.':'.$item->shipment_id, 'sorting_center_id' => $to === 'arrived' ? $trip->destination_hub_id : $trip->origin_hub_id, 'location' => $to === 'arrived' ? $trip->destinationHub->name : $trip->originHub->name, 'note' => 'Transfer Trip '.$trip->trip_reference.' '.$to.'.', 'metadata' => ['provider_id' => $provider->id, 'trip_id' => $trip->id]]);
                if ($to === 'exception') { $item->update(['status' => 'exception', 'exception_reason' => $values['exception_reason'] ?? null]); $transfer->update(['status' => 'exception', 'exception_reason' => $values['exception_reason'] ?? null]); $shipment->update(['status' => 'exception']); $shipment->assignments()->where('assignment_type', 'transfer')->whereNull('ended_at')->update(['status' => 'ended', 'ended_at' => now()]); }
            }
            if ($to === 'exception') { $driver = $trip->driver; if ($driver && ! $this->hasActiveTrip($driver->id)) $driver->riderProfile?->update(['availability' => 'available']); $trip->vehicle?->update(['status' => 'available']); }
            return $trip->fresh(['items.shipment', 'driver', 'vehicle', 'originHub', 'destinationHub']);
        }, 3);
    }

    private function hasActiveTrip(int $driverId): bool
    {
        return HubTransferTrip::where('transport_driver_id', $driverId)->whereIn('status', ['assigned', 'in_transit', 'arrived'])->exists();
    }

    private function finalizeIfResolved(HubTransferTrip $trip, int $providerId): void
    {
        if (! $trip->items->every(fn ($item) => in_array($item->status, ['received', 'exception'], true))) return;
        $trip->update(['status' => 'completed']);
        $driver = $trip->driver;
        if ($driver && ! $this->hasActiveTrip($driver->id) && ! Shipment::forProvider($providerId)->activeNonTerminal()->where(fn ($q) => $q->where('rider_id', $driver->id)->orWhere('pickup_rider_id', $driver->id))->exists()) $driver->riderProfile?->update(['availability' => 'available']);
        $trip->vehicle?->update(['status' => 'available']);
    }
}
