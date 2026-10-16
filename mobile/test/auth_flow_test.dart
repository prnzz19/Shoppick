import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shoppick_mobile/main.dart';
import 'package:shoppick_mobile/services/api_service.dart';
import 'marketplace_flow_test.dart' as fixtures;

http.Response ok(http.Request request) =>
    http.Response(jsonEncode(fixtures.responseFor(request.url.path)), 200);

void main() {
  setUp(() {
    FlutterSecureStorage.setMockInitialValues({});
    ApiService.session.value = SessionStatus.checking;
  });

  testWidgets('fresh startup shows login without requesting marketplace data',
      (tester) async {
    await http.runWithClient(() async {
      await tester.pumpWidget(const ShoppickApp());
      expect(find.byType(SplashScreen), findsOneWidget);
      expect(find.byType(NavigationBar), findsNothing);
      await tester.pumpAndSettle();
      expect(find.byType(LoginScreen), findsOneWidget);
      expect(find.byType(NavigationBar), findsNothing);
    }, () => MockClient((r) async => throw StateError('No request expected')));
  });

  testWidgets('stored token stays on splash until profile validates',
      (tester) async {
    FlutterSecureStorage.setMockInitialValues({'auth_token': 'valid'});
    final profile = Completer<http.Response>();
    final paths = <String>[];
    await http.runWithClient(() async {
      await tester.pumpWidget(const ShoppickApp());
      await tester.pump();
      expect(find.byType(SplashScreen), findsOneWidget);
      expect(find.byType(LoginScreen), findsNothing);
      expect(find.byType(NavigationBar), findsNothing);
      expect(paths, ['/api/v1/profile']);
      profile.complete(http.Response(
          jsonEncode(fixtures.responseFor('/api/v1/profile')), 200));
      await tester.pumpAndSettle();
      expect(find.byType(MarketplaceScreen), findsOneWidget);
      expect(await ApiService().token(), 'valid');
    },
        () => MockClient((r) async {
              paths.add(r.url.path);
              expect(r.url.origin, 'http://10.0.2.2:8000');
              expect(r.headers['Authorization'], 'Bearer valid');
              return r.url.path.endsWith('/profile') ? profile.future : ok(r);
            }));
  });

  for (final status in [401, 403]) {
    testWidgets('startup profile $status clears token and shows login',
        (tester) async {
      FlutterSecureStorage.setMockInitialValues({'auth_token': 'invalid'});
      await http.runWithClient(() async {
        await tester.pumpWidget(const ShoppickApp());
        await tester.pumpAndSettle();
        expect(find.byType(LoginScreen), findsOneWidget);
        expect(find.byType(NavigationBar), findsNothing);
        expect(await ApiService().token(), isNull);
      },
          () => MockClient((r) async {
                expect(r.url.path, '/api/v1/profile');
                return http.Response('{}', status);
              }));
    });
  }

  for (final retry in [true, false]) {
    testWidgets(
        'offline startup retains token then ${retry ? 'retries' : 'signs out'}',
        (tester) async {
      FlutterSecureStorage.setMockInitialValues({'auth_token': 'valid'});
      var offline = true;
      await http.runWithClient(() async {
        await tester.pumpWidget(const ShoppickApp());
        await tester.pumpAndSettle();
        expect(find.byType(SessionRetryScreen), findsOneWidget);
        expect(find.byType(NavigationBar), findsNothing);
        expect(await ApiService().token(), 'valid');
        offline = false;
        await tester.tap(find.text(retry ? 'Try Again' : 'Sign Out'));
        await tester.pumpAndSettle();
        expect(find.byType(retry ? MarketplaceScreen : LoginScreen),
            findsOneWidget);
        expect(await ApiService().token(), retry ? 'valid' : isNull);
      },
          () => MockClient((r) async {
                if (offline) {
                  throw http.ClientException('SocketException internal detail');
                }
                return ok(r);
              }));
    });
  }

  testWidgets('login saves token and replaces login navigation',
      (tester) async {
    await http.runWithClient(() async {
      await tester.pumpWidget(const ShoppickApp());
      await tester.pumpAndSettle();
      await tester.enterText(
          find.byType(TextField).at(0), 'buyer@example.test');
      await tester.enterText(find.byType(TextField).at(1), 'password');
      await tester.tap(find.text('Sign in'));
      await tester.pumpAndSettle();
      expect(await ApiService().token(), 'new-token');
      expect(find.byType(MarketplaceScreen), findsOneWidget);
      expect(
          Navigator.of(tester.element(find.byType(MarketplaceScreen))).canPop(),
          false);
    },
        () => MockClient((r) async {
              if (r.url.path.endsWith('/login')) {
                expect(r.url.toString(), 'http://10.0.2.2:8000/api/v1/login');
                expect(r.method, 'POST');
                expect(jsonDecode(r.body)['email'], 'buyer@example.test');
                return http.Response('{"token":"new-token"}', 201);
              }
              return ok(r);
            }));
  });

  testWidgets('wrong password stays on login with friendly form error',
      (tester) async {
    await http.runWithClient(() async {
      await tester.pumpWidget(const ShoppickApp());
      await tester.pumpAndSettle();
      await tester.enterText(
          find.byType(TextField).at(0), 'buyer@example.test');
      await tester.enterText(find.byType(TextField).at(1), 'wrong');
      await tester.tap(find.text('Sign in'));
      await tester.pumpAndSettle();
      expect(find.text('Invalid email or password.'), findsOneWidget);
      expect(find.byType(LoginScreen), findsOneWidget);
      expect(await ApiService().token(), isNull);
    },
        () => MockClient((r) async => http.Response(
            jsonEncode({
              'errors': {
                'email': ['The provided credentials are incorrect.']
              }
            }),
            422)));
  });

  testWidgets(
      'global 401 discards detail routes and prevents Back into marketplace',
      (tester) async {
    FlutterSecureStorage.setMockInitialValues({'auth_token': 'valid'});
    await http.runWithClient(() async {
      await tester.pumpWidget(const ShoppickApp());
      await tester.pumpAndSettle();
      final context = tester.element(find.byType(MarketplaceScreen));
      Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => const OrderDetailScreen(number: 'expired')));
      await tester.pumpAndSettle();
      expect(find.byType(LoginScreen), findsOneWidget);
      expect(find.byType(OrderDetailScreen), findsNothing);
      expect(await ApiService().token(), isNull);
      expect(Navigator.of(tester.element(find.byType(LoginScreen))).canPop(),
          false);
    },
        () => MockClient((r) async => r.url.path.endsWith('/orders/expired')
            ? http.Response('{}', 401)
            : ok(r)));
  });

  testWidgets('permission 403 preserves authenticated session', (tester) async {
    FlutterSecureStorage.setMockInitialValues({'auth_token': 'valid'});
    await http.runWithClient(() async {
      await tester.pumpWidget(const ShoppickApp());
      await tester.pumpAndSettle();
      await expectLater(ApiService().request('seller/application'),
          throwsA(isA<ApiException>()));
      await tester.pumpAndSettle();
      expect(find.byType(MarketplaceScreen), findsOneWidget);
      expect(await ApiService().token(), 'valid');
    },
        () => MockClient((r) async => r.url.path.endsWith('/seller/application')
            ? http.Response('{}', 403)
            : ok(r)));
  });

  testWidgets(
      'Home failure stays inside authenticated shell and retries only Home',
      (tester) async {
    FlutterSecureStorage.setMockInitialValues({'auth_token': 'valid'});
    var homeCalls = 0;
    await http.runWithClient(() async {
      await tester.pumpWidget(const ShoppickApp());
      await tester.pumpAndSettle();
      expect(find.byType(NavigationBar), findsOneWidget);
      expect(find.byType(SessionRetryScreen), findsNothing);
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(homeCalls, 2);
      expect(find.byType(NavigationBar), findsOneWidget);
      expect(await ApiService().token(), 'valid');
    },
        () => MockClient((r) async {
              if (r.url.path.endsWith('/home') && ++homeCalls == 1) {
                throw http.ClientException('offline');
              }
              return ok(r);
            }));
  });

  for (final offline in [false, true]) {
    testWidgets(
        'account logout clears local session with server offline=$offline',
        (tester) async {
      FlutterSecureStorage.setMockInitialValues({'auth_token': 'valid'});
      await http.runWithClient(() async {
        await tester.pumpWidget(const ShoppickApp());
        await tester.pumpAndSettle();
        await tester.tap(find.text('Account').last);
        await tester.pumpAndSettle();
        await tester.tap(find.byTooltip('Sign out'));
        await tester.pumpAndSettle();
        expect(find.byType(LoginScreen), findsOneWidget);
        expect(await ApiService().token(), isNull);
        expect(Navigator.of(tester.element(find.byType(LoginScreen))).canPop(),
            false);
      },
          () => MockClient((r) async {
                if (r.url.path.endsWith('/logout')) {
                  expect(r.method, 'POST');
                  if (offline) throw http.ClientException('offline');
                  return http.Response('{}', 200);
                }
                return ok(r);
              }));
    });
  }

  test('a delayed old-session 401 cannot clear a newly saved token', () async {
    FlutterSecureStorage.setMockInitialValues({'auth_token': 'old'});
    final started = Completer<void>();
    final response = Completer<http.Response>();
    await http.runWithClient(() async {
      final request = ApiService().request('profile');
      await started.future;
      await ApiService().saveToken('new');
      response.complete(http.Response('{}', 401));
      await expectLater(request, throwsA(isA<ApiException>()));
      expect(await ApiService().token(), 'new');
    },
        () => MockClient((r) {
              started.complete();
              return response.future;
            }));
  });
}
