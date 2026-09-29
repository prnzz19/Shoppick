import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:image_picker/image_picker.dart';
import '../config/app_config.dart';

class ApiFailure implements Exception {
  final String message;
  final int? status;
  const ApiFailure(this.message, [this.status]);
  @override
  String toString() => message;
}

class ApiClient extends ChangeNotifier {
  final http.Client client;
  final FlutterSecureStorage storage;
  ApiClient({http.Client? client, this.storage = const FlutterSecureStorage()})
      : client = client ?? http.Client();
  String? token;
  Map<String, dynamic>? user;
  bool get manager => user?['role'] == 'logistics';
  String get prefix => manager ? 'logistics' : 'rider';
  bool can(String permission) =>
      (user?['permissions'] as List? ?? []).contains(permission);
  Future<void> restore() async {
    token = await storage.read(key: 'logistics_token');
    if (token == null) return;
    try {
      user = (await request('GET', 'logistics/profile'))['user'];
    } on ApiFailure catch (e) {
      if (e.status != 401 && e.status != 403) rethrow;
    }
  }

  Future<void> login(String email, String password) async {
    final result = await request('POST', 'logistics/login',
        body: {'email': email.trim(), 'password': password});
    final role = result['user']['role'];
    if (role != 'logistics' && role != 'rider') {
      throw const ApiFailure(
          'This account does not have access to SHOPPICK Logistics.');
    }
    await storage.write(key: 'logistics_token', value: result['token']);
    token = result['token'];
    user = result['user'];
    notifyListeners();
  }

  Future<void> logout() async {
    await request('POST', 'logistics/logout');
    await clear();
  }

  Future<void> clear() async {
    token = null;
    user = null;
    await storage.delete(key: 'logistics_token');
    notifyListeners();
  }

  Future<Map<String, dynamic>> request(String method, String path,
      {Map<String, dynamic>? body, Map<String, String>? query}) async {
    final uri = Uri.parse('$apiBaseUrl/$path').replace(queryParameters: query);
    final req = http.Request(method, uri)
      ..headers.addAll({
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token'
      });
    if (body != null) req.body = jsonEncode(body);
    try {
      return await _decode(await http.Response.fromStream(
              await client.send(req).timeout(const Duration(seconds: 25)))
          .timeout(const Duration(seconds: 25)));
    } on ApiFailure {
      rethrow;
    } catch (_) {
      throw const ApiFailure(
          'Unable to connect to SHOPPICK Logistics. Please try again.');
    }
  }

  Future<Map<String, dynamic>> upload(
      int id, XFile file, String recipient, String notes) async {
    final bytes = await file.readAsBytes();
    if (bytes.length > 5 * 1024 * 1024) {
      throw const ApiFailure('Choose a photo smaller than 5 MB.');
    }
    final ext = file.name.split('.').last.toLowerCase();
    final type = ext == 'png'
        ? 'png'
        : ext == 'webp'
            ? 'webp'
            : 'jpeg';
    final req = http.MultipartRequest(
        'POST', Uri.parse('$apiBaseUrl/rider/deliveries/$id/proof'))
      ..headers.addAll(
          {'Accept': 'application/json', 'Authorization': 'Bearer $token'})
      ..fields
          .addAll({'recipient_name': recipient.trim(), 'notes': notes.trim()})
      ..files.add(http.MultipartFile.fromBytes('photo', bytes,
          filename: 'delivery.$type', contentType: MediaType('image', type)));
    try {
      return await _decode(await http.Response.fromStream(
              await client.send(req).timeout(const Duration(seconds: 45)))
          .timeout(const Duration(seconds: 45)));
    } on ApiFailure {
      rethrow;
    } catch (_) {
      throw const ApiFailure(
          'Unable to upload proof. Check your connection and try again.');
    }
  }

  Future<Map<String, dynamic>> _decode(http.Response response) async {
    Map<String, dynamic> data = {};
    try {
      data = jsonDecode(response.body) as Map<String, dynamic>;
    } catch (_) {/* Never expose HTML error pages. */}
    if (response.statusCode >= 200 && response.statusCode < 300) return data;
    if (response.statusCode == 401) {
      await clear();
      throw const ApiFailure(
          'Your session has expired. Please sign in again.', 401);
    }
    if (response.statusCode == 403 &&
        data['message'] ==
            'This account does not have access to SHOPPICK Logistics.') {
      await clear();
    }
    String message = 'Unable to complete this request. Please try again.';
    if ([403, 422].contains(response.statusCode)) {
      final errors = data['errors'];
      message = errors is Map && errors.isNotEmpty
          ? (errors.values.first as List).first.toString()
          : data['message']?.toString() ?? message;
    } else if (response.statusCode == 404) {
      message = 'This delivery or record is no longer available.';
    } else if (response.statusCode == 429) {
      message = 'Too many requests. Please wait a moment and try again.';
    }
    if (message.contains('SQLSTATE') || message.length > 400) {
      message = 'Unable to complete this request. Please try again.';
    }
    throw ApiFailure(message, response.statusCode);
  }
}
