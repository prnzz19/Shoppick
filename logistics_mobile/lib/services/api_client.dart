import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:image_picker/image_picker.dart';
import '../config/app_config.dart';
part 'rider_portal_api.dart';

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
  bool portalV1 = false;
  final Map<int, String> riderVersions = {};
  String? token;
  Map<String, dynamic>? user;
  bool get manager => user?['role'] == 'logistics';
  String get prefix => manager ? 'logistics' : 'rider';
  bool can(String permission) =>
      (user?['permissions'] as List? ?? []).contains(permission);
  Future<void> restore() async {
    token = await storage.read(key: 'logistics_token');
    portalV1 = await storage.read(key: 'rider_api_contract') == 'portal-v1';
    if (token == null) return;
    try {
      Map<String, dynamic> data;
      try {
        data = await request('GET', portalV1 ? 'profile' : 'logistics/profile');
      } on ApiFailure catch (error) {
        if (portalV1 || error.status != 404) rethrow;
        data = await request('GET', 'profile');
        data = {...data, 'user': portalRiderUser(data['user'])};
        await request('GET', 'rider/jobs', query: {'per_page': '1'});
        portalV1 = true;
        await storage.write(key: 'rider_api_contract', value: 'portal-v1');
      }
      user = portalV1 ? portalRiderUser(data['user']) : data['user'];
    } on ApiFailure catch (e) {
      if (e.status != 401 && e.status != 403) rethrow;
      await clear();
    }
  }

  Future<void> login(String email, String password) async {
    Map<String, dynamic> result;
    portalV1 = false;
    try {
      result = await request('POST', 'logistics/login',
          body: {'email': email.trim(), 'password': password});
    } on ApiFailure catch (error) {
      if (error.status != 404) rethrow;
      portalV1 = true;
      result = await request('POST', 'login',
          body: {'email': email.trim(), 'password': password});
      result = {...result, 'user': portalRiderUser(result['user'])};
    }
    if (result['token'] is! String || (result['token'] as String).isEmpty) {
      throw const ApiFailure(
          'The server did not return a valid mobile session.');
    }
    if (portalV1) {
      token = result['token'];
      try {
        await request('GET', 'rider/jobs', query: {'per_page': '1'});
      } catch (_) {
        await clear();
        rethrow;
      }
    }
    final role = result['user']['role'];
    if (role != 'logistics' && role != 'rider') {
      throw const ApiFailure(
          'This account does not have access to SHOPPICK Logistics.');
    }
    await storage.write(key: 'logistics_token', value: result['token']);
    token = result['token'];
    user = result['user'];
    await storage.write(
        key: 'rider_api_contract', value: portalV1 ? 'portal-v1' : 'legacy');
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      await request('POST', portalV1 ? 'logout' : 'logistics/logout');
    } on ApiFailure {
      if (manager) await clear();
      rethrow;
    }
    await clear();
  }

  Future<Map<String, dynamic>> refreshProfile() async {
    final path = portalV1
        ? 'profile'
        : manager
            ? 'logistics/profile'
            : 'rider/profile';
    final result = await request('GET', path);
    user = portalV1
        ? {...?user, ...portalRiderUser(result['user'])}
        : Map<String, dynamic>.from(result['user']);
    notifyListeners();
    return user!;
  }

  Future<Map<String, dynamic>> riderDashboard() =>
      request('GET', 'rider/dashboard');

  Future<Map<String, dynamic>> updateRiderProfile(
          Map<String, dynamic> fields) =>
      request('PATCH', 'rider/profile', body: fields);

  Future<Map<String, dynamic>> riderSettings() =>
      request('GET', 'rider/settings');

  Future<Map<String, dynamic>> updateRiderSettings(
          Map<String, bool> preferences) =>
      request('PUT', 'rider/settings', body: preferences);

  Future<void> changeRiderPassword(
      String currentPassword, String password, String confirmation) async {
    await request('PUT', 'rider/password', body: {
      'current_password': currentPassword,
      'password': password,
      'password_confirmation': confirmation,
    });
  }

  Future<Map<String, dynamic>> riderMessages({int page = 1}) =>
      request('GET', 'rider/messages', query: {'page': '$page'});

  Future<void> sendRiderMessage(String message) async {
    await request('POST', 'rider/messages', body: {'message': message.trim()});
  }

  Future<Map<String, dynamic>> riderCod({int page = 1}) =>
      request('GET', 'rider/cod', query: {'page': '$page'});

  Future<Map<String, dynamic>> riderProofs({int page = 1}) =>
      request('GET', 'rider/proofs', query: {'page': '$page'});

  Future<void> shareRiderLocationForDelivery(
      int deliveryId, double latitude, double longitude,
      {double? accuracy, DateTime? recordedAt}) async {
    if (portalV1) {
      throw const ApiFailure(
          'This API contract does not expose per-delivery location sharing.');
    }
    if (!latitude.isFinite ||
        !longitude.isFinite ||
        latitude.abs() > 90 ||
        longitude.abs() > 180) {
      throw const ApiFailure('The device did not provide a valid GPS fix.');
    }
    await request('POST', 'rider/deliveries/$deliveryId/location', body: {
      'latitude': latitude,
      'longitude': longitude,
      if (accuracy != null) 'accuracy': accuracy,
      'recorded_at': (recordedAt ?? DateTime.now()).toUtc().toIso8601String(),
    });
  }

  Future<void> markRiderNotificationRead(int id) async {
    await request('POST', 'rider/notifications/$id/read');
  }

  Future<String> setRiderAvailability(String availability) async {
    if (!['available', 'off_duty'].contains(availability)) {
      throw const ApiFailure('Choose Available or Off Duty.');
    }
    final result = await request('PATCH', 'rider/availability',
        body: {'availability': availability});
    final confirmed =
        (portalV1 ? (result['data']?['availability']) : result['availability'])
            ?.toString();
    if (!['available', 'off_duty'].contains(confirmed)) {
      throw const ApiFailure(
          'Availability was not confirmed. Refresh before retrying.');
    }
    user = {...?user, 'availability': confirmed};
    notifyListeners();
    return confirmed!;
  }

  Future<Map<String, dynamic>> riderAssignments(
          {int page = 1, Map<String, String> filters = const {}}) =>
      portalV1
          ? portalPage(page: page, filters: filters)
          : request('GET', 'rider/deliveries',
              query: {'history': '0', 'page': '$page', ...filters});
  Future<Map<String, dynamic>> riderHistory({int page = 1}) => portalV1
      ? portalPage(page: page, history: true)
      : request('GET', 'rider/deliveries',
          query: {'history': '1', 'page': '$page'});
  Future<Map<String, dynamic>> riderAssignment(int id) async {
    if (!portalV1) return request('GET', 'rider/deliveries/$id');
    final result = await request('GET', 'rider/jobs/$id');
    return {'delivery': portalDelivery(result['data'])};
  }

  Future<Map<String, dynamic>> acceptRiderAssignment(
      int id, String action) async {
    if (!['accept_pickup', 'accept_delivery'].contains(action)) {
      throw const ApiFailure('This assignment cannot be accepted.');
    }
    return _riderAssignmentAction(id, {'action': action});
  }

  Future<Map<String, dynamic>> declineRiderAssignment(
          int id, String reason, String details) =>
      _riderAssignmentAction(id, {
        'action': 'decline',
        'reason': reason,
        if (details.trim().isNotEmpty) 'details': details.trim()
      });

  Future<Map<String, dynamic>> performRiderDeliveryAction(
    int id,
    String action, {
    String? parcelCode,
    String? reason,
    String? details,
  }) {
    const allowed = {
      'confirm_pickup',
      'arrive_sorting',
      'start_delivery',
      'collect_cod',
      'delivered',
      'failed',
    };
    if (action == 'legacy_out') {
      throw const ApiFailure('This delivery action is not supported.');
    }
    if (action == 'start_delivery' && portalV1) {
      throw const ApiFailure(
          'This API contract does not verify destination parcel scans.');
    }
    if (action == 'start_delivery' &&
        (parcelCode == null || parcelCode.trim().isEmpty)) {
      throw const ApiFailure('Scan the parcel before starting delivery.');
    }
    if (!allowed.contains(action)) {
      throw const ApiFailure('This delivery action is not supported.');
    }
    return _riderAssignmentAction(id, {
      'action': action,
      if (parcelCode != null) 'parcel_code': parcelCode.trim(),
      if (reason != null) 'reason': reason,
      if (details != null && details.trim().isNotEmpty)
        'details': details.trim(),
    });
  }

  Future<Map<String, dynamic>> _riderAssignmentAction(
      int id, Map<String, dynamic> body) async {
    try {
      if (portalV1) {
        if (riderVersions[id] == null) {
          throw const ApiFailure(
              'Refresh this assignment before submitting an action.');
        }
        final action = body['action'];
        final endpoint = action == 'decline'
            ? 'decline'
            : action == 'collect_cod'
                ? 'cod'
                : action == 'accept_pickup' || action == 'accept_delivery'
                    ? 'accept'
                    : 'action';
        final payload = {
          ...body,
          if (riderVersions[id] != null) 'version': riderVersions[id]
        };
        if (endpoint == 'cod' ||
            endpoint == 'accept' ||
            endpoint == 'decline') {
          payload.remove('action');
        }
        return await request('POST', 'rider/shipments/$id/$endpoint',
            body: payload);
      }
      return await request('PATCH', 'rider/deliveries/$id/status', body: body);
    } on ApiFailure catch (error) {
      if (error.status == 404) {
        throw const ApiFailure('This assignment is no longer available.', 404);
      }
      rethrow;
    }
  }

  Future<void> clear() async {
    token = null;
    user = null;
    await storage.delete(key: 'logistics_token');
    await storage.delete(key: 'rider_api_contract');
    riderVersions.clear();
    notifyListeners();
  }

  Future<Map<String, dynamic>> request(String method, String path,
      {Map<String, dynamic>? body, Map<String, String>? query}) async {
    final uri = Uri.parse('$apiBaseUrl/$path').replace(queryParameters: query);
    if (kReleaseMode && uri.scheme != 'https') {
      throw const ApiFailure('A secure SHOPPICK connection is required.');
    }
    final req = http.Request(method, uri)
      ..headers.addAll({
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (token != null && token!.isNotEmpty)
          'Authorization': 'Bearer ${token!}',
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
      int id, XFile file, String recipient, String notes,
      {String? version}) async {
    if (kReleaseMode && Uri.parse(apiBaseUrl).scheme != 'https') {
      throw const ApiFailure('A secure SHOPPICK connection is required.');
    }
    if (recipient.trim().isEmpty ||
        recipient.trim().length > 100 ||
        notes.length > 1000) {
      throw const ApiFailure(
          'Enter a recipient name up to 100 characters and notes up to 1000 characters.');
    }
    if (token == null || token!.isEmpty) {
      throw const ApiFailure(
          'Sign in before submitting proof of delivery.', 401);
    }
    final latest = await riderAssignment(id);
    final delivery = latest['delivery'] as Map;
    if (version != null && version != delivery['version']) {
      throw const ApiFailure(
          'This assignment changed. Refresh it before submitting proof.', 409);
    }
    final actions =
        (delivery['allowed_actions'] ?? delivery['actions'] ?? []) as List;
    if (!actions.contains(portalV1 ? 'submit_pod' : 'proof')) {
      throw const ApiFailure(
          'Proof of Delivery is not currently authorized for this assignment.');
    }
    final bytes = await file.readAsBytes();
    if (!['jpg', 'jpeg', 'png', 'webp']
        .contains(file.name.split('.').last.toLowerCase())) {
      throw const ApiFailure('Choose a JPG, PNG, or WEBP delivery photo.');
    }
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
        'POST',
        Uri.parse(
            '$apiBaseUrl/${portalV1 ? 'rider/shipments/$id/pod' : 'rider/deliveries/$id/proof'}'))
      ..headers.addAll(
          {'Accept': 'application/json', 'Authorization': 'Bearer ${token!}'})
      ..fields.addAll({
        'recipient_name': recipient.trim(),
        'notes': notes.trim(),
        if (riderVersions[id] != null) 'version': riderVersions[id]!
      })
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
    if (response.statusCode >= 200 && response.statusCode < 300) {
      if ((!manager && data.isEmpty) || data['success'] == false) {
        throw const ApiFailure(
            'The server did not confirm this request. Refresh before retrying.');
      }
      return data;
    }
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
    if ([403, 409, 422].contains(response.statusCode)) {
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
