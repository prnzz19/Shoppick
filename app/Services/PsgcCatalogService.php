<?php

namespace App\Services;

use App\Models\PsgcLocation;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only access to the locally imported PSA PSGC hierarchy.
 *
 * The service deliberately returns null when the catalog is not installed or
 * populated, allowing the legacy provider-backed service to remain the safe
 * compatibility fallback during rollout.
 */
class PsgcCatalogService
{
    public function available(): bool
    {
        return (bool) config('services.psgc.local_catalog', true)
            && Schema::hasTable('psgc_locations')
            && PsgcLocation::query()->active()->exists();
    }

    public function regions(): ?array
    {
        return $this->list('region');
    }

    public function provinces(string $regionCode): ?array
    {
        return $this->children('province', $regionCode, 'region_code');
    }

    public function citiesMunicipalitiesForRegion(string $regionCode): ?array
    {
        return $this->children('city_municipality', $regionCode, 'region_code');
    }

    public function citiesMunicipalitiesForProvince(string $provinceCode): ?array
    {
        return $this->children('city_municipality', $provinceCode, 'province_code');
    }

    public function barangays(string $municipalityCode): ?array
    {
        return $this->children('barangay', $municipalityCode, 'municipality_code');
    }

    private function list(string $level): ?array
    {
        if (! $this->available()) {
            return null;
        }

        return PsgcLocation::query()->active()->level($level)
            ->orderBy('name')->get(['code', 'name', 'correspondence_code'])
            ->map(fn (PsgcLocation $row) => [
                'code' => $row->code,
                'name' => $row->name,
                'correspondence_code' => $row->correspondence_code,
            ])->values()->all();
    }

    private function children(string $level, string $parentCode, string $column): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $exists = PsgcLocation::query()->active()->where('code', $parentCode)->exists();
        abort_unless($exists, 422, 'Invalid Philippine location code.');

        return PsgcLocation::query()->active()->level($level)->where($column, $parentCode)
            ->orderBy('name')->get(['code', 'name', 'correspondence_code'])
            ->map(fn (PsgcLocation $row) => [
                'code' => $row->code,
                'name' => $row->name,
                'correspondence_code' => $row->correspondence_code,
            ])->values()->all();
    }
}
