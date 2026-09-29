# SHOPPICK Logistics implementation report

1. **Existing architecture:** Laravel already models deliveries as `Shipment` records.
   `ParcelWorkflowService` owns current pickup/sorting/delivery transitions;
   `ShipmentService` owns creation, tracking numbers and legacy transitions.
   `CodCollectionService`, `OrderProgressService`, `NotificationService` and private
   `ProofOfDelivery` storage already exist. Admin Logistics Overview/Deliveries/Riders
   are monitoring screens. No separate delivery or identity system was created.
2. **Tables reused:** `users`, `roles`, `role_user`, permissions/role permissions,
   `mobile_api_tokens`, `rider_profiles`, `shipments`, `shipment_events`, `orders`,
   `seller_orders`, `order_items`, `stores`, `payments`, `proof_of_deliveries`,
   `delivery_areas`, `delivery_area_rider`, `notifications_custom`; existing shipment
   address snapshots originate from the order's shipping address.
3. **Migration:** `2026_09_28_000001_add_logistics_mobile_provider_links.php` creates
   `logistics_providers` and nullable foreign keys on shipments and rider profiles.
   Existing source and live MySQL schema confirmed no provider table. This exact
   migration was previewed and applied locally. Existing rows were not removed,
   renamed or seeded. No destructive database command was run.
4. **New app:** `logistics_mobile/` contains a standalone Flutter Android project,
   unique application ID, modular Dart files, tests, manifests, icon and documentation.
5. **Logistics screens:** Dedicated login, dashboard with real counts, attention list,
   deliveries/search/filters/details, pickup/delivery assignment, sorting actions,
   rider tabs/details/deliveries, provider list/details/creation/linking, notifications
   and account/logout. Navigation: Dashboard, Deliveries, Riders, Providers, Account.
6. **Rider screens:** Separate home summary, assigned deliveries, detail/actions,
   photo/recipient proof form, delivery history, notifications and account/logout.
   Navigation: Home, Deliveries, History, Notifications, Account.
7. **APIs:** Added dedicated `/api/v1/logistics` and `/api/v1/rider` routes documented
   in README. Reused existing domain services, models, relationships, payment and
   notification architecture. Existing Buyer/Seller API routes are unchanged.
8. **Authorization:** Dedicated scoped tokens, 30-day expiry, active/approved account
   checks, role-specific route guards and existing Logistics permissions. Rider
   list/detail/mutations retain ownership scope. Delivery updates and assignment
   checks run inside transactions with locks. Buyer/Seller/Admin-only users are denied.
9. **Workflow:** `ready_for_pickup → pickup_assigned → pickup_accepted → picked_up →
   at_sorting_center → sorted → assigned_to_rider → out_for_delivery → delivered`.
   Hub arrival, parcel-code confirmation and delivery acceptance retain their existing
   timestamp checks. Existing status strings are never renamed. Legacy statuses remain
   visible with unsupported mobile actions omitted.
10. **Assignment:** Manager selects and confirms an eligible rider. Availability,
    account status, license, one-active-assignment, provider and area rules are
    enforced server-side. No silent dispatch. A small shared-service fix excludes
    the failed parcel itself when checking conflicts for retry and locks rider
    eligibility; retry resets acceptance and requires fresh proof.
11. **Providers:** Internal database records with real relationship counts, optional
    stored logos and neutral fallbacks. None are fabricated or seeded. Multiple
    records are supported; no carrier website/API is called.
12. **Proof:** Android photo selection/camera with size/quality reduction; Laravel
    validates and privately stores images. Recipient, notes and submission time reuse
    existing proof records and web review. Proof is required before completion; a
    rejected proof cannot complete a delivery. No signature or invented location.
13. **Failures:** Existing reason values plus required Other details; the server
    records `delivery_failed`, not Delivered, and notifies through existing services.
    Manager attention filters, same-rider reschedule and existing return workflow are
    exposed. New acceptance and proof are required on retries. COD is not fabricated.
14. **Flutter verification:** See final verification record below.
15. **Laravel verification:** See final verification record below. Tests run only on
    SQLite in memory. No real orders were mutated for workflow testing.
16. **Unsupported features:** No official carrier integration, push service, offline
    synchronization, signatures/GPS routing, failed-parcel reassignment to another
    rider, mobile license/application approvals, independent refunds or COD settlement.
    Web administration and legacy workflows remain available. Production signing is
    not configured. Camera/gallery hardware and real-account end-to-end delivery
    operations are not claimed as manually tested on the emulator.
17. **Separation:** `mobile/` was neither edited nor merged into this app. Both clients
    use the original Laravel backend/database. Existing marketplace tests are included
    in regression checks.
18. **Version control:** Work remains local. No commit, push or merge was performed.

## Final verification record

- `dart format lib test integration_test`: passed; 16 Dart files, zero remaining changes.
- `flutter analyze`: **No issues found**.
- `flutter test`: **9 passed**, including login/logout, secure-token expiry,
  safe server errors, connection retry, and 320/360/390 logical-pixel layouts at
  1.0× and 1.8× text. Layout coverage includes login, both role shells, dashboard,
  delivery detail/list/history, riders/providers, account, notifications and proof form.
- API 36 emulator integration test: **1 passed**. Verified Android secure-storage
  read/write/delete, real app startup, and actual Laravel login validation over
  `10.0.2.2:8000`. It deliberately uses invalid credentials, not real user orders.
- Laravel regression selection: **32 passed, 664 assertions** across
  LogisticsMobileApiTest, MobileMarketplaceTest, ReadyToShipLogisticsTest,
  AdminLogisticsMonitoringTest and LogisticsWorkflowTest.
- Debug APK built successfully. The normal app was rebuilt, installed and launched
  after the test harness using `flutter run -d emulator-5554 --no-resident`.
  APK: `build/app/outputs/flutter-apk/app-debug.apk` (local, ignored build artifact).
- Login screenshot: [API 36 screenshot](documentation/api36-login.png).
- `git diff --check`: passed. `git diff -- mobile`: empty.

The final installed APK targets the emulator's x86_64 architecture. Use
`flutter build apk --debug` to regenerate a debug APK for the standard Android ABI set.

## Local services

The existing XAMPP MySQL service was started on its configured port 3307. Laravel was
started with PHP 8.5 on `127.0.0.1:8000` for emulator connectivity. The installed
`Medium_Phone_API_36.1` emulator was started headlessly and reports Android SDK 36.
No second database was created. Test fixtures are isolated in memory.
