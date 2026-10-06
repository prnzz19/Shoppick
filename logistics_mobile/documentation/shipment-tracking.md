# Shipment tracking setup and review

This Android feature belongs to `yuan-logistics-mobile`. Buyer/Seller `mobile/` is untouched.

## Google Maps

In `logistics_mobile/android/local.properties`, preserve the existing SDK settings and add:

```properties
MAPS_API_KEY=YOUR_OWN_RESTRICTED_KEY
```

This file is ignored. Gradle supplies the key through a manifest placeholder; the application only exposes a configuration boolean to Dart. Never add a real key to tracked files. Android keys can be extracted from an installed application, so restrict the key in Google Cloud to Android application `com.shoppick.shoppick_logistics` and the signing certificate SHA-1. Restrict API access to **Maps SDK for Android**, enable that SDK, and attach an active billing account. For the debug certificate fingerprint:

```powershell
Set-Location C:\xampp\htdocs\Shoppick\logistics_mobile\android
.\gradlew.bat signingReport
```

Official setup: https://developers.google.com/maps/flutter-package/config
Key restrictions: https://developers.google.com/maps/api-security-best-practices

Routes/Directions and Geocoding APIs are not used or required. No addresses are automatically geocoded. The polyline connects actual recorded GPS points and does not represent a calculated road route. Map tiles require a valid key, billing, network connectivity, Google Play services, and matching restrictions. Missing keys preserve the timeline. An invalid key may leave blank map tiles; use the timeline and verify Cloud setup.

## Run

Start the existing local database and Laravel backend using the project's compatible PHP executable:

```powershell
Set-Location C:\xampp\htdocs\Shoppick
& 'C:\php\php-8.5.9-nts-Win32-vs17-x64\php.exe' artisan migrate:status
& 'C:\php\php-8.5.9-nts-Win32-vs17-x64\php.exe' artisan serve --host=0.0.0.0 --port=8000
```

No migration was added. Existing `shipment_tracking_points` and its `source` column must be installed in the database. If those existing migrations are pending, inspect all pending migrations before running normal `artisan migrate`.

In another terminal, after starting an Android emulator:

```powershell
Set-Location C:\xampp\htdocs\Shoppick\logistics_mobile
flutter pub get
flutter analyze
flutter test
flutter build apk --debug
flutter devices
flutter run -d emulator-5554 --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

After changing the key, rebuild and restart the app.

## Behavior

Logistics: Deliveries → select shipment → Track shipment. The map shows valid pickup/destination address snapshot coordinates, event metadata coordinates, recent Rider position, and recorded GPS path. Events without coordinates remain explicitly status-only. No courier's external live API is integrated. Provider names are labels.

Rider: open an assigned delivery → Track shipment → Share location for this delivery. The Rider must grant runtime location permission and enable GPS. Sharing is allowed during accepted pickup / pickup transit before sorting-center arrival, or out-for-delivery for the delivery Rider. The backend enforces the correct Rider for each phase, not just membership in the shipment.

The location stream uses a 25-meter distance filter, uploads at most once every 15 seconds, and does not run a foreground service or request background permission. Sharing stops when the screen closes, app leaves the foreground, delivery actions open, authorization/session fails, or polling reports the phase inactive. Returning requires explicitly enabling sharing again. A request already sent may finish after leaving the screen; shipment-row locking ensures terminal transitions reject new updates.

Both roles refresh tracking every 20 seconds while visible and can refresh manually. Location timestamps are shown separately from shipment timestamps. Recent Rider location is only shown for an active phase and a device point less than two minutes old. Historical points remain visible as recorded history. Responses include the latest 500 GPS points, in chronological order.

Delivery status / proof actions reuse the existing delivery detail screen, preserving acceptance, parcel scan, proof and COD rules. No fake stages, coordinates, events, demo records or geocoding are inserted.

## API and authorization

GET `/api/v1/logistics/deliveries/{id}/tracking`

GET `/api/v1/rider/deliveries/{id}/tracking`

POST `/api/v1/rider/deliveries/{id}/location`

Location body: `latitude`, `longitude`, optional `accuracy`, `recorded_at` (ISO 8601). Coordinates must be within geographic bounds; timestamps must be within two minutes in the past or 30 seconds in the future, and newer than the Rider's last device update. Endpoint throttling allows six attempts per minute.

Existing Logistics token middleware validates active accounts, token scope, expiry and role. Logistics read access requires `view_shipments`, using the existing project-wide Logistics management scope. Rider read access uses existing pickup/delivery assignment scope. GPS writes additionally require the current active phase's Rider, checked under a shipment row lock. Buyers and unauthenticated requests are rejected.

## Review notes

The earlier `logistics_mobile/` entry was removed from `.git/info/exclude` so new Logistics files are visible for review on this branch. Reapply that local rule if returning to `yuan-mobile` with leftover untracked Logistics files. No staging, commit or push is performed by this implementation.

Live local database inspection was unavailable because configured MySQL port 3307 refused connections. Tests use an isolated in-memory SQLite database. An Android emulator was unavailable and no Maps key was configured, so real map rendering and device-to-backend GPS smoke tests remain manual validation steps.
