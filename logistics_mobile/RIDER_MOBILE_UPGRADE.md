# Rider mobile upgrade

Validation date: 2026-10-31. Worktree: `C:/xampp/htdocs/Shoppick-logistics`.
Branch: `yuan-logistics-mobile`; unchanged HEAD: `e701e300ad375d2e4a76a2bed57a22318c33472c`.

**Status: Rider pickup scanning and a separate permission-gated Logistics staff scan are implemented against local API code. Production synchronization and device camera decoding remain unverified. The repository does not implement a destination-hub receipt workflow, so the complete multi-hub delivery handoff remains blocked and is not claimed as complete.**

## Reference and preservation

The tagged Flutter directory `C:/xampp/htdocs/Shoppick/logistics_mobile` is not a complete project; the actual Rider app is in this dedicated worktree. The primary `Shoppick` checkout was inspected read-only for local website/API contracts; it contains the Rider website and Buyer/Seller mobile API, but no Rider mobile API. The deployed protected website could not be inspected, and no production login or API request was attempted.

The user's 10 Rider screenshots are the primary visual reference. Behavior was checked against the existing dirty Rider worktree and Prince's clean local `shoppic-latest-update-2` reference at `bd909aad52f25f209a0c76a5ec7acec31b323e8f`. A read-only GitHub check found the same remote branch commit. The deployed protected website could not be inspected; no production login was attempted. This does not prove the deployed backend equals either checkout.

Pre-existing edits in both worktrees were preserved. This phase changes Rider Flutter scanner/API presentation and the local mobile API serializer, plus synthetic feature/widget tests. It does not change Buyer/Seller mobile, website controllers, the shared shipment workflow, production configuration, live database records, or production. No live login, migration, commit, push, merge, or deployment was performed.

## Screens and behavior

| Module | Completed mobile behavior | Current limit |
| --- | --- | --- |
| Dashboard | Authorized paginated job/history summaries, current job, today's dated tasks, availability, explicit single GPS share to active assignments, foreground refresh | Counts are all accessible records, not an invented daily earnings summary. |
| My Deliveries / Pickups | Tracking search, status filters, server pickup type filter, pagination, details, refresh, error/empty states | Latest jobs API supports q/status/type. History and legacy searches filter loaded records, explicitly labeled. No unsupported date query is sent. |
| Active Delivery | Current server out_for_delivery assignments and details | Destination start is gated as described below. |
| History | Authorized terminal records and pagination | No invented timestamps, earnings, or complete-history claims from one page. |
| Tracking | Actual assignment status, supplied centers/events, external maps for authoritative addresses/coordinates | Latest API omits event history, live position, ETA, hub address, and recipient/seller phone. Missing information stays unavailable. |
| Notifications | Paginated list, unread state, mark-all-read, individual read, ownership-checked assignment detail | Live push delivery is outside the current API; list/read is server-backed. |
| Logistics Support | Paginated Rider message thread, bounded send, backend notification to Logistics | Messages are server-stored and live manager response was not tested. |
| Profile | Authenticated account and Rider metadata, license status/masked number, emergency contacts, editable phone/emergency contact fields | License files remain private and are not rendered in this app. |
| Proof of Delivery | Eligible assignment photo capture/gallery, preview, recipient, notes, confirmation, versioned multipart upload, protected history | Live photo upload is unverified. |
| COD Collections | Exact assigned payment amounts, server-calculated collection/remittance summaries, collection history and pending items | No editable COD amount, Rider remittance confirmation, or invented earnings. Live collection is unverified. |
| Settings / Logout | Server-backed notification preferences, current-password verification and confirmed password change, explicit logout confirmation and server revoke | Live credential/password change is unverified. Failed Rider logout retains its session and shows an error. A 401 clears it. |

The official mark and wordmark were copied from Prince's repository assets. Navy headings, teal actions, light gray backgrounds, white outlined rounded cards, readable status badges, and compact forms follow the screenshots. Five mobile tabs (Dashboard, Deliveries, Scan, History, More) expose every module without reproducing a desktop sidebar. The Logistics manager shell and shared theme remain intact.

## Authentication and existing APIs integrated

All requests use the existing compile-time `API_BASE_URL` and one Laravel origin. The default is `https://shoppickmarket.shop/api/v1`; emulator/local hosts can still be supplied with `--dart-define=API_BASE_URL=...`. Release requests, including uploads, require HTTPS. Tokens stay in secure storage and authenticated JSON/multipart requests send the saved bearer token.

Compatibility retains the existing legacy login first. **Only a route 404** permits fallback to Prince's existing generic login on the same origin; invalid credentials, authorization failures, and network errors do not trigger another login. Latest mode validates Rider roles and probes authenticated jobs before persisting the session. The API contract is saved with the token. Legacy saved sessions can restore through the latest profile route after a route 404 and a successful Rider access check. Staff access is not inferred from a generic login.

Latest routes consumed, relative to /api/v1:

| Method | Existing route | Request/use |
| --- | --- | --- |
| POST / GET / POST | login / profile / logout | Generic token and roles, authenticated profile, session revoke |
| GET | rider/jobs | page, per_page=50, supported q/status/type filters |
| GET | rider/history | page and per_page only |
| GET | rider/jobs/{shipment} | Ownership-protected detail, allowed_actions, version |
| PATCH | rider/availability | available or off_duty; confirmed nested response |
| PATCH | rider/location | Explicit device latitude/longitude only |
| POST | rider/shipments/{id}/accept | Current version |
| POST | rider/shipments/{id}/decline | Current version, approved reason/details |
| POST | rider/shipments/{id}/action | Allowed action, current version, code/reason/details as applicable |
| POST | rider/shipments/{id}/pod | Fresh allowed_actions check, recipient_name, notes, photo, version |
| POST | rider/shipments/{id}/cod | Current version only; no client amount |

The local legacy Laravel contract also exposes the missing Rider modules:

| Method | Route | Behavior |
| --- | --- | --- |
| GET / PATCH | rider/profile | Safe Rider metadata; phone and emergency-contact updates |
| GET | rider/profile/license/{front\|back} | Authenticated private file download |
| GET / PUT | rider/settings | Read and save notification preferences |
| PUT | rider/password | Verify current password and confirmed new password |
| GET / POST | rider/messages | Own paginated thread; bounded support message send |
| GET | rider/proofs | Own pending and submitted Proof of Delivery records |
| GET | rider/cod | Exact pending items and server-calculated collection/remittance totals and records |
| POST | rider/notifications/{id}/read | Mark only the authenticated Rider's notification read |
| POST | rider/deliveries/{id}/location | Timestamped GPS point for an assigned active shipment |

These Rider routes and serializer changes are local API work, not deployed production changes. The separate Logistics staff scan uses the existing local manager-only `receive` then `scan` actions; rider actions cannot invoke that staff transition.

The latest shipment serializer is adapted to the existing detail widgets, including decimal COD strings and submit_pod action naming. The mobile app removes `parcel_code` from normalized Rider assignments, and the local API no longer returns it in ordinary assignment/detail payloads. A delivery assignment never receives a guessed seller address as its hub pickup destination.

## Scanning, workflow, POD, COD

- Code 128 camera scanning uses the installed mobile_scanner 7.4.2 API, with Android camera permission and optional camera hardware. This local checkout contains no QR label-generation/use evidence, so QR decoding is not enabled. Camera starts only after an explicit tap. Permission errors and manual entry are visible; flashlight and camera switching appear only when the device reports support; lifecycle pause/resume is handled.
- The accepted pickup assignment opens the scanner. A detected or manually entered physical code is previewed with the selected assignment and requires explicit confirmation. Duplicate detections are guarded. Nothing updates merely because a code was detected.
- The app submits the code to confirm_pickup or start_delivery. Laravel validates the code, ownership, state, and assignment before the mobile API transitions the shipment. No expected code is prefilled to simulate scanning. Manual entry is available as a camera fallback.
- arrive_sorting reports the Rider's arrival only. Authorized Sorting Center staff must perform the physical inbound scan.
- Rider pickup scanning submits only a physical/manual code for the selected assigned shipment. The API scopes that shipment to the authenticated Rider and the shared workflow checks assignment ownership, expected code, accepted pickup state, and duplicate status transitions. Rider arrival at the Sorting Center is distinct from the Logistics staff `receive` and subsequent `scan` actions. The staff screen submits the code to the existing manager/permission-gated API action; the server remains the authority.
- Delivery `start_delivery` checks the scanned code and accepted assignment in this local mobile API, but the available state machine does not require a recorded destination-hub receipt. The UI therefore describes assignment verification, not a verified destination-hub handoff. No `legacy_out` action is sent.
- Actions follow server allowed_actions. Latest actions carry the assignment version; absent versions require refresh. The details page refetches after success or failure. 403/404/409/422/429 errors are surfaced; a failed/stale COD request is never automatically replayed.
- POD accepts JPG/JPEG/PNG/WEBP up to 5 MB, recipient up to 100 characters, notes up to 1000. Submission refetches authorization and rejects a changed displayed version before upload. Confirmation is shown before transmission. Delivery completion remains a separate server-authorized action.
- COD is confirmed against the server's exact shipment/payment amount. The request has no amount override. The app waits for the response and refreshes. Backend allowed_actions controls when COD and final delivery can occur. No online payment success is fabricated.

## Same backend and website synchronization

The mobile API uses existing Shipment, Payment, RiderProfile, ProofOfDelivery, RiderMessage, and notification records, and delegates shipment state changes to the shared `ParcelWorkflowService` and `CodCollectionService`. It does not maintain parallel shipment state.

**Live synchronization was not tested.** Laravel feature tests use synthetic records and the configured SQLite `:memory:` test database; no shared or production database was used. They verify the local API contract and database transitions, but not live website visibility, real credentials, device GPS, or camera decoding.

Before a full demonstration, verify actual login and the complete workflow against an isolated current backend with synthetic Rider/provider/shipment fixtures. Validate real photo capture/upload, COD, delivery completion, GPS, and corresponding website records. Never use production parcels or COD payments.

## Remaining backend and live verification requirements

The local API changes are not deployed. Review them before any release, and retain the existing shared database and workflow services.

1. **Production rollout:** compare deployed routes/schema to this local checkout before configuring mobile to use it. No production API, configuration, migration, or database was changed or tested here.
2. **Destination hub receipt:** the local website's generic hub-movement action does not validate a scanned code or require physical arrival, and the mobile API has no hub-movement/receipt route. Do not treat dispatch as receipt or claim multi-hub workflow parity. Backend ownership must define the hub assignment/authorization model and explicit arrival transition before adding mobile support.
3. **Rider assignment policy:** the local RiderProfile schema has no Pickup/Delivery/Both assignment-type field, and no automatic Rider assignment service was found. The app displays assignments returned by the backend but cannot validate or implement those missing policies.
4. **Provider ownership:** the local mobile manager assignment endpoint checks provider mismatch, but the website pickup assignment path calls the shared workflow without that same check. Review/fix the backend policy centrally before claiming provider isolation across all assignment surfaces.
5. **Tracking and filters:** current API tracking omits complete event history, ETA, and live position. Date-range assignment/history filters and some contact/hub details are not part of this contract. Do not fabricate these fields.
6. **Device validation:** verify real camera preview/Code 128 decoding, location permission/GPS, and photo capture on a physical Android device.

### Narrow backend proposal: verified hub receipt

No route or workflow is invented here. Before adding one, the backend owner should define a Logistics-staff-only hub arrival operation that atomically verifies the authenticated staff's provider/hub scope, the shipment's expected destination hub and in-transit state, and the physical parcel code; records the inbound `hub_movements` and tracking event; updates the authoritative hub/status; and rejects wrong-hub, wrong-code, unauthorized, stale, and duplicate arrivals. A Rider must never be able to call this operation. Existing `ParcelWorkflowService::receive` is specifically the pickup Sorting Center receipt and is not a verified arbitrary-hub arrival service.

## Validation evidence

- Flutter analyze: **PASS**.
- Full Flutter test suite: **PASS**, 68 tests.
- Rider integration widget tests: **PASS**, 39 tests.
- Rider mobile API PHPUnit suite: **PASS**, 7 tests and 106 assertions. Logistics mobile API PHPUnit suite: **PASS**, 13 tests and 137 assertions.
- Android debug APK: **PASS** with `flutter build apk --debug`; output: `build/app/outputs/flutter-apk/app-debug.apk`. Gradle emitted a non-blocking `package_info_plus` Kotlin migration warning and a Java native-access warning.
- Existing manager, tracking, Rider Phase 5.1/5.2, and login tests remain covered. Updated old scanner/navigation assertions preserve their original purpose.
- New synthetic HTTP checks cover latest login/restore compatibility, role restrictions, pagination, decimal COD parsing, versioned action routing, destination-scan bypass rejection with zero HTTP, missing-version refusal, 401/403/404/409/422/429 handling, confirmed availability, GPS validation, POD multipart/type/size/stale/authorization checks, failed logout, and malformed success responses.
- All Rider modules were rendered and scrolled at 320px with 1.0x and 1.8x text; existing operational tests also cover 360/390/430px. More navigation, manual scan preview/reconfirmation, and disabled destination action were exercised.
- Visual evidence: `build/rider-upgrade/screenshots/` contains dashboard, deliveries, scan, profile, COD, settings, support, and POD captures, explicitly marked **SYNTHETIC UI TEST | No live backend**. They are widget renders, not live Rider account screenshots. Local capture command: `flutter test --no-pub --dart-define=RIDER_CAPTURE=true`; the optional export uses the local Windows/Flutter fonts.
- Logs and preservation audit are ignored under `build/rider-upgrade/`. They contain no real customer fixtures or credentials.

## Device checks and remaining limitations

An isolated APK application ID, com.shoppick.shoppick_logistics.rider_validation, prevented access to the existing installed app's tokens or data. The camera-only entrypoint is an ignored build artifact with no ApiClient, accounts, orders, or HTTP requests; it is not a production feature or a mocked claim of live integration.

- Android 36.1 Medium Phone: normal isolated APK installed, but Launcher and System UI ANRs prevented reliable app launch verification. Its error capture is named emulator36-system-ui-error.png.
- Android 35 Pixel 7 (16 KB page-size image): the camera-only app and real Rider scanner loaded. Explicit camera permission, real denial UI, manual-entry fallback, synthetic code preview, and cancellation were exercised. Screenshots: emulator-scanner.png, emulator-camera-denied.png, emulator-scan-preview.png.
- Permission denial followed by grant exposed overlapping camera-start requests and a stale error label. The scanner now guards pending starts, waits for its widget frame, clears stale errors after a confirmed running state, shows startup progress, and offers retry after initialization failures. The scanner fixes passed 66 tests at that point; the subsequent full suite passed all 68 tests.
- Camera permission was granted and native logs confirmed the emulator camera opened. The final scanner showed no stale startup error, but the headless emulator surface remained blank. An alternate renderer launch did not establish a usable image. The cause was not conclusively isolated. **Visible camera preview and real barcode/QR decoding remain unverified**, including on physical hardware; do not present the blank capture as a successful camera scan.
- Main authenticated Rider screens, real POD capture/upload, location hardware behavior, real COD/order transitions, duplicate/stale server enforcement, and website synchronization remain unverified against a running disposable current backend. The synthetic screenshots and MockClient cases establish UI/client behavior only.
- The original installed Logistics app and all other accounts were untouched. The isolated test app was stopped to release the camera.
- In the current validation session, the normal debug app launched on the Pixel 7 emulator (Android 15/API 35, 16 KB page-size system image). `API_BASE_URL` was overridden to an unused local loopback endpoint (`http://127.0.0.1:8765/api/v1`), so no production API was contacted. The app displayed its expected offline retry state; a Dart SDK MCP hot restart succeeded. The emulator initially showed a System UI unresponsive prompt; choosing Wait recovered the UI. This verifies startup and hot restart only—not authenticated Rider screens or backend synchronization.

Final normal debug APK: build/app/outputs/flutter-apk/app-debug.apk. A preserved identical copy is build/rider-upgrade/app-debug.apk; application ID is com.shoppick.shoppick_logistics. Temporary camera-check.apk uses the separate validation ID and must not be confused with the normal app.

Existing legacy Rider GPS tracking remains reachable from tracking and assignment details using the original TrackingScreen, with its foreground consent, throttling, authorization checks, and lifecycle shutdown. Latest API tracking uses its own complete page and does not call an unsupported legacy tracking endpoint.

**Ready for a complete live demo: NO.** Blocking items are authoritative destination pickup scan enforcement, missing optional module APIs, camera preview/decoding verification, and a disposable current backend for real end-to-end and website synchronization testing. No automated regression failure remains in the completed checks.

## Rider integration change scope

The integration adds local authenticated endpoints in `app/Http/Controllers/LogisticsMobileRiderController.php` and `routes/logistics_mobile_api.php`, and updates the mobile status action in `app/Http/Controllers/LogisticsMobileController.php`. Its Flutter surfaces include the Rider screens, `lib/services/api_client.dart`, `lib/services/rider_portal_api.dart`, Rider configuration/models, Android camera permission, and synthetic feature/widget tests. Existing dirty work in this worktree was preserved; unrelated Buyer, Seller, Admin, and manager changes were not part of this Rider integration.

Generated APKs and test/build outputs are ignored artifacts. Backend changes remain local and tested with synthetic records; no production API, configuration, database, or live shipment data was changed.
