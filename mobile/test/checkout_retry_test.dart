import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shoppick_mobile/services/api_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(() =>
      FlutterSecureStorage.setMockInitialValues({'auth_token': 'buyer-token'}));

  test('password reset accepts the secure link from the shared backend email',
      () async {
    await http.runWithClient(() async {
      await ApiService().resetPassword(
          '',
          'https://example.test/reset-password/reset-token?email=buyer%40example.test',
          'Example@2026',
          'Example@2026');
    },
        () => MockClient((request) async {
              expect(request.url.path, '/api/v1/password/reset');
              expect(jsonDecode(request.body)['email'], 'buyer@example.test');
              expect(jsonDecode(request.body)['token'], 'reset-token');
              return http.Response('{"message":"Password reset."}', 200);
            }));
  });

  test(
      'an uncertain checkout survives restart and reuses the exact submitted key and review',
      () async {
    final body = {
      'idempotency_key': 'same-checkout-key',
      'quote_token': 'server-review',
      'address_id': 1,
      'payment_method': 'cod'
    };
    var fail = true;
    final sent = <Map>[];
    await http.runWithClient(() async {
      await expectLater(
          ApiService().submitCheckout(body), throwsA(isA<ApiException>()));
      fail = false;
      final result = await ApiService().recoverCheckout();
      expect(result['order']['order_number'], 'SP-RECOVERED');
      expect(sent, [body, body]);
      await expectLater(
          ApiService().recoverCheckout(), throwsA(isA<ApiException>()));
    },
        () => MockClient((request) async {
              if (request.url.path.endsWith('/cart')) {
                return http.Response('{"items":[],"cart_count":0}', 200);
              }
              sent.add(jsonDecode(request.body) as Map);
              if (fail) {
                throw http.ClientException('connection lost after submission');
              }
              return http.Response(
                  '{"order":{"order_number":"SP-RECOVERED"}}', 201);
            }));
  });

  test('a rejected review clears pending checkout and allows a fresh review',
      () async {
    var reject = true;
    await http.runWithClient(() async {
      await expectLater(ApiService().submitCheckout({'idempotency_key': 'old'}),
          throwsA(isA<ApiException>()));
      reject = false;
      await ApiService().submitCheckout({'idempotency_key': 'new'});
    },
        () => MockClient((request) async {
              if (request.url.path.endsWith('/cart')) {
                return http.Response('{"items":[],"cart_count":0}', 200);
              }
              return reject
                  ? http.Response(
                      '{"errors":{"quote_token":["Review expired."]}}', 422)
                  : http.Response('{"order":{"order_number":"SP-NEW"}}', 201);
            }));
  });
}
