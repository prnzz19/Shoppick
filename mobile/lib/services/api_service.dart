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
  final Map<String, List<String>> fieldErrors;
  const ApiException(this.message,
      {this.statusCode, this.fieldErrors = const {}});
  @override
  String toString() => message;
}

enum SessionStatus {
  checking,
  signedOut,
  profileRequired,
  verificationRequired,
  authenticated,
  unavailable
}

class GoogleLoginResult {
  final bool requiresVerification;
  final String? message;
  const GoogleLoginResult({required this.requiresVerification, this.message});
}

class ApiService {
  static const _storage = FlutterSecureStorage();
  static final cartCount = ValueNotifier<int>(0);
  static final cartRevision = ValueNotifier<int>(0);
  static final notificationCount = ValueNotifier<int>(0);
  static final messageCount = ValueNotifier<int>(0);
  static final session = ValueNotifier<SessionStatus>(SessionStatus.checking);
  static String? sessionMessage;
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
      final identity = await request('auth/session');
      if (revision == _sessionRevision) {
        await acceptSession(identity);
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
    await acceptSession(data);
  }

  Future<GoogleLoginResult> loginWithGoogle(String idToken) async {
    final data = await request('auth/google',
        method: 'POST', body: {'id_token': idToken});
    final value = data is Map ? data['token'] : null;
    if (value is! String || value.trim().isEmpty) {
      throw const ApiException(
          'Unable to sign in with Google. Please try again.');
    }
    await saveToken(value);
    final requiresVerification =
        data is Map && data['requires_verification'] == true;
    final message = data is Map && data['message'] is String
        ? data['message'] as String
        : null;
    await acceptSession(data);
    return GoogleLoginResult(
        requiresVerification: requiresVerification, message: message);
  }

  Future<void> logout() async {
    try {
      await request('auth/logout', method: 'POST');
    } catch (_) {
      // Local sign-out must work even when the server cannot be reached.
    } finally {
      await clearToken();
    }
  }

  Future<void> register(Map<String, String> fields, String validIdPath) async {
    final file = await http.MultipartFile.fromPath('valid_id', validIdPath);
    final data =
        await request('register', method: 'POST', body: fields, files: [file]);
    final value = data is Map ? data['token'] : null;
    if (value is! String ||
        value.isEmpty ||
        data['requires_verification'] != true) {
      throw const ApiException(
          'Email verification is unavailable. Your account has not been signed in. Please contact SHOPPICK support.');
    }
    await saveToken(value);
    await acceptSession(data);
  }

  Future<void> verifyRegistration(String code) async {
    final data =
        await request('register/verify', method: 'POST', body: {'code': code});
    final value = data is Map ? data['token'] : null;
    if (value is String && value.isNotEmpty) await saveToken(value);
    await acceptSession(data);
  }

  Future<void> acceptSession(dynamic data) async {
    sessionMessage = data is Map && data['message'] is String
        ? data['message'] as String
        : null;
    if (data is Map && data['requires_verification'] == true) {
      session.value = SessionStatus.verificationRequired;
    } else if (data is Map && data['requires_profile'] == true) {
      session.value = SessionStatus.profileRequired;
    } else {
      await refreshNotificationCount();
      await refreshMessageCount();
      session.value = SessionStatus.authenticated;
    }
  }

  Future<void> completeProfile(
      Map<String, String> fields, String validIdPath) async {
    final data = await request('auth/complete-profile',
        method: 'POST',
        body: fields,
        files: [await http.MultipartFile.fromPath('valid_id', validIdPath)]);
    if (data is! Map || data['requires_verification'] != true) {
      throw const ApiException(
          'Email verification is unavailable. Please contact SHOPPICK support.');
    }
    if (data['token'] is String) await saveToken(data['token']);
    await acceptSession(data);
  }

  Future<void> resendRegistrationCode() =>
      request('register/resend', method: 'POST').then((_) {});

  Future<void> toggleWishlist(int productId) async {
    await request('wishlist/toggle',
        method: 'POST', body: {'product_id': productId});
  }

  Future<dynamic> updateProfile(Map<String, dynamic> fields,
      {String? avatarPath}) async {
    final files = avatarPath == null
        ? <http.MultipartFile>[]
        : [await http.MultipartFile.fromPath('avatar', avatarPath)];
    final data =
        await request('profile', method: 'POST', body: fields, files: files);
    if (data['requires_verification'] == true) await acceptSession(data);
    return data;
  }

  Future<dynamic> forgotPassword(String email) =>
      request('password/forgot', method: 'POST', body: {'email': email.trim()});

  Future<dynamic> resetPassword(
      String email, String token, String password, String confirmation) {
    final link = Uri.tryParse(token.trim());
    final isLink =
        link != null && (link.scheme == 'http' || link.scheme == 'https');
    return request('password/reset', method: 'POST', body: {
      'email': email.trim().isNotEmpty
          ? email.trim()
          : (isLink ? link.queryParameters['email'] ?? '' : ''),
      'token': isLink
          ? link.queryParameters['token'] ??
              (link.pathSegments.isEmpty ? '' : link.pathSegments.last)
          : token.trim(),
      'password': password,
      'password_confirmation': confirmation,
    });
  }

  Future<dynamic> request(String path,
      {String method = 'GET',
      Map<String, dynamic>? body,
      List<http.MultipartFile>? files}) async {
    final uri = Uri.parse('${ApiConfig.baseUrl}/$path');
    if (kReleaseMode && uri.scheme != 'https') {
      throw const ApiException('A secure SHOPPICK connection is unavailable.');
    }
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
              statusCode: 422,
              fieldErrors: (data['errors'] as Map).map((key, value) => MapEntry(
                  key.toString(),
                  value is List
                      ? value.whereType<String>().toList()
                      : <String>[])));
        }
      }
      if (path == 'login' && response.statusCode == 403) {
        throw ApiException(
            data is Map && data['message'] is String
                ? data['message']
                : 'This account is unavailable.',
            statusCode: 403);
      }
      if (response.statusCode == 403) {
        throw const ApiException(
            'You do not have permission to perform this action.',
            statusCode: 403);
      }
      if (response.statusCode == 404) {
        throw const ApiException('This item is no longer available.',
            statusCode: 404);
      }
      if ((response.statusCode == 409 || response.statusCode == 422) &&
          data is Map &&
          data['message'] is String) {
        throw ApiException(data['message'], statusCode: response.statusCode);
      }
      if (response.statusCode == 429) {
        throw const ApiException('Too many requests. Please try again shortly.',
            statusCode: 429);
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
    if (revision == _sessionRevision &&
        data is Map &&
        data['unread_count'] is num) {
      if (path.startsWith('notifications')) {
        notificationCount.value = (data['unread_count'] as num).toInt();
      } else if (path.startsWith('messages')) {
        messageCount.value = (data['unread_count'] as num).toInt();
      }
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

  Future<dynamic> submitCheckout(Map<String, dynamic> body) async {
    final pending = await _storage.read(key: 'pending_checkout');
    if (pending != null) {
      final previous = Map<String, dynamic>.from(jsonDecode(pending) as Map);
      if (previous['idempotency_key'] != body['idempotency_key']) {
        throw const ApiException(
            'Check your last submission before starting another checkout.');
      }
      return _sendCheckout(previous);
    }
    await _storage.write(key: 'pending_checkout', value: jsonEncode(body));
    return _sendCheckout(body);
  }

  Future<dynamic> recoverCheckout() async {
    final saved = await _storage.read(key: 'pending_checkout');
    if (saved == null) {
      throw const ApiException('There is no pending checkout to check.');
    }
    return _sendCheckout(Map<String, dynamic>.from(jsonDecode(saved) as Map));
  }

  Future<dynamic> _sendCheckout(Map<String, dynamic> body) async {
    final revision = _sessionRevision;
    try {
      final result = await request('checkout', method: 'POST', body: body);
      if (revision == _sessionRevision) {
        await _storage.delete(key: 'pending_checkout');
      }
      return result;
    } on ApiException catch (error) {
      if (revision == _sessionRevision &&
          error.statusCode != null &&
          error.statusCode! >= 400 &&
          error.statusCode! < 500) {
        await _storage.delete(key: 'pending_checkout');
      }
      rethrow;
    }
  }

  Future<void> refreshNotificationCount() async {
    try {
      await request('notifications/unread-count');
    } catch (_) {}
  }

  Future<void> refreshMessageCount() async {
    try {
      await request('messages/unread-count');
    } catch (_) {}
  }

  Future<Map<String, String>> authorizationHeaders() async {
    final value = await tokenValue();
    return value == null ? const {} : {'Authorization': 'Bearer $value'};
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
      await _storage.delete(key: 'pending_checkout');
    } finally {
      cartCount.value = 0;
      sessionMessage = null;
      notificationCount.value = 0;
      messageCount.value = 0;
      session.value = SessionStatus.signedOut;
    }
  }
}
