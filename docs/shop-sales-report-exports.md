# Shop Sales Report exports

Branch: `yuan-mobile`. A dedicated per-shop Sales Report did not exist in this checkout, so a compact report and shared read-only service were added alongside its exports. Inventory shop cards expose a Sales Report link to admins with `view_shops`; inventory editing rules were not changed.

1. **PDF tool:** project-local `dompdf/dompdf` v3.1.6, with its four supporting libraries; no Laravel wrapper, spreadsheet package, browser renderer or system-wide software installed. Composer scripts were not run. Official documentation: https://github.com/dompdf/dompdf. The working PowerShell transport cached official metadata/archives after PHP Composer downloads timed out; temporary repository configuration was removed. The final lockfile retains normal public package URLs; existing package versions were not updated.
2. **PDF route:** `GET /admin/shops/{shop}/sales-report/pdf`, `admin.shops.sales-report.pdf`.
3. **Print:** `GET /admin/shops/{shop}/sales-report/print`, `admin.shops.sales-report.print`, standalone `admin/shops/sales-report/document.blade.php`. Requests browser printing after load; no layout sidebar, navigation, filter controls, export buttons or pagination.
4. **CSV:** `GET /admin/shops/{shop}/sales-report/csv`, `admin.shops.sales-report.csv`, streamed download in chunks of 200 vendor orders.
5. **Authorization:** all four web/export routes use `web`, `auth`, `role:admin`, `permission:view_shops`, matching existing Shop viewing authorization. Signed-out JSON requests return 401; non-admins and admins without permission return 403.
6. **Filename:** `SHOPPICK_{sanitized-shop}_Sales_Report_YYYY-MM-DD_to_YYYY-MM-DD.pdf/csv`, or `..._All-Time.pdf/csv`. ASCII slug, length bound and ID fallback avoid path characters.
7. **PDF contents:** SHOPPICK title, shop/seller identity, period, active filters, generated timestamp, sales/completed orders/units/average summary, previous period and differences/percentages, performance status, sales by date/month table, top ten products, status breakdown, latest twenty matching orders and current inventory snapshot. No chart screenshot needed. Page numbers included.
8. **Print contents:** same shared report content as PDF/web, with the same metrics, trends and detail limits. Dedicated standalone HTML is readable on screen and prints on A4.
9. **CSV structure:** one row per matching seller order, with Shop, Seller, Report Start/End, vendor Order Number, placement/completion dates, Buyer, Products/Quantities in one cell, Units, Status, Seller Order Total (PHP), Recognized Completed Sales (PHP), Currency. Totals are not repeated per product. Noncompleted recognized-sales values are zero. UTF-8 BOM supports Excel/Filipino names; formula-like text is prefixed to keep spreadsheet names/products as text. No XLSX dependency added.
10. **Filters:** web, PDF, print and CSV all use `ShopSalesReportService::filters()` and `orders()`. Presets: All Time, Today, Last 7 Days, Last 30 Days, This Week, Last Week, This Month, Last Month, Custom Range. Custom dates are inclusive and validated, with status and order/buyer search filters preserved in every export link. This newly added report uses ordinary GET Apply filtering; links are regenerated with the currently viewed report, rather than having an independent asynchronous state. The existing asynchronous Sellers list was untouched.
11. **Source/trends:** existing completed `seller_orders.seller_total` and `completed_at` remain the financial source; units use the same vendor order's items. Parent buyer totals are never used. Completed detail/CSV rows use completion dates; noncompleted rows use placement dates. This rule keeps CSV recognized totals equal to web/PDF completed sales even when an order was placed in an earlier period. Failed/pending and cancelled orders are visible as operational rows, not recognized sales. As in existing seller analytics, completed totals are not separately adjusted for parent payment refunds. Top-product item sales use stored gross item totals and are explicitly distinguished from net seller revenue.

    Previous comparisons use the preceding equal-length calendar-day period, or the previous calendar month for month presets. Week means Monday–Sunday. Four metrics are compared: sales, orders, units and average value. States handle Stable, Improving, Declining, New Activity, No Sales, Mixed Performance. Zero baselines do not divide by zero; All Time has no comparable period. Inventory is clearly current and not date-filtered, using existing product-stock thresholds.
12. **Layout/time:** A4 portrait, simple bordered tables, repeating table headings and readable DejaVu Sans for PDF peso/Unicode text. Local title instead of image avoids broken external assets. Remote resources, PHP and JavaScript execution disabled in the PDF renderer. Generated time uses the application timezone (currently UTC), without a hardcoded display timezone.
13. **Tests:** new ShopSalesReportExportTest covers valid PDFs/download headers, web/PDF/print data parity, selected shop/date isolation, units/orders/trends, custom/search/status filter links, All Time filename, CSV totals/encoding/formula escaping, print isolation, endpoint authorization, invalid values, inventory snapshot, all performance states and no write queries. New dependency-free browser test `tests/Browser/shop-sales-report.mjs` verifies real download interactions and printing against SQLite-exported fixtures. Regression run: **34 passed, 814 assertions**, plus one intentionally skipped pre-existing optional Sellers fixture export. Build, PHP syntax checks, Composer validation, route middleware review and Git whitespace checks passed.
14. **Browser/manual verification:** isolated headless Edge downloaded PDF and CSV through Export Report using This Week fixtures; downloaded bytes match real Laravel-generated export responses. Print Report invoked printing, displayed the expected period/₱150.00/Improving trend, and excluded all Admin controls. A4 browser print PDF and screenshot saved in `storage/app/shop-report-fixtures/`. Print screenshot visually inspected. Fixture data came only from SQLite :memory:. Authenticated live-database export flow remains unverified because no admin session was available; fixture checks do not claim live-data verification.
15. **Read-only:** report/export logic performs no business/database writes. Tests confirmed no INSERT/UPDATE/DELETE/schema queries while requesting all formats. No migration created or run, no Seller/Order/Payment/Inventory records changed in the real database. Test fixture setup mutates only SQLite :memory:. Temporary PDF/font files and browser fixture artifacts are filesystem output, not report database records.

Exact files for this addition:

- `app/Http/Controllers/Admin/ShopSalesReportController.php` (new)
- `app/Services/ShopSalesReportService.php` (new)
- `resources/views/admin/shops/sales-report/index.blade.php` (new)
- `resources/views/admin/shops/sales-report/content.blade.php` (new)
- `resources/views/admin/shops/sales-report/document.blade.php` (new)
- `resources/views/admin/shops/sales-report/styles.blade.php` (new)
- `resources/views/admin/inventory/index.blade.php` (only permission-gated report link and one permission check added)
- `routes/admin.php` (four report routes added)
- `composer.json`, `composer.lock` (Dompdf and four new supporting packages)
- `tests/Feature/ShopSalesReportExportTest.php` (new)
- `tests/Browser/shop-sales-report.mjs` (new)
- `docs/shop-sales-report-exports.md` (this report)

Ignored `public/build` outputs rebuilt. Ignored sample exports/screenshots/downloads/browser profile remain under storage/app. Pre-existing unrelated edits, mobile apps, seller/shop archive logic and inventory editing rules preserved. Final status/stat/diff checked; working tree remains dirty with prior work and these additions. Nothing staged, committed, pushed, reset or merged.

Browser fixture reproduction (PowerShell; start an isolated debugging browser on port 9226 first):

```powershell
npm run build
$env:SHOPPICK_REPORT_BROWSER_FIXTURES='1'
php artisan test --filter='ShopSalesReportExportTest::test_web_print_and_pdf_use_identical_filtered_data_and_a_real_pdf'
Remove-Item Env:SHOPPICK_REPORT_BROWSER_FIXTURES
node tests/Browser/shop-sales-report.mjs
```
