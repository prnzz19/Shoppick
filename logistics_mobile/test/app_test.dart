import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shoppick_logistics/config/app_config.dart';
import 'package:shoppick_logistics/main.dart';
import 'package:shoppick_logistics/screens/auth/login_screen.dart';
import 'package:shoppick_logistics/screens/home_shell.dart';
import 'package:shoppick_logistics/screens/deliveries_screen.dart';
import 'package:shoppick_logistics/screens/account_screen.dart';
import 'package:shoppick_logistics/screens/notifications_screen.dart';
import 'package:shoppick_logistics/screens/rider/proof_screen.dart';
import 'package:shoppick_logistics/screens/delivery_detail_screen.dart';
import 'package:shoppick_logistics/screens/logistics/directory_screen.dart';
import 'package:shoppick_logistics/services/api_client.dart';

Map<String, dynamic> user(String role) => {
      'id': 1,
      'name': 'Juan Dela Cruz',
      'email': 'juan@example.test',
      'role': role,
      'permissions': [
        'view_shipments',
        'assign_shipments',
        'manage_shipments',
        'manage_riders',
        'manage_logistics_settings'
      ]
    };
Map<String, dynamic> delivery() => {
      'id': 1,
      'tracking_number': 'SH-TEST-000001',
      'order_number': 'SP-TEST-1',
      'buyer': 'Maria Santos',
      'shop': 'Panda Picks',
      'provider': 'Local Courier',
      'rider': 'Juan Dela Cruz',
      'status': 'out_for_delivery',
      'destination': '123 Long Street, Barangay Test, Manila',
      'updated_at': '2026-09-28T10:00:00Z',
      'actions': ['proof', 'failed', 'collect_cod'],
      'cod_amount': 120.0,
      'payment_type': 'cod',
      'payment_status': 'cod',
      'phone': '09170000000',
      'products': [
        {'name': 'Parcel product', 'quantity': 1}
      ],
      'events': []
    };
Map<String, dynamic> page(List<dynamic> data) =>
    {'data': data, 'current_page': 1, 'last_page': 1};
ApiClient fakeApi(String role) => ApiClient(client: MockClient((request) async {
      final path = request.url.path;
      Object body = {};
      if (path.endsWith('/login')) {
        body = {'token': 'test-token', 'user': user(role)};
      }
      if (path.endsWith('/profile')) body = {'user': user(role)};
      if (path.endsWith('/dashboard')) {
        body = {
          'summary': {
            'Assigned': 1,
            'Out for delivery': 1,
            'Completed today': 0
          },
          'deliveries': [delivery()],
          'today': 1,
          'unread_notifications': 0
        };
      }
      if (path.endsWith('/deliveries')) {
        body = {
          'deliveries': page([delivery()]),
          'statuses': ['ready_for_pickup', 'out_for_delivery', 'delivered']
        };
      }
      if (path.endsWith('/deliveries/1')) body = {'delivery': delivery()};
      if (path.endsWith('/riders')) {
        body = {
          'riders': page([
            {
              'id': 1,
              'name': 'Juan Dela Cruz',
              'status': 'active',
              'availability': 'available',
              'active_deliveries': 0,
              'completed_deliveries': 1
            }
          ])
        };
      }
      if (path.endsWith('/providers')) {
        body = {
          'providers': page([
            {
              'id': 1,
              'name': 'Local Courier',
              'status': 'active',
              'active_riders': 1,
              'active_deliveries': 1,
              'completed_deliveries': 0
            }
          ])
        };
      }
      if (path.endsWith('/notifications')) body = {'notifications': page([])};
      return http.Response(jsonEncode(body), 200);
    }))
      ..user = user(role);

Widget host(Widget child, double scale) => MaterialApp(
    theme: appTheme(),
    builder: (context, widget) => MediaQuery(
        data: MediaQuery.of(context)
            .copyWith(textScaler: TextScaler.linear(scale)),
        child: widget!),
    home: child);

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(() => FlutterSecureStorage.setMockInitialValues({}));

  for (final width in [320.0, 360.0, 390.0]) {
    for (final scale in [1.0, 1.8]) {
      testWidgets('Operational screens fit ${width}px at ${scale}x text',
          (tester) async {
        tester.view.physicalSize = Size(width, 900);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final manager = fakeApi('logistics'), rider = fakeApi('rider');
        for (final screen in [
          LoginScreen(api: manager),
          HomeShell(api: manager),
          HomeShell(api: rider),
          DeliveryDetailScreen(api: rider, id: 1),
          Scaffold(body: DeliveriesScreen(api: manager)),
          Scaffold(body: DeliveriesScreen(api: rider, history: true)),
          Scaffold(body: AccountScreen(api: rider)),
          Scaffold(body: NotificationsScreen(api: rider)),
          ProofScreen(api: rider, deliveryId: 1),
          Scaffold(body: DirectoryScreen(api: manager, riders: true)),
          Scaffold(body: DirectoryScreen(api: manager, riders: false))
        ]) {
          await tester.pumpWidget(host(screen, scale));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull,
              reason: '${screen.runtimeType} should not overflow');
          await tester.pumpWidget(const SizedBox());
        }
      });
    }
  }

  testWidgets('Login routes Rider to Rider home and logout clears token',
      (tester) async {
    final api = fakeApi('rider')..user = null;
    await tester.pumpWidget(LogisticsApp(api: api));
    await tester.pumpAndSettle();
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Email'), 'rider@example.test');
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Password'), 'password');
    await tester.ensureVisible(find.widgetWithText(FilledButton, 'Sign in'));
    await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
    await tester.pumpAndSettle();
    expect(find.text('YOUR DELIVERY DAY'), findsOneWidget);
    expect(find.text('Providers'), findsNothing);
    expect(await api.storage.read(key: 'logistics_token'), 'test-token');
    await tester.tap(find.byIcon(Icons.person_outline).last);
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Logout'), 300);
    await tester.tap(find.text('Logout'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirm'));
    await tester.pumpAndSettle();
    expect(find.text('Sign in'), findsOneWidget);
    expect(await api.storage.read(key: 'logistics_token'), isNull);
  });

  testWidgets('Connection error offers retry without clearing saved token',
      (tester) async {
    FlutterSecureStorage.setMockInitialValues({'logistics_token': 'saved'});
    final api = ApiClient(
        client: MockClient((_) async => throw Exception('network down')));
    await tester.pumpWidget(LogisticsApp(api: api));
    await tester.pumpAndSettle();
    expect(find.text('Try Again'), findsOneWidget);
    expect(await api.storage.read(key: 'logistics_token'), 'saved');
  });

  test('API keeps SQL and stack traces out of errors and clears expired token',
      () async {
    final api = ApiClient(
        client: MockClient((_) async =>
            http.Response('{"message":"SQLSTATE secret", "trace":[]}', 500)));
    await expectLater(
        api.request('GET', 'rider/deliveries'),
        throwsA(isA<ApiFailure>().having(
            (e) => e.message, 'safe message', isNot(contains('SQLSTATE')))));
    FlutterSecureStorage.setMockInitialValues({'logistics_token': 'expired'});
    final expired =
        ApiClient(client: MockClient((_) async => http.Response('{}', 401)))
          ..token = 'expired'
          ..user = user('rider');
    await expectLater(expired.request('GET', 'logistics/profile'),
        throwsA(isA<ApiFailure>()));
    expect(expired.user, isNull);
    expect(await expired.storage.read(key: 'logistics_token'), isNull);
  });
}
