# Public landing page and marketplace routes

- `/` is the new public landing page, route name `landing`, rendered by `LandingController` and `resources/views/landing.blade.php`.
- `/shop` is the existing Buyer marketplace, still named `home`, handled by the unchanged `HomeController` and `resources/views/storefront/home.blade.php`.
- `/shop/{slug}` remains the existing individual shop page. It does not conflict with `/shop`.

Keeping the original `home` route name on the marketplace preserves store logos, mobile Home navigation, product breadcrumbs, Back to store links, admin Store links, buyer login/Google login redirects, and generated deal notifications. The shared footer adds About SHOPPICK and Marketplace links so visitors can move between both pages.

The landing page uses the existing SHOPPICK logo, theme palette, MySQL `shoppick` database, Category and Product models, public visibility scopes, local product images and product detail routes. Product previews use actual current prices and the existing sale-price method. No records, authentication behavior, role relationships or shopping logic were duplicated.

Actions:

| Action | Existing destination |
| --- | --- |
| Shop Now | `/shop` |
| Login | `/login` |
| Register | `/register` |
| Sell on SHOPPICK | `/register/seller` |
| Deliver with SHOPPICK | `/register/rider` |

Verification: build and Blade compilation passed. Landing page checked in Edge at 1440, 1024, 768, 390 and 375px with no horizontal overflow, broken preview images or JavaScript page errors. Public marketplace, details and registration pages returned HTTP 200. Existing buyer login redirected to `/shop`. Seller, Admin, Logistics and Rider dashboard requests returned HTTP 200 using existing role accounts. Existing CartService add succeeded inside a rolled-back transaction. No real orders were placed or registrations submitted during verification.

Files added: `app/Http/Controllers/LandingController.php`, `resources/views/landing.blade.php`, `public/css/landing.css`, and this document.
Files modified: `routes/storefront.php` and `resources/views/components/storefront/footer.blade.php`. Vite assets were rebuilt. The original Buyer homepage was not rewritten or deleted. No database/schema changes, commits or pushes were made for this task.

## Milestone 2 refinement

The public page now uses the requested **Shop Easy. Pick Smart.** tagline and includes responsive Home/Categories/Shops/Why SHOPPICK navigation, a native collapsible mobile menu, category icon cards, existing eligible products with View Product links, approved shop cards, four factual benefits, the three shopping steps, Seller/Rider CTAs and a dedicated landing footer.

Shop cards use `Store::marketplaceActive()` and require published eligible products; Panda Picks is currently the eligible shop displayed. Counts are database queries, and missing shop logos use a neutral store icon. No reviews, ratings, statistics or security guarantees are fabricated. No verified public support contact exists in the project; the footer states that limitation and directs visitors to shop profiles for available business details.

Additional files: `resources/views/components/landing-shops.blade.php`, `landing-benefits.blade.php`, `landing-footer.blade.php`, and `public/js/landing.js`. Updated the existing landing controller, page and scoped stylesheet. No routes or shared marketplace footer were changed during this refinement.

Milestone 2 checks: all seven widths (1440, 1280, 1024, 768, 430, 390, 375px) passed without overflow. Native mobile menu, link-close and Escape-close passed. All rendered category, product and shop destinations returned HTTP 200; images decoded and no JavaScript page errors occurred. Marketplace and Buyer/Seller/Rider registration pages loaded. An existing Buyer logged in and reached `/shop`. All four role dashboard requests and a rolled-back CartService add passed. Registration submissions and real checkout were intentionally not performed.

## Readability and first-time shopper refinement

Reworked the existing landing page for clearer shopping actions: exact shared brand-500 teal (#14b8a6) and accent-500 orange (#f97316) wordmark, unchanged shared mascot, larger navigation and visible mobile authentication links. Primary buttons use the existing brand-700 shade for readable white text. The hero includes a shopping-bag illustration built around the existing mascot and actual product previews. No new raster logo or product data was generated.

Added a labeled GET search form using products.index and its existing q parameter. Database categories now have recognizable line icons with a generic fallback for other names. Latest Products follows categories, displaying actual prices, shop names and always-visible View Product actions. Simplified benefits, numbered shopping instructions, seller/rider actions and footer links. Icons follow the application's existing inline SVG approach and reuse seller icons where available.

Verified in Edge at 1440, 1280, 1024, 768, 430, 390 and 375px: no horizontal overflow; visible navigation, category and button targets at least 44px tall; working mobile link-close and Escape-close; six product images decoded; no JavaScript page errors. Computed wordmark colors exactly matched the shared tokens. Search for Wireless returned the existing Wireless Bluetooth Earbuds. All rendered category, product and shop links returned HTTP 200, including Panda Picks. Buyer login reached /shop. Buyer/Seller/Rider registration forms loaded. Authenticated cart, orders and checkout pages loaded. Seller/Admin/Logistics/Rider dashboard requests returned HTTP 200. CartService add passed inside a rolled-back transaction.

These are browser and readability checks, not a usability study with older customers. No registrations or orders were submitted; external Google OAuth was not exercised. Database, controller queries, routes, existing marketplace and role modules were unchanged during this refinement. No fabricated ratings, discounts or statistics were added.

Files changed: resources/views/landing.blade.php; resources/views/components/landing-benefits.blade.php; resources/views/components/landing-shops.blade.php; resources/views/components/landing-footer.blade.php; public/css/landing.css; public/js/landing.js; this document. Added resources/views/components/landing-icon.blade.php. Blade view compilation passed.

## Teacher's Milestone 2 alignment

Refined the existing implementation against the teacher's brief: simpler hero copy, Featured Shops heading and full-width single-shop layout (multiple eligible shops retain a responsive grid), restrained hover states, active Home indicator, and a warm orange seller panel using shared palette tokens. Clarified the smaller rider section and added the final Ready to Find Your Next Pick? shopping CTA before the footer. The footer now includes a short marketplace description and Company links. The mobile shopping illustration is more compact so categories appear sooner.

Retained exact logo tokens, the existing mascot and inherited application typography; existing labeled GET product search; dynamic categories and eligible products; prominent prices and always-visible View Product buttons; factual benefits and numbered shopping instructions. Shop counts are labeled listed products rather than implying that every listing is in stock. No fake records, ratings, discounts, claims or urgency elements were added.

Verification: Blade compilation passed. Edge checks at 1440, 1280, 1024, 768, 430, 390 and 375px passed with no overflow or JavaScript page errors. Visible navigation/category/button targets measured at least 44px high. Product images decoded at every width. Mobile navigation opened and closed on category selection. All rendered public links returned HTTP 200, including product/category/shop pages and Seller/Rider registration. Search for Wireless returned the existing earbuds. Actual buyer login redirected to /shop; authenticated cart/orders/checkout pages returned HTTP 200. All four role dashboards returned HTTP 200, and CartService add passed inside a rolled-back transaction. Wordmark computed colors matched #14b8a6 and #f97316.

No database, route, authentication, marketplace or role-module logic changed. Google OAuth and real registration/order submissions were not performed. Readability review was a design/browser audit, not testing with actual older customers. Contact remains transparent about the lack of a supplied public support contact.

Files modified in this pass: resources/views/landing.blade.php, resources/views/components/landing-shops.blade.php, resources/views/components/landing-benefits.blade.php, resources/views/components/landing-footer.blade.php, public/css/landing.css, documentation/landing-page.md.

## Product-led visual reference refinement

Applied the user's reference as layout inspiration: a large split hero with an actual product photograph, three image-focused category collections, and a six-product desktop grid. Preserved SHOPPICK's original mascot, shared teal/orange colors, typography, plain labels, visible search and persistent product actions. No reference branding, copy, shoe content or imagery was used.

LandingController now eager-loads each product category and reads up to six existing active, in-stock products. Collection cards use distinct active categories from those products and link to the existing category filter. No records or schema changes. The hero uses the first eligible product and has an empty-state fallback. Existing backend business logic and routes remain unchanged.

Edge checks passed at 1440, 1280, 1024, 768, 430, 390 and 375px: no horizontal overflow, all product images decoded, all buttons at least 44px tall, six products and three real category collections displayed. All 23 unique linked destinations returned HTTP 200. Product search returned the existing Wireless Bluetooth Earbuds, with no JavaScript page errors. Seller/Admin/Logistics/Rider dashboard checks returned HTTP 200, and a CartService add passed inside a rolled-back transaction. Blade compilation passed. No actual orders or registrations submitted; Google OAuth was not tested in this pass.

Files modified: app/Http/Controllers/LandingController.php, resources/views/landing.blade.php, public/css/landing.css, documentation/landing-page.md.
