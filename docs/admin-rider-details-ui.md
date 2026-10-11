# Admin Rider Details UI review

1. **Branch:** `yuan-mobile`, unchanged. Nothing staged, committed or pushed; no merge/reset/branch switch.
2. **Actual detail view:** `resources/views/admin/logistics/rider.blade.php`, reached through the existing `admin.logistics.riders.show` route at `/admin/logistics/riders/{rider}`. No duplicate page or route created.
3. **Reusable partials:** Rider-scoped styles, icons, badges, shipments, activity, pagination and async script under `resources/views/admin/logistics/rider/`. One dynamic template handles every rider; names, emails, IDs, shipment numbers and event descriptions come from records.
4. **Header:** Compact avatar/initial fallback, breadcrumb, dynamic rider name/email and account status. Existing Admin sidebar is unchanged.
5. **Metrics:** Status, Active Deliveries, Completed Deliveries and Total Assigned with small inline SVG icons, large values and supporting labels. Active/completed metrics keep the existing aggregate rules. Total Assigned counts distinct shipments where this user is the delivery or pickup rider, counting a shared assignment once. No invented scores or success rates.
6. **Current Delivery:** Teal-accented card displays existing selected shipment, status, tracking/parcel code, order, shop, actual updated timestamp and existing View Delivery route. Selection remains delivery-rider-only, excludes the same finished statuses and uses the same assigned-date order. Pickup work is not substituted into this selection; a pickup-only rider is directed to assignments below.
7. **Rider Information:** Actual user ID, optional phone, optional profile vehicle type and plate, availability and last recorded activity. Missing profile is safe. No fictional provider, address, license or vehicle information.
8. **Assigned Shipment table:** Clean tinted header, compact rows, consistent status badges, pickup/delivery assignment identities, timestamps and uniform View actions. Existing delivery detail links reused. Dedicated rider partial avoids changes to the shared delivery table used elsewhere.
9. **Filters:** Search tracking number, parcel code, order number and shop name; status dropdown uses only `Shipment::STATUSES`. Status auto-applies; search debounces 400 ms. Native GET fallback via noscript Apply. Clear Filters, loading, retry, 15-second timeout, cancellation and stale-response checks follow existing Admin fetch patterns. Search for `0` is supported.
10. **Shipment pagination:** Server-side 10 rows per page, showing range/total with Previous/Next and current page. Rider context, search, status and activity page are preserved. Filters reset shipment page while retaining activity position. URL updates on successful fetch and Back/Forward restore controls and results.
11. **Timeline:** Structured chronological list, latest first, with status-colored dots, readable badges, timestamp, linked tracking number and actual note/location where present. Color is supplementary to text.
12. **Activity pagination:** Retains the existing 10-event server-side limit and `activity_page` parameter. Loads only the current event page with shipment relations eager-loaded. Activity remains events **performed by this rider** (`actor_id`), not every event from any assigned shipment. Shipment status/search filters do not silently filter actor history.
13. **Empty states:** Clear messages for no current delivery, no assignments, no matching filtered assignments and no activity. Zero metrics render normally. Empty shipment results use a compact message instead of an empty scrolling table.
14. **Future riders:** Tested a new rider with no profile, shipments or events. No null errors, empty tables or invented profile fields; the same template renders all zeros and appropriate messages.
15. **Carlo visual review:** Existing demo seeder's Carlo data rendered in isolated SQLite, inspected on desktop and checked at tablet/mobile widths. Exception current shipment, assignments and history display dynamically. Screenshot `storage/app/rider-detail-previews/rider-carlo-desktop.png`. This is fixture-based visual verification, not a live authenticated account session.
16. **Juan visual review:** Same template tested with existing demo Juan data, including current delivery, distinct pickup/delivery assignments and paginated actor history. Desktop screenshot inspected; tablet/mobile checked. Screenshot `rider-juan-desktop.png` in the same preview directory. Live account untouched.
17. **Maria visual review:** Same template tested with demo Maria data, including In Transit shipment, real profile information and own event history. Desktop screenshot inspected; tablet/mobile checked. Screenshot `rider-maria-desktop.png`. Live account untouched.
18. **New rider review:** Zero-record, no-profile rider checked at all three widths; revised mobile empty-state screenshot visually reviewed. Screenshots `new-rider-desktop.png`, `new-rider-tablet.png`, `new-rider-mobile.png`. Test-only account existed solely in SQLite `:memory:`.
19. **Queries:** Existing summary correlated aggregates preserved. Assignment scope stays rider/pickup rider; filtered paginated records eager-load order/store/rider/pickupRider. Current delivery now eager-loads order/store to avoid presentation queries. Event pagination eager-loads shipments. Tests assert required relations are loaded and report requests perform SELECTs only. No per-row relation queries or analytics tables introduced.
20. **Responsive/accessibility:** Four cards on wide desktop, 2×2 on medium widths and stacked on mobile. Current Delivery and Rider Information share a desktop row; full-width shipment table prevents squeezing. Table scroll stays inside its card. Timeline wraps long descriptions. Visible keyboard focus, labeled controls/regions, semantic headings, table header scopes, real time elements, pagination labels, text statuses and hidden decorative icons. Verified at 1440, 1024 and 390 px with no page overflow.
21. **Validation:** `php artisan test --filter='AdminRiderDetailsTest|AdminLogisticsMonitoringTest|LogisticsWorkflowTest|ReadyToShipLogisticsTest'`: **18 passed, 628 assertions, 1 intentionally skipped opt-in fixture test**. Opt-in fixture generation: **1 passed, 13 assertions**. `node tests/Browser/admin-rider-details.mjs`: **8 groups passed**, including status/clear, debounce/focus/empty results, independent pagination/history, retry/stale-response protection and four rider layouts at three widths. No JavaScript exceptions or mutation requests. `npm run build` and `git diff --check` passed. No brittle pixel assertions.
22. **Exact source files changed:**

    Modified:
    - `app/Http/Controllers/Admin/LogisticsMonitoringController.php` — rider method only, supporting validated filters, pagination, aggregate total, eager loading and JSON fragments.
    - `resources/views/admin/logistics/rider.blade.php`

    Added:
    - `resources/views/admin/logistics/rider/styles.blade.php`
    - `resources/views/admin/logistics/rider/icon.blade.php`
    - `resources/views/admin/logistics/rider/badge.blade.php`
    - `resources/views/admin/logistics/rider/shipments.blade.php`
    - `resources/views/admin/logistics/rider/activity.blade.php`
    - `resources/views/admin/logistics/rider/pagination.blade.php`
    - `resources/views/admin/logistics/rider/script.blade.php`
    - `tests/Feature/AdminRiderDetailsTest.php`
    - `tests/Browser/admin-rider-details.mjs`
    - `docs/admin-rider-details-ui.md`

    Generated Vite assets, fixture HTML/JSON, screenshots and browser artifacts are ignored build/storage files. Existing controller methods, rider list, shared delivery table, Admin layout and unrelated report sources are unchanged in this task.

23. **Business rules:** No assignment, acceptance, status transition, ownership, tracking or event-generation rule changed. Monitoring remains GET-only and Admin-only via the existing route middleware. Other roles are denied for both HTML and JSON; non-rider IDs return 404. Existing logistics/rider workflow regression tests pass. No migration and no live data mutation. The demo seeder ran only inside an isolated test database with fake local storage.
24. **Scope:** Mobile/Flutter apps untouched, along with Sales Reports, seller management, Users, moderation, Inventory, order business logic, registration, Cloudflare and Z.com. Prior unrelated dirty work preserved.
25. **Final Git review:** `git status`, `git diff --stat`, `git diff` and `git diff --check` run. Branch remains `yuan-mobile`; existing dirty/untracked work remains alongside this change. No staging/commit/push. Review snapshots stored in ignored `storage/app/rider-detail-git-status.txt`, `rider-detail-git-stat.txt` and `rider-detail-git-diff.txt`. Untracked new partial/test/doc files are listed explicitly above because the ordinary tracked-file diff does not include them.
