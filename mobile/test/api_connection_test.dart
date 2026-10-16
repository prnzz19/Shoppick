import 'dart:async';
import 'dart:io';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shoppick_mobile/services/api_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(() => FlutterSecureStorage.setMockInitialValues({}));

  for (final status in [500, 503]) {
    test('HTTP $status is a server failure, including HTML responses',
        () async {
      await http.runWithClient(() async {
        await expectLater(
          ApiService().login('buyer@example.com', 'not-a-real-password'),
          throwsA(isA<ApiException>()
              .having((e) => e.statusCode, 'status', status)
              .having((e) => e.message, 'message',
                  'SHOPPICK is temporarily unavailable. Please try again.')),
        );
      },
          () => MockClient((request) async {
                expect(request.method, 'POST');
                expect(request.url.toString(),
                    'http://10.0.2.2:8000/api/v1/login');
                return http.Response('<html>Server error</html>', status);
              }));
    });
  }

  for (final error in [
    const SocketException('offline'),
    TimeoutException('timeout'),
    http.ClientException('offline'),
    StateError('unexpected'),
  ]) {
    test('classifies ${error.runtimeType} correctly', () async {
      await http.runWithClient(() async {
        await expectLater(
          ApiService().request('home'),
          throwsA(isA<ApiException>().having(
            (e) => e.message,
            'message',
            error is StateError
                ? 'Unable to complete this request. Please try again.'
                : 'Unable to connect to SHOPPICK. Please check your connection and try again.',
          )),
        );
      }, () => MockClient((_) async => throw error));
    });
  }
}
