import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:shoppick_logistics/main.dart';
import 'package:shoppick_logistics/services/api_client.dart';

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('API 36 startup, secure storage and Laravel login connection',
      (tester) async {
    final api = ApiClient();
    await api.clear();
    await api.storage.write(key: 'logistics_smoke', value: 'roundtrip');
    expect(await api.storage.read(key: 'logistics_smoke'), 'roundtrip');
    await api.storage.delete(key: 'logistics_smoke');
    await tester.pumpWidget(LogisticsApp(api: api));
    await tester.pumpAndSettle();
    expect(find.text('Logistics'), findsOneWidget);
    await tester.enterText(find.widgetWithText(TextFormField, 'Email'),
        'logistics-smoke-test@example.invalid');
    await tester.enterText(find.widgetWithText(TextFormField, 'Password'),
        'invalid-test-password');
    await tester.ensureVisible(find.widgetWithText(FilledButton, 'Sign in'));
    await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
    for (var i = 0; i < 35; i++) {
      await tester.pump(const Duration(seconds: 1));
      if (find
          .text('The email or password is incorrect.')
          .evaluate()
          .isNotEmpty) {
        break;
      }
    }
    expect(find.text('The email or password is incorrect.'), findsOneWidget);
    expect(api.user, isNull);
    expect(await api.storage.read(key: 'logistics_token'), isNull);
  });
}
