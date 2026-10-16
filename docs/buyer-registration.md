# Immediate Buyer access

Ordinary web, mobile and Google Buyer registration uses the shared
`BuyerAccountState` service. A completed registration receives the Buyer role,
`is_active = true` and `registration_status = approved`. Here `approved` reuses
the existing login-enabled value; there is no administrator approval step and
no new database column or migration. Public requests cannot choose their roles
or account state.

Web registration keeps the existing redirect to Login and now says registration
was successful. New Google accounts are active but have an `incomplete` profile;
the existing birthday, phone, address and valid-ID completion requirements remain.
Completion switches the profile to the normal approved state. Disabled/rejected
accounts cannot reactivate themselves through profile completion.

Login and protected account routes allow an active, normal Buyer with legacy
pending registration metadata without changing that record. Inactive accounts,
rejected registrations and other approval-dependent roles remain restricted.
The user archival UI was already retired in this working tree, so login continues
to ignore the historical user `archived_at` field, as required by the existing
archive regression test. Soft-deleted users and inactive accounts remain blocked.
Seller/shop archive behavior is unchanged. Mobile authentication also checks
account restrictions when issuing and using tokens.

A Seller application remains a separate pending record. Submission does not
change Buyer activation or grant Seller. Existing Admin approval adds Seller
while retaining Buyer. Normal Buyer approval controls are removed from Admin
Users; the old Buyer-review POST endpoint returns 410 without updating records.
User activation/deactivation controls and private valid-ID access remain.

## Existing pending Buyers: review before applying

The safe default is read-only:

```powershell
& 'C:\php\php-8.5.9-nts-Win32-vs17-x64\php.exe' artisan users:reconcile-buyer-status --dry-run
```

The command reports user IDs, roles, registration/account states and reasons,
without exposing passwords, document paths or personal contact details. An
eligible account must be an exclusively Buyer account with pending/inactive
metadata, completed normal-registration evidence, and matching creation/update
timestamps. Review notes, reviewer metadata, admin intervention, deleted/archive
flags, Rider workflows and Seller/shop restrictions disqualify it. Ambiguous
records require individual review; the historical schema cannot conclusively
prove the reason for every inactive account.

The implementation run reported **IDs 27 and 29**, two eligible candidates and
zero skipped candidates. Neither account was changed. Both remain inactive until
the user reviews the dry-run and explicitly authorizes repairs. Do not automatically
repair records at login or through a migration.

Future application requires `--apply` plus explicit reviewed `--user` IDs. It
rechecks eligibility under a transaction/row lock and records an audit entry.
`--apply` without IDs fails. Only isolated in-memory tests exercised application
of repairs; the real database was used solely for the dry-run.

## Verification

`BuyerImmediateAccessTest` covers immediate web/mobile login, public role-injection
attempts, pending Seller applications, approval retaining Buyer, disabled/deleted
and rejected accounts, token restrictions, Admin Users, and reconciliation safety.
Existing registration tests now expect active Buyers and test retirement of the
Buyer-review endpoint. All PHP tests use in-memory SQLite and fake file/mail
storage.

The live isolated Edge test created one clearly labeled local Buyer (ID 30) and
one pending test Seller application. Registration, immediate login, account/cart
access, and login/shopping after application submission succeeded. The account
remains active with Buyer only, and Seller access is false. No real application
was approved. These test records remain for review; no existing records were
reconciled or deleted.

`tests/Browser/buyer-registration.mjs` repeats the live test only when
`SHOPPICK_ALLOW_TEST_REGISTRATION=1`, with an isolated Edge/Chrome browser on
debugging port 9222. It generates synthetic test-upload fixtures, creates a new
test account and pending application, and never outputs the generated password.
`SHOPPICK_TEST_URL` overrides the default local URL.

## Files changed for this task

Runtime files:

- `app/Services/BuyerAccountState.php` (new)
- `app/Services/BuyerStatusReconciliation.php` (new)
- `app/Console/Commands/ReconcileBuyerStatus.php` (new)
- `app/Http/Controllers/Auth/RegisterController.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Auth/GoogleAuthController.php`
- `app/Http/Controllers/Auth/CompleteProfileController.php`
- `app/Http/Controllers/MobileApiController.php`
- `app/Http/Controllers/Admin/SellerApplicationController.php` (only Buyer review)
- `app/Http/Middleware/EnsureRegistrationApproved.php`
- `app/Http/Middleware/AuthenticateMobileToken.php`
- `app/Models/User.php` (normal Buyer classification)
- `resources/views/admin/users/index.blade.php`
- `resources/views/admin/users/show.blade.php`

Tests and documentation:

- `tests/Feature/BuyerImmediateAccessTest.php` (new)
- `tests/Browser/buyer-registration.mjs` (new)
- `tests/Feature/RegistrationAndProductVisibilityTest.php`
- `tests/Feature/AdminModerationDynamicSellerWorkflowTest.php` (remove obsolete Buyer approval step)
- `docs/buyer-registration.md` (new)

Earlier address fixes and the user's unrelated working changes are preserved.
No application implementations for products, inventory, categories, logistics,
Riders, archives, tracking, Flutter, public hosting or DNS are changed.
