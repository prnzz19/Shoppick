# Buyer website review report

Historical report from the initial Buyer improvements. The subsequent registration diagnosis, applied migration and Google sign-in changes are recorded in [buyer-auth-fix-google.md](buyer-auth-fix-google.md).

Prepared for review only. No files were staged, committed, or pushed. No live database migration was run.

1. **Cases reviewed:** TC-BUY-001 through TC-BUY-030 from `C:/Users/Yuan Gallardo/Downloads/Blackbox_Test_Cases_Account_Creation-1.xlsx`. Read Summary, Test Data, Test Files, Assumptions, and Buyer. Extracted only the ten specification columns; excluded Actual Result, Status, and Remarks. The original-code classifications are in `buyer-workbook-audit.md`.
2. **Before:** valid website Buyer registrations immediately became active/approved and redirected to Login. There was no Buyer email OTP. Existing roles and shopping authorization already existed.
3. **After:** Guest → server validation → Buyer-only Unverified/inactive account → emailed OTP → verification → active account and authenticated storefront session. No Admin approval. Existing active accounts are not reset or bulk migrated.
4. **Core fields retained:** first name, last name, email, mobile, DOB, password, confirmation, Terms/Privacy acceptance.
5. **SHOPPICK fields preserved:** optional middle initial, required sex, derived read-only age, private valid-ID upload, structured delivery address and default address creation. JPG/PNG/PDF ID up to 5 MB remains required. No workbook-only fields were invented.
6. **Names:** authoritative Unicode letter/punctuation validation, 2–50 characters, at least one letter; spaces, ñ/Ñ, hyphen, apostrophe, period accepted. Numeric/symbol-only names rejected. Inline errors and browser feedback added.
7. **Email:** trims and lowercases, validates format including a dotted domain, checks normalized uniqueness across all users including soft-deleted accounts. New website canonical email has a unique database key. New website Buyer profile email changes require verification again.
8. **Mobile:** strict `09XXXXXXXXX` or `+639XXXXXXXXX`; canonical `+639XXXXXXXXX`. Rejects arbitrary symbols, letters and wrong lengths. Checks legacy local/international contact aliases, including old formatting, across all users. New website canonical phone has a unique database key.
9. **DOB:** server checks at least 18 on the Philippine registration date; birthday is authoritative and submitted age ignored. Tests derive age boundaries at runtime instead of using dated workbook examples.
10. **Password:** 8+ characters, uppercase, lowercase, number, non-whitespace special character; exact confirmation; stored hashed. Buyer password reset/change also enforce strength. Registration and Login have accessible show/hide toggles; registration has a requirements checklist.
11. **Terms:** explicit accepted value required server-side. Links open usable `/terms` and `/privacy` pages. No policy content existed in the inspected project: pages read `legal.terms` / `legal.privacy` from SystemSettings and honestly indicate missing content. Owner-approved policy text must be published before deployment; no legal text was fabricated.
12. **Duplicates:** browser Processing/disabled state and repeat-submit guard; transaction; unique canonical contact/signup keys; session-owned random registration token provides idempotent retries with matching credentials. Three repeated requests produce one user, one private ID, one challenge and one email.
13. **OTP generation:** secure `random_int`, six digits, delivered through Laravel Mail. Only its hash is stored in the separate challenge table. Browser responses show a masked destination, not the OTP. Production log-mail transport is rejected. Tests use Mail::fake and example.com addresses.
14. **OTP expiry:** five minutes, rejected at the exact expiry boundary. Countdown provides expiry/resend guidance without overriding server checks. Successful use deletes the challenge.
15. **Resend:** new, different OTP replaces the old challenge; old code fails. 60-second cooldown, five resend requests/minute, ten verification requests/minute, five wrong-code attempts per challenge. Mail failure rolls back the challenge replacement, preserving the prior code.
16. **Unverified Login:** correct credentials establish a restricted session and redirect to Email Verification. Account/cart/checkout remain blocked. Mobile Login also rejects the inactive website account. Remember-me is deferred until verification.
17. **Verified Login:** session ID regeneration, existing remember-me/logout behavior, Buyer name and account/storefront access retained. Five credential failures per normalized email/IP trigger a 60-second delay. Unexpected authentication errors produce a private 503 page/JSON response.
18. **Access control:** existing backend role middleware remains authoritative. Buyer-only users cannot render Admin, Logistics, Rider or Seller dashboards; verified Buyer tests expect 403. OTP cannot activate soft-deleted/archived or administratively disabled accounts.
19. **Buyer + Seller:** existing seller application/approval and dual-role shopping retained. Regression tests register and verify Buyers before applying to Become a Seller; Seller/Admin production logic was not redesigned.
20. **Address:** existing dependent Region → Province → City/Municipality → Barangay preserved. Four-digit postal code enforced for Buyer website registration/completion. Existing provider normalization, fallback, retry, saved-address and validation tests pass. Google profile completion retains trusted Google email verification and adopts Buyer identity rules.
21. **Tests added/updated:** `BuyerWorkbookTest` and isolated browser runner added; Buyer registration fixtures in seven existing feature files updated. Mobile registration assertions were preserved. The existing order-history IDOR test fixture now explicitly activates its second Buyer so authorization is actually exercised.
22. **Coverage:** all 30 workbook requirements mapped below, with additional malicious-array, hash/one-use, mail-failure, CSRF, attempt-limit, administrative-disable, legacy-contact and email-change checks.
23. **Results:** final `php artisan test --compact`: **277 passed, 6 skipped, 0 failed, 4245 assertions**. Skips are existing conditional/optional tests plus opt-in fixture generation. The isolated Buyer fixture generator separately passed (13 assertions). `npm run build` passed. `git diff --check` passed.
24. **Browser/manual verification:** automated headless Edge checks at 1440, 768 and 390 px; 21 screenshots; visually inspected registration and verification phone screenshots. Checked password toggles, valid/invalid live input, field preservation/errors, one registration submission, Login Processing state, dependent address selections, geography outage/Retry, pasted OTP without automatic submission, numeric OTP validation, expiry and resend countdowns. These used an isolated recording server and rendered SQLite-memory fixtures. No human inbox delivery or live-database end-to-end session was performed.
25. **Migration:** yes, additive `2026_10_10_000001_add_buyer_web_verification.php`; adds three nullable unique website identity keys and `buyer_email_verifications`. It does not rewrite legacy users or alter existing API schemas. **Prepared, not applied to the live database.** After review, use a migration restricted to this path rather than automatically running all unrelated pending migrations. Configure a real delivery mail transport before enabling this website flow.
26. **Mobile compatibility:** existing `/api/v1/register` immediate-activation payload/behavior is unchanged; shared `BuyerRegistrationRequest` and `BuyerAccountState` are unchanged. Existing mobile authentication and Buyer regression tests pass. Website-unverified accounts become eligible for mobile Login after OTP activation. No Flutter/device smoke test was performed. Legacy cross-role contact checks are application-side; the new canonical unique keys protect participating website identities without rewriting mobile/shared-role data.
27. **Exact Buyer files:** listed below. Compiled assets and fixture/screenshots are ignored build/storage outputs.
28. **Other roles:** no new Seller/Admin/Logistics/Rider production changes were made for this task. Shared Login gained normalization/throttling/UX and shared User gained hidden internal fields; existing role redirects and restrictions pass regression tests. Prior uncommitted Rider/mobile changes remain present and were preserved. Admin/Seller test changes only adapt their Buyer-registration setup to OTP.
29. **Database safety:** no live migration, reset, seed, delete, reconciliation or destructive command. RefreshDatabase ran only with PHPUnit's SQLite `:memory:` database. Browser server has no database connection and records POSTs without executing them; mail is faked.
30. **Git:** working tree remains unstaged with Buyer changes plus earlier uncommitted work. No commit or push. `git status`, `git diff --stat`, `git diff` and whitespace checks were run. Final status is recorded below; it includes pre-existing Rider/mobile/report work as well as this task.

## Workbook coverage

| TC-BUY | Requirement | Verification |
| --- | --- | --- |
| 001 | Valid primary Buyer registration | Feature: unverified, private ID/default address, one email |
| 002 | All fields empty | Feature: errors, zero accounts/emails |
| 003 | Each mandatory field blank | Feature: individual field errors |
| 004 | Numeric/inappropriate name symbols | Feature partitions and browser feedback |
| 005 | ñ, hyphen, apostrophe | Feature and browser valid names |
| 006 | Name 1/2/50/51 boundaries | Feature for both names |
| 007 | 17 / 18 today / 18 + one day | Feature using current Philippine date |
| 008 | Future DOB | Feature and browser |
| 009 | Invalid email partitions | Feature and browser |
| 010 | Duplicate email | Feature, zero extra users |
| 011 | Uppercase duplicate email | Feature, including legacy other-role account |
| 012 | Email whitespace trim | Feature canonical storage |
| 013 | Invalid PH phone partitions | Feature and browser |
| 014 | 10/11/12 local digits | Feature partitions and valid registration |
| 015 | Duplicate phone | Feature, legacy/new identity checks |
| 016 | 09 and +639 equivalent | Feature canonical storage/duplicate rejection |
| 017 | 7/8 password boundary | Feature, valid Bili@202 |
| 018 | Missing password category | Feature partitions and browser checklist |
| 019 | Confirmation mismatch | Feature and browser |
| 020 | Hidden → shown → hidden | Browser, default masked registration/Login |
| 021 | Terms unticked | Feature; usable policy-page browser checks |
| 022 | Rapid repeated Register | Feature nonce retries/database keys; browser one recorded POST |
| 023 | Correct current OTP | Feature activation, timestamp, one-time deletion |
| 024 | Incorrect OTP | Feature remains inactive, attempt increment |
| 025 | 5/7 digits, letters, mixed, blank | Feature; browser numeric/paste input |
| 026 | Expired code | Feature exact five-minute boundary; browser expiry guidance |
| 027 | Resend invalidates previous code | Feature cooldown/new/old codes/attempt reset; browser countdown |
| 028 | Login before OTP | Feature verification redirect, protected-page/API denial |
| 029 | Active Login/name/session | Feature account/name/cart, session regeneration/logout |
| 030 | Other role URLs denied | Feature Admin/Seller/Logistics/Rider 403 |

## Exact Buyer files

```text
app/Http/Controllers/AccountController.php
app/Http/Controllers/Auth/BuyerVerificationController.php (new)
app/Http/Controllers/Auth/CompleteProfileController.php
app/Http/Controllers/Auth/LoginController.php
app/Http/Controllers/Auth/RegisterController.php
app/Http/Controllers/Auth/ResetPasswordController.php
app/Http/Middleware/EnsureRegistrationApproved.php
app/Http/Requests/WebsiteBuyerRegistrationRequest.php (new)
app/Mail/BuyerVerificationCode.php (new)
app/Models/User.php
app/Services/BuyerEmailVerification.php (new)
app/Support/BuyerIdentity.php (new)
bootstrap/app.php (Buyer error callback; existing Rider changes preserved)
database/migrations/2026_10_10_000001_add_buyer_web_verification.php (new)
resources/js/app.js (Buyer import; existing Rider import preserved)
resources/js/buyer-auth.js (new)
resources/views/auth/buyer-policy.blade.php (new)
resources/views/auth/buyer-unavailable.blade.php (new)
resources/views/auth/buyer-verification.blade.php (new)
resources/views/auth/complete-profile.blade.php
resources/views/auth/login.blade.php
resources/views/auth/register.blade.php
resources/views/emails/buyer-verification.blade.php (new)
resources/views/storefront/account/profile.blade.php
routes/auth.php
tests/Browser/buyer-workbook.mjs (new)
tests/Feature/AdminModerationDynamicSellerWorkflowTest.php
tests/Feature/AdminUserTabsTest.php
tests/Feature/BirthdayAgeFieldsTest.php
tests/Feature/BuyerImmediateAccessTest.php (existing mobile assertions preserved)
tests/Feature/BuyerOrderHistoryTest.php
tests/Feature/BuyerWorkbookTest.php (new)
tests/Feature/PhilippineLocationResilienceTest.php
tests/Feature/RegistrationAndProductVisibilityTest.php
docs/buyer-workbook-audit.md (new)
docs/buyer-workbook-report.md (new)
```

## Reproduce isolated verification

```powershell
php artisan test --compact
npm run build
$env:BUYER_WORKBOOK_FIXTURES='1'
php artisan test --compact --filter='BuyerWorkbookTest.*test_opt_in_browser'
Remove-Item Env:BUYER_WORKBOOK_FIXTURES
# With an isolated headless Edge CDP page running on port 9227:
node tests/Browser/buyer-workbook.mjs
```

Screenshots: `storage/app/buyer-workbook-previews/`. These are test fixtures, not live account data. The browser runner deliberately refuses to perform real registration writes.

## Final git status

```text
 M app/Http/Controllers/AccountController.php
 M app/Http/Controllers/Auth/CompleteProfileController.php
 M app/Http/Controllers/Auth/LoginController.php
 M app/Http/Controllers/Auth/RegisterController.php
 M app/Http/Controllers/Auth/ResetPasswordController.php
 M app/Http/Controllers/MobileApiController.php
 M app/Http/Controllers/Rider/RiderController.php
 M app/Http/Middleware/AuthenticateMobileToken.php
 M app/Http/Middleware/EnsureRegistrationApproved.php
 M app/Models/User.php
 M bootstrap/app.php
 M resources/css/app.css
 M resources/js/app.js
 M resources/views/auth/complete-profile.blade.php
 M resources/views/auth/login.blade.php
 M resources/views/auth/register.blade.php
 M resources/views/components/rider/job-card.blade.php
 M resources/views/layouts/rider.blade.php
 M resources/views/rider/dashboard.blade.php
 M resources/views/rider/jobs.blade.php
 M resources/views/rider/notifications.blade.php
 M resources/views/rider/profile.blade.php
 M resources/views/rider/show.blade.php
 M resources/views/storefront/account/profile.blade.php
 M routes/auth.php
 M routes/rider.php
 M tests/Feature/AdminModerationDynamicSellerWorkflowTest.php
 M tests/Feature/AdminUserTabsTest.php
 M tests/Feature/BirthdayAgeFieldsTest.php
 M tests/Feature/BuyerImmediateAccessTest.php
 M tests/Feature/BuyerOrderHistoryTest.php
 M tests/Feature/PhilippineLocationResilienceTest.php
 M tests/Feature/RegistrationAndProductVisibilityTest.php
?? app/Http/Controllers/Auth/BuyerVerificationController.php
?? app/Http/Controllers/Rider/RiderLoginController.php
?? app/Http/Middleware/EnsureRiderPortalAccess.php
?? app/Http/Requests/WebsiteBuyerRegistrationRequest.php
?? app/Mail/
?? app/Services/BuyerEmailVerification.php
?? app/Services/RiderPortalActionService.php
?? app/Support/BuyerIdentity.php
?? app/Support/RiderPortal.php
?? database/migrations/2026_10_07_000001_add_archived_at_to_users.php
?? database/migrations/2026_10_10_000001_add_buyer_web_verification.php
?? docs/admin-rider-details-ui.md
?? docs/admin-sales-analytics.md
?? docs/admin-sales-reports.md
?? docs/admin-seller-async-filtering.md
?? docs/admin-seller-performance.md
?? docs/buyer-registration.md
?? docs/buyer-workbook-audit.md
?? docs/buyer-workbook-report.md
?? docs/rider-web-portal.md
?? docs/sales-reports-ui.md
?? docs/shop-sales-report-exports.md
?? resources/js/buyer-auth.js
?? resources/js/rider-portal.js
?? resources/views/auth/buyer-policy.blade.php
?? resources/views/auth/buyer-unavailable.blade.php
?? resources/views/auth/buyer-verification.blade.php
?? resources/views/emails/
?? resources/views/rider/active.blade.php
?? resources/views/rider/login.blade.php
?? resources/views/rider/unavailable.blade.php
?? tests/Browser/buyer-workbook.mjs
?? tests/Browser/rider-portal.mjs
?? tests/Feature/BuyerWorkbookTest.php
?? tests/Feature/RiderPortalTest.php
```
