# SHOPPICK Logistics

Independent Android Flutter application for approved Logistics and Rider accounts.
The Laravel application and its existing database remain the source of truth.
The Buyer/Seller app in `../mobile` is unchanged.

## Run locally

Start the existing XAMPP MySQL service on its configured port (3307 in this workspace).
Laravel requires PHP 8.2 or newer; this machine's XAMPP PHP 8.0 is too old.
From the Laravel repository root, use the installed compatible PHP:

```powershell
& C:\php\php-8.5.9-nts-Win32-vs17-x64\php.exe artisan migrate --path=database/migrations/2026_09_28_000001_add_logistics_mobile_provider_links.php
& C:\php\php-8.5.9-nts-Win32-vs17-x64\php.exe artisan serve --host=127.0.0.1 --port=8000
```

The additive migration was applied to the existing local database during implementation.
Running it again skips it. No reset, refresh, wipe, or demo seed is needed.

Open **this folder** in Android Studio, then:

```powershell
flutter pub get
flutter run -d emulator-5554
```

The default API is `http://10.0.2.2:8000/api/v1`. Override it with:

```powershell
flutter run --dart-define=API_BASE_URL=https://your-shoppick-host.example/api/v1
```

Android package: `com.shoppick.shoppick_logistics`. Display name: **SHOPPICK Logistics**.
Internet permission is in the main manifest. Cleartext HTTP is enabled only by the
debug manifest for local development. Backup is disabled for secure token storage.
Release signing and a production HTTPS endpoint must be configured before distribution.

Use existing approved Logistics or Rider accounts. No credentials or demo delivery
counts are embedded in the app. A Logistics account also needs the existing logistics
permissions; Rider accounts need an active RiderProfile. Public rider applications,
license approval, account suspension and administrative approval remain in the web app.

## Daily workflow

1. Logistics opens a ready-for-pickup parcel and optionally sets its provider.
2. Select **Assign rider**, choose an eligible rider, and confirm.
3. Pickup rider accepts, confirms the physical parcel code, and records hub arrival.
4. Logistics receives the parcel, confirms its code, and sorts it to an existing area.
5. Logistics assigns an available, licensed delivery rider covering that area.
6. Delivery rider accepts and starts delivery, uploads a recipient/photo proof, and
   explicitly records COD collection if applicable, before marking delivered.
7. Failed attempts require a reason. Logistics can reschedule to the existing rider
   or return to seller. A retry requires new acceptance and new proof.

Providers start empty intentionally. Authorized Logistics staff can add internal
provider records, associate idle riders with them, then select a provider on eligible
shipments. Existing shipments and riders retain null provider links until assigned.
Logos are displayed when an existing public-storage logo path is present; otherwise
the app uses a neutral icon. There are no external carrier integrations.

## Layout

```text
lib/
  config/              API URL and SHOPPICK theme
  models/              Delivery DTO and status labels
  services/            HTTP, secure session, upload and friendly errors
  screens/auth/        Dedicated Logistics login
  screens/logistics/   Rider and provider directories, selection and details
  screens/rider/       Proof capture/upload
  screens/             Role shell, dashboards, deliveries/details/history,
                       account and notifications
  widgets/             Wordmark, delivery cards, badges and load/error states
test/                   Responsive, auth, logout and network tests
integration_test/       Android secure-storage and real Laravel login smoke test
android/                Separate Android project and package
```

## API contract

All paths below begin with `/api/v1`. Logistics and Rider tokens are issued only by
the dedicated login endpoint. They are SHA-256 hashed in `mobile_api_tokens`, scoped
by the `logistics-mobile` name, and expire 30 days after issuance. Existing marketplace
token behavior is unchanged. Role, active account and registration approval are
checked again on every request; logout revokes the current token.

| Method | Path | Access / purpose |
| --- | --- | --- |
| POST | `/logistics/login` | Email/password login for approved Logistics or Rider |
| GET | `/logistics/profile` | Current mobile user; both allowed roles |
| POST | `/logistics/logout` | Revoke current token; both allowed roles |
| GET | `/{role}/dashboard` | Counts and current parcels |
| GET | `/{role}/deliveries` | Paginated search and backend status filters |
| GET | `/{role}/deliveries/{id}` | Scoped delivery details and valid actions |
| PATCH | `/{role}/deliveries/{id}/status` | Explicit, validated workflow action |
| GET | `/{role}/notifications` | Current user's paginated notifications |
| POST | `/{role}/notifications/read-all` | Current user's read state only |
| PATCH | `/logistics/deliveries/{id}/assign-rider` | Manual confirmed assignment |
| PATCH | `/logistics/deliveries/{id}/provider` | Set provider before assignment |
| GET | `/logistics/riders` | Rider directory or eligible assignment choices |
| PATCH | `/logistics/riders/{id}/provider` | Associate an idle rider with a provider |
| GET / POST | `/logistics/providers` | List metrics / create internal provider |
| GET | `/logistics/delivery-areas` | Existing active sorting areas |
| POST | `/rider/deliveries/{id}/proof` | Private photo and recipient upload |

`{role}` is `logistics` or `rider`. Endpoints reject the opposite role. The shared
profile/logout endpoints intentionally accept both. Logistics permissions are checked
in addition to role membership. Neither role gains Admin permissions.

Delivery filters include `q`, `status`, `history=0|1`, `rider_id`, `provider_id`, `page`.
Search covers tracking number, parcel code, order number, buyer name, pickup/delivery
rider, shop and provider. Rider queries always retain the authenticated user's
pickup/delivery ownership predicate, including with filters. Ownership is rechecked
under a shipment lock for mutations. Riders who previously handled a pickup retain
read access to that shipment as in the existing web architecture; delivery actions
belong exclusively to its current delivery rider.

Rider actions: `accept_pickup`, `confirm_pickup`, `arrive_sorting`, `accept_delivery`,
`start_delivery`, `failed`, `collect_cod`, `delivered`. Logistics actions: `receive`,
`scan`, `sort`, `reschedule`, `return`. Assignment and proof use separate endpoints.
The server returns currently valid actions. It rejects stale or invalid requests,
including duplicate completion and jumps from pickup to delivered.

Assignment retains the existing one-active-assignment rule, account/availability
checks, verified/unexpired license rules and delivery-area coverage. Provider-scoped
shipments also require matching rider providers. The rider profile is locked while
eligibility is checked to prevent competing assignments.

## Verification commands

```powershell
dart format lib test integration_test
flutter analyze
flutter test
flutter test integration_test/login_smoke_test.dart -d emulator-5554
flutter build apk --debug
```

From the Laravel root:

```powershell
& C:\php\php-8.5.9-nts-Win32-vs17-x64\php.exe artisan test --compact --filter='LogisticsMobileApiTest|MobileMarketplaceTest|ReadyToShipLogisticsTest|AdminLogisticsMonitoringTest|LogisticsWorkflowTest'
```

Laravel tests use the repository's isolated SQLite `:memory:` test connection and
never reset the real MySQL database. The emulator test uses invalid credentials to
verify the real Laravel connection without changing orders or creating test users.
Widget tests use test-only HTTP fixtures, never production fallback data.

## Boundaries

- No push notification service, offline synchronization, automatic dispatch,
  signature capture, background location or optimized GPS route planning.
- Maps and calls open an external app only after a user tap. The delivery list is
  ordered by assignment time; it does not claim to be an optimized route.
- Existing legacy shipment statuses remain visible/searchable. Actions target the
  current ParcelWorkflowService flow; legacy ShipmentService transitions remain
  managed through existing web operations.
- Rescheduling uses the assigned rider. Reassigning a failed parcel to a different
  rider is not exposed because the existing workflow does not support it.
- Return records the existing shipment return state. It does not initiate refunds.
- COD uses the existing order-level Payment and CodCollectionService, including its
  order-level amount for multi-shop orders. No independent settlement or remittance
  system is added; remittance confirmation remains with existing web operations.
- Proof photos are resized to at most 1600×1600 at quality 80 by the Android picker.
  Laravel validates actual image type, 5 MB maximum and 6000×6000 dimensions, stores
  privately on the local disk, and persists only the path with recipient/note/time.
  Existing web review/download authorization remains in force. No GPS is fabricated.
  If Android kills the app during camera selection, reopen proof and select the photo
  again; there is no persistent offline upload queue.
- Provider deletion, status administration, logo upload and rider activation/license
  management are not included in this mobile operations UI.

Implementation references: [Flutter image_picker](https://pub.dev/packages/image_picker)
and [flutter_secure_storage](https://pub.dev/packages/flutter_secure_storage).
See [IMPLEMENTATION_REPORT.md](IMPLEMENTATION_REPORT.md) for the requested report and
recorded verification results.
