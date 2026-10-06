# SHOPPICK System Settings

Implemented in C:/xampp/htdocs/E-commerce. No new project or mobile/Flutter changes.

## Existing architecture and storage

The project already has StoreSetting (per-shop preferences), CommissionSetting, LogisticsSetting (operational preferences), and AdminActivityLog. There was no marketplace-wide settings table. These existing systems remain intact.

One additive migration, 2026_09_26_000001_create_system_settings_table.php, creates system_settings with a unique key, JSON value, type, group, and timestamps. It was applied using XAMPP PHP 8.2.12. Its rollback deliberately retains preferences instead of deleting administrator data.

SystemSettings reads all marketplace values once per request, applies existing configuration defaults when no saved value exists, casts booleans/numbers on save, and invalidates its request cache after saving. Subsequent requests query current database values. No persistent settings cache can become stale.

The delivery-tracking preference reads and writes the existing logistics_settings.buyer_tracking_visibility key. It is not duplicated in system_settings. Seller Applications uses the same marketplace.allow_seller_applications key in both Marketplace and Seller sections.

Default marketplace name and timezone use current app configuration (currently SHOPPICK and UTC). PHP is the current currency. Support contact defaults are blank when no configured value exists. All live behavior defaults were preserved; the migration does not seed altered preferences.

## UI and routes

SYSTEM → System Settings appears near the bottom of both Admin menus with a gear icon and teal active state. Store/Logout remain fixed at the bottom.

- GET /admin/settings — admin.settings.index
- PUT /admin/settings/{group} — admin.settings.update
- POST /admin/settings/clear-cache — admin.settings.cache

SystemSettingsController uses the existing auth and role:admin group. Buyer, Seller, Rider, and Logistics accounts cannot access it. CSRF protection remains enabled. Unknown payload fields are not saved. Secrets and .env contents are never displayed or editable.

Sections: General, Branding, Marketplace, Orders, Seller, Logistics, Notifications, Maintenance. Each editable section has its own Save Changes form, validation feedback, success banner, and stays on the selected section. Browser unsaved-change warnings are enabled. Maintenance shows application environment, Laravel version and PHP version.

## Enforced controls

| Setting | Current behavior |
| --- | --- |
| Marketplace status | Maintenance returns HTTP 503 for public shopping/Buyer web routes. Admin, Seller, Logistics, Rider, authentication/recovery and location lookup routes stay available. This is web marketplace maintenance, not Laravel's global down command. |
| Buyer registration | Disables email registration and new Google account creation. Existing Google accounts can still sign in. New Google account creation is also blocked during marketplace maintenance. |
| Seller applications | Disables submissions/resubmissions, retaining application-status access. |
| Product reviews | Disables creating/submitting reviews; existing reviews remain visible. |
| Cancellation enabled / hours | Enforced by the existing OrderService cancellation operation, alongside existing order-status restrictions. Zero hours means no extra deadline. |
| Seller logo required / maximum KB | Applied to seller application validation. Defaults optional / 2048 KB. Existing shop-edit upload rules are unchanged. |
| Rider applications | Controls public Rider application GET/POST and resubmission. |
| Seller/Rider approval | Remains mandatory and read-only to preserve current role approval architecture. |
| Notification preferences | Controls existing in-app seller_application, rider_application, new-order, order/buyer_order_progress, report and moderation notifications. Buyer and Seller order confirmations carry explicit new-order metadata. Existing email decisions, logistics assignment alerts, and other event types are unaffected. |
| Cache clear | Calls only the fixed Laravel optimize:clear command, then records an audit entry. No arbitrary command input. |

## Stored for future integration

The UI explicitly labels these preferences:
- General business/contact preferences, currency and timezone: saved, but public text, payments, currency conversion, and timestamp handling are not globally changed.
- Branding logo, favicon and colors: saved and previewed in settings; existing public/shared branding remains unchanged until a separate rollout.
- Automatic completion and days: stored; no scheduled completion job was introduced.
- Manual Rider assignment: stored; operational assignment remains available.
- Delivery tracking: reuses the existing Logistics preference, but does not change current tracking visibility.
- Low-stock notification preference: stored; no existing notification event was found and none was invented.

No automatic approval, new role architecture, duplicated logistics data, or notification subsystem was added.

## Uploads and auditing

Logo: PNG/JPEG/WEBP, up to 2 MB and 4096×4096.
Favicon: PNG/JPEG/WEBP, up to 512 KB and 512×512.
SVG is rejected. Files receive unique Laravel Storage filenames under storage/app/public/system. No absolute Windows paths are saved. The existing public storage link is available.
Replacing or restoring branding deletes only the previous feature-owned system/{filename} image. Other uploads and original mascot assets are preserved. Failed saves clean up newly uploaded files.

Important changes are recorded in the existing admin_activity_logs with group and before/after values. Cache clearing is also logged. No second audit system was introduced.

## Files created

- app/Models/SystemSetting.php
- app/Services/SystemSettings.php
- app/Http/Controllers/Admin/SystemSettingsController.php
- app/Http/Middleware/MarketplacePreferences.php
- config/system-settings.php
- database/migrations/2026_09_26_000001_create_system_settings_table.php
- resources/views/admin/settings/index.blade.php
- resources/views/errors/marketplace-maintenance.blade.php
- tests/Feature/SystemSettingsTest.php
- documentation/system-settings.md

## Existing files modified

- routes/admin.php
- bootstrap/app.php
- resources/views/layouts/admin.blade.php
- app/Http/Controllers/Auth/GoogleAuthController.php
- app/Http/Controllers/CheckoutController.php
- app/Http/Controllers/SellerApplicationController.php
- app/Services/OrderService.php
- app/Services/NotificationService.php
- resources/views/seller/apply.blade.php
- Frontend build outputs regenerated.

## Verification

- 115 tests passed, 1843 assertions. Test database: SQLite :memory:, not live MySQL.
- Tests cover all settings groups, validation, persistence, non-admin denial, required approval protection, upload replacement/fallback/scope, audit logging, marketplace gates, Google registration, cancellation limits, seller-logo validation, notification controls and the fixed cache command.
- Existing regression tests cover Buyer/Seller/Admin/Logistics/Rider behavior.
- npm.cmd run build succeeded.
- php artisan optimize:clear succeeded.
- Read-only live rendering succeeded for all eight settings sections.
- Browser previews at 1440, 768 and 390 pixels: all sections had no document overflow and the selected settings tab was marked current. Desktop/mobile screenshots were inspected.
- Before/after hashes matched for 16 existing data tables: users, roles, role_user, products, categories, stores, orders, order_items, payments, shipments, shipment_events, seller_applications, rider_profiles, logistics_settings, proof_of_deliveries, admin_activity_logs.
- Preserved counts include users 27, products 27, orders 23, stores 9, shipments 16. Live settings were not toggled during testing.
- No destructive database commands were used. No separate Flutter/mobile project was modified.