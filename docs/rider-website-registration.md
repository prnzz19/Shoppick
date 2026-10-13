# Rider website registration completion

1. **Already completed before resuming:** the dedicated `/rider/register` route, registration request/service, initial three-step form/field component, pending-review helper, setting guard and submitted-page shell were present. These were inspected and retained; the Rider system was not restarted.
2. **Unfinished:** Rider Login had no registration link; success lacked status navigation; the application status page signed out to Buyer Login; suspended/inactive Login had no dedicated restricted screen; tests/build/browser verification were incomplete.
3. **Completed:** connected Rider Login, success/status and sign-out; added a restricted account page; finished validation/error/document handling; verified existing Logistics approval and manual Rider compatibility; completed tests and responsive browser checks.
4. **Routes:** GET/POST `/rider/register`, GET `/rider/register/submitted`, GET/POST `/rider/login`, GET `/rider-application/status`, POST `/rider-application/resubmit`, GET `/rider/account-unavailable`. Legacy GET/POST `/register/rider` uses the same controller/request/service; its form posts to the canonical endpoint. No duplicate registration implementation.
5. **Login/status:** valid pending/rejected/needs-resubmission Rider credentials regenerate a restricted session and open the application-status page. The dashboard/parcel routes remain blocked until activation. Approved, active Rider → dashboard. Inactive/suspended Rider → restricted page; credential-based inactive Login does not establish a session. Archived/deleted profiles cannot enter. Rider sign-out returns to Rider Login. Pending remember-me is not persisted.
6. **Roles:** the server hardcodes only `rider`, `registration_type=rider`, `registration_status=pending`, `is_active=false`. Submitted roles, account statuses, user IDs, hub/vehicle IDs and privilege fields are ignored. New accounts do not receive Buyer role, Buyer OTP or Seller privileges.
7. **Buyer prerequisite:** none. Registration, pending Login, Logistics approval and dashboard access were tested for a person with only Rider role. Buyer-only accounts remain denied. Existing Buyer/Seller/Google/OTP production code and mobile code were untouched; regression tests passed.
8. **Approval:** reuses `RiderProfile`, private IDs/license documents, license audit rows and existing Logistics application review/notifications. Pending profile is inactive/unavailable with license pending_review. Existing Logistics approval activates User and RiderProfile and verifies the license; availability remains off_duty until chosen/assigned under existing rules. No second approval system, immediate activation or bypass.
9. **Validation:** Unicode names with sensible 2–50 bounds; lowercased/trimmed email and normalized case-insensitive uniqueness including soft-deleted accounts; strict 09/+639 canonical PH phone uniqueness; hashed strong confirmed passwords; accepted Terms; private JPG/PNG/PDF documents within existing size limit; supported vehicle preferences; unexpired license; dependent geography normalization. Existing past-DOB rule retained; no new fixed Rider age requirement was invented. Postal code remains optional but must be four digits when supplied. Emergency/vehicle preferences remain optional. Existing accounts cannot be silently converted; errors offer Rider sign-in/support instead.
10. **UX:** SHOPPICK teal/orange/navy branding; three progressive sections for personal/address, Rider details/documents, and account/review; full-form fallback without JS. Accessible labels/feedback, inline errors, preserved valid values, explicit document reattachment, password show/hide/checklist, Back/Continue, Processing state and repeat-submit guard. Driver documents stay private. Geography outage/Retry works. Success has Check Application Status and Back to Rider Login.
11. **Tests:** new `RiderWebsiteRegistrationTest` covers guest/alias routes, Rider-only registration, normalization, role injection, hashes/private documents, real Logistics approval, pending/rejected/suspended behavior, duplicate contacts/license, invalid inputs, cleanup, manual Rider compatibility, CSRF/settings/maintenance. Existing RiderApplicationWorkflow/RiderPortal tests updated to new redirects and canonical geography fixtures. New isolated browser runner covers the frontend flow.
12. **Results:** full `php artisan test --compact` passed **375 tests, 8 skipped, 5330 assertions, no failures**. After the final canonical/legacy maintenance-policy consistency adjustment, Rider registration + SystemSettings checks passed **35 tests, 1 optional fixture skipped, 383 assertions**. Buyer/Google/Rider targeted regression previously passed 68 tests. Skips are conditional/opt-in test fixtures. The optional onboarding fixture generator separately passed 13 assertions. No unrelated failure fixes were made.
13. **Build/runtime:** `npm run build` passed (74 modules). Routes, relevant PHP syntax and `git diff --check` passed. Normal `optimize:clear` could not clear the database cache because local MySQL on 3307 is stopped. A process-only `CACHE_STORE=array` override cleared compiled/routes/views caches and was removed; `.env` was not changed. Browser tests at 1440/768/390 px passed with 27 screenshots and no horizontal overflow/runtime exceptions. The full register → pending Login → Logistics approval → active Rider dashboard scenario was exercised using the real application controllers in SQLite-memory tests. Headless browser checks used rendered fixtures and a recording-only POST server. No live account registration/approval was performed; start MySQL on 3307 before live manual acceptance testing.
14. **Exact files:** listed below. No new migration: existing User/RiderProfile/address/audit tables and existing nullable unique website identity keys are reused. The internally named `buyer_web_email` / `buyer_web_phone` keys provide database uniqueness only; populating them does not grant Buyer role or Buyer verification behavior. Existing identities are not rewritten. No destructive database command or live migration was used.
15. **Git:** branch `yuan-improvements-website`; Rider work remains unstaged for review. No add, commit or push. `git status`, `git diff --stat`, `git diff` and whitespace checks were run. Final snapshot below. Seller Marketing/Inventory/Admin Analytics/Orders/Products and Buyer/Google/mobile files have no changes from this task.

## Exact files changed

```text
app/Http/Controllers/Auth/RiderApplicationController.php
app/Http/Controllers/Rider/RiderLoginController.php
app/Http/Middleware/EnsureRiderPortalAccess.php
app/Http/Middleware/MarketplacePreferences.php
app/Support/RiderPortal.php
bootstrap/app.php
resources/js/app.js
resources/views/auth/register-rider.blade.php
resources/views/auth/rider-application-status.blade.php
resources/views/rider/login.blade.php
routes/auth.php (Rider legacy POST throttle only)
routes/rider.php
tests/Feature/RiderApplicationWorkflowTest.php
tests/Feature/RiderPortalTest.php
app/Http/Requests/RiderWebsiteRegistrationRequest.php (new)
app/Services/RiderWebsiteRegistration.php (new)
resources/js/rider-registration.js (new)
resources/views/components/rider/registration-field.blade.php (new)
resources/views/rider/account-unavailable.blade.php (new)
resources/views/rider/registration-submitted.blade.php (new)
tests/Browser/rider-registration.mjs (new)
tests/Feature/RiderWebsiteRegistrationTest.php (new)
docs/rider-website-registration.md (new)
```

MarketplacePreferences changes enforce the existing Rider-application setting and the same maintenance behavior on canonical/legacy public signup. Bootstrap extends the existing private Rider error renderer to the legacy alias. App.js imports only the new onboarding module and preserves Buyer/Rider/Seller/analytics imports.

## Local review

Start the existing local MySQL service, run SHOPPICK, and open `http://127.0.0.1:8000/rider/register`. Submit a genuinely new email/mobile with valid documents. Confirm Rider-only roles, pending application and inactive profile in Logistics → Rider Applications. Sign in at `/rider/login` to check status. Approve through the existing Logistics review UI, then sign in again and confirm the Rider dashboard. Test a Buyer-only Login to ensure it is denied. No email OTP is required for Rider onboarding; it keeps the existing document/Logistics approval lifecycle.

## Reproduce isolated browser checks

```powershell
$env:RIDER_REGISTRATION_FIXTURES='1'
php artisan test --compact --filter='RiderWebsiteRegistrationTest.*test_opt_in'
Remove-Item Env:RIDER_REGISTRATION_FIXTURES
# With isolated headless Edge CDP on port 9228:
node tests/Browser/rider-registration.mjs
```

Screenshots are in ignored `storage/app/rider-registration-previews/`. No production data or real documents are included. The browser server never connects to the application database.

## Final git status

```text
 M app/Http/Controllers/Auth/RiderApplicationController.php
 M app/Http/Controllers/Rider/RiderLoginController.php
 M app/Http/Middleware/EnsureRiderPortalAccess.php
 M app/Http/Middleware/MarketplacePreferences.php
 M app/Support/RiderPortal.php
 M bootstrap/app.php
 M resources/js/app.js
 M resources/views/auth/register-rider.blade.php
 M resources/views/auth/rider-application-status.blade.php
 M resources/views/rider/login.blade.php
 M routes/auth.php
 M routes/rider.php
 M tests/Feature/RiderApplicationWorkflowTest.php
 M tests/Feature/RiderPortalTest.php
?? app/Http/Requests/RiderWebsiteRegistrationRequest.php
?? app/Services/RiderWebsiteRegistration.php
?? docs/rider-website-registration.md
?? resources/js/rider-registration.js
?? resources/views/components/rider/registration-field.blade.php
?? resources/views/rider/account-unavailable.blade.php
?? resources/views/rider/registration-submitted.blade.php
?? tests/Browser/rider-registration.mjs
?? tests/Feature/RiderWebsiteRegistrationTest.php
```
