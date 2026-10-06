import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shoppick_mobile/main.dart';
import 'package:shoppick_mobile/models/shipment_tracking.dart';
import 'package:shoppick_mobile/services/api_service.dart';

Map<String, dynamic> shipment({String status = 'out_for_delivery'}) => {
      'id': 1,
      'tracking_number': 'SH-TEST',
      'status': status,
      'shop': 'Test shop',
      'events': [
        {'id': 1, 'status': status, 'created_at': '2026-10-05T08:00:00Z'}
      ],
      'points': [],
      'live': status == 'out_for_delivery',
      'delivered_at': status == 'delivered' ? '2026-10-05T08:00:00Z' : null,
    };
Map<String, dynamic> tracking(
        {String status = 'out_for_delivery', bool poll = true}) =>
    {
      'shipments': [shipment(status: status)],
      'poll': poll
    };

void main() {
  setUp(() {
    FlutterSecureStorage.setMockInitialValues({'auth_token': 'test'});
    ApiService.session.value = SessionStatus.authenticated;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
            const MethodChannel('shoppick/maps'), (_) async => false);
  });
  tearDown(() => TestDefaultBinaryMessengerBinding
      .instance.defaultBinaryMessenger
      .setMockMethodCallHandler(const MethodChannel('shoppick/maps'), null));
  test('parses real coordinates and hides live Rider after delivery', () {
    expect(trackingCoordinate(null), isNull);
    expect(trackingCoordinate({'latitude': 91, 'longitude': 121}), isNull);
    expect(trackingCoordinate({'latitude': 'NaN', 'longitude': 121}), isNull);
    final model = ShipmentTrackingModel(shipment()
      ..['current_rider_location'] = {'latitude': 14.5, 'longitude': 121});
    expect(model.current!.latitude, 14.5);
    expect(
        ShipmentTrackingModel(shipment(status: 'delivered')
              ..['current_rider_location'] = {
                'latitude': 14.5,
                'longitude': 121
              })
            .current,
        isNull);
    expect(model.events.single['status'], 'out_for_delivery');
  });
  testWidgets('Buyer Track Order action is present only with shipment data',
      (tester) async {
    await tester.pumpWidget(MaterialApp(
        home: Scaffold(
            body: TrackingEntry(
                orderNumber: 'SP-TEST', shipments: [shipment()]))));
    expect(find.text('Track Order'), findsOneWidget);
    await tester.pumpWidget(const MaterialApp(
        home: Scaffold(
            body: TrackingEntry(
                orderNumber: 'SP-TEST', shipments: [], details: true))));
    expect(find.text('Track Order on Map'), findsNothing);
    expect(find.text('No shipment created yet.'), findsOneWidget);
  });
  for (final seller in [false, true]) {
    testWidgets(
        '${seller ? 'Seller' : 'Buyer'} map preserves no-coordinate timeline without key',
        (tester) async {
      await tester.pumpWidget(MaterialApp(
          home: TrackingMapScreen(
              orderNumber: 'SP-TEST',
              sellerOrderId: seller ? 1 : null,
              mapsConfigured: () async => false,
              fetch: () async => tracking())));
      await tester.pumpAndSettle();
      expect(find.text('Map location is not available for this shipment yet.'),
          findsOneWidget);
      expect(
          find.textContaining('Google Maps is not configured'), findsOneWidget);
      await tester.scrollUntilVisible(find.textContaining('Status only'), 200);
      expect(find.textContaining('Status only'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    });
  }
  testWidgets('loading, API failure and retry are safe', (tester) async {
    final pending = Completer<dynamic>();
    await tester.pumpWidget(MaterialApp(
        home: TrackingMapScreen(
            orderNumber: 'SP-TEST',
            mapsConfigured: () async => false,
            fetch: () => pending.future)));
    await tester.pump();
    expect(find.text('Loading tracking...'), findsOneWidget);
    pending.completeError(const ApiException(
        'You do not have permission to perform this action.'));
    await tester.pumpAndSettle();
    expect(find.text('You do not have permission to perform this action.'),
        findsOneWidget);
    expect(find.text('Retry'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('delivered state stops polling and resume refresh',
      (tester) async {
    var calls = 0;
    await tester.pumpWidget(MaterialApp(
        home: TrackingMapScreen(
            orderNumber: 'SP-TEST',
            mapsConfigured: () async => false,
            fetch: () async {
              calls++;
              return tracking(status: 'delivered', poll: false);
            })));
    await tester.pumpAndSettle();
    expect(find.text('Delivered'), findsOneWidget);
    await tester.pump(const Duration(seconds: 60));
    expect(calls, 1);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await tester.pumpAndSettle();
    expect(calls, 1);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('active order polls every 20 seconds and stops in background',
      (tester) async {
    var calls = 0;
    await tester.pumpWidget(MaterialApp(
        home: TrackingMapScreen(
            orderNumber: 'SP-TEST',
            mapsConfigured: () async => false,
            fetch: () async {
              calls++;
              return tracking();
            })));
    await tester.pumpAndSettle();
    await tester.pump(const Duration(seconds: 20));
    await tester.pumpAndSettle();
    expect(calls, 2);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
    await tester.pump(const Duration(seconds: 60));
    expect(calls, 2);
    await tester.pumpWidget(const SizedBox());
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
  });
  testWidgets('Seller outgoing orders use own endpoint and tracking action',
      (tester) async {
    await http.runWithClient(() async {
      await tester.pumpWidget(const MaterialApp(home: SellerOrdersScreen()));
      await tester.pumpAndSettle();
      expect(find.text('Track Shipment'), findsOneWidget);
      await tester.tap(find.text('Track Shipment'));
      await tester.pumpAndSettle();
      expect(find.byType(TrackingMapScreen), findsOneWidget);
      expect(
          tester
              .widget<TrackingMapScreen>(find.byType(TrackingMapScreen))
              .sellerOrderId,
          7);
      await tester.pumpWidget(const SizedBox());
    },
        () => MockClient((request) async {
              if (request.url.path.endsWith('/tracking')) {
                expect(request.url.path, endsWith('/seller/orders/7/tracking'));
                return http.Response(jsonEncode(tracking(poll: false)), 200);
              }
              expect(request.url.path, endsWith('/seller/orders'));
              return http.Response(
                  jsonEncode({
                    'data': [
                      {
                        'id': 7,
                        'seller_order_number': 'SO-TEST',
                        'status': 'shipped',
                        'shipments': [shipment()]
                      }
                    ],
                    'last_page': 1
                  }),
                  200);
            }));
  });
}
