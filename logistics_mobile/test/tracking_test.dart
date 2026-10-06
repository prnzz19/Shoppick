import 'dart:convert';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shoppick_logistics/models/shipment_tracking.dart';
import 'package:shoppick_logistics/screens/tracking_screen.dart';
import 'package:shoppick_logistics/services/api_client.dart';
import 'package:shoppick_logistics/services/rider_location.dart';

class DeniedLocation implements RiderLocation {
  @override
  Future<void> requestAccess() async => throw const ApiFailure(
      'Location permission is required while delivering this shipment.');
  @override
  Stream<Position> positions() => const Stream.empty();
}

class TestLocation implements RiderLocation {
  final updates = StreamController<Position>();
  @override
  Future<void> requestAccess() async {}
  @override
  Stream<Position> positions() => updates.stream;
}

void main() {
  testWidgets('GPS uploads are throttled and stop when app pauses',
      (tester) async {
    final location = TestLocation();
    var uploads = 0;
    final api = ApiClient(client: MockClient((request) async {
      if (request.method == 'POST') {
        uploads++;
        final payload = jsonDecode(request.body);
        expect(payload['latitude'], 14.5);
        expect(payload['recorded_at'], isNotNull);
        return http.Response('{}', 201);
      }
      return http.Response(
          jsonEncode({
            'delivery': {
              'id': 1,
              'tracking_number': 'SH-TEST',
              'status': 'out_for_delivery',
              'actions': []
            },
            'events': [],
            'points': [],
            'active': true,
            'can_share_location': true
          }),
          200);
    }))
      ..user = {'role': 'rider'}
      ..token = 'test';
    await tester.pumpWidget(MaterialApp(
        home: TrackingScreen(
            api: api,
            id: 1,
            mapsConfigured: () async => false,
            location: location)));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Share location for this delivery'));
    await tester.tap(find.text('Share location for this delivery'));
    await tester.pumpAndSettle();
    final p = Position(
        latitude: 14.5,
        longitude: 121,
        timestamp: DateTime.now(),
        accuracy: 10,
        altitude: 0,
        altitudeAccuracy: 0,
        heading: 0,
        headingAccuracy: 0,
        speed: 0,
        speedAccuracy: 0);
    location.updates.add(p);
    await tester.pumpAndSettle();
    expect(uploads, 1);
    location.updates.add(p);
    await tester.pumpAndSettle();
    expect(uploads, 1);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
    await tester.pumpAndSettle();
    expect(location.updates.hasListener, isFalse);
    location.updates.add(p);
    await tester.pumpAndSettle();
    expect(uploads, 1);
    await tester.pumpWidget(const SizedBox());
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    unawaited(location.updates.close());
    api.dispose();
  });
  test('validates coordinates without inventing missing positions', () {
    expect(trackingCoordinate(null), isNull);
    expect(trackingCoordinate({'latitude': 91, 'longitude': 121}), isNull);
    expect(trackingCoordinate({'latitude': 'NaN', 'longitude': 121}), isNull);
    expect(trackingCoordinate({'latitude': '14.5', 'longitude': 121})!.latitude,
        14.5);
    expect(ShipmentTracking({'delivery': {}, 'events': []}).canShare, isFalse);
  });

  for (final role in ['logistics', 'rider']) {
    testWidgets('$role tracking handles no coordinates and missing map key',
        (tester) async {
      final api = ApiClient(
          client: MockClient((request) async => http.Response(
              jsonEncode({
                'delivery': {
                  'id': 1,
                  'tracking_number': 'SH-TEST',
                  'status': 'out_for_delivery',
                  'actions': []
                },
                'events': [
                  {'id': 1, 'status': 'out_for_delivery'}
                ],
                'points': [],
                'active': true,
                'can_share_location': role == 'rider',
              }),
              200)))
        ..user = {'role': role}
        ..token = 'test';
      await tester.pumpWidget(MaterialApp(
          home: TrackingScreen(
              api: api,
              id: 1,
              mapsConfigured: () async => false,
              location: DeniedLocation())));
      await tester.pumpAndSettle();
      expect(
          find.text('No map location has been recorded yet.'), findsOneWidget);
      expect(
          find.textContaining('Google Maps is not configured'), findsOneWidget);
      if (role == 'rider') {
        await tester
            .ensureVisible(find.text('Share location for this delivery'));
        await tester.tap(find.text('Share location for this delivery'));
        await tester.pumpAndSettle();
        expect(
            find.text(
                'Location permission is required while delivering this shipment.'),
            findsOneWidget);
      }
      await tester.scrollUntilVisible(find.textContaining('Status only'), 200);
      expect(find.textContaining('Status only'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
      api.dispose();
    });
  }

  testWidgets('tracking API failure gives a retry state', (tester) async {
    final api = ApiClient(
        client: MockClient((_) async => http.Response(
            '{"message":"Tracking temporarily unavailable"}', 503)))
      ..user = {'role': 'logistics'}
      ..token = 'test';
    await tester.pumpWidget(MaterialApp(
        home: TrackingScreen(
            api: api, id: 1, mapsConfigured: () async => false)));
    await tester.pumpAndSettle();
    expect(find.text('Unable to complete this request. Please try again.'),
        findsOneWidget);
    expect(find.text('Try Again'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    api.dispose();
  });
}
