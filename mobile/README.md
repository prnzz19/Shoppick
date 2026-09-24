# SHOPPICK mobile client

Flutter source for the existing Laravel SHOPPICK application. The app uses the shared Laravel database only through `/api/v1`.

## Current setup

- Set the base API URL in `lib/config/api_config.dart`, or pass `--dart-define=SHOPPICK_API_URL=http://HOST:8000/api/v1`.
- Android emulator default: `http://10.0.2.2:8000/api/v1`.
- Physical Android device: use the development computer's LAN IP and allow the device to reach the Laravel server.
- Authentication bearer tokens are stored with `flutter_secure_storage`.
- Buyer registration follows Laravel's approval process and requires the required identity and address fields plus a valid ID file. The current mobile UI directs registration to the website.
- Checkout currently supports Cash on Delivery and uses the buyer's saved default address. Maintain addresses on the SHOPPICK website.

## Run

Install Flutter 3.24+ and Android Studio, then from `mobile/` run `flutter pub get` and `flutter run`. Start Laravel from the repository root with `php artisan serve --host=0.0.0.0`; migrations must be applied to the existing SHOPPICK database.

This source checkout was assembled without the Flutter SDK, so generated `android/` and `ios/` runner projects and local analyzer output are not included. Generate platform runners with `flutter create --platforms=android,ios .` after installing Flutter.
