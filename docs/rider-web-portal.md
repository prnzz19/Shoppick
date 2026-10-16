# Rider website portal

Open `/rider/login` using an existing approved Rider account. The portal uses the existing web guard, users, Rider profiles, assignments, shipments, events, payments, POD records, notifications, and Logistics services. No database migration, account creation, or live database maintenance is required.

Navigation covers Dashboard, My Deliveries, Pickups, Active Delivery, Delivery History, Tracking, Notifications, Profile, and the existing Logistics support, POD, COD, and settings pages. Existing Rider URLs remain available.

## Access and updates

- Login validates credentials before checking role/status, limits failures per email/IP plus a request throttle, supports remember-me, and regenerates the session. Logout invalidates the session and regenerates its CSRF token.
- Portal access requires an active approved Rider profile and active user. Suspended, disabled, archived, deleted, and privileged Admin/Logistics/Seller accounts are denied. Pending applications retain the existing application-status flow.
- Every shipment detail, tracking, action, COD, decline, and POD endpoint checks ownership. Updates lock the shipment and refresh the acting account/profile and assignment before calling the existing lifecycle services. Submitted form versions include shipment attributes, the latest event, and existing proof, catching concurrent updates even within one timestamp second.
- Both current pickup/sorting/delivery and legacy assigned/transit workflows remain supported. Returns, sorting, assignment, and reassignment remain Logistics actions.
- Delivery requires the existing POD and COD prerequisites. Duplicate status changes produce no duplicate events. Identical proof submissions do not reset review or send duplicate notices; changed proofs follow the existing resubmission/review workflow.
- Forms use explicit allowed fields. Rider server errors use a generic portal response; ordinary authorization, missing-record, and validation responses remain intact.
- Notification writes use CSRF-protected POST requests. Notification links must stay in the portal and cannot redirect to an assignment the Rider does not own.

## Interface

Desktop delivery tables switch to cards on narrow screens. Search supports tracking, parcel, and order numbers, with actual status filters, inclusive optional dates, and pagination. List views omit recipient contact and street details. Pickup-only Riders see pickup information and pickup events rather than delivery recipients or another Rider's later events.

Sensitive actions use keyboard-accessible styled dialogs with Cancel/Confirm controls. Problem and decline forms use the existing reason values and require notes for Other. Submit buttons disable while sending. Changed POD, report, and profile forms warn before navigation; unchanged forms do not. Browser closing/refreshing uses the browser's native unsaved warning.

Tracking displays real shipment events and external Maps links for directions. There is no live GPS feed in this backend, so the portal does not simulate one. No second Rider database, mobile application changes, or mobile API changes were made for this task.

## Verification

Final checks: 230 PHP tests passed with 3,868 assertions and five optional tests skipped, excluding only the existing buyer-history failure described below. The final production asset build and all fixture browser checks passed. The optional fixture-generation test also passed independently.

`tests/Feature/RiderPortalTest.php` exercises login, rate limits, session/logout, role/status restrictions, endpoint ownership, list privacy, search/date/status/history filters, pagination, empty states, both lifecycles, COD/POD, duplicate and stale submissions, decline, notification safety, allowed profile fields, private server errors, and real CSRF enforcement.

`tests/Browser/rider-portal.mjs` tests Laravel-rendered in-memory fixtures at 1440, 768, and 390 pixels, password visibility, dirty forms, delivery confirmation/keyboard cancellation, double submission, problem reporting, and mobile navigation/logout. Fixture submissions stay on a local test server and never write to the website database. Screenshots are in `storage/app/rider-portal-previews/`.

Generate fixtures after building assets:

```powershell
$env:RIDER_PORTAL_FIXTURES='1'
php artisan test --compact --filter=test_opt_in_browser_fixtures_use_only_in_memory_test_records
node tests/Browser/rider-portal.mjs
```

The browser script expects an isolated headless Edge CDP session on port 9227. Regular PHP tests use the existing PHPUnit SQLite `:memory:` configuration.

One unrelated existing test, `BuyerOrderHistoryTest::test_purchase_history_filters_searches_and_protects_buyer_orders`, creates an inactive Buyer and expects an order-ownership 404 instead of the account-activation redirect. This failure was reproduced in the saved website snapshot before the Rider changes. Buyer code and that test were left unchanged.

Changes remain local for website review; no commit, push, or deployment was performed.
