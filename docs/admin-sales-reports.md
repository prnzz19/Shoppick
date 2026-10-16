# Admin Seller Management — Sales Reports

1. **Branch:** `yuan-mobile`; unchanged. No staging, commit, push, merge, reset, or branch switch.
2. **Sidebar:** `resources/views/layouts/admin.blade.php`. Sales Reports follows Sellers under Seller Management on desktop and mobile. Uses the existing inline SVG icon system. Both overview and individual report routes highlight Sales Reports; Shops is excluded on individual reports.
3. **Routes:** `GET /admin/sales-reports`, named `admin.sales-reports.index`; GET-only `/filter`, `/pdf`, `/print`, `/csv` endpoints use the corresponding names. Existing `/admin/shops/{shop}/sales-report` routes remain.
4. **Controllers/services:** New `SalesReportsController` and `MarketplaceSalesReportService` extend the existing `ShopSalesReportService` source/date/aggregate rules. Shared `SalesReportExportService` renders PDF and protects CSV text for both modules.
5. **Views:** `resources/views/admin/sales-reports/index.blade.php`, with summary, table, pagination, period, exports, trend and standalone document partials.
6. **Metrics:** Filtered net sales, completed seller-order count, completed item units, and weighted average order value (total sales / total orders). Cards cover every matching shop, independent of pagination. Zero denominators produce zero.
7. **Shop filter:** All Shops or a selected shop; optional stored shop status (active/suspended), shop/seller/email search and sorting. Nondeleted historical shops remain reportable. Search does not become an order-search condition.
8. **Date filter:** Existing All Time, Today, This Week, Last Week, Last 7 Days, Last 30 Days, This Month, Last Month and inclusive Custom Range. Weeks are Monday–Sunday; calendar month presets include the full month. Dates use the existing application timezone, currently UTC. Financial timestamps use `completed_at`.
9. **Auto-filtering:** `resources/js/admin-sales-reports.js` follows the existing Sellers fetch/abort/history architecture. Dropdowns update immediately; search debounces 400 ms. Includes incomplete/reversed custom-date handling, loading state, retry, stale-response protection, pagination, Clear Filters, pushState and popstate. Controls remain in place to preserve typing/focus. Export links update with successful results and are disabled while fetching. No new framework. Native GET form and a noscript Apply button remain available.
10. **Performance filter:** Improving, Stable, Declining, Mixed Performance, New Activity, No Sales, plus No comparable period for All Time. SQL filters apply before totals and pagination.
11. **Comparison:** Preserves existing exact comparison across sales, orders, units and average order value, rather than introducing the suggested 5% threshold. Shared SQL classification rules serve the individual and overview reports. Previous period is the preceding equal-length range, except calendar months compare with the previous calendar month. All Time has no comparable baseline. Zero-baseline growth displays New Activity instead of an infinite percentage. Sales Trend shows the sales percentage; Performance considers all four metrics.
12. **View Report:** Each row links to that shop's existing detailed report, preserving range/from/to. Overview shop search, sorting and performance constraints are not passed into order-search controls. The individual report's Back link now leads to Sales Reports with the selected dates.
13. **PDF:** Shared existing Dompdf configuration produces A4 PDFs with page numbers, filtered rows, totals, report period, generated timestamp and comparisons. Includes all matching shops, not just the displayed page. No extra dependency added in this change.
14. **Print:** Standalone A4 document contains the same overview rows/totals without sidebar, filters, buttons or pagination. Requests printing on load.
15. **CSV:** UTF-8 with BOM, one row per matching shop and a totals row, period/generated metadata, previous sales and percentage changes where comparable. Currency is PHP. Formula-like names are prefixed to prevent spreadsheet execution. Shop-specific exports contain only the selected shop. Existing individual order-level CSV remains available.
16. **Authorization:** All five overview endpoints require `auth`, `role:admin`, and existing `permission:view_shops`, matching individual reports. Buyers, sellers, logistics, riders and admins without permission are denied. Unauthenticated JSON receives 401.
17. **Queries:** Grouped completed seller-order subqueries join grouped item units once, preventing item duplication and multi-vendor parent-total leakage. Joins load seller names without per-shop queries. Overview data uses exactly three SELECTs (summary, pagination count, rows); tests confirm this stays constant as shops increase. Export rows use a cursor. No new sales table.
18. **Tests:** `tests/Feature/AdminSalesReportsTest.php` covers sidebar, authorization, metrics, dates, shop/search/status/sort, every classification, detail parity, trends, all-page exports, CSV protection, validation, pagination, read-only queries and constant query count. Full-page and async fragments are compared. Existing shop export, Sellers, Inventory and archive suites run together. `tests/Browser/admin-sales-reports.mjs` covers browser behavior and downloaded files.
19. **Results:** Combined command `php artisan test --filter='AdminSalesReportsTest|ShopSalesReportExportTest|AdminInventoryGroupingTest|InventoryAuthorizationTest|AdminSellerPerformanceTest|SellerManagementArchiveTest'`: **44 passed, 1,116 assertions, 2 intentionally skipped opt-in fixture tests**. Opt-in overview suite: **11 passed, 317 assertions**. `npm run build` passed; `git diff --check` passed. Browser script: **9 groups passed**, no JavaScript exceptions, no mutation requests.
20. **Browser verification:** Isolated headless Edge used real Blade/JSON/PDF/CSV generated by Laravel against SQLite `:memory:`, plus compiled project assets. Verified auto-filtering, 400 ms debounce, focus, custom validation, history, pagination, retry, stale-response cancellation, filtered downloads, desktop/mobile active navigation and print invocation. Desktop and print screenshots visually reviewed. Fixtures/previews live under ignored `storage/app/sales-overview-fixtures/`. This is not a live authenticated production-session verification; no credentials/accounts were changed.
21. **Migration:** None required or added. Tests migrate only their isolated in-memory database. Existing uncommitted migration files belong to earlier work and were preserved.
22. **Read-only:** Report endpoints are GET-only; no order, payment, inventory, revenue or account mutation. Query-log tests verify report data performs SELECTs only. Revenue remains completed seller-order net totals; pending/cancelled orders are excluded. Parent order amounts and item gross amounts are never substituted for net sales.
23. **Scope:** Existing Sellers quick performance and async behavior remain intact; Inventory's existing small report shortcut remains. No changes in this task to Users, Categories, registration, moderation, logistics/riders, mobile/Flutter apps, Cloudflare or Z.com. Their earlier dirty changes are preserved.
24. **Exact source files changed in this task:**

    Modified:
    - `app/Http/Controllers/Admin/ShopSalesReportController.php`
    - `app/Services/ShopSalesReportService.php`
    - `resources/js/app.js` (one report import)
    - `resources/views/layouts/admin.blade.php`
    - `resources/views/admin/shops/sales-report/index.blade.php` (Back link)
    - `routes/admin.php` (five overview GET routes)

    Added:
    - `app/Http/Controllers/Admin/SalesReportsController.php`
    - `app/Services/MarketplaceSalesReportService.php`
    - `app/Services/SalesReportExportService.php`
    - `resources/js/admin-sales-reports.js`
    - `resources/views/admin/sales-reports/index.blade.php`
    - `resources/views/admin/sales-reports/summary.blade.php`
    - `resources/views/admin/sales-reports/table.blade.php`
    - `resources/views/admin/sales-reports/pagination.blade.php`
    - `resources/views/admin/sales-reports/period.blade.php`
    - `resources/views/admin/sales-reports/exports.blade.php`
    - `resources/views/admin/sales-reports/trend.blade.php`
    - `resources/views/admin/sales-reports/document.blade.php`
    - `tests/Feature/AdminSalesReportsTest.php`
    - `tests/Browser/admin-sales-reports.mjs`
    - `docs/admin-sales-reports.md`

    Generated Vite assets/manifest, browser profiles, download artifacts and previews are ignored build/storage files, not staged source changes.

25. **Final Git review:** `git status`, `git diff --stat`, `git diff`, and `git diff --check` run. Working tree remains dirty with prior unrelated work and new reporting files; nothing staged, committed or pushed. Full review snapshots are saved in ignored `storage/app/sales-overview-git-status.txt`, `sales-overview-git-stat.txt` and `sales-overview-git-diff.txt`. Individual shop report files remain untracked from their earlier implementation; this task's edits to them are included in the exact list above.
