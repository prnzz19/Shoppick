# Buyer registration fix and Google sign-in review

1. **Exact registration cause:** local Laravel logs at 2026-10-10 04:43:06 and 04:45:15 report MySQL error 1054: unknown column `buyer_web_signup_key`. `WebsiteBuyerRegistrationRequest::rules()` calls `retryUser()` before the controller runs; its query referenced this missing column. The safe authentication exception callback in `bootstrap/app.php` rendered `auth.buyer-unavailable` with HTTP 503. This was a schema failure, not ordinary validation or OTP rejection. An earlier connection-refused entry was separate; MySQL was reachable during this investigation.
2. **Exact fix:** applied only the additive `2026_10_10_000001_add_buyer_web_verification.php` migration. The first attempt exposed MySQL error 1067, invalid default for `sent_at`: legacy implicit TIMESTAMP behavior conflicted with strict defaults. Changed challenge times to DATETIME and added per-column existence checks to safely resume MySQL's partially persisted DDL. The targeted retry succeeded; migration is recorded as batch 36. Did not remove the generic exception handler or bypass verification.
3. **Password registration:** validate → one Buyer-only Unverified/inactive account and private ID/default address → OTP → verification page → correct OTP activates account and regenerates the session. No Admin approval.
4. **OTP:** six random digits, hashed challenge, five-minute expiry, one-time deletion, cooldown/resend and attempts remain enforced. Initial mail failure retains the account, rolls back the incomplete challenge, and redirects to verification with Resend. Tests cover correct/wrong/expired/new/old codes and unverified Login. Existing password-registered unverified accounts still need SHOPPICK OTP when using Google.
5. **Duplicates:** same-form retries remain idempotent. A fresh session-owned registration form may resume an Unverified Buyer only after its normalized email, canonical phone and password match; no identity/profile data is overwritten and no additional email/user/ID is created. Incorrect credentials or active-account duplicates produce normal validation errors and a sign-in continuation link. Anonymous registration does not expose pending status without credential proof.
6. **Google already existed:** yes, controller, routes, service config, button availability helper and `social_accounts` provider mapping already existed. The current architecture was extended.
7. **Google changes:** check verified provider email on every callback, including already-linked subjects; normalize matching; include soft-deleted accounts when resolving email; reject unavailable/privileged/orphaned identities; prevent replacing an existing different Google subject; handle redirect/account failures safely; retain only reliable supplied first/last names; assign canonical website email for new Google Buyers. First-time profile completion now requires Terms and keeps the authenticated session through storefront redirect.
8. **Socialite:** yes, already installed. Stateful `user()` is retained; no `stateless()` or client-submitted identity shortcuts. Scopes are only openid/email/profile. Google tokens/passwords are not stored by this integration.
9. **Matching:** stable `(provider, provider_id)` identifies linked accounts. Unlinked emails use `LOWER(TRIM(email))`; new accounts are created transactionally. Verified Gmail or Google Workspace identities may match an existing eligible Buyer. Other external-domain existing accounts require normal SHOPPICK password login instead of automatic email-based linking. Already-linked subjects can continue using Google without email-based reassignment. Existing roles are retained; public Google Login denies Admin/non-Buyer accounts.
10. **New Google Buyer:** trusted verified provider identity → Buyer only, email verified, status incomplete → secure session → Complete Profile. Missing DOB/mobile/address/ID/Terms are collected from the user. No data is invented and no redundant SHOPPICK OTP is sent for a new Google-verified identity.
11. **Complete Profile:** Unicode name rules, 18+ DOB, canonical unique PH mobile, four-digit postal code, structured address and private valid ID remain authoritative. Explicit Terms/Privacy acceptance added. Success activates the ordinary Buyer, regenerates session ID, retains Login and redirects to marketplace. It does not require Google users to log out and authenticate again.
12. **Roles:** new users get only Buyer, regardless of OAuth/request role parameters. Existing Buyer+Seller retains both and the existing seller application flow. Disabled/archived/admin-reviewed restrictions are not cleared by OTP or Google.
13. **Callback route:** GET `/auth/google/callback`, named `auth.google.callback`, confirmed with `php artisan route:list --path=auth/google`.
14. **Local Authorized redirect URI:** `http://127.0.0.1:8000/auth/google/callback`. Use a Google Cloud OAuth client of type Web application. Use the same host consistently for registration, Login and callback so session cookies/state match. This server-side Socialite redirect flow does not require an Authorized JavaScript origin; if adding one for separate browser integration, the local origin is `http://127.0.0.1:8000`.
15. **Production redirect URI:** add separately: `https://shoppickmarket.shop/auth/google/callback`. Local testing does not depend on the production domain. No Cloudflare/Z.com/DNS changes were made.
16. **Environment variable names:** `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `APP_URL`. Existing `.env.example` already has placeholders and the local callback; `config/services.php` already uses those variables. SMTP needs `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, and provider-appropriate `MAIL_SCHEME` if required. After editing local environment configuration, run `php artisan config:clear` and retry. Do not replace live SMTP sender with an example.com testing address.
17. **Secrets:** no actual credentials displayed, hardcoded or committed. Configuration inspection reported booleans only. Local Google client ID/secret are missing; callback configuration exists. SMTP transport is selected but sender address is empty. A verified sender address was requested; no value was fabricated.
18. **Tests run:** full feature/unit suite; production frontend build; opt-in SQLite-memory Buyer fixture generation and Edge browser runner; local MySQL transaction smoke; HTTP GETs on the running port 8000 server; route/migration status; git status/stat/diff/whitespace review.
19. **Results:** `php artisan test --compact`: **293 passed, 6 skipped, 0 failed, 4376 assertions**. Google tests mock provider responses and also exercise real Socialite redirect state generation and state mismatch rejection. Local MySQL smoke passed registration, OTP activation, verification page and account access; rolled back all fixture records. Fixture generator separately passed 13 assertions. Browser checks passed at 1440/768/390 px. `npm run build` and `git diff --check` passed.
20. **Registration verification:** running `http://127.0.0.1:8000/register/buyer` returns 200 with registration form and no unavailable page. A local-MySQL HTTP-kernel POST creates Unverified Buyer, redirects to verification and accepts OTP. This uses faked mail and geography in a rolled-back CLI transaction; it is not a human browser account creation or real inbox-delivery claim. Existing account count was unchanged. Automated initial-SMTP-failure test confirms one recoverable account with a working verification page.
21. **Google manual readiness:** code/tests ready; live authentication awaits your Google Cloud client credentials and consent/test-user configuration. Login returns 200 with a disabled unavailable state; Google redirect without config returns safely to Login (302). Add the exact local redirect URI, configure the three Google variables, clear config cache and test with an eligible Google test user. Real SMTP delivery also awaits a verified sender address; additional provider credentials/network setup may still be needed. No real Google consent flow or inbox delivery was performed.
22. **Migration:** required Buyer migration now safely applied to local MySQL. No additional Google migration required: `social_accounts` with unique provider/subject already exists and was already migrated. No fresh/refresh/wipe, user deletion or global status reconciliation.
23. **Exact files for this follow-up:** listed below. The Git snapshot includes earlier uncommitted Buyer/Rider/mobile/report changes; those are not newly introduced by this follow-up.
24. **Password Login:** preserved. Password Login, forgot/reset password, session handling and normal OTP tests pass. Google is an additional method.
25. **Admin approval:** normal Buyer activation still uses OTP or trusted Google identity plus complete profile, with no Admin approval. Seller approval remains separate.
26. **Mobile:** no `mobile/`, `logistics_mobile/`, MobileApiController or AuthenticateMobileToken edits in this follow-up. Their pre-existing diffs remain unchanged. Shared status and mobile API regression tests pass.
27. **Git:** no staging, commit or push. Final working tree is unstaged, with previous changes preserved; snapshot below. Build/storage fixtures are ignored outputs.

## Files changed in this follow-up

```text
app/Http/Controllers/Auth/GoogleAuthController.php
app/Http/Controllers/Auth/CompleteProfileController.php
app/Http/Controllers/Auth/RegisterController.php
app/Http/Requests/WebsiteBuyerRegistrationRequest.php
app/Services/BuyerEmailVerification.php
database/migrations/2026_10_10_000001_add_buyer_web_verification.php
resources/views/auth/complete-profile.blade.php
resources/views/auth/register.blade.php
tests/Feature/RegistrationAndProductVisibilityTest.php
tests/Feature/GoogleBuyerAuthTest.php (new)
tests/Support/local-buyer-registration-smoke.php (new)
docs/buyer-auth-fix-google.md (new)
docs/buyer-workbook-report.md (historical-report cross-reference)
```

The local smoke can be rerun explicitly:

```powershell
$env:RUN_LOCAL_BUYER_SMOKE='1'
php tests/Support/local-buyer-registration-smoke.php
Remove-Item Env:RUN_LOCAL_BUYER_SMOKE
```

It refuses non-local/non-MySQL execution, uses a rollback transaction, fakes mail/location responses, never prints OTPs/secrets and creates no lasting accounts. It does not apply migrations.

Official references: [Laravel Socialite](https://laravel.com/framework/docs/socialite) describes the stateful provider flow; [Google OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect) documents exact redirect matching, stable subjects, verified emails and authoritative Gmail/Workspace identities. No OAuth scopes beyond identity were added.

## Final git status

```text
 M app/Http/Controllers/AccountController.php
 M app/Http/Controllers/Auth/CompleteProfileController.php
 M app/Http/Controllers/Auth/GoogleAuthController.php
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
?? docs/buyer-auth-fix-google.md
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
?? tests/Feature/GoogleBuyerAuthTest.php
?? tests/Feature/RiderPortalTest.php
?? tests/Support/local-buyer-registration-smoke.php
```
