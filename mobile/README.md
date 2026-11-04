# SHOPPICK Buyer mobile

Use the existing Flutter app in `C:\xampp\htdocs\Shoppick\.worktrees\yuan-mobile\mobile`. Its branch is `yuan-mobile`, based on Prince's release `bd909aad52f25f209a0c76a5ec7acec31b323e8f`. The original dirty checkout is preserved separately.

The app uses the same Laravel `/api/v1` backend and database as the website. It includes native registration/OTP/Google onboarding, catalog and shops, cart and Buy Now, server-reviewed checkout, orders/tracking/actions/reviews, profile/addresses, wishlist, notifications, Buyer/Seller chat and product reporting. Only configured payment drivers are offered; the current real option is COD.

See [the full handoff and parity checklist](../documentation/mobile-buyer-parity.md) for endpoints, changed files, test results and remaining device/service checks.

## Local backend

PHP 8.2+ is required. This host has PHP 8.5.9 at `C:\php\php-8.5.9-nts-Win32-vs17-x64\php.exe`; XAMPP's older PHP cannot run this Laravel release.

No environment file has been copied or edited. Before starting the backend, manually prepare a **local-only** environment in this worktree from the example and your own local settings. Use the existing local SHOPPICK MySQL database, an appropriate local APP_KEY, `APP_ENV=local`, `APP_DEBUG=false`, `APP_URL=http://127.0.0.1:8000`, and locally configured mail. Do not copy production database, Google secret or SMTP credentials. Use a mail catcher/test mailbox while validating registration.

Review database state and back up the local database before applying changes. The database must already match Prince's baseline migrations. The new additive migration only adds nullable checkout retry columns and an index to `orders`; no new Buyer/cart/order database is created. Run the following **manually against the local database only** after reviewing it:

```powershell
Set-Location 'C:\xampp\htdocs\Shoppick\.worktrees\yuan-mobile'
$buyerPhp = 'C:\php\php-8.5.9-nts-Win32-vs17-x64\php.exe'
git branch --show-current
& $buyerPhp artisan migrate:status
& $buyerPhp artisan migrate --path=database/migrations/2026_10_26_000001_add_mobile_checkout_keys_to_orders.php
```

Never use `migrate:fresh`, `refresh`, production seeders or PSGC reimport commands for this work.

To reuse images and private attachments already stored by your original **local** installation, set the optional filesystem roots in the Laravel terminal. These are directory paths, not credentials; this avoids duplicate upload stores. Omit them when using this worktree's own local storage:

```powershell
$env:FILESYSTEM_PUBLIC_ROOT = 'C:\xampp\htdocs\Shoppick\storage\app\public'
$env:FILESYSTEM_PRIVATE_ROOT = 'C:\xampp\htdocs\Shoppick\storage\app\private'
& $buyerPhp artisan storage:link
$env:CACHE_STORE = 'file'
& $buyerPhp artisan serve --host=0.0.0.0 --port=8000
```

Serve both the local website and mobile API from this updated backend. Keep this terminal and local MySQL running. `GET /api/v1/home` should return JSON; an empty JSON `POST /api/v1/login` should return validation status 422.

## Flutter and Android

This worktree was checked with Flutter 3.47.5 / Dart 3.13.4 and Android SDK 36.1.0. Dependencies are locked. The debug APK is under `mobile/build/app/outputs/flutter-apk/app-debug.apk` after a successful build.

```powershell
Set-Location 'C:\xampp\htdocs\Shoppick\.worktrees\yuan-mobile\mobile'
$buyerFlutter = 'C:\src\flutter_windows_3.47.5-stable\flutter\bin\flutter.bat'
& $buyerFlutter pub get
& $buyerFlutter devices
& $buyerFlutter run --no-pub -d <emulator-id> --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

Replace `<emulator-id>` with the connected Android emulator identifier. For USB hardware, use `adb reverse tcp:8000 tcp:8000`, then use `API_BASE_URL=http://127.0.0.1:8000/api/v1` and the real device identifier. For a phone on the same local network, use the laptop's actual reachable LAN address. Existing Android map keys are optional: the parcel timeline remains usable without a map key.

On this Windows host, set `$env:FLUTTER_WINDOWS='false'` and `$env:FLUTTER_LINUX='false'` in the current terminal before `pub get` for Android work. This avoids desktop-plugin symlink checks without changing Windows Developer Mode or global Flutter settings. Let `pub get` finish successfully: stopping after dependency resolution can leave Android's generated plugin registration missing, even when an APK builds. Then use `--no-pub` for Android commands. Do not run a second `flutter create`.

Google sign-in needs the Web OAuth server client ID supplied as `--dart-define=GOOGLE_SERVER_CLIENT_ID=<web-client-id>`, matching backend `GOOGLE_MOBILE_CLIENT_ID` (or its existing `GOOGLE_CLIENT_ID` fallback). Register the actual Android application ID and signing SHA in Google Cloud. No secret belongs in Dart, and none was embedded. New Google Buyers still complete the required profile/ID/address/consent form and SHOPPICK OTP.

Release builds require an HTTPS `API_BASE_URL`. Release signing and publishing are outside this local debug handoff.

## Verification commands

```powershell
Set-Location 'C:\xampp\htdocs\Shoppick\.worktrees\yuan-mobile'
& 'C:\php\php-8.5.9-nts-Win32-vs17-x64\php.exe' -d extension=gd vendor/phpunit/phpunit/phpunit --filter 'MobileBuyer|MobileGoogle|MobileOrderTracking|BuyerShoppingWorkflow|BuyerWorkbook|GoogleBuyerAuth|BuyerSellerMessaging|BuyerImmediateAccess|RiderMobileApi|SortingCenterScannerApi|LogisticsWorkflow|RiderWorkbook|HubTransferWorkflow|SellerOrders|BuyerSellerApplicationWorkflow'
Set-Location mobile
& 'C:\src\flutter_windows_3.47.5-stable\flutter\bin\dart.bat' format --output=none --set-exit-if-changed lib test
& $buyerFlutter analyze --no-pub
& $buyerFlutter test --no-pub
& $buyerFlutter build apk --debug --no-pub
```

PHPUnit forces SQLite `:memory:` and a test-only encryption key. Test mail, queues and sessions are isolated; GD is enabled for that PHP process only. Do not configure these tests against the existing MySQL database. Automated cross-client tests use the real website controllers and the same in-memory records.

Checkout uses server totals and a signed review token. After an uncertain network response, use checkout's history icon, **Check last submission**, to recover the saved request with its original retry key. An error does not imply that an order was not placed; recovery returns the existing order when it was already accepted.

The isolated real-device validation harness and its limitations are described in [tools/mobile-demo](../tools/mobile-demo/README.md). Google OAuth, external SMTP delivery, physical hardware behavior and live map rendering require their own verification; passing mocks or widget tests does not establish live success.
