import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shoppick_mobile/main.dart';
import 'package:shoppick_mobile/services/api_service.dart';
import 'marketplace_flow_test.dart' as fixtures;

void main() {
  setUp(() {
    FlutterSecureStorage.setMockInitialValues(
        {'auth_token': 'onboarding-token'});
    ApiService.session.value = SessionStatus.checking;
  });

  testWidgets(
      'app restart resumes OTP without requesting protected marketplace data',
      (tester) async {
    final paths = <String>[];
    await http.runWithClient(() async {
      await tester.pumpWidget(const ShoppickApp());
      await tester.pumpAndSettle();
      expect(find.byType(RegisterOtpScreen), findsOneWidget);
      expect(find.byType(NavigationBar), findsNothing);
      expect(paths, ['/api/v1/auth/session']);
      expect(await ApiService().token(), 'onboarding-token');
    },
        () => MockClient((request) async {
              paths.add(request.url.path);
              return http.Response(
                  jsonEncode({
                    'requires_verification': true,
                    'requires_profile': false
                  }),
                  200);
            }));
  });

  testWidgets(
      'invalid OTP keeps the session gated and valid OTP opens the marketplace',
      (tester) async {
    var attempts = 0;
    await http.runWithClient(() async {
      await tester.pumpWidget(const ShoppickApp());
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), '123456');
      await tester.pump();
      await tester.tap(find.text('Verify'));
      await tester.pumpAndSettle();
      expect(find.text('Invalid verification code.'), findsOneWidget);
      expect(find.byType(NavigationBar), findsNothing);
      await tester.enterText(find.byType(TextField), '654321');
      await tester.pump();
      await tester.tap(find.text('Verify'));
      await tester.pumpAndSettle();
      expect(find.byType(MarketplaceScreen), findsOneWidget);
    },
        () => MockClient((request) async {
              if (request.url.path.endsWith('/auth/session')) {
                return http.Response('{"requires_verification":true}', 200);
              }
              if (request.url.path.endsWith('/register/verify')) {
                if (++attempts == 1) {
                  return http.Response(
                      '{"errors":{"code":["Invalid verification code."]}}',
                      422);
                }
                expect(jsonDecode(request.body)['code'], '654321');
                return http.Response(
                    '{"token":"activated-token","requires_verification":false,"requires_profile":false}',
                    200);
              }
              return http.Response(
                  jsonEncode(fixtures.responseFor(request.url.path)), 200);
            }));
  });

  testWidgets('Google onboarding resumes the required native profile form',
      (tester) async {
    await http.runWithClient(() async {
      await tester.pumpWidget(const ShoppickApp());
      await tester.pumpAndSettle();
      expect(find.byType(RegisterScreen), findsOneWidget);
      expect(find.text('Complete Buyer Profile'), findsOneWidget);
      expect(find.text('YOUR DELIVERY ADDRESS'), findsOneWidget);
      expect(find.byKey(const ValueKey('registration-address_line')), findsOneWidget);
      expect(find.byType(NavigationBar), findsNothing);
    },
        () => MockClient((request) async => http.Response(
            jsonEncode({
              'requires_profile': true,
              'requires_verification': false,
              'user': {
                'first_name': 'Google',
                'last_name': 'Buyer',
                'email': 'google@example.test'
              },
            }),
            200)));
  });

  test(
      'Google ID token is sent to Laravel and does not activate an incomplete Buyer',
      () async {
    await http.runWithClient(() async {
      await ApiService().loginWithGoogle('verified-by-server');
      expect(ApiService.session.value, SessionStatus.profileRequired);
      expect(await ApiService().token(), 'google-onboarding');
    },
        () => MockClient((request) async {
              expect(request.url.path, '/api/v1/auth/google');
              expect(
                  jsonDecode(request.body), {'id_token': 'verified-by-server'});
              return http.Response(
                  '{"token":"google-onboarding","requires_profile":true}', 202);
            }));
  });
}
