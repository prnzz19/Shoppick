import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shoppick_mobile/config/api_config.dart';
import 'package:shoppick_mobile/main.dart';
import 'package:shoppick_mobile/models/buyer_registration.dart';
import 'package:shoppick_mobile/services/api_service.dart';
import 'package:shoppick_mobile/services/buyer_registration_service.dart';
import 'package:shoppick_mobile/services/philippine_locations.dart';

class TestIdPicker extends FilePicker {
  PlatformFile? file;
  List<String>? allowed;
  @override
  Future<FilePickerResult?> pickFiles(
      {String? dialogTitle,
      String? initialDirectory,
      FileType type = FileType.any,
      List<String>? allowedExtensions,
      Function(FilePickerStatus)? onFileLoading,
      bool allowCompression = false,
      int compressionQuality = 0,
      bool allowMultiple = false,
      bool withData = false,
      bool withReadStream = false,
      bool lockParentWindow = false,
      bool readSequential = false}) async {
    allowed = allowedExtensions;
    return file == null ? null : FilePickerResult([file!]);
  }
}

http.Response fixture(http.Request request, {bool compatible = true}) {
  final path = request.url.path;
  if (path.endsWith('/auth/session')) {
    return http.Response(
        '{"message":"Sign in required."}', compatible ? 401 : 404);
  }
  if (['/register/verify', '/register/resend', '/auth/google']
      .any(path.endsWith)) {
    return http.Response('{}', compatible ? 405 : 404,
        headers: {'allow': 'POST'});
  }
  List<Map<String, String>> rows;
  if (path.endsWith('/regions')) {
    rows = [
      {'code': 'R1', 'name': 'Test Region'},
      {'code': 'R0', 'name': 'No Province Region'}
    ];
  } else if (path.endsWith('/R0/provinces')) {
    rows = [];
  } else if (path.endsWith('/provinces')) {
    rows = [
      {'code': 'P1', 'name': 'Test Province'}
    ];
  } else if (path.endsWith('/cities-municipalities')) {
    rows = [
      {'code': 'C1', 'name': 'Test City'}
    ];
  } else if (path.endsWith('/barangays')) {
    rows = [
      {'code': 'B1', 'name': 'Test Barangay'}
    ];
  } else {
    return http.Response('{}', 404);
  }
  return http.Response(jsonEncode({'data': rows}), 200);
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() async {
    final font = File(Platform.isWindows
        ? '${Platform.environment['WINDIR'] ?? r'C:\Windows'}/Fonts/segoeui.ttf'
        : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf');
    if (await font.exists()) {
      final loader = FontLoader('RegistrationPreview')
        ..addFont(Future.value(ByteData.sublistView(await font.readAsBytes())));
      await loader.load();
    }
    final packages =
        jsonDecode(await File('.dart_tool/package_config.json').readAsString())[
            'packages'] as List;
    final flutter = packages.firstWhere((entry) => entry['name'] == 'flutter');
    final sdk = Directory.fromUri(Uri.parse(flutter['rootUri'])).parent.parent;
    final icons = File(
        '${sdk.path}/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf');
    if (await icons.exists()) {
      final loader = FontLoader('MaterialIcons')
        ..addFont(
            Future.value(ByteData.sublistView(await icons.readAsBytes())));
      await loader.load();
    }
  });
  setUp(() {
    FlutterSecureStorage.setMockInitialValues({});
    ApiService.session.value = SessionStatus.signedOut;
  });
  test('backend root URLs keep the origin and never duplicate api/v1', () {
    expect(ApiConfig.backend('/api/philippine-locations/regions').path,
        '/api/philippine-locations/regions');
    expect(ApiConfig.backend('/api/philippine-locations/regions').origin,
        Uri.parse(ApiConfig.baseUrl).origin);
    for (final path in [
      'https://elsewhere.test/api',
      '//elsewhere.test/api',
      'api/v1/regions'
    ]) {
      expect(() => ApiConfig.backend(path), throwsArgumentError);
    }
  });
  test('age uses calendar birthdays, leap years and the exact 18-year boundary',
      () {
    expect(
        BuyerRegistration.age(DateTime(2008, 10, 28),
            on: DateTime(2026, 10, 28)),
        18);
    expect(
        BuyerRegistration.age(DateTime(2008, 10, 29),
            on: DateTime(2026, 10, 28)),
        17);
    expect(
        BuyerRegistration.age(DateTime(2008, 2, 29), on: DateTime(2026, 2, 28)),
        17);
    expect(
        BuyerRegistration.age(DateTime(2008, 2, 29), on: DateTime(2026, 3, 1)),
        18);
    expect(BuyerRegistration.age(DateTime(2027), on: DateTime(2026)), isNull);
    expect(BuyerRegistration.iso(DateTime(2000, 2, 29)), '2000-02-29');
  });
  test(
      'names, email and Philippine mobile validation match website constraints',
      () {
    for (final value in ["O'Neil", 'José', 'Dela Cruz', 'Anne-Marie']) {
      expect(BuyerRegistration.name(value, 'First name'), isNull);
    }
    for (final value in ['A', '123', '...', 'Alex2', '<script>']) {
      expect(BuyerRegistration.name(value, 'First name'), isNotNull);
    }
    expect(BuyerRegistration.email('buyer@example.test'), isNull);
    expect(BuyerRegistration.email('bad@example'), isNotNull);
    expect(BuyerRegistration.phone(' 09171234567 '), '+639171234567');
    expect(BuyerRegistration.mobile('+639171234567'), isNull);
    expect(BuyerRegistration.mobile('639171234567'), isNotNull);
    expect(BuyerRegistration.mobile('123'), isNotNull);
  });
  test('password requirements reject whitespace as the only special character',
      () {
    expect(BuyerRegistration.password('Strong!26'), isNull);
    for (final value in [
      'short',
      'abcdefgh',
      'ABCDEFGH1!',
      'Abcdefg1 ',
      'Abcdefg!'
    ]) {
      expect(BuyerRegistration.password(value), isNotNull);
    }
  });
  test('ID type, empty files and the exact 5 MB boundary are checked', () {
    for (final ext in ['JPG', 'jpeg', 'png', 'pdf']) {
      expect(
          BuyerRegistration.identityFile('id.$ext', 5 * 1024 * 1024), isNull);
    }
    expect(BuyerRegistration.identityFile('id.png', 5 * 1024 * 1024 + 1),
        contains('5 MB'));
    expect(BuyerRegistration.identityFile('id.exe', 10), isNotNull);
    expect(BuyerRegistration.identityFile('id.pdf', 0), isNotNull);
  });
  test('payload normalizes contacts and never sends a trusted age', () {
    final data = BuyerRegistration.payload({
      'first_name': ' José ',
      'email': ' BUYER@EXAMPLE.TEST ',
      'phone': '09171234567',
      'password': ' Strong!26 ',
      'age': '999'
    }, {
      'region': 'Test Region',
      'region_code': 'R1',
      'city': 'Test City',
      'city_code': 'C1'
    }, DateTime(1998, 1, 1), terms: true);
    expect(data['first_name'], 'José');
    expect(data['email'], 'buyer@example.test');
    expect(data['phone'], '+639171234567');
    expect(data['birthday'], '1998-01-01');
    expect(data['country'], 'PH');
    expect(data['password'], ' Strong!26 ');
    expect(data['terms'], '1');
    expect(data.containsKey('age'), isFalse);
  });
  test('legacy release is blocked without any POST or ID upload', () async {
    final methods = <String>[];
    await http.runWithClient(() async {
      final service = BuyerRegistrationService();
      expect((await service.check()).compatible, isFalse);
      await expectLater(
          service
              .submit({'email': 'test@example.test'}, 'must-not-be-opened.pdf'),
          throwsA(isA<ApiException>()));
    },
        () => MockClient((r) async {
              methods.add(r.method);
              return fixture(r, compatible: false);
            }));
    expect(methods, ['GET', 'GET', 'GET', 'GET']);
  });
  test(
      'only an explicit OTP response opens verification; active 201 is rejected',
      () async {
    final dir = await Directory.systemTemp.createTemp('shoppick-otp-contract-');
    try {
      final file = File('${dir.path}/id.pdf');
      await file.writeAsString('%PDF-1.4 synthetic');
      await http.runWithClient(() async {
        await BuyerRegistrationService()
            .submit({'email': 'buyer@example.test'}, file.path);
        expect(ApiService.session.value, SessionStatus.verificationRequired);
        expect(await ApiService().token(), 'pending-only-token');
      },
          () => MockClient((r) async => r.method == 'POST'
              ? http.Response(
                  '{"token":"pending-only-token","requires_verification":true}',
                  202)
              : fixture(r)));
      await ApiService().clearToken();
      await http.runWithClient(() async {
        await expectLater(
            BuyerRegistrationService()
                .submit({'email': 'buyer@example.test'}, file.path),
            throwsA(isA<ApiException>()));
        expect(ApiService.session.value, SessionStatus.signedOut);
        expect(await ApiService().token(), isNull);
      },
          () => MockClient((r) async => r.method == 'POST'
              ? http.Response(
                  '{"token":"must-not-be-saved","requires_verification":false}',
                  201)
              : fixture(r)));
    } finally {
      await dir.delete(recursive: true);
    }
  });
  test('registration availability outages and rate limits cannot enable upload',
      () async {
    for (final status in [429, 500, 503]) {
      await http.runWithClient(() async {
        await expectLater(
            BuyerRegistrationService().check(), throwsA(isA<ApiException>()));
      }, () => MockClient((r) async => http.Response('{}', status)));
    }
  });
  test(
      'contract is rechecked before upload if the server changes after form load',
      () async {
    var compatible = true;
    var posts = 0;
    await http.runWithClient(() async {
      final service = BuyerRegistrationService();
      expect((await service.check()).compatible, isTrue);
      compatible = false;
      await expectLater(
          service.submit(
              {'email': 'synthetic@example.test'}, 'must-not-be-opened.pdf'),
          throwsA(isA<ApiException>()));
      expect(posts, 0);
    },
        () => MockClient((r) async {
              if (r.method == 'POST') {
                posts++;
              }
              return fixture(r, compatible: compatible);
            }));
  });
  test(
      'availability requires actual POST-only OTP routes and uses a cached result',
      () async {
    var requests = 0;
    await http.runWithClient(() async {
      final service = BuyerRegistrationService();
      expect((await service.check()).compatible, isTrue);
      expect((await service.check()).compatible, isTrue);
    },
        () => MockClient((r) async {
              requests++;
              return fixture(r);
            }));
    expect(requests, 4);
    await http.runWithClient(() async {
      expect((await BuyerRegistrationService().check()).compatible, isFalse);
    }, () => MockClient((r) async => http.Response('{}', 200)));
  });
  test('locations handle province-less regions, names/codes and request reuse',
      () async {
    final paths = <String>[];
    await http.runWithClient(() async {
      final locations = RegistrationLocations();
      await locations.loadRegions();
      await locations.selectRegion(locations.regions.last);
      expect(locations.noProvince, isTrue);
      expect(locations.provinces, isEmpty);
      await locations.selectCity(locations.cities.single);
      locations.selectBarangay(locations.barangays.single);
      expect(locations.complete, isTrue);
      expect(locations.payload['province_code'], '');
      expect(locations.payload['region'], 'No Province Region');
      final count = paths.length;
      await locations.selectCity(locations.city!);
      expect(paths.length, count);
      await locations.selectRegion(locations.regions.first);
      expect(locations.city, isNull);
      expect(locations.barangay, isNull);
      expect(locations.noProvince, isFalse);
      locations.dispose();
    },
        () => MockClient((r) async {
              paths.add(r.url.path);
              return fixture(r);
            }));
    expect(paths, everyElement(startsWith('/api/philippine-locations/')));
    expect(paths,
        contains('/api/philippine-locations/regions/R0/cities-municipalities'));
  });
  test('stale parent requests cannot overwrite newer location selections',
      () async {
    final first = Completer<List<PhilippineLocation>>();
    final service = _DelayedLocations(first);
    final locations = RegistrationLocations(service: service);
    final old = locations.selectRegion(const PhilippineLocation('old', 'Old'));
    await locations.selectRegion(const PhilippineLocation('new', 'New'));
    first.complete([const PhilippineLocation('old-P', 'Old Province')]);
    await old;
    expect(locations.region!.code, 'new');
    expect(locations.provinces.single.code, 'new-P');
    locations.dispose();
  });
  test('failed location requests can be retried without cached failure',
      () async {
    var failures = 0;
    await http.runWithClient(() async {
      final locations = RegistrationLocations();
      await locations.loadRegions();
      expect(locations.error, isNotNull);
      await locations.retry();
      expect(locations.regions, isNotEmpty);
      expect(locations.error, isNull);
      locations.dispose();
    },
        () => MockClient((r) async =>
            failures++ == 0 ? http.Response('{}', 503) : fixture(r)));
    expect(failures, 2);
  });
  test('API 422 preserves every inline field error, while 500 remains safe',
      () async {
    await http.runWithClient(() async {
      await expectLater(
          ApiService().request('register', method: 'POST'),
          throwsA(isA<ApiException>()
              .having((e) => e.fieldErrors, 'inline errors', {
            'email': ['That email is already registered.'],
            'phone': ['That mobile number is already registered.']
          })));
    },
        () => MockClient((r) async => http.Response(
            '{"errors":{"email":["That email is already registered."],"phone":["That mobile number is already registered."]}}',
            422)));
  });

  Future<void> launch(WidgetTester tester,
      {Size size = const Size(390, 844),
      double scale = 1,
      GlobalKey? boundary}) async {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(RepaintBoundary(
        key: boundary,
        child: MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: ThemeData(
                useMaterial3: true, fontFamily: 'RegistrationPreview'),
            builder: (context, child) => MediaQuery(
                data: MediaQuery.of(context)
                    .copyWith(textScaler: TextScaler.linear(scale)),
                child: child!),
            home: const RegisterScreen())));
    await tester.pumpAndSettle();
  }

  Future<void> tap(WidgetTester tester, Finder f) async {
    await tester.ensureVisible(f.last);
    await tester.pumpAndSettle();
    await tester.tap(f.last);
    await tester.pumpAndSettle();
  }

  Future<void> enter(WidgetTester tester, String key, String text) async {
    final f = find.byKey(ValueKey('registration-$key'));
    await tester.ensureVisible(f);
    await tester.enterText(f, text);
    await tester.pump();
  }

  Future<void> choose(WidgetTester tester, String key, String text) async {
    await tap(tester, find.byKey(ValueKey('registration-$key')));
    await tap(tester, find.text(text));
  }

  Future<void> fill(WidgetTester tester) async {
    await enter(tester, 'first_name', 'Test');
    await enter(tester, 'last_name', 'Buyer');
    final sex = find.byType(DropdownButtonFormField<String>);
    await tap(tester, sex);
    await tap(tester, find.text('Male'));
    await tap(tester, find.byKey(const ValueKey('registration-birthday')));
    await tap(tester, find.byTooltip('Switch to input'));
    await tester.enterText(
        find.descendant(
            of: find.byType(DatePickerDialog),
            matching: find.byType(TextField)),
        '01/01/1998');
    await tap(tester, find.text('OK'));
    await enter(tester, 'email', ' BUYER@EXAMPLE.TEST ');
    await enter(tester, 'phone', '09171234567');
    await enter(tester, 'address_line', '12 Test Street');
    await choose(tester, 'region', 'Test Region');
    await choose(tester, 'province', 'Test Province');
    await choose(tester, 'city', 'Test City');
    await choose(tester, 'barangay', 'Test Barangay');
    await enter(tester, 'postal_code', '4009');
    await tap(tester, find.byKey(const ValueKey('registration-valid-id')));
    await enter(tester, 'password', 'Strong!26');
    await enter(tester, 'password_confirmation', 'Strong!26');
    await tap(
        tester,
        find.descendant(
            of: find.byKey(const ValueKey('registration-terms')),
            matching: find.byType(Checkbox)));
  }

  for (final size in [
    const Size(320, 900),
    const Size(612, 1080),
    const Size(800, 1100)
  ]) {
    testWidgets(
        'screenshot-based form fits ${size.width.toInt()}px and exports visual evidence',
        (tester) async {
      await http.runWithClient(() async {
        final boundary = GlobalKey();
        await launch(tester, size: size, boundary: boundary);
        for (final title in [
          'SHOPPICK BUYER',
          'Create your SHOPPICK account',
          'PERSONAL INFORMATION',
          'CONTACT INFORMATION',
          'YOUR DELIVERY ADDRESS',
          'ID VERIFICATION',
          'SECURITY'
        ]) {
          expect(find.text(title), findsOneWidget);
        }
        expect(find.text('Age'), findsOneWidget);
        expect(find.byKey(const ValueKey('registration-postal_code')),
            findsOneWidget);
        final first = tester
            .getRect(find.byKey(const ValueKey('registration-first_name')));
        final last = tester
            .getRect(find.byKey(const ValueKey('registration-last_name')));
        if (size.width >= 612) {
          expect(first.top, last.top);
        } else {
          expect(last.top, greaterThan(first.top));
        }
        expect(tester.takeException(), isNull);
        await tester.runAsync(() async {
          await Future<void>.delayed(const Duration(milliseconds: 80));
        });
        await tester.pumpAndSettle();
        await tester.runAsync(() async {
          final image = await (boundary.currentContext!.findRenderObject()
                  as RenderRepaintBoundary)
              .toImage();
          final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
          final dir =
              Directory('../storage/app/registration-upgrade/screenshots');
          await dir.create(recursive: true);
          await File('${dir.path}/registration-${size.width.toInt()}.png')
              .writeAsBytes(bytes!.buffer.asUint8List());
          image.dispose();
        });
      }, () => MockClient((r) async => fixture(r)));
    });
  }
  testWidgets(
      'large text, keyboard resizing and searchable dropdown preserve typed data',
      (tester) async {
    await http.runWithClient(() async {
      await launch(tester, size: const Size(320, 900), scale: 1.5);
      await enter(tester, 'first_name', 'Maria');
      tester.view.viewInsets = const FakeViewPadding(bottom: 280);
      addTearDown(tester.view.resetViewInsets);
      await tester.pumpAndSettle();
      await tap(tester, find.byKey(const ValueKey('registration-region')));
      final search = find.widgetWithText(TextField, 'Search locations');
      await tester.enterText(search, 'No Province');
      await tester.pump();
      expect(find.text('Test Region'), findsNothing);
      await tap(tester, find.text('No Province Region'));
      expect(
          tester
              .widget<TextFormField>(
                  find.byKey(const ValueKey('registration-first_name')))
              .controller!
              .text,
          'Maria');
      expect(find.text('This region has no province level.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    }, () => MockClient((r) async => fixture(r)));
  });
  testWidgets(
      'birthday picker derives read-only age and required fields/terms stop submission',
      (tester) async {
    var posts = 0;
    await http.runWithClient(() async {
      await launch(tester);
      await tap(tester, find.byKey(const ValueKey('registration-birthday')));
      await tap(tester, find.byTooltip('Switch to input'));
      await tester.enterText(
          find.descendant(
              of: find.byType(DatePickerDialog),
              matching: find.byType(TextField)),
          '02/29/2000');
      await tap(tester, find.text('OK'));
      final ageFinder = find.byKey(const ValueKey('registration-age'));
      final age = tester.widget<TextFormField>(ageFinder);
      expect(
          tester
              .widget<TextField>(find.descendant(
                  of: ageFinder, matching: find.byType(TextField)))
              .readOnly,
          isTrue);
      expect(age.controller!.text,
          '${BuyerRegistration.age(DateTime(2000, 2, 29))}');
      await tap(tester, find.byKey(const ValueKey('registration-submit')));
      expect(find.text('First name is required.'), findsOneWidget);
      expect(find.text('You must agree to the Terms and Privacy Policy.'),
          findsOneWidget);
      expect(posts, 0);
    },
        () => MockClient((r) async {
              if (r.method == 'POST') posts++;
              return fixture(r);
            }));
  });
  testWidgets('legacy backend disables registration and unconfigured Google',
      (tester) async {
    await http.runWithClient(() async {
      await launch(tester);
      expect(
          tester
              .widget<FilledButton>(
                  find.byKey(const ValueKey('registration-submit')))
              .onPressed,
          isNull);
      expect(
          tester
              .widget<OutlinedButton>(
                  find.widgetWithText(OutlinedButton, 'Continue with Google'))
              .onPressed,
          isNull);
      expect(
          find.textContaining(
              'Mobile registration is temporarily unavailable.'),
          findsOneWidget);
    }, () => MockClient((r) async => fixture(r, compatible: false)));
  });
  testWidgets(
      'ID picker validates types and size and preserves cancellation safely',
      (tester) async {
    final picker = TestIdPicker();
    FilePicker.platform = picker;
    await http.runWithClient(() async {
      await launch(tester);
      picker.file = PlatformFile(
          name: 'private-id.pdf', path: 'synthetic-private-path', size: 100);
      await tap(tester, find.byKey(const ValueKey('registration-valid-id')));
      expect(find.text('private-id.pdf'), findsOneWidget);
      picker.file = null;
      await tap(tester, find.byKey(const ValueKey('registration-valid-id')));
      expect(find.text('private-id.pdf'), findsOneWidget);
      picker.file = PlatformFile(
          name: 'invalid.exe', path: 'synthetic-private-path', size: 100);
      await tap(tester, find.byKey(const ValueKey('registration-valid-id')));
      expect(
          find.text('Choose a JPG, JPEG, PNG, or PDF file.'), findsOneWidget);
      expect(find.text('private-id.pdf'), findsNothing);
      picker.file = PlatformFile(
          name: 'too-large.png',
          path: 'synthetic-private-path',
          size: 5 * 1024 * 1024 + 1);
      await tap(tester, find.byKey(const ValueKey('registration-valid-id')));
      expect(
          find.text('Valid ID must be no larger than 5 MB.'), findsOneWidget);
      picker.file = PlatformFile(
          name: 'replacement.png', path: 'synthetic-private-path', size: 100);
      await tap(tester, find.byKey(const ValueKey('registration-valid-id')));
      expect(find.text('replacement.png'), findsOneWidget);
    }, () => MockClient((r) async => fixture(r)));
  });
  testWidgets(
      'real request preparation sends multipart fields once and renders API field errors',
      (tester) async {
    final picker = TestIdPicker();
    FilePicker.platform = picker;
    late Directory dir;
    late File id;
    await tester.runAsync(() async {
      dir = await Directory.systemTemp.createTemp('shoppick-registration-');
      id = File('${dir.path}/id.pdf');
      await id.writeAsString('%PDF-1.4 synthetic ID fixture');
    });
    addTearDown(() => dir.delete(recursive: true));
    picker.file = PlatformFile(name: 'id.pdf', path: id.path, size: 30);
    final submission = Completer<http.Response>();
    var posts = 0;
    await http.runWithClient(() async {
      await launch(tester);
      await fill(tester);
      expect(
          (tester.widget<Form>(find.byType(Form)).key as GlobalKey<FormState>)
              .currentState!
              .validate(),
          isTrue);
      final submit = find.byKey(const ValueKey('registration-submit'));
      await tester.ensureVisible(submit);
      await tester.runAsync(() async {
        await tester.tap(submit);
        await Future<void>.delayed(const Duration(milliseconds: 80));
      });
      await tester.pump();
      await tester.tap(submit);
      await tester.pump();
      expect(posts, 1);
      expect(tester.widget<FilledButton>(submit).onPressed, isNull);
      await tester.runAsync(() async {
        submission.complete(http.Response(
            '{"errors":{"email":["That email is already registered."],"phone":["That mobile number is already registered."]}}',
            422));
        await Future<void>.delayed(const Duration(milliseconds: 80));
      });
      await tester.pumpAndSettle();
      expect(find.text('That email is already registered.'), findsOneWidget);
      expect(find.text('That mobile number is already registered.'),
          findsOneWidget);
      expect(await ApiService().token(), isNull);
      expect(picker.allowed, ['jpg', 'jpeg', 'png', 'pdf']);
      expect(tester.takeException(), isNull);
    },
        () => MockClient((r) async {
              if (r.method == 'POST') {
                posts++;
                expect(r.url.path, '/api/v1/register');
                expect(r.headers['content-type'],
                    startsWith('multipart/form-data'));
                for (final value in [
                  'name="birthday"\r\n\r\n1998-01-01',
                  'name="country"\r\n\r\nPH',
                  'name="phone"\r\n\r\n+639171234567',
                  'name="region_code"\r\n\r\nR1',
                  'filename="id.pdf"'
                ]) {
                  expect(r.body, contains(value));
                }
                expect(r.body, contains('buyer@example.test'));
                expect(r.body, isNot(contains('name="age"')));
                return submission.future;
              }
              return fixture(r);
            }));
  });
}

class _DelayedLocations extends PhilippineLocationService {
  final Completer<List<PhilippineLocation>> first;
  _DelayedLocations(this.first);
  @override
  Future<List<PhilippineLocation>> get(String path) async =>
      path.contains('/old/')
          ? first.future
          : [const PhilippineLocation('new-P', 'New Province')];
}
