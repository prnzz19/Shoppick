<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PhilippineGeographyService
{
    public function regions(): array
    {
        return $this->fetch('regions');
    }

    public function provinces(string $regionCode): array
    {
        return $this->fetch("regions/{$this->code($regionCode)}/provinces");
    }

    public function citiesMunicipalitiesForRegion(string $regionCode): array
    {
        return $this->fetch("regions/{$this->code($regionCode)}/cities-municipalities");
    }

    public function citiesMunicipalitiesForProvince(string $provinceCode): array
    {
        return $this->fetch("provinces/{$this->code($provinceCode)}/cities-municipalities");
    }

    public function barangays(string $cityMunicipalityCode): array
    {
        return $this->fetch("cities-municipalities/{$this->code($cityMunicipalityCode)}/barangays");
    }

    private function fetch(string $path): array
    {
        $baseUrl = rtrim((string) config('services.psgc.base_url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('The PSGC provider is not configured.');
        }

        return Cache::remember(
            'psgc:'.sha1($baseUrl.'/'.$path),
            now()->addSeconds((int) config('services.psgc.cache_ttl', 86400)),
            function () use ($baseUrl, $path): array {
                $response = Http::acceptJson()
                    ->timeout((int) config('services.psgc.timeout', 10))
                    ->retry(2, 200)
                    ->get($baseUrl.'/'.$path)
                    ->throw();

                $records = $response->json('data', $response->json());
                if (! is_array($records)) {
                    throw new RuntimeException('The PSGC provider returned an invalid response.');
                }

                return collect($records)
                    ->filter(fn ($record) => is_array($record) && filled($record['code'] ?? null) && filled($record['name'] ?? null))
                    ->map(fn ($record) => [
                        'code' => (string) $record['code'],
                        'name' => (string) $record['name'],
                    ])
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();
            }
        );
    }

    private function code(string $code): string
    {
        abort_unless(preg_match('/^[A-Za-z0-9-]+$/', $code) === 1, 422, 'Invalid PSGC code.');

        return $code;
    }
}
