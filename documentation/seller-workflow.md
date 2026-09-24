# Buyer-first seller workflow

Implemented in C:/xampp/htdocs/E-commerce, served by Apache from its public directory.

## Architecture and behavior

Reuses roles/role_user, SellerApplication, SellerProfile, Store (stores table), private registration documents, NotificationService/notifications_custom, and AdminActivityLog. No parallel role, notification, application or shop system was created.

Public /register now displays Buyer registration directly. Email registration attaches only buyer. Google ignores seller registration intent and creates Buyer accounts. The existing Buyer registration approval policy remains: a newly registered Buyer must receive the existing Admin registration approval before normal shopping/application access. Rider applications remain available and unchanged.

Old GET /register/seller and /complete-seller-registration bookmarks redirect to the authenticated seller application. Their POST endpoints return 410 and cannot create users or mutate registration state. Old direct-seller templates now explain the Buyer-first flow.

The existing /seller/apply GET/POST pair handles application status and submission on the same user_id. Active, registration-approved Buyers may apply. Pending/escalated/awaiting_final_review applicants see status, not another form. Rejected applicants retain the existing reapplication policy. Needs Resubmission permits correction. Resubmission updates the same application and records the prior application snapshot in the existing audit log; old document files remain private and are not deleted. Approved sellers go to Seller Dashboard. A user-row transaction lock serializes first submissions, resubmissions and approval.

Admin approval attaches buyer and seller without detaching any existing role, approves the profile, activates the existing/new shop, and records reviewer/time. Repeated identical decisions are idempotent and do not duplicate users, shops, profiles, roles or notifications. Rejection and resubmission require reasons; Buyer access remains intact. The existing HasRoles::removeRole helper was corrected to detach only the requested role IDs (it previously detached all roles).

All Seller routes now require auth, approved registration, Seller role, approved profile and active shop. Ownership checks in existing Seller controllers remain. Buyer access alone or a pending profile never unlocks Seller modules. Documents and review endpoints remain protected by Admin role and permissions. Private documents are downloads, not public storage URLs. Review forms require confirmation. Existing seller features were preserved.

## Admin and account UI

- Sidebar: Buyers; Seller Management with Shops, Seller Applications and Sellers; Marketplace; other existing modules retained. Applications and Sellers have distinct active states.
- Application badge counts pending/escalated/awaiting_final_review only. Needs Resubmission awaits the applicant and is not counted as Admin work.
- Dashboard: Total Buyers (including dual-role users), Pending Seller Applications, Active Sellers, plus existing metrics and five recent applications.
- Applications: dedicated paginated application list with name/email/shop search, all existing statuses and Needs Resubmission filter; dedicated review page for applicant, shop, contacts, documents, status, review and history.
- Sellers: approved-profile Seller-role accounts only, with shop status, counts, date and seller detail page. Details reuse existing shop-management links and display products/order totals/history without exposing identity documents.
- Buyers: existing user tabs/search retained, Buyer + Seller capability label added. Buyer registration review/document actions moved to user detail so they remain available outside seller applications.
- Buyer account page and profile menu: state-aware seller card/link. Seller navigation adds Shop as Buyer in the same session.
- Landing selling links use the same state-aware destination. Guests hit login through the authenticated application route; pending/rejected/resubmission applicants see their own status/form; approved sellers open their dashboard.
- Notifications: submission receipt plus Admin notification; approved/rejected/resubmission notifications reuse the current database/email service. No live test email was sent.

## Routes

Reused seller.apply and seller.apply.store and existing review/document routes. Added admin.sellers.applications.show (GET admin/sellers/applications/{application}), admin.sellers.index (GET admin/sellers), admin.sellers.show (GET admin/sellers/{user}). Legacy signup routes are compatibility redirects/disabled POSTs. Seller group adds approval middleware.

## Safe migration and data verification

2026_09_24_000001_preserve_buyer_role_for_approved_sellers.php is an additive data-only migration. It attaches Buyer to existing approved sellers with insertOrIgnore; does nothing on empty installations and does not revoke Buyer access on rollback. Applied successfully as batch 31. Role assignments changed 29 -> 36 (seven additions).

Live record counts and full-table hashes before/after migration are in seller-workflow-verification.json. All compared tables were unchanged: 27 users, 2 applications, 9 seller profiles, 9 stores, 27 products, 23 orders, 32 order items, 23 payments, 38 categories. No records were deleted, recreated, or reseeded. No destructive database command was run against the live database.

## Files changed for this workflow

Backend:
- app/Http/Controllers/Admin/{AdminDashboardController,SellerApplicationController,SellerController}.php
- app/Http/Controllers/Auth/{RegisterController,GoogleAuthController,CompleteProfileController,LoginController}.php
- app/Http/Controllers/SellerApplicationController.php
- app/Http/Middleware/{EnsureApprovedSeller,EnsureRegistrationApproved}.php
- app/Models/{User,SellerApplication}.php
- app/Services/{SellerRegistrationService,SellerShopApprovalService,AdminSidebarCounts}.php
- app/Traits/HasRoles.php
- routes/{auth,seller,admin}.php
- database/migrations/2026_09_24_000001_preserve_buyer_role_for_approved_sellers.php

Views:
- resources/views/admin/dashboard.blade.php
- resources/views/admin/sellers/{index,application,active,show}.blade.php
- resources/views/admin/users/{index,show}.blade.php
- resources/views/auth/{register,register-choice,register-seller,complete-seller-profile}.blade.php
- resources/views/components/admin/status-badge.blade.php
- resources/views/components/storefront/header.blade.php
- resources/views/components/{landing-footer,landing-shops}.blade.php
- resources/views/landing.blade.php (selling destinations/labels only)
- resources/views/layouts/{admin,account,seller-center}.blade.php
- resources/views/seller/apply.blade.php
- resources/views/storefront/account/profile.blade.php

Tests: BuyerSellerApplicationWorkflowTest (new); AdminModerationDynamicSellerWorkflowTest, BirthdayAgeFieldsTest, PhilippineLocationCoverageTest, RegistrationAndProductVisibilityTest, RiderApplicationWorkflowTest updated for the new signup contract. Earlier unrelated landing/image/font edits in the working tree were preserved.

## Validation

101 tests passed, 1501 assertions. Tests forced DB_CONNECTION=sqlite / DB_DATABASE=:memory:, with array mail; never ran test fixtures on live MySQL. Covers new Buyer-only signup including forged Google seller intent, application/duplicates, approval/idempotency, dual-role Seller pages and Buyer checkout, rejection/resubmission/history, private documents, unauthorized reviews, incomplete users, legacy backfill repeatability, and existing marketplace/rider/logistics workflows.

npm run build, php artisan optimize:clear, route:list and migrate:status succeeded. Apache browser checks: homepage, /shop, Buyer registration, guest seller entry redirects; no JavaScript errors. Read-only Admin dashboard rendered using actual existing records and visually checked at desktop width, without changing registrations or applications. Interactive live Admin decisions were intentionally not submitted; those mutations were verified in isolated integration tests.
