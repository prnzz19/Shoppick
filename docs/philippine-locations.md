# Philippine address dropdowns

The shared Blade component and `resources/js/philippine-locations.js` call the
same-origin `/api/philippine-locations` routes. `PhilippineGeographyService` calls
the existing public PSGC Cloud v1 API at <https://psgc.cloud/api> and normalizes
each record to its provider code and name, with the requested parent code added.
The provider is community maintained; this project does not ship an official or
complete local PSGC dataset. Do not invent identifiers or postal codes.

## HTTPS and settings

On Windows, PHP cURL uses `CURLSSLOPT_NATIVE_CA` to verify the provider against
Windows trusted certificate roots. Verification remains enabled. On other
systems, PHP/Guzzle's normal CA configuration applies. An explicit trusted PEM
bundle can be selected with `PSGC_CA_BUNDLE`. Never set verification to false.

Existing settings remain supported: `PSGC_API_URL`, `PSGC_API_TIMEOUT` (10 seconds)
and `PSGC_CACHE_TTL` (one day). Connections time out after three seconds, with at
most two attempts separated by 200 ms. Browser requests time out after 25 seconds.

## Cache and fallback

`PSGC_CACHE_STORE` defaults to the dedicated Laravel `psgc` file store under
`storage/app/psgc-cache`. Successful lists are cached for one day, with a separate
last-successful copy retained indefinitely. Expired lists refresh on their next
use; when refresh fails, the last-successful copy is returned. Failed requests
have a 60-second cooldown to avoid repeated provider calls during outages.

The fallback covers lists previously loaded successfully. A never-loaded list
still needs the provider. No small hardcoded dataset is substituted. Normal
`artisan optimize:clear` clears the default application cache, leaving the PSGC
fallback intact. To deliberately discard this cache, use
`artisan cache:clear --store=psgc`; this removes the offline fallback too.
Changing `PSGC_API_URL` gives the provider its own cache namespace.

No API key is needed. Cache files contain public geographic lists, not user
addresses. Tests select the array cache store and HTTP fakes so they cannot alter
the local PSGC cache or connect to the real database.

## Forms and validation

Buyer registration, profile completion and account address saves resolve the
submitted hierarchy through trusted lists before saving canonical names/codes.
The legacy seller-registration request uses the same validation for its store
address. Name-only saved values can be restored and matched against the lists;
arbitrary names and mismatched codes are rejected. NCR follows the existing
region-to-city flow without a province. The current authenticated seller
application has a free-text business address; it has no cascading component and
is outside this loading fix.

Changing a parent clears its descendants. Obsolete requests are aborted, and
successful lists are reused within the page. Failures stop displaying loading
placeholders and show a friendly field error and Retry. Saved values survive
failed restoration and can be retried. Street and postal code are separate manual
fields and are never modified by location loading. Account validation redirects
reopen the address modal with the previous values and correct create/edit action.

## Verification

Run the focused PHP tests with PHP 8.2 or newer:

```powershell
& 'C:\php\php-8.5.9-nts-Win32-vs17-x64\php.exe' artisan test --filter='PhilippineLocation|BuyerAddressManagement|BirthdayAgeFields|RegistrationAndProductVisibility|AdminModerationDynamicSellerWorkflow|AdminUserTabs'
```

For the browser regression test, start a separate headless Edge/Chrome instance
with `--remote-debugging-port=9222` and an isolated temporary `--user-data-dir`,
then run `node tests/Browser/philippine-locations.mjs`. `SHOPPICK_TEST_URL` can
override the default `http://127.0.0.1:8000`. The script checks live cascading,
code/name restoration, rapid changes, NCR and simulated network failure/Retry.
It never submits a real registration. Database save tests use in-memory SQLite.

## Files in this fix

Runtime changes:

- `app/Services/PhilippineGeographyService.php`
- `app/Http/Controllers/PhilippineLocationController.php`
- `app/Http/Controllers/AddressController.php`
- `app/Http/Controllers/Auth/CompleteProfileController.php`
- `app/Http/Requests/BuyerRegistrationRequest.php`
- `config/cache.php`
- `config/services.php`
- `resources/js/philippine-locations.js`
- `resources/views/components/philippine-location-fields.blade.php`
- `resources/views/storefront/account/addresses.blade.php`
- `resources/views/auth/complete-profile.blade.php`

Existing tests updated for canonical addresses and isolated provider responses:

- `tests/Feature/PhilippineLocationCoverageTest.php`
- `tests/Feature/BuyerAddressManagementTest.php`
- `tests/Feature/BirthdayAgeFieldsTest.php`
- `tests/Feature/RegistrationAndProductVisibilityTest.php`
- `tests/Feature/AdminModerationDynamicSellerWorkflowTest.php`
- `tests/Feature/AdminUserTabsTest.php`

New files:

- `tests/Feature/PhilippineLocationResilienceTest.php`
- `tests/Support/MocksPhilippineLocations.php`
- `tests/Browser/philippine-locations.mjs`
- `docs/philippine-locations.md`

Admin test changes only update registration address fixtures and mock geographic
HTTP responses; no admin implementation is changed by this fix.
