# SHOPPICK mobile completion report

Local branch: `yuan-mobile`. Previous work was retained and extended; no project reset or regeneration.

Authentication follow-up: startup now validates stored tokens with `/profile` instead of trusting token presence. A shared session notifier controls splash, Login, Marketplace, and connection retry screens. Runtime 401 responses clear the session and discard authenticated routes; ordinary permission 403 responses preserve it. Startup profile 403 responses return to Login. Offline logout clears local storage, and failed startup connections retain the token. Login errors are friendly. Home/shared retry callbacks were also corrected. The current Flutter suite passes **139 tests**, including **14 authentication regressions**, and analysis reports **No issues found**. The current debug build targets `127.0.0.1:8000` for ADB reverse. No Laravel files were changed by this follow-up.

1. **Unfinished items found:** seller eligibility needed regression coverage; checkout rendered a passed-in cart snapshot alongside fresh totals; pagination needed deterministic ordering/deduplication; address refresh was only offered when no address existed; network timeout did not cover the initial send; the README had an incorrect configuration key and obsolete runner instructions. These are resolved. Shop information now scrolls with products, and item totals avoid narrow trailing columns.

2. **Seller access:** verified against `User::hasApprovedSellerAccess()` and `EnsureApprovedSeller`. Access requires an active account, Seller role, approved seller profile, and active shop. Pending/unapproved profiles and suspended/restricted/inactive shops do not unlock Seller Center. Buyer access is retained. The eligible action explicitly opens the real website Seller Center; there is no pretend native dashboard.

3. **Seller application:** widget tests cover no application, pending, needs resubmission, rejected, approved/eligible, and approved/unavailable. Helper text and submit/resubmit actions reflect each state. API tests verify required documents, private storage, optional logo absence and PNG upload, duplicate rejection, and resubmission reusing one application. Existing/selected logos can be previewed. Description is optional, matching API validation.

4. **Checkout:** preview returns the selected item data used for server totals. Tests verify no orders/inventory changes during review, multi-shop grouping, selected-only placement, matching preview/order totals, and rejection of another account's address. Flutter disables placement without an address, posts only after Place Order, shows success, and opens the created order. Saved addresses can be refreshed after website edits.

5. **Cart:** individual selection/deselection, quantity increases/decreases, removal, selected subtotal, and server refresh are implemented. Tests verify ownership and preservation of unselected items after checkout. There is no select-all control.

6. **Orders:** cards show the first item, additional-item count, shop, number, date, amount, status, and View Order. A navigation test verifies opening the matching order. Detail views use real items, prices, quantities, shop statuses, address, totals, payment labels, and status history. Backend status values remain unchanged; delivery estimates/tracking are not invented.

7. **Shops:** shared Laravel marketplace scopes control visibility. API tests confirm active-shop visibility and suspended-shop exclusion. Logo fallback, shop information, product count, and paginated products are retained. Shop information scrolls with the product list.

8. **Categories/products:** API tests cover parent/child filters, direct product access, search, and 25-product pagination without overlapping IDs. Product ordering now breaks timestamp ties by ID; Flutter also deduplicates loaded IDs. Existing category hierarchy and product visibility rules remain in use.

9. **Buyer account/authentication:** native profile/address reading and order access remain. Email, phone, and member-since data are displayed. Profile editing, address management, wishlist, notifications, and password changes link to the correct existing website routes with clear labels. Login/profile/logout tests verify bearer authentication and revocation of only the current token; Flutter tests verify local token removal and cart badge reset. Secure storage remains in use.

10. **Images/errors:** all network images use the shared loading/fallback widget. URL tests cover relative storage paths, localhost rewriting, external URLs, and invalid schemes. A failed-network-image test verifies the fallback. HTTP 500 details are not shown to users; connection errors are friendly, and request logging excludes response bodies and tokens. Android host configuration is documented in README.

11. **Responsive checks:** 108 widget scenarios cover Home, Categories, Products, Product Details, Shop, Cart, Checkout, Orders, Order Details, Account, Seller Application, and five-tab navigation at 320/360/390 logical pixels and text scales 1.0/1.5/2.0. Tests scroll through lazy content and detect Flutter layout exceptions. All pass. These automated checks do not replace a physical-device visual, browser, and file-picker smoke test.

12. **Laravel files in the local diff:** `app/Http/Controllers/MobileApiController.php`, `routes/api.php`, and `tests/Feature/MobileMarketplaceTest.php`. New routes are shop reading and authenticated checkout preview. Cart/order/application operations retain existing services and token middleware. No models, migrations, web templates, or Admin/Seller/Logistics/Rider functionality were changed.

13. **Flutter files in the local diff:** `lib/main.dart`, `lib/config/api_config.dart`, `lib/services/api_service.dart`, `lib/screens/shopping_screens.dart`, `lib/screens/account_screens.dart`, `lib/widgets/marketplace_widgets.dart`, `test/widget_test.dart`, `test/marketplace_flow_test.dart`, `pubspec.yaml`, `pubspec.lock`, Linux/Windows generated plugin registrants and plugin CMake lists, macOS generated plugin registrant, README, and this report. Plugin registration changes support the existing file-picker/browser-link additions.

14. **Test results:** Flutter: **125 passed**. Laravel targeted suites: **43 passed, 633 assertions** across MobileMarketplaceTest, BuyerSellerApplicationWorkflowTest, BuyerShoppingWorkflowTest, and RegistrationAndProductVisibilityTest. Laravel used SQLite `:memory:`. Compatible PHP 8.5.9 was used because the installed XAMPP PHP 8.0 does not meet this project's Composer requirement.

15. **Quality checks:** `dart format lib test` completed. Flutter analysis: **No issues found!** `git diff --check` passed. The original Android debug build succeeded using emulator host `10.0.2.2`; the authentication follow-up rebuild uses `http://127.0.0.1:8000/api/v1` for ADB reverse at `build/app/outputs/flutter-apk/app-debug.apk`. The build reports a future-compatibility warning about file_picker's Kotlin Gradle Plugin usage. Flutter commands used the installed tool snapshot directly because the sandboxed batch launcher could not acquire the SDK cache lock; checks ran with approved SDK cache access.

16. **Limitations:** the mobile API has no profile/address mutation, wishlist, notification, or password-management endpoints; those actions remain on the website. Registration and seller management also use existing website flows. Online payments follow the existing simulated backend drivers. Device networking, real image downloads from the local Laravel host, browser launch/sign-in, and the native file picker still need the user's device smoke test.

17. **Database safety:** no destructive database commands were used. No migrations were created, and no existing application data was deleted or recreated. Test database setup was isolated in memory.

18. **Repository safety:** nothing was committed, pushed, merged, reset, or restored. All changes remain local on `yuan-mobile` for user testing.
