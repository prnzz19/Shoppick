import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shoppick_logistics/config/app_config.dart';
import 'package:shoppick_logistics/models/delivery.dart';
import 'package:shoppick_logistics/screens/rider/assignment_detail_screen.dart';
import 'package:shoppick_logistics/screens/rider/assignments_screen.dart';
import 'package:shoppick_logistics/screens/rider/availability_card.dart';
import 'package:shoppick_logistics/screens/rider/rider_home_screen.dart';
import 'package:shoppick_logistics/services/api_client.dart';

Map<String, dynamic> riderUser({String availability = 'available'}) => {
      'id': 7,
      'name': 'Rider Juan',
      'email': 'rider@example.test',
      'role': 'rider',
      'availability': availability,
      'status': 'active',
      'permissions': <String>[],
    };

Map<String, dynamic> assignment() => {
      'id': 51,
      'tracking_number': 'SH-PHASE-51',
      'parcel_code': 'PCL-51',
      'order_number': 'SP-51',
      'seller_order_number': 'SP-51-S1',
      'item_count': 2,
      'buyer': 'Buyer Bea',
      'shop': 'Seller Shop',
      'status': 'pickup_assigned',
      'assignment_type': 'pickup',
      'pickup_address': 'Pickup Street, Quezon City',
      'destination': 'Dropoff Street, Manila',
      'pickup_coordinates': {'latitude': 14.65, 'longitude': 121.03},
      'dropoff_coordinates': {'latitude': 14.59, 'longitude': 120.98},
      'pickup_phone': '09171111111',
      'phone': '09172222222',
      'instructions': 'Leave at reception',
      'cod_amount': 120.0,
      'assigned_at': '2026-10-16T08:00:00Z',
      'updated_at': '2026-10-16T08:00:00Z',
      'actions': ['accept_pickup', 'decline'],
      'products': <dynamic>[],
      'events': <dynamic>[],
    };

Map<String, dynamic> page(List<dynamic> data) => {
      'data': data,
      'current_page': 1,
      'last_page': 1,
    };

ApiClient riderApi({
  int availabilityStatus = 200,
  void Function(http.Request)? observe,
}) {
  final api = ApiClient(
    client: MockClient((request) async {
      observe?.call(request);
      if (request.url.path.endsWith('/availability')) {
        return http.Response(
          jsonEncode(availabilityStatus == 200
              ? {'availability': 'off_duty'}
              : {
                  'message':
                      'Finish your current assignment before changing availability.'
                }),
          availabilityStatus,
        );
      }
      if (request.url.path.endsWith('/profile')) {
        return http.Response(jsonEncode({'user': riderUser()}), 200);
      }
      if (request.url.path.endsWith('/deliveries/51/status')) {
        return http.Response(jsonEncode({'delivery': assignment()}), 200);
      }
      if (request.url.path.endsWith('/deliveries/51')) {
        return http.Response(jsonEncode({'delivery': assignment()}), 200);
      }
      if (request.url.path.endsWith('/deliveries')) {
        return http.Response(
          jsonEncode({
            'deliveries': page([assignment()]),
            'statuses': <String>[],
          }),
          200,
        );
      }
      return http.Response('{}', 404);
    }),
  )
    ..token = 'test-token'
    ..user = riderUser();
  return api;
}

Widget host(Widget child, {double scale = 1}) => MaterialApp(
      theme: appTheme(),
      builder: (context, widget) => MediaQuery(
        data: MediaQuery.of(context).copyWith(
          textScaler: TextScaler.linear(scale),
        ),
        child: widget!,
      ),
      home: Scaffold(body: child),
    );

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(() => FlutterSecureStorage.setMockInitialValues({}));

  test('navigation uses coordinates first and safely falls back to address',
      () {
    final delivery = Delivery(assignment());
    expect(
      riderNavigationUri(delivery, pickup: true).toString(),
      contains('destination=14.65%2C121.03'),
    );
    final withoutCoordinates = Delivery({
      ...assignment(),
      'dropoff_coordinates': null,
    });
    expect(
      riderNavigationUri(withoutCoordinates, pickup: false).toString(),
      contains('Dropoff+Street%2C+Manila'),
    );
    expect(
      riderNavigationUri(
        Delivery({
          ...assignment(),
          'pickup_coordinates': null,
          'pickup_address': ''
        }),
        pickup: true,
      ),
      isNull,
    );
  });

  testWidgets('availability changes only after a successful server response',
      (tester) async {
    String? method;
    final api = riderApi(observe: (request) => method = request.method);
    await tester.pumpWidget(host(AvailabilityCard(api: api)));
    await tester.tap(find.byType(Switch));
    await tester.pumpAndSettle();
    expect(method, 'PATCH');
    expect(api.user?['availability'], 'off_duty');
    expect(find.text('Offline'), findsOneWidget);

    final rejected = riderApi(availabilityStatus: 422);
    await tester.pumpWidget(host(AvailabilityCard(api: rejected)));
    await tester.tap(find.byType(Switch));
    await tester.pumpAndSettle();
    expect(rejected.user?['availability'], 'available');
    expect(find.text('Online for assignments'), findsOneWidget);
    expect(
      find.text('Finish your current assignment before changing availability.'),
      findsOneWidget,
    );
  });

  for (final width in [320.0, 360.0, 390.0, 430.0]) {
    testWidgets('Rider Phase 5.1 screens fit ${width}px', (tester) async {
      tester.view.physicalSize = Size(width, 900);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final api = riderApi();
      for (final screen in [
        RiderHomeScreen(api: api),
        RiderAssignmentsScreen(api: api),
        RiderAssignmentDetailScreen(api: api, id: 51),
      ]) {
        await tester.pumpWidget(MaterialApp(theme: appTheme(), home: screen));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        await tester.pumpWidget(const SizedBox());
      }
    });
  }

  testWidgets('assignment UI exposes Phase 5.1 details and actions only',
      (tester) async {
    final api = riderApi();
    await tester.pumpWidget(
      MaterialApp(
        theme: appTheme(),
        home: RiderAssignmentDetailScreen(api: api, id: 51),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Pickup'), findsOneWidget);
    expect(find.text('Drop-off'), findsOneWidget);
    expect(find.text('Navigate to Pickup'), findsOneWidget);
    expect(find.text('Navigate to Drop-off'), findsOneWidget);
    final scrollable = find.byType(Scrollable).first;
    await tester.scrollUntilVisible(
      find.text('Parcel'),
      300,
      scrollable: scrollable,
    );
    expect(find.text('Parcel'), findsAtLeastNWidgets(1));
    await tester.scrollUntilVisible(
      find.text('COD amount'),
      300,
      scrollable: scrollable,
    );
    expect(find.text('COD amount'), findsOneWidget);
    final acceptButton = find.widgetWithText(FilledButton, 'Accept assignment');
    await tester.scrollUntilVisible(acceptButton, 300, scrollable: scrollable);
    expect(acceptButton, findsOneWidget);
    expect(
      find.widgetWithText(OutlinedButton, 'Decline assignment'),
      findsOneWidget,
    );
  });
}
