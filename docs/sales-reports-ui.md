# Sales Reports UI review

1. **Sales Reports overview:** Stronger navy heading, subtle breadcrumb, compact teal export action, organized filters, icon-led metrics and a cleaner shop table.
2. **Individual shop report:** Dashboard web view with a compact shop identity card and separate Performance, Sales Over Time, Top Products, Order Status Breakdown, Recent Orders and Current Inventory Snapshot cards. The web page no longer embeds the printable document.
3. **Shared presentation:** Report-scoped styles and Blade partials for icons, metric cards, performance/status badges, change indicators, trends and export menus. Reuses the existing inline SVG approach and SHOPPICK colors; no new dependency or framework.
4. **Filters:** Overview uses four aligned dropdowns followed by Sort and a wide Search field. Clear Filters appears only when filtered and sits in the filter heading. Existing automatic fetch, 400 ms search debounce, retry, loading, stale-response cancellation and URL/history behavior are unchanged. Shop report retains its existing Apply behavior, with aligned date/status/search controls and custom-date handling.
5. **Metrics:** Four compact cards with subtle icon tiles, strong values, supporting text and tabular numbers. Shop metrics show the existing changes when a previous period exists. Overview does not invent aggregate trend figures that are not supplied by the service. Zero sales has explicit supporting text.
6. **Badges:** Muted teal for Improving/New Activity/Completed/Active; blue for Stable and progressing order states; rose for Declining/Cancelled/Failed/Suspended; purple for Mixed Performance; gray for other/zero-comparison states. Text labels convey meaning independently of color. The overview strip clearly labels its classification counts **On this page**, while the existing summary totals still cover every matching shop.
7. **Trends:** Direction arrows and signed existing percentages, with a short comparison caption. All-time rows use a subtle dash/No comparison display instead of repeating the long sentence. Underlying classification remains unchanged.
8. **Sales chart:** No chart library is installed, so the shop web view uses an accessible numeric table with teal mini progress bars. Bars are decorative, scaled only for presentation; sales and order values remain the existing data. No dependency added.
9. **Top Products:** Product icon fallback, stronger product names, right-aligned units/orders/item-sales and quiet top-three rank tags. Existing aggregate product rows do not include thumbnail URLs; no extra product queries were introduced. Gross item-sales explanation remains visible.
10. **Order Status:** Compact status badges, counts and proportional progress bars. Completion-versus-placement date explanation retained. Bars do not replace the readable counts.
11. **Recent Orders:** Distinct buyer/date/units/status/total columns with status pills. View links use the existing `admin.orders.show` route and parent order ID; no new order detail or authorization route.
12. **Inventory:** Dedicated current snapshot card with Products, Low Stock and Out of Stock, explicitly labeled not date-filtered. Existing inventory numbers and rules are untouched.
13. **Exports:** Shared teal button and icon menu for PDF, Print and CSV. Native details/summary keeps keyboard operation. Routes and query parameters remain intact. Standalone print/PDF document templates, their styles, data and export contents were not edited.
14. **Responsive:** Four metric cards on wide desktop, two columns at tablet widths and one column on mobile. Filters wrap into a single column; tables scroll inside their cards as necessary. Sidebar remains unchanged. Verified at 1440, 1024 and 390 px widths.
15. **Accessibility:** Visible keyboard focus, readable field labels, semantic headings/sections, column-header scopes, pagination current/disabled states, labeled order actions and decorative SVGs hidden from assistive technology. Numeric text remains available alongside progress bars.
16. **Business logic:** No controllers, services, models, routes, permissions, sales formulas, order inclusion rules, previous-period calculations, performance thresholds or database schema changed in this task. No live database mutations or migrations.
17. **Export logic:** PDF/print/CSV generation and contents preserved. Real file downloads and print invocation pass the existing browser checks. Changes are restricted to the web export control's presentation.
18. **Tests:** Existing `AdminSalesReportsTest` and `ShopSalesReportExportTest`: **18 passed, 425 assertions, 1 intentionally skipped opt-in fixture test**. Fixture generation checks: **2 passed, 44 assertions**. No backend tests edited. `npm run build` passed. Existing overview browser suite: **9 groups passed**; shop export browser suite: **2 groups passed**. Browser harnesses were corrected to ignore Edge's Downloads UI tab when reconnecting and to reset screen media.
19. **Visual review:** Isolated headless Edge rendered Laravel-generated Blade/JSON fixtures from SQLite `:memory:` and real compiled assets. Overview, all-time shop report, weekly shop report and zero-sales shop report checked at desktop/tablet/mobile, with no page overflow. Screenshots reviewed for spacing, hierarchy, cards, numeric alignment and readability. This does not claim a live authenticated admin-session check; no accounts or real records were changed. Previews: `storage/app/report-ui-previews/overview-desktop.png`, `shop-all-desktop.png`, `shop-week-desktop.png`, `shop-zero-desktop.png` and corresponding `-tablet`/`-mobile` versions. Temporary fixture/gallery tools remain in ignored storage.
20. **Exact source files changed in this task:**

    Modified:
    - `resources/views/admin/sales-reports/index.blade.php`
    - `resources/views/admin/sales-reports/exports.blade.php`
    - `resources/views/admin/sales-reports/summary.blade.php`
    - `resources/views/admin/sales-reports/period.blade.php`
    - `resources/views/admin/sales-reports/table.blade.php`
    - `resources/views/admin/sales-reports/pagination.blade.php`
    - `resources/views/admin/shops/sales-report/index.blade.php`
    - `tests/Browser/admin-sales-reports.mjs`
    - `tests/Browser/shop-sales-report.mjs`

    Added:
    - `resources/views/admin/sales-reports/ui/styles.blade.php`
    - `resources/views/admin/sales-reports/ui/icon.blade.php`
    - `resources/views/admin/sales-reports/ui/badge.blade.php`
    - `resources/views/admin/sales-reports/ui/metrics.blade.php`
    - `resources/views/admin/sales-reports/ui/change.blade.php`
    - `resources/views/admin/sales-reports/ui/export.blade.php`
    - `resources/views/admin/sales-reports/ui/trend.blade.php`
    - `resources/views/admin/shops/sales-report/dashboard.blade.php`
    - `docs/sales-reports-ui.md`

    Generated Vite assets/manifest and storage fixtures, screenshots, downloads and visual-check helpers are ignored artifacts. Unrelated modules and mobile apps were not modified in this task.

21. **Final Git state:** Branch remains `yuan-mobile`. `git status`, `git diff --stat`, `git diff` and `git diff --check` reviewed. The existing dirty tree and prior untracked reporting files remain; this UI task adds the files listed above. No changes staged, no branch switch/reset/merge, no commit or push. Review snapshots are in ignored `storage/app/report-ui-git-status.txt`, `report-ui-git-stat.txt`, and `report-ui-git-diff.txt`. Git's tracked-file diff does not include the previously untracked report views, so the exact list above also identifies those changes.
