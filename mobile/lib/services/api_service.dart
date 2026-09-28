import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  const ApiException(this.message, {this.statusCode});
  @override
  String toString() => message;
}

enum SessionStatus { checking, signedOut, authenticated, unavailable }

class ApiService {
  static const _storage = FlutterSecureStorage();
  static final cartCount = ValueNotifier<int>(0);
  static final cartRevision = ValueNotifier<int>(0);
  static final session = ValueNotifier<SessionStatus>(SessionStatus.checking);
  static int _sessionRevision = 0;

  Future<void> restoreSession() async {
    final revision = _sessionRevision;
    session.value = SessionStatus.checking;
    try {
      final saved = await token();
      if (revision != _sessionRevision) return;
      if (saved == null || saved.trim().isEmpty) {
        await clearToken();
        return;
      }
      await request('profile');
      if (revision == _sessionRevision) {
        session.value = SessionStatus.authenticated;
      }
    } on ApiException catch (error) {
      if (revision != _sessionRevision) return;
      // Profile is the startup identity check, not a permissioned operation.
      if (error.statusCode == 401 || error.statusCode == 403) {
        await clearToken();
      } else {
        session.value = SessionStatus.unavailable;
      }
    } catch (_) {
      if (revision == _sessionRevision) {
        session.value = SessionStatus.unavailable;
      }
    }
  }

  Future<void> login(String email, String password) async {
    final data = await request('login', method: 'POST', body: {
      'email': email.trim(),
      'password': password,
    });
    final value = data is Map ? data['token'] : null;
    if (value is! String || value.trim().isEmpty) {
      throw const ApiException('Unable to sign in. Please try again.');
    }
    await saveToken(value);
    session.value = SessionStatus.authenticated;
  }

  Future<void> logout() async {
    try {
      await request('logout', method: 'POST');
    } catch (_) {
      // Local sign-out must work even when the server cannot be reached.
    } finally {
      await clearToken();
    }
  }

  Future<dynamic> request(String path,
      {String method = 'GET',
      Map<String, dynamic>? body,
      List<http.MultipartFile>? files}) async {
    final uri = Uri.parse('${ApiConfig.baseUrl}/$path');
    if (kDebugMode) {
      // Query values, headers and request bodies can contain credentials.
      debugPrint('SHOPPICK $method ${uri.origin}${uri.path}');
    }
    final revision = _sessionRevision;
    String? token;
    try {
      token = await tokenValue();
    } catch (_) {
      throw const ApiException(
          'Unable to read your saved session. Please restart SHOPPICK and try again.');
    }
    final headers = <String, String>{'Accept': 'application/json'};
    if (token != null) headers['Authorization'] = 'Bearer $token';
    late http.Response response;
    try {
      if (files != null) {
        final request = http.MultipartRequest(method, uri)
          ..headers.addAll(headers);
        request.fields
            .addAll((body ?? {}).map((k, v) => MapEntry(k, v.toString())));
        request.files.addAll(files);
        response = await request
            .send()
            .then(http.Response.fromStream)
            .timeout(const Duration(seconds: 60));
      } else {
        final request = http.Request(method, uri)..headers.addAll(headers);
        request.headers['Content-Type'] = 'application/json';
        if (body != null) request.body = jsonEncode(body);
        response = await request
            .send()
            .then(http.Response.fromStream)
            .timeout(const Duration(seconds: 25));
      }
    } catch (error) {
      if (kDebugMode) debugPrint('SHOPPICK exception: ${error.runtimeType}');
      if (error is SocketException ||
          error is TimeoutException ||
          error is http.ClientException) {
        throw const ApiException(
            'Unable to connect to SHOPPICK. Please check your connection and try again.');
      }
      throw const ApiException(
          'Unable to complete this request. Please try again.');
    }
    dynamic data;
    try {
      data = jsonDecode(response.body);
    } catch (_) {
      data = null;
    }
    if (kDebugMode) {
      debugPrint('SHOPPICK status: ${response.statusCode}');
      // Log only body structure: even error messages may echo secrets or SQL.
      final safeBody = data is Map
          ? {
              'token': data.containsKey('token') ? '[REDACTED]' : null,
              'user': data.containsKey('user') ? '[REDACTED]' : null,
              'message': data.containsKey('message') ? '[REDACTED]' : null,
              'errors':
                  data['errors'] is Map ? '[REDACTED validation errors]' : null
            }
          : {'body': data == null ? '[non-JSON omitted]' : '[content omitted]'};
      debugPrint('SHOPPICK safe response body: ${jsonEncode(safeBody)}');
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      if (response.statusCode >= 500) {
        throw ApiException(
            'SHOPPICK is temporarily unavailable. Please try again.',
            statusCode: response.statusCode);
      }
      if (response.statusCode == 401) {
        if (token != null &&
            path != 'login' &&
            path != 'register' &&
            revision == _sessionRevision) {
          await clearToken();
        }
        if (path == 'login') {
          throw const ApiException('Invalid email or password.',
              statusCode: 401);
        }
        throw const ApiException(
            'Your session has expired. Please sign in again.',
            statusCode: 401);
      }
      if (response.statusCode == 422 && data is Map && data['errors'] is Map) {
        final values = (data['errors'] as Map).values;
        if (values.isNotEmpty) {
          final error = values.first;
          if (path == 'login' &&
              error is List &&
              error.contains('The provided credentials are incorrect.')) {
            throw const ApiException('Invalid email or password.',
                statusCode: 422);
          }
          throw ApiException(
              error is List && error.isNotEmpty
                  ? error.first.toString()
                  : 'Please check the information entered.',
              statusCode: 422);
        }
      }
      if (path == 'login' && response.statusCode == 403) {
        throw const ApiException(
            'Your account is awaiting administrator approval.',
            statusCode: 403);
      }
      if (response.statusCode == 403) {
        throw const ApiException(
            'You do not have permission to perform this action.',
            statusCode: 403);
      }
      if (response.statusCode == 404) {
        throw const ApiException('This item is no longer available.');
      }
      if (response.statusCode == 429) {
        throw const ApiException(
            'Too many requests. Please try again shortly.');
      }
      throw const ApiException(
          'Unable to complete this request. Please try again.');
    }
    if (data == null) {
      throw const ApiException(
          'Unable to load SHOPPICK data. Please try again.');
    }
    if (revision == _sessionRevision &&
        data is Map &&
        data['cart_count'] is num) {
      cartCount.value = (data['cart_count'] as num).toInt();
    }
    if (method != 'GET' && (path.startsWith('cart') || path == 'checkout')) {
      cartRevision.value++;
      if (path == 'checkout') {
        await refreshCartCount();
      }
    }
    return data;
  }

  Future<void> refreshCartCount() async {
    try {
      await request('cart');
    } catch (_) {}
  }

  Future<void> saveToken(String token) async {
    _sessionRevision++;
    try {
      await _storage.write(key: 'auth_token', value: token);
    } catch (_) {
      throw const ApiException(
          'Unable to save your session securely. Please try again.');
    }
  }

  Future<String?> tokenValue() => _storage.read(key: 'auth_token');
  Future<String?> token() => tokenValue();
  Future<void> clearToken() async {
    _sessionRevision++;
    try {
      await _storage.delete(key: 'auth_token');
    } finally {
      cartCount.value = 0;
      session.value = SessionStatus.signedOut;
    }
  }
}
