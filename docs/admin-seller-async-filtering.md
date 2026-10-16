# Admin Sellers asynchronous filtering report

1. **Branch:** `yuan-mobile`, unchanged.
2. **Endpoint added:** authenticated GET returning rendered Blade fragments and metadata, with `private, no-store` caching.
3. **Route:** `/admin/sellers/filter`, named `admin.sellers.filter`, registered before `sellers/{user}`.
4. **Authorization:** existing `web`, `auth`, `role:admin`, and `permission:manage_sellers` middleware. Signed-out JSON requests return 401; unauthorized roles/permissions return 403.
5. **Shared backend:** `SellerController::sellerData()` powers both index and filter actions. Existing completed seller-order SUM(seller_total), COUNT(*), completion dates, archive separation and allowlisted sorting remain the source of truth. No duplicate business query or revenue formula.
6. **Blade:** summary, table, pagination, tabs and period partials used by both responses. Sellers-only archive action markup retained; its delegated submission guard moved into the page module to work after partial replacement.
7. **Dropdowns:** Shop, Sales Date, Seller Status, Sort, Shop Status and Sales automatically fetch on change and reset pagination. No visible Filter button with JavaScript; a noscript Apply filters button retains the ordinary GET fallback.
8. **Search:** 400 ms debounce; typing also invalidates any previous pending response immediately.
9. **Cancellation:** native AbortController plus a generation counter; stale requests cannot overwrite newer results. Requests time out after 15 seconds.
10. **Loading:** Updating sellers text, subtle table opacity, aria-busy, live announcements; controls remain keyboard usable. Focus in replaced result sections moves to the labelled results region without scrolling; search focus remains in place.
11. **Clear:** clears search, all six dropdown filters/sort and both custom dates, then fetches defaults asynchronously. Retains the Current/Archived tab. Hidden at default settings. Empty-result Clear Filters also works through delegated clicks.
12. **URL:** successful requests push canonical normal-page URLs; blank/default/unknown parameters are omitted. Pagination links point to the normal index, never the JSON endpoint. Failed requests retain existing results and the last successful URL.
13. **History:** popstate restores all controls, custom dates, archive tab and page, fetches matching results, and replaces only the normalized current history URL.
14. **Pagination:** delegated async links preserve selected filters. Filter changes start at page 1. Existing 15-row pagination retained.
15. **Cards:** all four cards refresh with every response. Total Sellers now counts matching sellers in the selected tab; Active Shops counts marketplace-active shops among those matching sellers. Sales/Orders use the selected completion period. Seller identity/count remains present for zero-sales sellers unless a sales-performance filter excludes them. Current/Archived tab counts remain global state counts.
16. **Custom dates:** local validation waits for both dates and requires To >= From. Backend validates YYYY-MM-DD and inclusive boundaries. Existing from/to parameters remain supported, with date_from/date_to aliases accepted and canonicalized. Leaving Custom hides/disables the dates; Clear removes them.
17. **Errors/offline:** retain table/cards, end loading, show Unable to update sellers. Try again. and Retry. Raw server/SQL errors are never displayed. Incomplete or reversed custom dates receive a local validation message without a request.
18. **Queries:** grouped vendor-performance joins, one combined summary aggregate, eager profile/store loading. Async requests omit the full shop-dropdown query. Query-count regression coverage confirms query count does not grow with displayed sellers.
19. **Automated coverage:** extended AdminSellerPerformanceTest to compare full-page versus async partials across existing filters; added combined/card, custom alias, invalid value, pagination URL and endpoint authorization checks. Existing archive/restore tests unchanged. Added dependency-free real-browser CDP test and opt-in SQLite fixture export.
20. **Results:** 18 backend tests passed, 580 assertions; one browser-fixture export test is intentionally skipped unless opted in. The opt-in export passed separately with 24 assertions. Vite build, PHP/JS syntax, route middleware review and git diff --check passed. Browser script passed 10 interaction groups, including all dropdowns, debounce/focus, custom dates, pagination/history, server errors, offline recovery, cancellation, archive confirmation, empty results and status/sorting. No browser JavaScript exceptions.
21. **Browser verification:** headless Edge exercised the built frontend using actual Blade HTML and JSON exported by the authorized Laravel controller against SQLite :memory:. Screenshot: `storage/app/admin-sellers-async-preview.png`. No fixture-server mutation was submitted. The real http://127.0.0.1:8000/admin/sellers browser visit redirected to Login, and a signed-out JSON fetch returned 401. The authenticated live-data flow remains unverified because no admin session was available; fixture verification does not claim otherwise.
22. **Migration:** NO. No database schema command was run against the real database.
23. **Data:** no real Seller/Order/Sales records changed. Fixture setup and archive/restore tests only mutate SQLite :memory:.
24. **Scope:** unrelated modules/mobile apps untouched by this task. The shared JS entry received only the Sellers-module import; the new module returns immediately outside this page. All earlier uncommitted work preserved.
25. **Exact task files:**
    - `app/Http/Controllers/Admin/SellerController.php`
    - `routes/admin.php` (only new Sellers filter route in this task)
    - `resources/js/app.js` (one import)
    - `resources/js/admin-sellers.js` (new)
    - `resources/views/admin/sellers/active.blade.php`
    - `resources/views/admin/sellers/_archive-actions.blade.php`
    - `resources/views/admin/sellers/partials/summary.blade.php` (new)
    - `resources/views/admin/sellers/partials/table.blade.php` (new)
    - `resources/views/admin/sellers/partials/pagination.blade.php` (new)
    - `resources/views/admin/sellers/partials/tabs.blade.php` (new)
    - `resources/views/admin/sellers/partials/period.blade.php` (new)
    - `tests/Feature/AdminSellerPerformanceTest.php`
    - `tests/Browser/admin-sellers.mjs` (new)
    - `docs/admin-seller-async-filtering.md` (this report)
    - Ignored Vite build output regenerated; ignored fixtures, screenshot and isolated browser profile under storage/app.
26. **Final Git state:** branch remains yuan-mobile with the existing dirty working tree plus the listed changes. Final status/stat/diff and whitespace checks reviewed. Nothing staged, committed, pushed, merged or reset.

To rerun browser checks in PowerShell, export isolated fixtures and use an isolated Edge/Chrome instance on debugging port 9225:

```powershell
$env:SHOPPICK_SELLER_BROWSER_FIXTURES='1'
php artisan test --filter='AdminSellerPerformanceTest::test_export_isolated_browser_fixtures_when_requested'
Remove-Item Env:SHOPPICK_SELLER_BROWSER_FIXTURES
node tests/Browser/admin-sellers.mjs
```

The browser script serves fixtures on 127.0.0.1:8127 and rejects all mutations. Regenerate fixtures after rebuilding because their HTML references the current Vite asset hashes.
