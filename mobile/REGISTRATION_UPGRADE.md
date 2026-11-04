# Buyer registration upgrade — 28 October 2026

Implemented on `yuan-mobile` in `C:\xampp\htdocs\Shoppick\.worktrees\yuan-mobile`. Existing unfinished implementation and backend changes from the earlier integration task were preserved. This registration upgrade changes Flutter code and focused Flutter tests only.

The supplied screenshot is the primary visual reference. Prince's `shoppic-latest-update-2` branch was checked read-only through GitHub; its current commit remains [`bd909aad52f25f209a0c76a5ec7acec31b323e8f`](https://github.com/prnzz19/Shoppick/commit/bd909aad52f25f209a0c76a5ec7acec31b323e8f). The public website and local demo server were unreachable during this upgrade. No production registration was attempted.

## Completed in Flutter

| Area | Result |
|---|---|
| Visual layout | Centered official logo, light background, white bordered card, navy labels, teal section headings/dividers, rounded outlined fields and controls. Single column on phones; two columns when sufficient width and text scaling permit. |
| Fields | Personal information, contacts, inline delivery address, ID verification, security, consent and registration actions follow the requested order. Sex requires an explicit selection. |
| Birthday and age | Native Flutter date picker; `dd/mm/yyyy` display; read-only calculated age; future dates prevented. Submission uses `YYYY-MM-DD` and never sends age. Age eligibility remains 18+, with Manila calendar dates and leap-day boundary tests. |
| Address | Uses the configured backend origin and `/api/philippine-locations/...`, never `/api/v1/philippine-locations`. Searches by name/code; resets descendants; supports regions without provinces; protects against stale responses; caches repeated successful requests; retries failures; supplies canonical selected names and codes. No Laguna restriction or fabricated postal mapping. Postal code is four-digit manual entry; country is always `PH`. |
| Valid ID | Real existing FilePicker integration restricted to JPG/JPEG/PNG/PDF, maximum 5 MB. Shows filename, supports replacement/cancellation, rejects invalid replacements, uploads multipart to the existing API. No ID preview, public copy, new permission or ID logging was introduced. |
| Validation | Website-compatible name rules, email, Philippine phone normalization, address completeness, postal code, birthday, ID, strong password indicators, show/hide controls, matching confirmation and mandatory consent. Uniqueness and geographic hierarchy remain server-authoritative. Laravel field errors remain available individually and render beside the correct input. |
| Submission | Busy guard prevents duplicate requests. Network/server/rate-limit errors retain entered data and allow retry. A registration response must explicitly require email verification before its token is saved; an active/approved `201` response is not accepted as successful onboarding. |
| Google | Disabled unless the existing secure mobile Google SDK flow has build configuration, platform support and matching backend routes. No OAuth shortcut or client secret was added. Real Google account selection is unverified. |
| Existing flows | Existing login and OTP screens are retained. Google profile completion uses the same upgraded required form. Seller, Rider, Logistics and Admin screens were not changed by this upgrade. |

## Registration / OTP compatibility

Prince's current release [`routes/api.php`](https://github.com/prnzz19/Shoppick/blob/shoppic-latest-update-2/routes/api.php) still routes registration to the older [`MobileApiController::register`](https://github.com/prnzz19/Shoppick/blob/shoppic-latest-update-2/app/Http/Controllers/MobileApiController.php). That method uses `BuyerAccountState::attributes()` and returns `201` with a login-ready message. The release has no mobile registration OTP routes. This conflicts with its unverified website registration flow.

The current local worktree already contains a compatible OTP contract from the **previously authorized integration work**, including `MobileBuyerAuthController` and `/api/v1/register/verify` and `/api/v1/register/resend`. Those existing Laravel changes were not edited, approved, committed or merged by this Flutter upgrade.

The form checks the known session and POST-only OTP routes using **GET requests only**, before enabling submission. It rechecks the contract immediately before uploading personal data. Missing routes, malformed responses, outages and rate limits leave submission unavailable. Known legacy registration is never used as a probe. The same build therefore cannot silently fall back to the release's immediate-activation registration.

When a compatible server returns an onboarding token and `requires_verification: true`, Flutter displays the existing OTP screen and uses the existing verification/resend behavior. This is covered by synthetic contract tests; it is **not a claim of a new live registration/SMTP success for this upgraded form**. The earlier device evidence remains separate under `storage/app/mobile-demo-validation/`.

## Separate backend approval request for Prince

Approval/integration of the existing local OTP backend work is required before enabling registration against Prince's release. No backend edits are included in this upgrade.

Please review the existing `MobileBuyerAuthController`, `MobileBuyerRegistrationRequest`, `MobileBuyerSession`, shared `BuyerEmailVerification`/`RegistrationVerification` behavior, and their route changes as a separate backend change. Required behavior:

1. `POST /api/v1/register` accepts the existing multipart fields and privately stored `valid_id`, applies website validation/normalization, and creates an inactive, unverified Buyer with canonical address names/codes. Return an onboarding token plus `requires_verification: true`; do not activate the account.
2. `GET /api/v1/auth/session` reports the current onboarding state. An onboarding token cannot access protected normal Buyer operations.
3. Existing `POST /api/v1/register/verify` verifies the actual six-digit email OTP, expiry and attempt limits before enabling the Buyer account. Existing `POST /api/v1/register/resend` applies the shared resend cooldown and delivery error handling.
4. Existing `POST /api/v1/auth/logout` can revoke pending onboarding tokens. Google registration, if enabled, must use the existing server-verified ID-token flow and required profile/verification gates.

The website's session/CSRF `registration_token` is intentionally not sent by the native multipart API. The compatible local `MobileBuyerRegistrationRequest` already removes that website-only requirement. No new database architecture or migration is requested for this Flutter task.

## Verification and evidence

- `flutter analyze --no-pub lib test`: **No issues found**.
- `flutter test --no-pub`: **221 tests passed**, including existing login, shopping and Seller-related Flutter coverage. The upgraded registration adds 23 focused tests covering payloads, contract gating/refresh, OTP state, validation, location dependencies/races/retry, responsive layout, keyboard preservation, file handling and one multipart submission.
- Android debug APK: built successfully at `build/app/outputs/flutter-apk/app-debug.apk`. The existing `file_picker` Kotlin migration/native-access warnings remain non-blocking.
- `git diff --check`: passed.
- The protected-file hash audit reports **zero** changes to Laravel, website resources, routes, migrations, Composer configuration or PHP tests during this registration upgrade. The original `.env` hash is unchanged. No shared database connection, write or migration was performed for this upgrade.
- No commit, push, merge, deployment, OS setting change or production test-account creation was performed.

Generated evidence is ignored by Git under `storage/app/registration-upgrade/`: `analyze.log`, `flutter-tests.log`, `android-build.log`, `baseline.json`, `scope-proof.json`, and `screenshots/registration-320.png`, `registration-612.png`, `registration-800.png`. The previews are rendered Flutter widget screenshots using synthetic API fixtures and an installed system font, **not live-device or live-backend proof**. Older integration harnesses are outside the scoped app/unit/widget analysis above.

New end-to-end registration, nationwide live location service availability, external SMTP delivery and real Google OAuth remain unverified for this upgraded form. Production registration must stay disabled until the backend OTP contract is approved and available.

## Flutter files changed

- `lib/main.dart`: imports/part wiring; moved the existing registration class into the focused registration screen file.
- `lib/screens/registration_screen.dart` (new): responsive form, date/age, searchable selectors, identity picker, validation, availability guard and submission UI.
- `lib/models/buyer_registration.dart` (new): age, normalization, basic validation and payload preparation.
- `lib/services/philippine_locations.dart` (new): existing public endpoint client and dependent selection state.
- `lib/services/buyer_registration_service.dart` (new): read-only compatibility checks and guarded registration submission.
- `lib/services/api_service.dart`: field-error details and explicit OTP-response gates.
- `lib/services/google_auth_service.dart`: supported/configured mobile-flow availability.
- `lib/config/api_config.dart`: safe backend-origin URL builder.
- `test/buyer_registration_upgrade_test.dart` (new): 23 focused synthetic tests and responsive previews.
- `test/onboarding_flow_test.dart`: updates the existing profile-form assertion for inline address fields.

This report is the only additional handoff document created for the registration upgrade. Dependency manifests and native platform files were not changed by this upgrade.
