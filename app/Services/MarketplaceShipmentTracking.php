<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only customer projection of the existing Logistics shipment records. */
class MarketplaceShipmentTracking
{
    public function coordinates(?array $value): ?array
    {
        $lat = $value['latitude'] ?? null;
        $lng = $value['longitude'] ?? null;
        if (! is_numeric($lat) || ! is_numeric($lng) || ! is_finite((float) $lat) || ! is_finite((float) $lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return null;
        }

        return ['latitude' => (float) $lat, 'longitude' => (float) $lng];
    }

    public function summary(Shipment $s): array
    {
        return ['id' => $s->id, 'tracking_number' => $s->shipment_number, 'status' => $s->status,
            'shop' => $s->store?->name, 'updated_at' => $s->updated_at?->toIso8601String(),
            'events' => $s->events->map(fn ($e) => ['id' => $e->id, 'status' => $e->status, 'created_at' => $e->created_at?->toIso8601String(),
                'coordinates' => in_array(data_get($e->metadata, 'source'), ['device', 'rider'], true) ? null : $this->coordinates($e->metadata)])->values()];
    }

    public function data(Shipment $s): array
    {
        $s->loadMissing(['store', 'events']);
        $providerId = $s->getAttribute('logistics_provider_id');
        $provider = $providerId && Schema::hasTable('logistics_providers') ? DB::table('logistics_providers')->where('id', $providerId)->value('name') : null;
        $live = $s->status === 'out_for_delivery'
            && ! in_array($s->order?->status, ['cancelled', 'completed', 'delivered'], true)
            && ! in_array($s->sellerOrder?->status, ['cancelled', 'completed', 'delivered'], true);
        $finished = in_array($s->status, ['delivered', 'completed'], true);
        // A phase boundary prevents exposing pickup/previous-attempt GPS to customers.
        $start = $s->events->where('status', 'out_for_delivery')->sortByDesc('created_at')->first()?->created_at;
        $points = collect();
        if (($live || $finished) && $start && $s->rider_id) {
            $points = DB::table('shipment_tracking_points')->where('shipment_id', $s->id)->where('rider_id', $s->rider_id)
                ->where('source', 'device')->where('recorded_at', '>=', $start)
                ->when($finished, fn ($q) => $q->where('recorded_at', '<=', $s->delivered_at ?? $s->updated_at))
                ->orderByDesc('recorded_at')->orderByDesc('id')->limit(500)->get()->reverse()->values()
                ->filter(fn ($p) => $this->coordinates((array) $p) !== null)
                ->map(fn ($p) => $this->coordinates((array) $p) + ['recorded_at' => Carbon::parse($p->recorded_at)->toIso8601String()])->values();
        }
        $current = $live ? $points->last(fn ($p) => Carbon::parse($p['recorded_at'])->greaterThan(now()->subMinutes(2))) : null;
        $latest = collect([$s->updated_at, $s->events->max('created_at'), $points->last()['recorded_at'] ?? null])->filter()->map(fn ($t) => Carbon::parse($t))->sort()->last();

        return $this->summary($s) + ['provider' => $provider,
            'origin' => $this->coordinates($s->pickup_address), 'destination' => $this->coordinates($s->delivery_address),
            'pickup_address' => $this->address($s->pickup_address), 'destination_address' => $this->address($s->delivery_address),
            'points' => $points, 'current_rider_location' => $current, 'live' => $live,
            'poll' => ! in_array($s->status, ['delivered', 'completed', 'delivery_failed', 'returned', 'exception', 'delivery_attempted', 'cancelled'], true),
            'last_tracking_update' => $latest?->toIso8601String(), 'delivered_at' => $s->delivered_at?->toIso8601String(),
            'estimated_delivery_at' => $s->estimated_delivery_at?->toIso8601String()];
    }

    private function address(?array $address): string
    {
        return collect($address)->only(['address', 'address_line', 'street', 'barangay', 'city', 'municipality', 'province', 'postal_code'])
            ->filter(fn ($v) => is_scalar($v) && filled($v))->unique()->implode(', ');
    }
}
