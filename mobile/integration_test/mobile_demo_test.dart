// Opt-in, actual Android -> Laravel -> disposable MariaDB/SMTP integration.
// Run only with the guarded tools/mobile-demo setup and its ignored client.json.
import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:integration_test/integration_test.dart';
import 'package:shoppick_mobile/main.dart';
import 'package:shoppick_mobile/services/api_service.dart';

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  const password = String.fromEnvironment('DEMO_PASSWORD');
  const email = String.fromEnvironment('DEMO_EMAIL');
  const base = String.fromEnvironment('API_BASE_URL');
  testWidgets(
      'Actual Android buyer journey against the isolated shared backend',
      (tester) async {
    if (!base.endsWith(':8126/api/v1') ||
        !email.endsWith('@mobile-demo.test') ||
        password.isEmpty) {
      fail('This test requires the isolated demo configuration.');
    }
    final api = ApiService();
    final checks = <String>[];
    binding.reportData = {
      'checks': checks,
      'transport': 'actual Android HTTP, MariaDB and SMTP; no mocks'
    };
    Future<void> waitFor(Finder finder) async {
      for (var i = 0; i < 150; i++) {
        await tester.pump(const Duration(milliseconds: 200));
        if (finder.evaluate().isNotEmpty) return;
      }
      expect(finder, findsWidgets);
    }

    Future<void> settle() async {
      await tester.pumpAndSettle(const Duration(milliseconds: 200),
          EnginePhase.sendSemanticsUpdate, const Duration(seconds: 45));
    }

    Future<void> shot(String name) async {
      await tester.pump(const Duration(milliseconds: 500));
      await binding.takeScreenshot(name);
    }

    Future<void> page(Widget widget) async {
      Navigator.of(tester.element(find.byType(Scaffold).first))
          .push(MaterialPageRoute<void>(builder: (_) => widget));
      await settle();
    }

    Future<void> tapText(String text) async {
      await tester.pump();
      final matches = find.text(text);
      if (matches.evaluate().isEmpty) {
        await tester.scrollUntilVisible(matches, 250, maxScrolls: 30);
      }
      final target = matches.last;
      await tester.ensureVisible(target);
      await tester.tap(target);
      await settle();
    }

    Finder field(String label) => find.byWidgetPredicate(
        (w) => w is TextField && w.decoration?.labelText == label);
    await api.clearToken();
    await tester.pumpWidget(const ShoppickApp());
    await waitFor(find.text('Welcome back'));
    await binding.convertFlutterSurfaceToImage();
    await tester.pump();
    await shot('android-01-login');
    await tester.enterText(field('Email'), email);
    await tester.enterText(field('Password'), password);
    await tapText('Sign in');
    await waitFor(find.text('Featured picks'));
    checks.add('Existing disposable buyer login through native form');
    await shot('android-02-home');
    await api.logout();
    await waitFor(find.text('Welcome back'));
    await tapText("Don't have an account? Create Account");
    await shot('android-03-registration-form');
    Navigator.of(tester.element(find.byType(RegisterScreen))).pop();
    await settle();
    final registeredEmail =
        'native-${DateTime.now().millisecondsSinceEpoch}@mobile-demo.test';
    final id = File('${Directory.systemTemp.path}/demo-id.png');
    await id.writeAsBytes(base64Decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aV1EAAAAASUVORK5CYII='));
    // Actual multipart upload from Android. The native document picker is not automated here.
    await api.register({
      'first_name': 'Native',
      'last_name': 'Buyer',
      'sex': 'male',
      'birthday': '1998-01-01',
      'email': registeredEmail,
      'phone':
          '0917${DateTime.now().millisecondsSinceEpoch.toString().substring(6, 13)}',
      'password': password,
      'password_confirmation': password,
      'terms': '1',
      'full_name': 'Native Buyer',
      'address_line': '12 Demo Street',
      'region': 'Region IV-A (CALABARZON)',
      'region_code': '0400000000',
      'province': 'Laguna',
      'province_code': '0403400000',
      'city': 'Santa Cruz',
      'city_code': '0403426000',
      'barangay': 'Bubukal',
      'barangay_code': '0403426003',
      'postal_code': '4009',
      'country': 'PH',
    }, id.path);
    binding.reportData!['registered_email'] = registeredEmail;
    await waitFor(find.text('Verification code'));
    await shot('android-04-otp');
    final mailbox = await http
        .get(Uri.parse('http://10.0.2.2:8026/?recipient=$registeredEmail'));
    expect(mailbox.statusCode, 200);
    final code = jsonDecode(mailbox.body)['code'] as String;
    expect(code.length, 6);
    await tester.enterText(field('Verification code'), code);
    await tapText('Verify');
    await waitFor(find.text('Featured picks'));
    checks.add(
        'Android multipart registration, real SMTP delivery to local capture, native OTP verification');
    await page(const CatalogSearchScreen());
    await tester.enterText(field('Products, categories or shops'), 'Wireless');
    await waitFor(find.text('Demo Wireless Headset'));
    FocusManager.instance.primaryFocus?.unfocus();
    await settle();
    await shot('android-05-search');
    await tapText('Demo Wireless Headset');
    await waitFor(find.text('Choose option'));
    await tester.ensureVisible(find.byType(DropdownButtonFormField<int>));
    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await settle();
    await tapText('Color: Teal');
    await shot('android-06-product-variant');
    await tapText('Add to Cart');
    final cart = await api.request('cart');
    expect(cart['items'].length, 1);
    expect(cart['items'][0]['quantity'], 1);
    checks.add(
        'Native search suggestions, product detail, variant selection and add to cart');
    await page(const CartTab());
    await waitFor(find.text('Proceed to Checkout'));
    await shot('android-07-cart');
    await tapText('Proceed to Checkout');
    await waitFor(find.text('Delivery Address'));
    await tapText('Apply');
    await waitFor(find.text('Voucher discount'));
    final payment = await api.request('checkout?voucher_codes[]=DEMO20');
    expect((payment['payment_methods'] as Map).keys.toList(), ['cod']);
    expect(payment['totals']['voucher_discount'], 20);
    await tester.ensureVisible(find.text('Order Summary'));
    await shot('android-08-checkout-cod-voucher');
    final place = find.textContaining('Place Order');
    await tester.ensureVisible(place);
    await tester.tap(place);
    await waitFor(find.text('Thank you for your order!'));
    final success =
        tester.widget<OrderSuccessScreen>(find.byType(OrderSuccessScreen));
    final number = success.number;
    binding.reportData!['mobile_order'] = number;
    checks.add(
        'Native voucher application, COD-only checkout and order placement');
    await shot('android-09-order-success');
    await tapText('View order');
    await waitFor(find.text(number));
    await shot('android-10-order-details');
    await page(TrackingMapScreen(orderNumber: number));
    await waitFor(find.text('Track Order'));
    await shot('android-11-tracking');
    final tracking = await api.request('orders/$number/tracking');
    expect(tracking, isA<Map>());
    checks.add(
        'Order detail and actual tracking endpoint for the newly placed pending order');
    await page(const NotificationsScreen());
    await waitFor(find.text('Notifications'));
    await shot('android-12-notifications');
    final notices = await api.request('notifications');
    expect(jsonEncode(notices), contains(number));
    checks.add('Order notification persisted and read on Android');
    final thread = await api.request('messages/start', method: 'POST', body: {
      'product_id': int.parse(const String.fromEnvironment('DEMO_PRODUCT_ID'))
    });
    final conversation = thread['conversation'];
    await page(ConversationScreen(
        conversationId: conversation['id'] as int,
        initialStore: conversation['store'] as Map?));
    await waitFor(find.byTooltip('Send'));
    final composer = find.byWidgetPredicate(
        (w) => w is TextField && w.decoration?.hintText == 'Write a message…');
    await tester.enterText(composer, 'Hello from the real Android demo.');
    await tester.tap(find.byTooltip('Send'));
    for (var i = 0; i < 150; i++) {
      await tester.pump(const Duration(milliseconds: 200));
      if (tester.widget<TextField>(composer).controller!.text.isEmpty) break;
    }
    expect(tester.widget<TextField>(composer).controller!.text, isEmpty);
    await waitFor(find.text('Hello from the real Android demo.'));
    FocusManager.instance.primaryFocus?.unfocus();
    await shot('android-13-messaging');
    checks.add('Native message composition and server-persisted conversation');
    await page(const ProfileScreen());
    await waitFor(find.text('Edit Profile'));
    await shot('android-14-profile');
    await tapText('Edit Profile');
    await tester.enterText(field('Full Name'), 'Native Demo Buyer');
    await tapText('Save Changes');
    expect((await api.request('profile'))['user']['name'], 'Native Demo Buyer');
    checks.add('Native profile display and save');
    await page(const OrdersTab());
    await waitFor(find.text(number));
    await shot('android-15-order-history');
  }, timeout: const Timeout(Duration(minutes: 12)));
}
