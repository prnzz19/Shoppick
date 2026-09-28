# SHOPPICK mobile client

Flutter source for the existing Laravel SHOPPICK application. The app uses the shared Laravel database only through `/api/v1`.

## Local testing

The dependency lockfile requires Dart 3.12+ and Flutter 3.44+. This workspace uses Flutter 3.47.5 / Dart 3.13.4. Keep the existing platform runners and database.

Laravel requires PHP 8.2+; XAMPP's PHP 8.0 cannot run it. Start Laravel from the repository root with the compatible installed PHP:

```powershell
Set-Location C:\xampp\htdocs\Shoppick
$env:CACHE_STORE = 'file'
& C:\php\php-8.5.9-nts-Win32-vs17-x64\php.exe artisan serve --host=127.0.0.1 --port=8000
```

For an optional physical-phone setup, enable ADB reverse, then run from `mobile/`:

```powershell
& C:\Android\Sdk\platform-tools\adb.exe devices
& C:\Android\Sdk\platform-tools\adb.exe -s 2aae5ba4 reverse tcp:8000 tcp:8000
& C:\Android\Sdk\platform-tools\adb.exe -s 2aae5ba4 reverse --list
Set-Location C:\xampp\htdocs\Shoppick\mobile
flutter run -d 2aae5ba4 --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
```

Keep the Laravel terminal open. Start MySQL in XAMPP if port 3307 is not
listening; the existing database is `shoppick` on `127.0.0.1:3307`.
The phone must appear as `2aae5ba4 device` (accept its USB debugging prompt).
Reapply ADB reverse after reconnecting USB or restarting ADB/the phone/laptop.
Laptop checks: `GET http://127.0.0.1:8000/api/v1/home` should return 200 JSON;
an empty JSON `POST http://127.0.0.1:8000/api/v1/login` with
`Accept: application/json` should return 422 JSON.
Debug builds log the method, URL without query values, status, redacted body
summary and transport exception type. Passwords, tokens and raw server error
bodies are never logged. Release builds omit these diagnostics.

For current Android Studio emulator development, start Medium Phone and run:

```powershell
Set-Location C:\xampp\htdocs\Shoppick\mobile
flutter run -d emulator-5554 --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

The default API URL is `http://10.0.2.2:8000/api/v1`: Android Emulator maps
`10.0.2.2` to the Windows host. No ADB reverse is needed for the emulator.
Override `API_BASE_URL` with `--dart-define` for other devices; the physical
USB phone commands above use `127.0.0.1` with ADB reverse. Laravel localhost
image URLs are rewritten to the configured API host. Website links use the
same host and require a separate browser sign-in.

## Supported flows

- Home, categories/subcategories, search, paginated products, product details, and shop pages.
- Secure token login/logout, cart quantities and individual selection, server-calculated checkout review, saved address selection, final order placement, orders and order details.
- Startup validates saved tokens through `/profile` before opening Marketplace. Missing/invalid tokens show Login; connection failures preserve the token and offer Try Again or Sign Out. Runtime 401 responses reset the session; permission 403 responses do not.
- Seller applications with required ID/permit files and optional logo. Resubmissions use Laravel's existing workflow. Seller Center opens on the website only when Laravel grants approved seller access; Buyer access remains available.
- Profile and saved addresses are readable in the app. Profile editing, address management, wishlist, notifications, and password changes use labeled website links because the mobile API has no corresponding management endpoints.
- Online payment options follow the backend's simulated demo drivers. No actual card payment integration is added.

## Checks

```powershell
dart format lib test
flutter analyze
flutter test
```

Laravel feature tests use SQLite `:memory:` through `phpunit.xml`. Do not run destructive database commands against the existing SHOPPICK database.
