# PSA PSGC catalog imports

The application does not bundle a hard-coded national location list. Obtain
the current publication directly from the Philippine Statistics Authority,
review the release, convert it to the import JSON shape, and keep the file
outside the public web root (the default path is `database/psgc/`, which is not
served by Laravel).

The current reference used for this implementation is PSA's **Third Quarter
2026 PSGC publication, as of 30 September 2026**, released 12 October 2026.
It contains 18 regions, 82 provinces, 1,642 cities/municipalities (33 HUC,
5 ICC, 111 component cities, and 1,493 municipalities), and 42,010 barangays.

Run only against an explicitly isolated database:

```text
php artisan psgc:import path/to/reviewed-psgc.json --release=Q3_2026 --dry-run
php artisan psgc:import path/to/reviewed-psgc.json --release=Q3_2026
```

The JSON may be a list or `{ "version": "Q3_2026", "data": [...] }`. Each
row must include `psgc_code`, `area_name`, and `geographic_level`; the command
derives parent codes from the official ten-digit code when the PSA export
contains only its short `reg`, `prv`, or `mun` fields. Duplicate codes,
missing parents, unsupported levels, and malformed codes abort before writes.

PSA source: https://psa.gov.ph/classification/psgc and the official PSGC API
documentation at https://psa.gov.ph/classifications-api/psgc.
