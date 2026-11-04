// Actual Android UI checks for website orders, document picking and registration.
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:integration_test/integration_test.dart';
import 'package:shoppick_mobile/main.dart';
import 'package:shoppick_mobile/services/api_service.dart';

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  testWidgets('Website order on Android and full native registration form',
      (tester) async {
    const password = String.fromEnvironment('DEMO_PASSWORD');
    const email = String.fromEnvironment('DEMO_NATIVE_EMAIL');
    const websiteOrder = String.fromEnvironment('DEMO_WEBSITE_ORDER');
    if (!email.endsWith('@mobile-demo.test') ||
        websiteOrder.isEmpty ||
        !const String.fromEnvironment('API_BASE_URL').endsWith(':8126/api/v1'))
      fail('Disposable demo configuration required.');
    final checks = <String>[];
    binding.reportData = {'checks': checks, 'website_order': websiteOrder};
    Future<void> waitFor(Finder finder) async {
      for (var i = 0; i < 300; i++) {
        await tester.pump(const Duration(milliseconds: 200));
        if (finder.evaluate().isNotEmpty) return;
      }
      expect(finder, findsWidgets);
    }

    Future<void> settle() => tester.pumpAndSettle(
        const Duration(milliseconds: 200),
        EnginePhase.sendSemanticsUpdate,
        const Duration(seconds: 45));
    Future<void> visible(Finder finder) async {
      await tester.pump();
      if (finder.evaluate().isEmpty)
        await tester.scrollUntilVisible(finder, 250, maxScrolls: 35);
      await tester.ensureVisible(finder.last);
      await tester.pump();
    }

    Future<void> tap(String text) async {
      final f = find.text(text);
      await visible(f);
      await tester.tap(f.last);
      await settle();
    }

    Finder field(String label) => find.byWidgetPredicate(
        (w) => w is TextField && w.decoration?.labelText == label);
    Future<void> fill(String label, String value) async {
      final f = field(label);
      await visible(f);
      await tester.enterText(f.last, value);
      await tester.pump();
    }

    Future<void> shot(String name) async {
      await settle();
      await binding.takeScreenshot(name);
    }

    Future<void> page(Widget widget) async {
      Navigator.of(tester.element(find.byType(Scaffold).first))
          .push(MaterialPageRoute<void>(builder: (_) => widget));
      await settle();
    }

    final api = ApiService();
    await api.clearToken();
    await tester.pumpWidget(const ShoppickApp());
    await waitFor(find.text('Welcome back'));
    await binding.convertFlutterSurfaceToImage();
    await tester.pump();
    await fill('Email', email);
    await fill('Password', password);
    await tap('Sign in');
    await waitFor(find.text('Featured picks'));
    await shot('android-16-home-final');
    await page(const OrdersTab());
    await waitFor(find.text(websiteOrder));
    await shot('android-17-website-order-in-native-history');
    await tap(websiteOrder);
    await waitFor(find.text('Seller deliveries'));
    await shot('android-18-website-order-details');
    checks.add(
        'Website-created order appears in actual Android order history and details');
    final thread = await api.request('messages/start', method: 'POST', body: {
      'product_id': int.parse(const String.fromEnvironment('DEMO_PRODUCT_ID'))
    });
    final conversation = thread['conversation'];
    await page(ConversationScreen(
        conversationId: conversation['id'] as int,
        initialStore: conversation['store'] as Map?));
    await waitFor(find.text('Hello from the real Android demo.'));
    await shot('android-19-persisted-message');
    checks.add(
        'Previously sent message survives a new Android process and server reload');
    await api.logout();
    await waitFor(find.text('Welcome back'));
    await tap('Continue with Google');
    await waitFor(
        find.text('Google sign-in is not configured for this build.'));
    await shot('android-20-google-unconfigured');
    checks.add('Unconfigured Google Sign-In fails without granting a session');
    await tap("Don't have an account? Create Account");
    final nativeEmail =
        'ui-${DateTime.now().millisecondsSinceEpoch}@mobile-demo.test';
    await fill('First name', 'Native');
    await fill('Last name', 'Form Buyer');
    final birthday = field('Birthday (YYYY-MM-DD)');
    await visible(birthday);
    await tester.tap(birthday.last);
    await settle();
    await tester.tap(find.byTooltip('Switch to input'));
    await settle();
    final dateField = find.descendant(
        of: find.byType(DatePickerDialog), matching: find.byType(TextField));
    await tester.enterText(dateField, '01/01/1998');
    await tap('OK');
    await fill('Email', nativeEmail);
    await fill('Mobile (+639XXXXXXXXX)',
        '0918${DateTime.now().millisecondsSinceEpoch.toString().substring(6, 13)}');
    await tap('Choose Philippine address');
    await waitFor(find.text('Region'));
    Future<void> choose(String label, String value) async {
      final f = find.ancestor(
          of: find.text(label),
          matching: find.byType(DropdownButtonFormField<String>));
      await visible(f);
      await tester.tap(f.last);
      await settle();
      await tap(value);
    }

    await choose('Region', 'Region IV-A (CALABARZON)');
    await choose('Province', 'Laguna');
    await choose('City / Municipality', 'Santa Cruz');
    await choose('Barangay', 'Bubukal');
    await fill('Postal Code', '4009');
    await fill('House No. / Street', '14 Native Form Demo Street');
    await tap('Save Address');
    await waitFor(find.text('Update address'));
    await fill('Password', password);
    await fill('Confirm password', password);
    // Host helper chooses only SHOPPICK-demo-id.png from the emulator's Downloads.
    await tap('Choose valid ID');
    await waitFor(find.text('Valid ID selected'));
    checks.add('Real Android system document picker selected a disposable PNG');
    await tap('I accept the Terms and Privacy Policy.');
    await tap('Create Account');
    await waitFor(find.text('Verification code'));
    final mailbox = await http
        .get(Uri.parse('http://10.0.2.2:8026/?recipient=$nativeEmail'));
    expect(mailbox.statusCode, 200);
    await fill('Verification code', jsonDecode(mailbox.body)['code'] as String);
    await tap('Verify');
    await waitFor(find.text('Featured picks'));
    binding.reportData!['ui_registered_email'] = nativeEmail;
    checks.add(
        'Entire native registration form, birthday picker, dependent address dropdowns, consent, document upload and SMTP OTP verification');
    await shot('android-21-full-registration-success');
  }, timeout: const Timeout(Duration(minutes: 12)));
}
