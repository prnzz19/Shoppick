import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';

class ApiService {
  static const _storage = FlutterSecureStorage();
  Future<dynamic> request(String path,
      {String method = 'GET',
      Map<String, dynamic>? body,
      bool multipart = false}) async {
    final uri = Uri.parse('${ApiConfig.baseUrl}/$path');
    final token = await _storage.read(key: 'auth_token');
    final headers = <String, String>{'Accept': 'application/json'};
    if (!multipart) headers['Content-Type'] = 'application/json';
    if (token != null) headers['Authorization'] = 'Bearer $token';
    late http.Response response;
    try {
      switch (method) {
        case 'POST':
          response = await http
              .post(uri, headers: headers, body: jsonEncode(body ?? {}))
              .timeout(const Duration(seconds: 20));
          break;
        case 'PATCH':
          response = await http
              .patch(uri, headers: headers, body: jsonEncode(body ?? {}))
              .timeout(const Duration(seconds: 20));
          break;
        case 'DELETE':
          response = await http
              .delete(uri, headers: headers)
              .timeout(const Duration(seconds: 20));
          break;
        default:
          response = await http
              .get(uri, headers: headers)
              .timeout(const Duration(seconds: 20));
      }
    } catch (e) {
      throw Exception(
        'Connection error: $e. Check your internet connection and API URL.',
      );
    }
    dynamic data;
    try {
      data = jsonDecode(response.body);
    } catch (_) {
      data = {};
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final errors = data is Map ? data['errors'] : null;
      final firstError = errors is Map && errors.values.isNotEmpty
          ? errors.values.first.first
          : null;
      throw Exception(firstError ??
          (data is Map ? data['message'] : null) ??
          'Request failed (${response.statusCode}).');
    }
    return data;
  }

  Future<void> saveToken(String token) =>
      _storage.write(key: 'auth_token', value: token);
  Future<String?> token() => _storage.read(key: 'auth_token');
  Future<void> clearToken() => _storage.delete(key: 'auth_token');
}
