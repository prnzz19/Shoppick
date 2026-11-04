import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:shoppick_mobile/main.dart';
import 'package:shoppick_mobile/services/api_service.dart';

void main() {
  final binding = IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  testWidgets('Restored Android session and actual ready-for-pickup shipment',
      (tester) async {
    const email = String.fromEnvironment('DEMO_NATIVE_EMAIL');
    const number = String.fromEnvironment('DEMO_WEBSITE_ORDER');
    if (!email.endsWith('@mobile-demo.test') ||
        number.isEmpty ||
        !const String.fromEnvironment('API_BASE_URL').endsWith(':8126/api/v1'))
      fail('Disposable demo configuration required.');
    final api = ApiService();
    final hadToken = (await api.token())?.isNotEmpty == true;
    await tester.pumpWidget(const ShoppickApp());
    for (var i = 0;
        i < 200 && ApiService.session.value == SessionStatus.checking;
        i++) {
      await tester.pump(const Duration(milliseconds: 200));
    }
    expect(hadToken, isTrue);
    expect(ApiService.session.value, SessionStatus.authenticated);
    binding.reportData = {'session_restored_after_new_process': true};
    await api.logout();
    await api.login(email, const String.fromEnvironment('DEMO_PASSWORD'));
    await tester.pumpAndSettle();
    await binding.convertFlutterSurfaceToImage();
    await tester.pump();
    final data = await api.request('orders/$number/tracking');
    expect((data['shipments'] as List), isNotEmpty);
    Navigator.of(tester.element(find.byType(Scaffold).first)).push(
        MaterialPageRoute<void>(
            builder: (_) => TrackingMapScreen(orderNumber: number)));
    for (var i = 0; i < 200; i++) {
      await tester.pump(const Duration(milliseconds: 200));
      if (find.text('Ready For Pickup').evaluate().isNotEmpty) break;
    }
    // The response and rendered timeline describe a real service-created shipment.
    expect(data['shipments'][0]['status'], 'ready_for_pickup');
    await tester.pumpAndSettle();
    await binding.takeScreenshot('android-22-shipment-tracking');
    binding.reportData!.addAll({
      'website_order': number,
      'shipment_status': 'ready_for_pickup',
      'shipments': (data['shipments'] as List).length
    });
  }, timeout: const Timeout(Duration(minutes: 4)));
}
