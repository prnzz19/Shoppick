import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
import 'api_service.dart';

class RegistrationAvailability {
  final bool compatible;
  final bool google;
  const RegistrationAvailability(
      {required this.compatible, this.google = false});
}

/// Fail closed before uploading personal data to the release's legacy activator.
/// Probes only known routes with GET; it never sends a registration as a probe.
class BuyerRegistrationService {
  RegistrationAvailability? _availability;
  Future<RegistrationAvailability> check({bool refresh = false}) async {
    if (!refresh && _availability != null) return _availability!;
    if (refresh) _availability = null;
    try {
      Future<http.Response> get(String path) {
        final uri = Uri.parse('${ApiConfig.baseUrl}/$path');
        if (kReleaseMode && uri.scheme != 'https') {
          throw const ApiException(
              'A secure SHOPPICK connection is unavailable.');
        }
        return http.get(uri, headers: {'Accept': 'application/json'}).timeout(
            const Duration(seconds: 15));
      }

      final responses = await Future.wait([
        get('auth/session'),
        get('register/verify'),
        get('register/resend'),
        get('auth/google'),
      ]);
      bool postRoute(http.Response r) =>
          r.statusCode == 405 &&
          (r.headers['allow'] ?? '')
              .split(',')
              .map((s) => s.trim().toUpperCase())
              .contains('POST');
      final session = responses[0];
      dynamic body;
      try {
        body = jsonDecode(session.body);
      } catch (_) {
        body = null;
      }
      final compatible =
          (session.statusCode == 401 || session.statusCode == 200) &&
              body is Map &&
              postRoute(responses[1]) &&
              postRoute(responses[2]);
      if (responses
          .take(3)
          .any((r) => r.statusCode >= 500 || r.statusCode == 429)) {
        throw const ApiException(
            'Registration is temporarily unavailable. Please try again.');
      }
      return _availability = RegistrationAvailability(
          compatible: compatible,
          google: compatible && postRoute(responses[3]));
    } on ApiException {
      rethrow;
    } catch (_) {
      throw const ApiException(
          'Unable to check registration availability. Check your connection and retry.');
    }
  }

  Future<void> submit(Map<String, String> fields, String file,
      {bool profileOnly = false}) async {
    if (_availability?.compatible == false ||
        !(await check(refresh: true)).compatible) {
      throw const ApiException(
          'Mobile registration is temporarily unavailable. Please register on the SHOPPICK website.');
    }
    if (profileOnly) {
      await ApiService().completeProfile(fields, file);
    } else {
      await ApiService().register(fields, file);
    }
  }
}
