# Admin Sellers performance report

1. **Branch:** `yuan-mobile`; no branch switch, merge, staging, commit, push, or reset.
2. **Sales source:** existing Seller Sales/Dashboard and Admin Seller detail logic, `seller_orders.seller_total`.
3. **Statuses:** only `completed` seller orders count. Pending, confirmed, processing, packed, ready_to_ship, shipped, delivered, and cancelled do not count. Failed/refunded are parent payment states, not seller-order states. As in existing seller analytics, payments are not a separate revenue source and completed totals are not independently adjusted for refunds. This is completed seller revenue, not a refund-adjusted payment ledger.
4. **Sales date:** `seller_orders.completed_at`, using the existing application timezone (UTC). All Time includes completed legacy rows even if their completion timestamp is missing; dated filters require a completion timestamp.
5. **Controller:** validates filters, resolves dates, joins grouped vendor performance, computes summaries, filters and sorts at database level, and eager-loads seller profiles and shops. Suspended established sellers are now visible and their existing detail action is available.
6. **UI:** compact navy/teal/orange cards, grouped responsive filters, seller/shop identity, logo with initial fallback, completed orders, peso sales, seller/shop states, join date, existing detail and archive actions. Zero-order sellers show No sales in period. No optional units column, top badge, revenue limits, or new analytics page.
7. **Cards:** Total Sellers and Active Shops describe all current sellers/marketplace-active shops. Total Sales and Orders describe the matching sellers, selected archive tab, and date period; both totals use one aggregate query.
8. **Search:** server-side name, email, or shop substring search.
9. **Shop:** actual database shops, selected by ID; schema enforces one store per seller user.
10. **Presets:** All Time, Today, Last 7 Days, Last 30 Days, This Month, Last Month, Custom Range. Rolling ranges include today and the preceding 6/29 calendar days.
11. **Custom dates:** both YYYY-MM-DD dates required, validated in chronological order; both boundary dates inclusive. Queries use completion >= start and completion < the day after end. Dates do not change identity or joined fields.
12. **Status:** actual seller approved/suspended and shop active/suspended states; Archived stays separate. Pending/rejected applications remain in their existing module.
13. **Performance:** All Sales, With Sales (positive completed revenue), No Sales (zero completed revenue).
14. **Sort:** newest/oldest seller, highest/lowest sales, most/least orders, shop A–Z, seller A–Z; stable seller-ID tie breaking.
15. **Revenue:** SUM(seller_total) per store, COALESCE to zero; formatted as PHP peso with two decimals. Existing OrderService computes the stored net seller total; no formula was duplicated here.
16. **Orders:** COUNT(*) of matching completed vendor orders, never buyer parent orders or items. Shared multi-vendor parent orders are not double-counted as seller revenue.
17. **Pagination:** 15 sellers per page, showing first–last of total.
18. **State:** all query parameters retained by pagination and archive-tab links; GET filter submission preserves the other selected controls and resets pagination.
19. **Clear:** resets filters and sort while retaining the archive tab; hidden on an unfiltered nonempty list, offered on an empty result.
20. **Archive:** separate Current/Archived lists, existing confirmation modal, archive/restore/delete routes and history protections retained. Archived revenue remains available on the Archived list. Archive records and endpoint implementation were not changed.
21. **Queries:** grouped seller-order subquery joined to unique shops; database date predicates and sorting; one combined revenue/order summary; eager profile/shop loading. A Sellers-only action partial reuses existing confirmation behavior without the shared component's per-row permission query. The page and mutation routes retain permission middleware. Query-count test verifies 15 sellers do not issue more queries than one seller.
22. **Tests:** added AdminSellerPerformanceTest, covering page rendering, name/email/shop search, shop/status filters, all presets, custom boundaries/validation, seller-specific and multi-vendor metrics, cancelled/delivered/pending exclusion, failed/refunded noncompleted orders, zero totals, sorting, performance filters, pagination, archive/restore/history, authorization, clear/empty states, and query scaling. Existing SellerManagementArchiveTest unchanged.
23. **Results:** `php artisan test --filter='AdminSellerPerformanceTest|SellerManagementArchiveTest'`: 14 passed, 227 assertions, SQLite :memory:. PHP syntax check passed. `npm run build` passed. `git diff --check` passed (existing line-ending notices only).
24. **Browser:** isolated headless Edge visited http://127.0.0.1:8000/admin/sellers and redirected to the Login page. No authenticated admin session was available, so live visual inspection and the authenticated filter/archive flow remain unverified. Automated tests render and exercise the admin page in isolation. No real archive action was submitted in the browser.
25. **Migrations:** none created or run for this feature. Existing uncommitted archive migrations were preserved.
26. **Historical data:** no real seller/order/payment records were changed or deleted. Test mutations only used isolated SQLite.
27. **Scope:** unrelated modules and mobile apps untouched by this task; all pre-existing edits preserved.
28. **Exact task files:**
    - app/Http/Controllers/Admin/SellerController.php
    - resources/views/admin/sellers/active.blade.php
    - resources/views/admin/sellers/_archive-actions.blade.php (new)
    - tests/Feature/AdminSellerPerformanceTest.php (new)
    - docs/admin-seller-performance.md (this report)
    - Ignored public/build output regenerated by Vite; temporary browser profiles remain under ignored storage/app.
29. **Final Git state:** branch yuan-mobile remains dirty with the pre-existing modified/untracked work. This task changes the existing Sellers controller and active view and adds the Sellers-only action partial, focused test and this report. No files staged, committed, or pushed. Final status, diff/stat and whitespace checks reviewed.
