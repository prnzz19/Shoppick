<?php

namespace App\Console\Commands;

use App\Models\PsgcLocation;
use App\Models\PsgcBarangay;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Import a verified PSA PSGC JSON export into the local reference catalog.
 *
 * The command is intentionally file-only: downloading and reviewing a PSA
 * release is an operator decision. It never reads Laravel .env credentials
 * and it refuses to run until the catalog migration is present.
 */
class ImportPsgcCatalog extends Command
{
    /** @var array<string, bool> */
    private array $knownProvinceCodes = [];

    protected $signature = 'psgc:import
        {file : Path to an operator-reviewed PSA PSGC JSON export}
        {--release= : PSA release identifier, for example Q3_2026}
        {--dry-run : Validate and report counts without writing}';

    protected $description = 'Import a reviewed PSA PSGC hierarchy into the local catalog';

    public function handle(): int
    {
        if (! Schema::hasTable('psgc_locations') || ! Schema::hasTable('psgc_barangays')) {
            $this->error('Run the reviewed PSGC catalog migration in an isolated database first.');
            return self::FAILURE;
        }

        $path = $this->argument('file');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('The PSGC export file does not exist or is not readable.');
            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        $rows = $this->rows($payload);
        $version = trim((string) ($this->option('release') ?: data_get($payload, 'version', 'unknown')));
        if ($version === '') {
            throw new RuntimeException('A non-empty PSA release version is required.');
        }

        $this->knownProvinceCodes = collect($rows)
            ->filter(fn (array $row) => in_array(strtolower((string) ($row['geographic_level'] ?? $row['level'] ?? '')), ['province', 'prov'], true))
            ->map(fn (array $row) => (string) ($row['psgc_code'] ?? $row['code'] ?? ''))
            ->filter()->flip()->all();
        $normalized = collect($rows)->map(fn (array $row) => $this->normalize($row, $version))->values();
        $this->validateCatalog($normalized->all());
        $counts = $normalized->countBy('level');
        $this->info('Validated '.$normalized->count().' PSA PSGC rows: '.$counts->map(fn ($n, $level) => $level.'='.$n)->implode(', '));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($normalized, $version): void {
            $codes = $normalized->pluck('code');
            $retiredLocations = PsgcLocation::query()->pluck('code')->diff($codes)->values();
            foreach ($retiredLocations->chunk(500) as $chunk) {
                PsgcLocation::query()->whereIn('code', $chunk->all())->update(['is_active' => false]);
            }
            $names = $normalized->keyBy('code')->map(fn (array $row) => $row['name']);
            foreach ($normalized as $row) {
                PsgcLocation::updateOrCreate(
                    ['code' => $row['code']],
                    Arr::only($row, [
                        'correspondence_code', 'level', 'name', 'parent_code',
                        'region_code', 'province_code', 'municipality_code',
                        'source_version', 'is_urban', 'population', 'is_active',
                    ])
                );
            }

            $barangays = $normalized->where('level', 'barangay');
            $retiredBarangays = PsgcBarangay::query()->pluck('code')->diff($barangays->pluck('code'))->values();
            foreach ($retiredBarangays->chunk(500) as $chunk) {
                PsgcBarangay::query()->whereIn('code', $chunk->all())->update(['is_active' => false]);
            }
            foreach ($barangays as $row) {
                DB::table('psgc_barangays')->updateOrInsert(['code' => $row['code']], [
                    'correspondence_code' => $row['correspondence_code'],
                    'province_code' => $row['province_code'],
                    'province' => $names->get($row['province_code'], ''),
                    'municipality_code' => $row['municipality_code'],
                    'municipality' => $names->get($row['municipality_code'], ''),
                    'name' => $row['name'],
                    'is_urban' => $row['is_urban'] ?? false,
                    'population' => $row['population'],
                    'source_version' => $version,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        });

        $this->info('PSGC catalog import completed idempotently for '.$version.'.');
        return self::SUCCESS;
    }

    private function rows(mixed $payload): array
    {
        $rows = data_get($payload, 'data', $payload);
        if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            throw new RuntimeException('Expected a non-empty JSON list or a {"data": [...]} PSA export.');
        }
        return $rows;
    }

    private function normalize(array $row, string $version): array
    {
        $code = (string) ($row['psgc_code'] ?? $row['code'] ?? '');
        $name = trim((string) ($row['area_name'] ?? $row['name'] ?? ''));
        $rawLevel = strtolower((string) ($row['geographic_level'] ?? $row['level'] ?? ''));
        $level = match (true) {
            str_contains($rawLevel, 'region') || $rawLevel === 'reg' => 'region',
            str_contains($rawLevel, 'province') || $rawLevel === 'prov' => 'province',
            str_contains($rawLevel, 'municip') || str_contains($rawLevel, 'city') || in_array($rawLevel, ['mun', 'citymun'], true) => 'city_municipality',
            str_contains($rawLevel, 'barangay') || $rawLevel === 'bgy' => 'barangay',
            default => throw new RuntimeException('Unsupported PSGC geographic level for '.$code.'.'),
        };
        if (preg_match('/^\d{9,10}$/', $code) !== 1 || $name === '') {
            throw new RuntimeException('Invalid PSGC code or name in the import.');
        }

        $regionCode = $this->hierarchyCode($row['region_code'] ?? $row['reg'] ?? null, $code, 'region');
        $rawProvinceCode = $row['province_code'] ?? $row['prv'] ?? null;
        $provinceCode = $this->hierarchyCode($rawProvinceCode, $code, 'province');
        // Province-less cities (including NCR and independent cities) must
        // hang directly from their region. A prefix-derived province that is
        // identical to the city code is not a real parent.
        if ($level === 'city_municipality' && ! is_string($rawProvinceCode)
            && ($provinceCode === $code || ! isset($this->knownProvinceCodes[$provinceCode]))) {
            $provinceCode = null;
        }
        $municipalityCode = $this->hierarchyCode($row['municipality_code'] ?? $row['mun'] ?? null, $code, 'city_municipality');
        $parentCode = match ($level) {
            'province' => $regionCode,
            'city_municipality' => $provinceCode ?: $regionCode,
            'barangay' => $municipalityCode,
            default => null,
        };

        return [
            'code' => $code,
            'correspondence_code' => $row['correspondence_code'] ?? null,
            'level' => $level,
            'name' => $name,
            'parent_code' => $parentCode,
            'region_code' => $regionCode,
            'province_code' => $provinceCode,
            'municipality_code' => $municipalityCode,
            'source_version' => $version,
            'is_urban' => ($row['urban_rural'] ?? null) === 'Urban' ? true : (($row['urban_rural'] ?? null) === 'Rural' ? false : null),
            'population' => $this->population($row),
        ];
    }

    private function hierarchyCode(mixed $candidate, string $code, string $level): ?string
    {
        if (is_string($candidate) && preg_match('/^\d{9,10}$/', $candidate) === 1) {
            return $candidate;
        }

        return match ($level) {
            'region' => substr($code, 0, 2).'00000000',
            'province' => substr($code, 0, 5).'00000',
            'city_municipality' => substr($code, 0, 7).'000',
            default => null,
        };
    }

    private function population(array $row): ?int
    {
        $value = $row['population'] ?? data_get($row, 'population_data.0.population');
        if ($value === null || $value === '') return null;
        return (int) preg_replace('/[^0-9]/', '', (string) $value);
    }

    private function validateCatalog(array $rows): void
    {
        $codes = collect($rows)->pluck('code');
        if ($codes->duplicates()->isNotEmpty()) throw new RuntimeException('Duplicate PSGC code in import.');
        foreach (['region', 'province', 'city_municipality', 'barangay'] as $level) {
            if (! collect($rows)->contains('level', $level)) throw new RuntimeException('The import is missing '.$level.' rows.');
        }
        $set = $codes->flip();
        foreach ($rows as $row) {
            if ($row['parent_code'] === $row['code']) {
                throw new RuntimeException('A PSGC row cannot be its own parent: '.$row['code'].'.');
            }
            if ($row['parent_code'] !== null && ! $set->has($row['parent_code'])) {
                throw new RuntimeException('Missing parent '.$row['parent_code'].' for '.$row['code'].'.');
            }
        }
    }
}
