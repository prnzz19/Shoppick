# Buyer/Seller order tracking

## File inventory

Added:

- `app/Http/Controllers/MobileTrackingController.php`
- `app/Services/MarketplaceShipmentTracking.php`
- `tests/Feature/MobileOrderTrackingTest.php`
- `mobile/lib/models/shipment_tracking.dart`
- `mobile/lib/screens/tracking_screens.dart`
- `mobile/test/order_tracking_test.dart`
- `mobile/documentation/order-tracking.md`

Modified:

- `app/Http/Controllers/MobileApiController.php`
- `routes/api.php`
- `mobile/lib/main.dart`
- `mobile/lib/screens/account_screens.dart`
- `mobile/lib/screens/shopping_screens.dart`
- `mobile/pubspec.yaml`
- `mobile/pubspec.lock`
- `mobile/android/app/build.gradle.kts`
- `mobile/android/app/src/main/AndroidManifest.xml`
- `mobile/android/app/src/main/kotlin/com/example/shoppick_mobile/MainActivity.kt`

## Scope and preserved Logistics work

Implemented on `yuan-mobile`. Buyer/Seller consumes existing `shipments`, `shipment_events`, and `shipment_tracking_points`; there is no new tracking table or GPS writer. No Logistics application code or Rider GPS logic was edited.

Before switching branches, the earlier uncommitted Logistics implementation was preserved in a named Git stash:

`On yuan-logistics-mobile: Preserve uncommitted Logistics tracking before Buyer Seller order tracking`

Inspect it with `git stash list`. Do not apply it on `yuan-mobile`. After preserving/reviewing this Buyer/Seller work and switching back to `yuan-logistics-mobile`, apply the corresponding stash there. Prefer `git stash apply` to retain the backup until verified. The local `logistics_mobile/` exclusion was restored for `yuan-mobile`; remove that exclusion when reviewing new Logistics files on its branch. No branch commit or push was made.

## Google Maps key

Add your own key to **`mobile/android/local.properties`**, preserving existing SDK properties:

```properties
MAPS_API_KEY=YOUR_OWN_RESTRICTED_KEY
```

The file is ignored. Gradle passes the key into the Android manifest through a placeholder. Dart receives only a boolean indicating whether the key is configured. No location permissions or location package are added to the Buyer/Seller app.

Enable **Maps SDK for Android** and billing in Google Cloud. Restrict the key to Android package **`com.example.shoppick_mobile`**, the applicable signing certificate SHA-1, and the Maps SDK for Android API. The Logistics app has a different application ID and its own local configuration. Keys in installed Android applications are extractable; use restrictions.

Get the debug SHA-1:

```powershell
Set-Location C:\xampp\htdocs\Shoppick\mobile\android
.\gradlew.bat signingReport
```

Official setup: https://developers.google.com/maps/flutter-package/config
Restrictions: https://developers.google.com/maps/api-security-best-practices

Routes/Directions and Geocoding APIs are not required or called. Lines connect recorded delivery GPS samples, not calculated roads. Existing address snapshots supply coordinates when available; address text remains visible otherwise. No addresses are silently geocoded.

Missing key: timeline and details work; map construction is skipped. Invalid key, billing, Google Play services, or network configuration can leave blank tiles; the timeline remains usable. Rebuild after changing the key. Other platforms keep the timeline fallback; this implementation configures Android Maps only.

## API and privacy

GET `/api/v1/orders/{orderNumber}/tracking`

GET `/api/v1/seller/orders` (paginated outgoing orders)

GET `/api/v1/seller/orders/{sellerOrderId}/tracking`

Existing Buyer orders/list detail responses include safe shipment summaries and event timelines so tracking buttons appear only for existing shipments. Multiple shops can produce multiple shipments for one Buyer order; the map has a shipment selector.

Buyer API checks Buyer role and order ownership through the authenticated user's orders relation. Seller API checks the existing approved-profile/active-shop eligibility rule and restricts seller orders to stores owned by the token user. Other people's IDs return 404; unauthorized roles return 403; unauthenticated access returns 401.

Responses contain selected customer-facing fields rather than raw shipment/model serialization. Internal notes, event notes, actor IDs, Rider email/phone/address, arbitrary metadata and other shipments are omitted. Event coordinates tagged as Rider/device GPS are excluded from public timelines.

Current Rider GPS is visible **only during `out_for_delivery`**, for the shipment's assigned delivery Rider, from `source=device` records less than two minutes old. The latest actual `out_for_delivery` event establishes the start of the delivery phase, excluding pickup and earlier-attempt GPS. Without that boundary, GPS is withheld. Cancelled/finished parent orders do not expose current GPS.

Before delivery, valid shipment event coordinates are mapped; events without coordinates remain status-only. Provider names are labels, not official courier API integrations. On this branch provider-link support is not installed by migrations; if a restored database already has the provider association, its name can be read safely, otherwise the UI says Not assigned. No schema changes were added.

Completed deliveries can retain up to 500 delivery-phase GPS points ending at the recorded delivery completion time, without exposing a current Rider marker. Failed/returned/exception stages hide exact Rider GPS. Updates use the existing records produced by Logistics/Rider; no Buyer or Seller device GPS is requested or sent.

Polling occurs every 20 seconds while the route is visible and the app is in the foreground, with a manual refresh button. It stops on completion, failed/returned/exception/cancelled states, session changes, errors, or screen disposal. Resume does not automatically refresh a completed order. Timeline dates use the device's local timezone.

## Run and smoke test

Start the existing local database. It was unavailable during implementation (configured MySQL port 3307 refused connections). No destructive database commands, seeding, or migrations were run. Isolated tests use SQLite `:memory:`.

Start Laravel in one terminal:

```powershell
Set-Location C:\xampp\htdocs\Shoppick
& 'C:\php\php-8.5.9-nts-Win32-vs17-x64\php.exe' artisan migrate:status
& 'C:\php\php-8.5.9-nts-Win32-vs17-x64\php.exe' artisan serve --host=0.0.0.0 --port=8000
```

Inspect pending migrations before deciding whether any existing ones need applying. This feature adds none.

After adding the key and starting an Android emulator:

```powershell
Set-Location C:\xampp\htdocs\Shoppick\mobile
flutter pub get
flutter analyze
flutter test
flutter build apk --debug
flutter devices
flutter run -d emulator-5554 --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

If Windows reports symlink permissions during pub get, enable Windows Developer Mode or run in an appropriately elevated terminal. In this workspace ignored local Windows plugin junctions were created to let pub get complete without changing system settings.

Buyer: sign in → My Orders → an order with a shipment → Track Order (or Order details → Track Order on Map). Verify actual timeline, selected shop shipment, available coordinates, recent Rider marker during Out for Delivery, and no live refresh after Delivered. Confirm another Buyer's order cannot be accessed.

Seller: sign in with an approved Seller account → Account → Seller Status → Outgoing Orders / Track Shipments → own outgoing order → Track Shipment. Manage Order on Website retains the existing Seller management flow. Verify that another Seller's ID is rejected.

No emulator was connected and no Maps key was configured during implementation. Real tile rendering, device networking and live Rider movement remain smoke-test steps. Do not insert fake production coordinates to populate the map.
