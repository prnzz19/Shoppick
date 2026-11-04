import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';
import 'package:shoppick_logistics/services/api_client.dart';
import 'package:shoppick_logistics/screens/delivery_detail_screen.dart';
import 'package:shoppick_logistics/screens/rider/portal_shell.dart';
import 'package:shoppick_logistics/screens/rider/portal_support.dart';
import 'package:shoppick_logistics/screens/rider/assignment_detail_screen.dart';
import 'package:shoppick_logistics/screens/rider/parcel_scan_screen.dart';
import 'package:shoppick_logistics/screens/rider/proof_screen.dart';
import 'package:shoppick_logistics/screens/tracking_screen.dart';

final version = 'a' * 64;
Map<String, dynamic> job(
        {int id = 71,
        List<String> actions = const ['confirm_pickup'],
        String status = 'pickup_accepted',
        String type = 'pickup'}) =>
    {
      'id': id,
      'shipment_number': 'TEST-PARCEL-$id',
      'parcel_code': 'TEST-CODE-$id',
      'assignment_type': type,
      'assignment_status': 'accepted',
      'status': status,
      'version': version,
      'assigned_at': '2026-10-29T09:00:00Z',
      'provider': {'id': 9, 'name': 'Synthetic Logistics'},
      'pickup': {'address': 'Synthetic seller address', 'snapshot': {}},
      'dropoff': {'address': 'Synthetic delivery address', 'snapshot': {}},
      'destination_center': {
        'id': 4,
        'name': 'Test Sorting Center',
        'code': 'TEST'
      },
      'allowed_actions': actions,
      'cod': {
        'required': true,
        'amount': '125.50',
        'status': 'pending',
        'payment_id': 8
      }
    };
http.Response jsonResponse(Object body, [int status = 200]) =>
    http.Response(jsonEncode(body), status,
        headers: {'content-type': 'application/json'});
ApiClient portal(Future<http.Response> Function(http.Request) handler) =>
    ApiClient(client: MockClient(handler))
      ..portalV1 = true
      ..token = 'synthetic-token'
      ..user = {
        'id': 7,
        'name': 'Synthetic Rider',
        'roles': ['rider'],
        'role': 'rider',
        'email': 'rider@example.test'
      };
http.Response defaultResponse(http.Request request) {
  final path = request.url.path;
  if (path.endsWith('/profile')) {
    return jsonResponse({
      'user': {
        'id': 7,
        'name': 'Synthetic Rider',
        'email': 'rider@example.test',
        'roles': ['rider']
      }
    });
  }
  if (path.endsWith('/jobs/71')) {
    return jsonResponse({'success': true, 'data': job()});
  }
  if (path.endsWith('/jobs')) {
    return jsonResponse({
      'success': true,
      'data': [job()],
      'meta': {'current_page': 1, 'last_page': 1, 'total': 1}
    });
  }
  if (path.endsWith('/history')) {
    return jsonResponse({
      'success': true,
      'data': [],
      'meta': {'current_page': 1, 'last_page': 1, 'total': 0}
    });
  }
  return jsonResponse({'success': true});
}

Widget host(Widget child, {double scale = 1, GlobalKey? boundary}) =>
    MaterialApp(
        home: Builder(
            builder: (context) => Theme(
                data: riderTheme(context),
                child: MediaQuery(
                    data: MediaQuery.of(context)
                        .copyWith(textScaler: TextScaler.linear(scale)),
                    child: RepaintBoundary(
                        key: boundary, child: Scaffold(body: child))))));
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(() => FlutterSecureStorage.setMockInitialValues({}));
  test('latest assignment normalization strips parcel verification code', () {
    final api = portal((_) async => jsonResponse({}));
    expect(api.portalDelivery(job()), isNot(contains('parcel_code')));
  });
  test(
      'latest login falls back only on route 404, verifies active Rider access and persists contract',
      () async {
    final calls = <String>[];
    final api = ApiClient(client: MockClient((r) async {
      calls.add('${r.method} ${r.url.path}');
      if (r.url.path.endsWith('/logistics/login')) return jsonResponse({}, 404);
      if (r.url.path.endsWith('/login')) {
        return jsonResponse({
          'token': 'synthetic-token',
          'user': {
            'id': 7,
            'roles': ['buyer', 'rider'],
            'name': 'Synthetic Rider'
          }
        }, 201);
      }
      expect(r.headers['Authorization'], 'Bearer synthetic-token');
      return defaultResponse(r);
    }));
    await api.login('rider@example.test', 'synthetic-password');
    expect(calls, [
      'POST /api/v1/logistics/login',
      'POST /api/v1/login',
      'GET /api/v1/rider/jobs'
    ]);
    expect(api.portalV1, isTrue);
    expect(api.user?['role'], 'rider');
    expect(await api.storage.read(key: 'rider_api_contract'), 'portal-v1');
  });
  test(
      'saved legacy Rider token can restore after backend upgrades to latest routes',
      () async {
    FlutterSecureStorage.setMockInitialValues(
        {'logistics_token': 'synthetic-token'});
    final paths = <String>[];
    final api = ApiClient(client: MockClient((r) async {
      paths.add(r.url.path);
      if (r.url.path.endsWith('/logistics/profile')) {
        return jsonResponse({}, 404);
      }
      return defaultResponse(r);
    }));
    await api.restore();
    expect(api.portalV1, isTrue);
    expect(api.user?['role'], 'rider');
    expect(paths,
        ['/api/v1/logistics/profile', '/api/v1/profile', '/api/v1/rider/jobs']);
    expect(await api.storage.read(key: 'rider_api_contract'), 'portal-v1');
  });
  test('invalid credentials do not fall back to another login endpoint',
      () async {
    var requests = 0;
    final api = ApiClient(client: MockClient((r) async {
      requests++;
      return jsonResponse({'message': 'Incorrect credentials.'}, 422);
    }));
    await expectLater(api.login('rider@example.test', 'incorrect'),
        throwsA(isA<ApiFailure>()));
    expect(requests, 1);
    expect(api.token, isNull);
  });
  test('generic role payload requires Rider and forbids staff role crossover',
      () {
    for (final roles in [
      <String>[],
      ['buyer'],
      ['seller', 'rider'],
      ['logistics', 'rider'],
      ['admin', 'rider']
    ]) {
      expect(
          () => portalRiderUser({'roles': roles}), throwsA(isA<ApiFailure>()));
    }
    expect(
        portalRiderUser({
          'roles': ['rider']
        })['role'],
        'rider');
  });
  test('restore uses saved latest contract and expires unauthorized sessions',
      () async {
    FlutterSecureStorage.setMockInitialValues({
      'logistics_token': 'synthetic-token',
      'rider_api_contract': 'portal-v1'
    });
    final api = portal((r) async {
      expect(r.url.path, '/api/v1/profile');
      return jsonResponse({'message': 'Session expired.'}, 401);
    });
    await api.restore();
    expect(api.token, isNull);
    expect(api.user, isNull);
    expect(await api.storage.read(key: 'logistics_token'), isNull);
  });
  test(
      'latest payload normalizes decimal COD without inventing a hub pickup address',
      () {
    final api = portal((r) async => defaultResponse(r));
    final result = api.portalDelivery(job(type: 'delivery'));
    expect(result['cod_amount'], 125.5);
    expect(result['pickup_address'], isNull);
    expect(result['pickup_coordinates'], isNull);
    expect(result['hub'], 'Test Sorting Center');
  });
  test(
      'dashboard summaries retrieve every page rather than reporting a partial total',
      () async {
    final pages = <String?>[];
    final api = portal((r) async {
      pages.add(r.url.queryParameters['page']);
      final page = int.parse(r.url.queryParameters['page']!);
      return jsonResponse({
        'success': true,
        'data': [job(id: 70 + page)],
        'meta': {'current_page': page, 'last_page': 2, 'total': 2}
      });
    });
    final rows = await api.allRiderRecords();
    expect(rows.map((r) => r['id']), [71, 72]);
    expect(pages, ['1', '2']);
  });
  for (final action in [
    'accept_pickup',
    'accept_delivery',
    'decline',
    'confirm_pickup',
    'arrive_sorting',
    'failed',
    'collect_cod',
    'delivered'
  ]) {
    test('latest $action sends version to the approved endpoint only',
        () async {
      http.Request? sent;
      final api = portal((r) async {
        sent = r;
        return jsonResponse({'success': true, 'data': job()});
      });
      api.portalDelivery(job());
      if (action.startsWith('accept_')) {
        await api.acceptRiderAssignment(71, action);
      } else if (action == 'decline') {
        await api.declineRiderAssignment(
            71, 'vehicle_issue', 'Synthetic reason');
      } else {
        await api.performRiderDeliveryAction(71, action,
            parcelCode: action == 'confirm_pickup' ? ' TEST-CODE-71 ' : null,
            reason: action == 'failed' ? 'recipient_unavailable' : null);
      }
      final endpoint = action.startsWith('accept_')
          ? 'accept'
          : action == 'decline'
              ? 'decline'
              : action == 'collect_cod'
                  ? 'cod'
                  : 'action';
      expect(sent!.method, 'POST');
      expect(sent!.url.path, '/api/v1/rider/shipments/71/$endpoint');
      final body = jsonDecode(sent!.body) as Map;
      expect(body['version'], version);
      expect(body.containsKey('amount'), isFalse);
      expect(body.containsKey('shipment_id'), isFalse);
      if (action == 'confirm_pickup') {
        expect(body['parcel_code'], 'TEST-CODE-71');
      }
      if (endpoint != 'action') expect(body.containsKey('action'), isFalse);
    });
  }
  test(
      'destination handoff requires a code in the verified contract and never supports legacy_out',
      () async {
    final portalCalls = <http.Request>[];
    final portalApi = portal((r) async {
      portalCalls.add(r);
      return defaultResponse(r);
    });
    expect(
        () => portalApi.performRiderDeliveryAction(71, 'start_delivery',
            parcelCode: 'TEST-CODE-71'),
        throwsA(isA<ApiFailure>()));
    expect(() => portalApi.performRiderDeliveryAction(71, 'legacy_out'),
        throwsA(isA<ApiFailure>()));
    expect(portalCalls, isEmpty);

    final legacyCalls = <http.Request>[];
    final legacyApi = ApiClient(client: MockClient((r) async {
      legacyCalls.add(r);
      return defaultResponse(r);
    }))
      ..token = 'synthetic-token'
      ..user = {'id': 7, 'role': 'rider'};
    expect(() => legacyApi.performRiderDeliveryAction(71, 'start_delivery'),
        throwsA(isA<ApiFailure>()));
    expect(
        () => legacyApi.performRiderDeliveryAction(71, 'legacy_out',
            parcelCode: 'TEST-CODE-71'),
        throwsA(isA<ApiFailure>()));
    expect(legacyCalls, isEmpty);
    await legacyApi.performRiderDeliveryAction(71, 'start_delivery',
        parcelCode: 'TEST-CODE-71');
    expect(legacyCalls.single.url.path, '/api/v1/rider/deliveries/71/status');
    expect(jsonDecode(legacyCalls.single.body)['parcel_code'], 'TEST-CODE-71');
  });
  test('missing version requires refresh and causes no mutation', () async {
    var requests = 0;
    final api = portal((r) async {
      requests++;
      return defaultResponse(r);
    });
    await expectLater(api.acceptRiderAssignment(71, 'accept_pickup'),
        throwsA(isA<ApiFailure>()));
    expect(requests, 0);
  });
  for (final status in [403, 404, 409, 422, 429]) {
    test('action $status is surfaced without automatic mutation replay',
        () async {
      var requests = 0;
      final api = portal((r) async {
        requests++;
        return jsonResponse(
            {'message': 'Assignment updated elsewhere.'}, status);
      });
      api.portalDelivery(job());
      await expectLater(
          api.performRiderDeliveryAction(71, 'collect_cod'),
          throwsA(isA<ApiFailure>()
              .having((e) => e.status, 'HTTP status', status)));
      expect(requests, 1);
      expect(api.token, 'synthetic-token');
    });
  }
  test('latest availability accepts only confirmed writable states', () async {
    final api = portal((r) async {
      expect(r.method, 'PATCH');
      expect(r.url.path, '/api/v1/rider/availability');
      return jsonResponse({
        'success': true,
        'data': {'availability': 'off_duty'}
      });
    });
    expect(await api.setRiderAvailability('off_duty'), 'off_duty');
    expect(api.user?['availability'], 'off_duty');
    await expectLater(
        api.setRiderAvailability('busy'), throwsA(isA<ApiFailure>()));
  });
  test(
      'location uses approved Rider endpoint and rejects invalid coordinates before HTTP',
      () async {
    var calls = 0;
    final api = portal((r) async {
      calls++;
      expect(r.url.path, '/api/v1/rider/location');
      expect(r.method, 'PATCH');
      return jsonResponse({
        'success': true,
        'data': {'latitude': 14.5, 'longitude': 121.0}
      });
    });
    await api.shareRiderLocation(14.5, 121);
    await expectLater(
        api.shareRiderLocation(91, 121), throwsA(isA<ApiFailure>()));
    expect(calls, 1);
  });
  test('legacy location sharing sends a timestamped update for one delivery',
      () async {
    final requests = <http.Request>[];
    final api = ApiClient(client: MockClient((r) async {
      requests.add(r);
      return jsonResponse({'message': 'Location recorded.'}, 201);
    }))
      ..token = 'synthetic-token'
      ..user = {'id': 7, 'role': 'rider'};
    final recordedAt = DateTime.utc(2026, 10, 29, 12);
    await api.shareRiderLocationForDelivery(71, 14.5, 121,
        accuracy: 8, recordedAt: recordedAt);
    expect(requests, hasLength(1));
    expect(requests.single.method, 'POST');
    expect(requests.single.url.path, '/api/v1/rider/deliveries/71/location');
    expect(requests.single.headers['Authorization'], 'Bearer synthetic-token');
    expect(jsonDecode(requests.single.body), {
      'latitude': 14.5,
      'longitude': 121.0,
      'accuracy': 8.0,
      'recorded_at': recordedAt.toIso8601String(),
    });
    await expectLater(api.shareRiderLocationForDelivery(72, 91, 121),
        throwsA(isA<ApiFailure>()));
    expect(requests, hasLength(1));
  });
  test('POD requires fresh authorization and posts versioned multipart photo',
      () async {
    final requests = <http.Request>[];
    final api = portal((r) async {
      requests.add(r);
      if (r.method == 'GET') {
        return jsonResponse({
          'success': true,
          'data': job(actions: ['submit_pod'])
        });
      }
      expect(r.url.path, '/api/v1/rider/shipments/71/pod');
      expect(r.headers['Authorization'], 'Bearer synthetic-token');
      expect(r.headers['content-type'], startsWith('multipart/form-data'));
      final multipart = utf8.decode(r.bodyBytes, allowMalformed: true);
      expect(multipart, contains('name="recipient_name"'));
      expect(multipart, contains('Synthetic Recipient'));
      expect(multipart, contains(version));
      expect(multipart, contains('filename="delivery.png"'));
      return jsonResponse({
        'success': true,
        'data': {'id': 1}
      });
    });
    await api.upload(
        71,
        XFile.fromData(
            base64Decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP9sAAAAASUVORK5CYII='),
            path: 'proof.png'),
        'Synthetic Recipient',
        'Synthetic note',
        version: version);
    expect(requests.map((r) => r.method), ['GET', 'POST']);
  });
  test('POD stale or unauthorized never sends an upload', () async {
    var writes = 0;
    final api = portal((r) async {
      if (r.method != 'GET') writes++;
      return jsonResponse({'success': true, 'data': job(actions: [])});
    });
    final file =
        XFile.fromData(Uint8List.fromList([1, 2, 3]), path: 'proof.png');
    await expectLater(
        api.upload(71, file, 'Recipient', '', version: 'b' * 64),
        throwsA(
            isA<ApiFailure>().having((e) => e.status, 'stale status', 409)));
    await expectLater(
        api.upload(71, file, 'Recipient', ''), throwsA(isA<ApiFailure>()));
    expect(writes, 0);
  });
  test('POD rejects wrong type and oversized photos without sending multipart',
      () async {
    var writes = 0;
    final api = portal((r) async {
      if (r.method != 'GET') writes++;
      return jsonResponse({
        'success': true,
        'data': job(actions: ['submit_pod'])
      });
    });
    await expectLater(
        api.upload(
            71,
            XFile.fromData(Uint8List.fromList([1]), path: 'proof.pdf'),
            'Recipient',
            ''),
        throwsA(isA<ApiFailure>()));
    await expectLater(
        api.upload(
            71,
            XFile.fromData(Uint8List(5 * 1024 * 1024 + 1), path: 'proof.jpg'),
            'Recipient',
            ''),
        throwsA(isA<ApiFailure>()));
    expect(writes, 0);
  });
  test('failed Rider logout retains token, confirmed logout removes it',
      () async {
    var succeeds = false;
    final api = portal((r) async => succeeds
        ? jsonResponse({'message': 'Logged out.'})
        : jsonResponse({}, 503));
    await expectLater(api.logout(), throwsA(isA<ApiFailure>()));
    expect(api.token, 'synthetic-token');
    succeeds = true;
    await api.logout();
    expect(api.token, isNull);
    expect(api.user, isNull);
  });
  test(
      'malformed success and false confirmation cannot report a successful save',
      () async {
    for (final response in [
      http.Response('<html>error</html>', 200),
      jsonResponse({'success': false})
    ]) {
      final api = portal((r) async => response);
      await expectLater(api.request('POST', 'rider/shipments/71/cod'),
          throwsA(isA<ApiFailure>()));
    }
  });
  testWidgets(
      'latest tracking route supplies its own page and renders actual status',
      (tester) async {
    final api = portal((r) async => defaultResponse(r));
    await tester.pumpWidget(MaterialApp(home: RiderTracking(api: api, id: 71)));
    await tester.pumpAndSettle();
    expect(find.byType(Scaffold), findsOneWidget);
    expect(find.text('TEST-PARCEL-71'), findsOneWidget);
    expect(find.text('Tracking'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets(
      'legacy tracking keeps the existing GPS-capable screen accessible',
      (tester) async {
    const mapsChannel = MethodChannel('shoppick/maps');
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(mapsChannel, (_) async => false);
    addTearDown(() => TestDefaultBinaryMessengerBinding
        .instance.defaultBinaryMessenger
        .setMockMethodCallHandler(mapsChannel, null));
    final paths = <String>[];
    final api = portal((r) async {
      paths.add(r.url.path);
      return jsonResponse({}, 404);
    })
      ..portalV1 = false;
    await tester.pumpWidget(MaterialApp(home: RiderTracking(api: api, id: 71)));
    await tester.pumpAndSettle();
    expect(find.byType(TrackingScreen), findsOneWidget);
    expect(paths, contains('/api/v1/rider/deliveries/71/tracking'));
    expect(tester.takeException(), isNull);
  });
  testWidgets(
      'Logistics hub scanning uses its role-scoped API action and hides expected code',
      (tester) async {
    final staffDelivery = <String, dynamic>{
      'id': 71,
      'tracking_number': 'TEST-PARCEL-71',
      'parcel_code': 'SECRET-EXPECTED-CODE',
      'status': 'at_sorting_center',
      'actions': ['scan'],
      'events': <dynamic>[],
      'products': <dynamic>[],
    };
    final requests = <http.Request>[];
    final api = ApiClient(client: MockClient((request) async {
      requests.add(request);
      if (request.method == 'GET') {
        return jsonResponse({'delivery': staffDelivery});
      }
      return jsonResponse({'delivery': staffDelivery});
    }))
      ..token = 'synthetic-token'
      ..user = {'id': 8, 'name': 'Synthetic Staff', 'role': 'logistics'};
    await tester.pumpWidget(MaterialApp(
        home: DeliveryDetailScreen(api: api, id: 71, showTracking: false)));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
        find.text('Confirm parcel code'), 250,
        scrollable: find
            .descendant(
                of: find.byType(ListView).first,
                matching: find.byType(Scrollable))
            .first);
    expect(find.text('SECRET-EXPECTED-CODE'), findsNothing);
    await tester.tap(find.text('Confirm parcel code'));
    await tester.pumpAndSettle();
    expect(find.text('Logistics hub scan'), findsOneWidget);
    expect(find.textContaining('authorized Logistics receipt step'),
        findsOneWidget);
    await tester.enterText(
        find.byKey(const ValueKey('manual-parcel-code')), 'PHYSICAL-CODE-71');
    await tester.tap(find.text('Preview entered code'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Submit code'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirm'));
    await tester.pumpAndSettle();
    final mutation =
        requests.singleWhere((request) => request.method == 'PATCH');
    expect(mutation.url.path, '/api/v1/logistics/deliveries/71/status');
    expect(jsonDecode(mutation.body), {
      'action': 'scan',
      'parcel_code': 'PHYSICAL-CODE-71',
    });
  });
  testWidgets(
      'manual scan previews exact code and requires explicit confirmation',
      (tester) async {
    String? code;
    await tester.pumpWidget(MaterialApp(
        home: Builder(
            builder: (context) => Scaffold(
                body: FilledButton(
                    onPressed: () async {
                      code = await Navigator.push<String>(
                          context,
                          MaterialPageRoute(
                              builder: (_) => const ParcelScanScreen(
                                  trackingNumber: 'TEST-PARCEL-71')));
                    },
                    child: const Text('Open scanner'))))));
    await tester.tap(find.text('Open scanner'));
    await tester.pumpAndSettle();
    expect(find.text('Enable camera'), findsOneWidget);
    await tester.enterText(
        find.byKey(const ValueKey('manual-parcel-code')), 'TEST-CODE-71');
    await tester.ensureVisible(find.text('Preview entered code'));
    await tester.tap(find.text('Preview entered code'));
    await tester.pumpAndSettle();
    expect(code, isNull);
    expect(find.text('Confirm parcel code'), findsOneWidget);
    await tester.tap(find.text('Scan again'));
    await tester.pumpAndSettle();
    expect(code, isNull);
    await tester.tap(find.text('Preview entered code'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Submit code'));
    await tester.pumpAndSettle();
    expect(code, 'TEST-CODE-71');
  });
  testWidgets('destination scan remains disabled without a verified API',
      (tester) async {
    final api = portal((r) async => jsonResponse({
          'success': true,
          'data': job(type: 'delivery', actions: ['start_delivery'])
        }));
    await tester
        .pumpWidget(host(RiderAssignmentDetailScreen(api: api, id: 71)));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
        find.textContaining('parcel code at hub handoff'), 400,
        scrollable: find
            .descendant(
                of: find.byType(ListView).first,
                matching: find.byType(Scrollable))
            .first);
    expect(find.textContaining('parcel code at hub handoff'), findsOneWidget);
    final buttons = tester.widgetList<FilledButton>(find.byType(FilledButton));
    expect(buttons.every((b) => b.onPressed == null), isTrue);
  });
  for (final scale in [1.0, 1.8]) {
    testWidgets('all Rider modules fit 320px with ${scale}x text',
        (tester) async {
      tester.view.physicalSize = const Size(320, 900);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      var writes = 0;
      final api = portal((r) async {
        if (r.method != 'GET') writes++;
        return defaultResponse(r);
      });
      final pages = <Widget>[
        RiderPortalShell(api: api),
        RiderJobList(api: api),
        RiderJobList(api: api, kind: 'pickups'),
        RiderJobList(api: api, kind: 'active'),
        RiderJobList(api: api, kind: 'history'),
        RiderTracking(api: api, id: 71),
        RiderNotifications(api: api),
        RiderSupport(api: api),
        RiderProfile(api: api),
        RiderRecords(api: api, kind: 'pod'),
        RiderRecords(api: api, kind: 'cod'),
        RiderSettings(api: api),
        RiderMore(api: api),
        const ParcelScanScreen(trackingNumber: 'TEST-PARCEL-71'),
        ProofScreen(api: api, deliveryId: 71)
      ];
      for (final page in pages) {
        await tester.pumpWidget(host(page, scale: scale));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull,
            reason: '${page.runtimeType} initial layout');
        final lists = find.byType(ListView);
        if (lists.evaluate().isNotEmpty) {
          for (var i = 0; i < 5; i++) {
            await tester.drag(lists.first, const Offset(0, -360));
            await tester.pumpAndSettle();
            expect(tester.takeException(), isNull,
                reason: '${page.runtimeType} scrolling');
          }
        }
        await tester.pumpWidget(const SizedBox());
        await tester.pumpAndSettle();
      }
      expect(writes, 0);
    });
  }
  testWidgets('More navigation opens each supported or unavailable module',
      (tester) async {
    final api = portal((r) async => defaultResponse(r));
    await tester.pumpWidget(host(RiderMore(api: api)));
    await tester.pumpAndSettle();
    for (final title in [
      'Pickups',
      'Active Delivery',
      'Tracking',
      'Notifications',
      'Logistics Support',
      'Profile',
      'Proof of Delivery',
      'COD Collections',
      'Settings'
    ]) {
      await tester.scrollUntilVisible(find.text(title), 200);
      await tester.tap(find.text(title));
      await tester.pumpAndSettle();
      expect(find.byType(AppBar), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pageBack();
      await tester.pumpAndSettle();
    }
  });
  if (const bool.fromEnvironment('RIDER_CAPTURE')) {
    testWidgets('export synthetic phone visual evidence', (tester) async {
      tester.view.physicalSize = const Size(390, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.runAsync(() async {
        final font = FontLoader('RiderEvidence')
          ..addFont(File('C:/Windows/Fonts/segoeui.ttf')
              .readAsBytes()
              .then((b) => ByteData.sublistView(b)));
        await font.load();
        final icons = FontLoader('MaterialIcons')
          ..addFont(File(
                  'C:/src/flutter_windows_3.47.5-stable/flutter/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf')
              .readAsBytes()
              .then((b) => ByteData.sublistView(b)));
        await icons.load();
      });
      final api = portal((r) async => defaultResponse(r));
      final pages = <String, Widget>{
        'dashboard': RiderPortalShell(api: api),
        'deliveries': RiderJobList(api: api),
        'scan': const ParcelScanScreen(trackingNumber: 'TEST-PARCEL-71'),
        'profile': RiderProfile(api: api),
        'cod': RiderRecords(api: api, kind: 'cod'),
        'settings': RiderSettings(api: api),
        'support': RiderSupport(api: api),
        'pod': ProofScreen(api: api, deliveryId: 71)
      };
      for (final page in pages.entries) {
        final key = GlobalKey();
        await tester.pumpWidget(MaterialApp(
            theme: ThemeData(fontFamily: 'RiderEvidence'),
            home: RepaintBoundary(
                key: key,
                child: Scaffold(
                    body: Column(children: [
                  Container(
                      color: Colors.amber.shade100,
                      padding: const EdgeInsets.all(8),
                      child: const Text('SYNTHETIC UI TEST | No live backend',
                          style: TextStyle(fontSize: 11))),
                  Expanded(child: page.value)
                ])))));
        await tester.pumpAndSettle();
        final boundary =
            key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
        await tester.runAsync(() async {
          final image = await boundary.toImage(pixelRatio: 2);
          final data = await image.toByteData(format: ui.ImageByteFormat.png);
          final file = File('build/rider-upgrade/screenshots/${page.key}.png');
          await file.parent.create(recursive: true);
          await file.writeAsBytes(data!.buffer.asUint8List());
          image.dispose();
        });
        await tester.pumpWidget(const SizedBox());
        await tester.pumpAndSettle();
      }
    });
  }
}
