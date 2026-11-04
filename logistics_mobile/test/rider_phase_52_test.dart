import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shoppick_logistics/config/app_config.dart';
import 'package:shoppick_logistics/models/delivery.dart';
import 'package:shoppick_logistics/screens/rider/assignment_detail_screen.dart';
import 'package:shoppick_logistics/screens/rider/history_screen.dart';
import 'package:shoppick_logistics/services/api_client.dart';

Map<String, dynamic> rider() => {
      'id': 7,
      'name': 'Rider Juan',
      'email': 'rider@example.test',
      'role': 'rider',
      'availability': 'on_delivery',
      'permissions': <String>[],
    };

Map<String, dynamic> delivery({
  String status = 'out_for_delivery',
  String type = 'delivery',
  List<String> actions = const ['proof', 'collect_cod', 'failed'],
}) =>
    {
      'id': 51,
      'tracking_number': 'SH-PHASE-52',
      'parcel_code': 'PCL-52',
      'order_number': 'SP-52',
      'seller_order_number': 'SP-52-S1',
      'item_count': 2,
      'buyer': 'Buyer Bea',
      'shop': 'Seller Shop',
      'status': status,
      'assignment_type': type,
      'pickup_stop_number': type == 'pickup' ? 1 : null,
      'pickup_stop_count': type == 'pickup' ? 2 : 0,
      'hub': type == 'delivery' ? 'Central Hub' : null,
      'hub_address': type == 'delivery' ? 'Hub Road, Manila' : null,
      'pickup_address': 'Seller Street, Quezon City',
      'destination': 'Buyer Street, Manila',
      'pickup_coordinates': {'latitude': 14.65, 'longitude': 121.03},
      'dropoff_coordinates': {'latitude': 14.59, 'longitude': 120.98},
      'pickup_phone': '(0917) 111-1111',
      'phone': '+63 917 222 2222',
      'instructions': 'Leave at reception',
      'cod_amount': 120.0,
      'cod_collected_amount': null,
      'cod_status': 'pending',
      'payment_type': 'cod',
      'assigned_at': '2026-10-16T08:00:00Z',
      'updated_at': '2026-10-16T08:00:00Z',
      'delivered_at': status == 'delivered' ? '2026-10-16T10:00:00Z' : null,
      'actions': actions,
      'products': [
        {'name': 'Parcel item', 'quantity': 2}
      ],
      'events': [
        {
          'status': status,
          'note': 'Status updated',
          'created_at': '2026-10-16T08:00:00Z'
        }
      ],
      'proof': null,
    };

Map<String, dynamic> page(List<dynamic> data) => {
      'data': data,
      'current_page': 1,
      'last_page': 1,
    };

ApiClient apiFor(
  Map<String, dynamic> current, {
  void Function(http.Request)? observe,
  List<Map<String, dynamic>>? history,
}) {
  final api = ApiClient(
    client: MockClient((request) async {
      observe?.call(request);
      if (request.url.path.endsWith('/profile')) {
        return http.Response(jsonEncode({'user': rider()}), 200);
      }
      if (request.url.path.endsWith('/deliveries/51/status')) {
        final body = jsonDecode(request.body) as Map<String, dynamic>;
        if (body['action'] == 'confirm_pickup') {
          current['status'] = 'picked_up';
          current['actions'] = ['arrive_sorting'];
        }
        return http.Response(jsonEncode({'delivery': current}), 200);
      }
      if (request.url.path.endsWith('/deliveries/51')) {
        return http.Response(jsonEncode({'delivery': current}), 200);
      }
      if (request.url.path.endsWith('/deliveries') &&
          request.url.queryParameters['history'] == '1') {
        return http.Response(
          jsonEncode({
            'deliveries': page(history ?? [current]),
            'statuses': <String>[],
          }),
          200,
        );
      }
      return http.Response('{}', 404);
    }),
  )
    ..token = 'test-token'
    ..user = rider();
  return api;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(() => FlutterSecureStorage.setMockInitialValues({}));

  test('hub navigation and dialer links use safe authoritative values', () {
    final item = Delivery(delivery());
    expect(
      riderNavigationUri(item, pickup: true).toString(),
      contains('Hub+Road%2C+Manila'),
    );
    expect(riderPhoneUri('+63 (917) 222-2222').toString(), 'tel:+639172222222');
    expect(riderPhoneUri(''), isNull);
  });

  testWidgets('multi-seller stop confirms only the selected parcel pickup',
      (tester) async {
    final current = delivery(
      status: 'pickup_accepted',
      type: 'pickup',
      actions: ['confirm_pickup'],
    );
    Map<String, dynamic>? sent;
    final api = apiFor(current, observe: (request) {
      if (request.url.path.endsWith('/status')) {
        sent = jsonDecode(request.body) as Map<String, dynamic>;
      }
    });
    await tester.pumpWidget(
      MaterialApp(
        theme: appTheme(),
        home: RiderAssignmentDetailScreen(api: api, id: 51),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Pickup stop 1 of 2'), findsOneWidget);
    for (var i = 0; i < 4; i++) {
      await tester.drag(find.byType(ListView), const Offset(0, -500));
      await tester.pump();
    }
    final button = find.widgetWithText(FilledButton, 'Confirm seller pickup');
    expect(button, findsOneWidget);
    await tester.tap(button);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey('manual-parcel-code')),
      'PCL-52',
    );
    await tester
        .tap(find.widgetWithText(OutlinedButton, 'Preview entered code'));
    await tester.pumpAndSettle();
    expect(sent, isNull);
    await tester.tap(find.widgetWithText(FilledButton, 'Submit code'));
    await tester.pumpAndSettle();
    expect(sent?['action'], 'confirm_pickup');
    expect(sent?['parcel_code'], 'PCL-52');
    expect(current['status'], 'picked_up');
  });

  testWidgets('delivery workflow exposes POD, COD, issues and safe contact',
      (tester) async {
    final current = delivery();
    await tester.pumpWidget(
      MaterialApp(
        theme: appTheme(),
        home: RiderAssignmentDetailScreen(
          api: apiFor(current),
          id: 51,
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Hub pickup'), findsOneWidget);
    for (var i = 0; i < 3; i++) {
      await tester.drag(find.byType(ListView), const Offset(0, -450));
      await tester.pump();
    }
    expect(find.text('Cash on delivery'), findsAtLeastNWidgets(1));
    expect(find.text('Contact'), findsAtLeastNWidgets(1));
    expect(find.text('Call buyer'), findsOneWidget);
    expect(find.text('Call seller'), findsOneWidget);
    expect(
      find.textContaining('Direct buyer or seller chat is not supported'),
      findsOneWidget,
    );
    for (var i = 0; i < 3; i++) {
      await tester.drag(find.byType(ListView), const Offset(0, -450));
      await tester.pump();
    }
    expect(find.text('Add delivery photo'), findsOneWidget);
    expect(find.text('Confirm COD collected'), findsOneWidget);
    expect(find.text('Report delivery issue'), findsOneWidget);
    await tester.tap(find.text('Report delivery issue'));
    await tester.pumpAndSettle();
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    expect(find.text('Wrong address'), findsOneWidget);
    expect(find.text('Buyer unreachable'), findsOneWidget);
    expect(find.text('Damaged item'), findsOneWidget);
  });

  testWidgets('history shows own terminal work without invented earnings',
      (tester) async {
    final delivered = delivery(status: 'delivered', actions: []);
    final failed = {
      ...delivery(status: 'delivery_failed', actions: []),
      'id': 52,
      'tracking_number': 'SH-FAILED-52',
      'failure_reason': 'Buyer unreachable',
    };
    final api = apiFor(delivered, history: [delivered, failed]);
    await tester.pumpWidget(
      MaterialApp(
        theme: appTheme(),
        home: Scaffold(body: RiderHistoryScreen(api: api)),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Rider earnings are not available'), findsOneWidget);
    expect(find.text('SH-PHASE-52'), findsOneWidget);
    expect(find.text('SH-FAILED-52'), findsOneWidget);
    expect(find.textContaining('Issue: Buyer unreachable'), findsOneWidget);
  });

  for (final width in [320.0, 360.0, 390.0, 430.0]) {
    testWidgets('Phase 5.2 Rider screens fit ${width}px', (tester) async {
      tester.view.physicalSize = Size(width, 900);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final current = delivery();
      for (final screen in [
        RiderAssignmentDetailScreen(api: apiFor(current), id: 51),
        Scaffold(body: RiderHistoryScreen(api: apiFor(current))),
      ]) {
        await tester.pumpWidget(MaterialApp(theme: appTheme(), home: screen));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        await tester.pumpWidget(const SizedBox());
      }
    });
  }
}
