<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PhilippineGeographyService
{
    public function regions(): array
    {
        return $this->fetch('regions');
    }

    public function provinces(string $regionCode): array
    {
        $this->code($regionCode);
        $this->parent($regionCode, $this->regions());

        return $this->withParent($this->fetch("regions/{$regionCode}/provinces"), 'region_code', $regionCode);
    }

    public function citiesMunicipalitiesForRegion(string $regionCode): array
    {
        $this->code($regionCode);
        $this->parent($regionCode, $this->regions());

        return $this->withParent($this->fetch("regions/{$regionCode}/cities-municipalities"), 'region_code', $regionCode);
    }

    public function citiesMunicipalitiesForProvince(string $provinceCode): array
    {
        $this->code($provinceCode);
        $records = $this->fetch("provinces/{$provinceCode}/cities-municipalities");
        abort_if($records === [], 422, 'Invalid province code.');

        return $this->withParent($records, 'province_code', $provinceCode);
    }

    public function barangays(string $cityMunicipalityCode): array
    {
        $this->code($cityMunicipalityCode);
        $records = $this->fetch("cities-municipalities/{$cityMunicipalityCode}/barangays");
        abort_if($records === [], 422, 'Invalid city / municipality code.');

        return $this->withParent($records, 'city_code', $cityMunicipalityCode);
    }

    /** Resolve the hierarchy and derive names from trusted provider records. */
    public function normalizeAddress(array $data, string $prefix = ''): array
    {
        $match = function (array $records, string $level) use ($data, $prefix): array {
            $code = $data[$prefix.$level.'_code'] ?? '';
            $name = $data[$prefix.$level] ?? '';
            foreach ($records as $record) {
                if ($code !== '' ? $record['code'] === $code : strcasecmp($record['name'], $name) === 0) {
                    return $record;
                }
            }
            throw ValidationException::withMessages([$prefix.$level => 'Select a valid '.$level.' for this address.']);
        };
        try {
            $region = $match($this->regions(), 'region');
            $provinces = $this->provinces($region['code']);
            $province = $provinces ? $match($provinces, 'province') : ['code' => '', 'name' => ''];
            if (! $provinces && filled($data[$prefix.'province_code'] ?? null)) {
                throw ValidationException::withMessages([$prefix.'province' => 'This region has no province level.']);
            }
            $cities = $provinces ? $this->citiesMunicipalitiesForProvince($province['code']) : $this->citiesMunicipalitiesForRegion($region['code']);
            $city = $match($cities, 'city');
            $barangay = $match($this->barangays($city['code']), 'barangay');
            foreach (compact('region', 'province', 'city', 'barangay') as $level => $record) {
                $data[$prefix.$level] = $record['name'];
                $data[$prefix.$level.'_code'] = $record['code'] ?: null;
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([$prefix.'region' => 'Unable to verify this address. Please try again.']);
        }

        return $data;
    }

    private function fetch(string $path): array
    {
        $baseUrl = rtrim((string) config('services.psgc.base_url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('The PSGC provider is not configured.');
        }
        $cache = Cache::store(config('services.psgc.cache_store', 'psgc'));
        $key = 'psgc:v2:'.sha1($baseUrl.'/'.$path);
        $cached = $cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $stale = $cache->get($key.':last-success');
        // Avoid repeated refreshes during outages, including when no data is available yet.
        if ($cache->has($key.':unavailable')) {
            if (is_array($stale)) {
                return $stale;
            }
            throw new RuntimeException('The PSGC provider is temporarily unavailable.');
        }
        try {
            $options = [];
            if ($bundle = config('services.psgc.ca_bundle')) {
                $options['verify'] = $bundle;
            } elseif (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
                // Verify TLS using Windows trusted roots when PHP has no separate CA bundle.
                $options['curl'] = [CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA];
            }
            $response = Http::acceptJson()->withOptions($options)->connectTimeout(3)
                ->timeout((int) config('services.psgc.timeout', 10))->retry(2, 200)
                ->get($baseUrl.'/'.$path)->throw();
            $records = $response->json('data', $response->json());
            if (! is_array($records) || ! array_is_list($records)) {
                throw new RuntimeException('The PSGC provider returned an invalid response.');
            }
            foreach ($records as $record) {
                if (! is_array($record) || ! is_string($record['code'] ?? null)
                    || preg_match('/^\d{9,10}$/', $record['code']) !== 1
                    || ! is_string($record['name'] ?? null) || trim($record['name']) === '') {
                    throw new RuntimeException('The PSGC provider returned an invalid location.');
                }
            }
            if ($path === 'regions' && $records === []) {
                throw new RuntimeException('The PSGC provider returned no regions.');
            }
            $records = collect($records)->map(fn ($record) => [
                'code' => $record['code'], 'name' => trim($record['name']),
            ])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
            $cache->put($key, $records, max(60, (int) config('services.psgc.cache_ttl', 86400)));
            $cache->forever($key.':last-success', $records);
            $cache->forget($key.':unavailable');

            return $records;
        } catch (Throwable $exception) {
            if ($exception instanceof RequestException && in_array($exception->response->status(), [404, 422], true)) {
                throw new HttpException(422, 'Invalid PSGC code.', $exception);
            }
            $cache->put($key.':unavailable', true, 60);
            if (is_array($stale)) {
                return $stale;
            }
            throw $exception;
        }
    }

    private function withParent(array $records, string $field, string $code): array
    {
        return array_map(fn ($record) => $record + [$field => $code], $records);
    }

    private function parent(string $code, array $records): void
    {
        $this->code($code);
        abort_unless(in_array($code, array_column($records, 'code'), true), 422, 'Invalid PSGC code.');
    }

    private function code(string $code): void
    {
        abort_unless(preg_match('/^\d{9,10}$/', $code) === 1, 422, 'Invalid PSGC code.');
    }
}
