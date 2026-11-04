import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
import 'api_service.dart';

class PhilippineLocation {
  final String code, name;
  const PhilippineLocation(this.code, this.name);
  static PhilippineLocation fromJson(Map value) {
    if (value['code'] is! String || value['name'] is! String) {
      throw const FormatException('Invalid Philippine location response.');
    }
    return PhilippineLocation(value['code'], value['name']);
  }
}

class PhilippineLocationService {
  final Map<String, Future<List<PhilippineLocation>>> _cache = {};
  Future<List<PhilippineLocation>> get(String path) =>
      _cache.putIfAbsent(path, () => _fetch(path));
  Future<List<PhilippineLocation>> _fetch(String path) async {
    try {
      final uri = ApiConfig.backend('/api/philippine-locations/$path');
      if (kReleaseMode && uri.scheme != 'https') {
        throw const ApiException(
            'A secure SHOPPICK connection is unavailable.');
      }
      final response = await http.get(uri, headers: {
        'Accept': 'application/json'
      }).timeout(const Duration(seconds: 20));
      if (response.statusCode != 200) {
        throw const ApiException(
            'Philippine locations are temporarily unavailable. Please retry.');
      }
      final data = jsonDecode(response.body);
      if (data is! Map || data['data'] is! List) {
        throw const FormatException('Invalid location response.');
      }
      return (data['data'] as List)
          .map((row) => PhilippineLocation.fromJson(row as Map))
          .toList();
    } catch (_) {
      _cache.remove(path);
      rethrow;
    }
  }
}

class RegistrationLocations extends ChangeNotifier {
  final PhilippineLocationService service;
  RegistrationLocations({PhilippineLocationService? service})
      : service = service ?? PhilippineLocationService();
  List<PhilippineLocation> regions = [],
      provinces = [],
      cities = [],
      barangays = [];
  PhilippineLocation? region, province, city, barangay;
  String? loading, error, errorField;
  bool noProvince = false, _disposed = false;
  int _revision = 0;
  Future<void> Function()? _retry;
  bool get complete =>
      region != null &&
      (noProvince || province != null) &&
      city != null &&
      barangay != null &&
      loading == null;
  void _notify() {
    if (!_disposed) notifyListeners();
  }

  Future<void> _load(String field, Future<void> Function(int) action,
      Future<void> Function() retry) async {
    final revision = ++_revision;
    loading = field;
    error = null;
    errorField = null;
    _retry = null;
    _notify();
    try {
      await action(revision);
    } catch (_) {
      if (!_disposed && revision == _revision) {
        error =
            'Philippine locations are temporarily unavailable. Please retry.';
        errorField = field;
        _retry = retry;
      }
    } finally {
      if (!_disposed && revision == _revision) {
        loading = null;
        _notify();
      }
    }
  }

  bool _current(int revision) => !_disposed && revision == _revision;
  Future<void> loadRegions() => _load('region', (revision) async {
        final values = await service.get('regions');
        if (_current(revision)) regions = values;
      }, loadRegions);
  Future<void> selectRegion(PhilippineLocation value,
      {bool retry = false}) async {
    if (!retry && region?.code == value.code) return;
    region = value;
    province = city = barangay = null;
    provinces = [];
    cities = [];
    barangays = [];
    noProvince = false;
    await _load('province', (revision) async {
      final values = await service
          .get('regions/${Uri.encodeComponent(value.code)}/provinces');
      if (!_current(revision)) return;
      provinces = values;
      noProvince = values.isEmpty;
      if (noProvince) {
        loading = 'city';
        _notify();
        final rows = await service.get(
            'regions/${Uri.encodeComponent(value.code)}/cities-municipalities');
        if (_current(revision)) cities = rows;
      }
    }, () => selectRegion(value, retry: true));
  }

  Future<void> selectProvince(PhilippineLocation value,
      {bool retry = false}) async {
    if (!retry && province?.code == value.code) return;
    province = value;
    city = barangay = null;
    cities = [];
    barangays = [];
    await _load('city', (revision) async {
      final rows = await service.get(
          'provinces/${Uri.encodeComponent(value.code)}/cities-municipalities');
      if (_current(revision)) cities = rows;
    }, () => selectProvince(value, retry: true));
  }

  Future<void> selectCity(PhilippineLocation value,
      {bool retry = false}) async {
    if (!retry && city?.code == value.code) return;
    city = value;
    barangay = null;
    barangays = [];
    await _load('barangay', (revision) async {
      final rows = await service.get(
          'cities-municipalities/${Uri.encodeComponent(value.code)}/barangays');
      if (_current(revision)) barangays = rows;
    }, () => selectCity(value, retry: true));
  }

  void selectBarangay(PhilippineLocation value) {
    barangay = value;
    _notify();
  }

  Future<void> retry() async {
    await _retry?.call();
  }

  Map<String, String> get payload => {
        'region': region?.name ?? '',
        'region_code': region?.code ?? '',
        'province': province?.name ?? '',
        'province_code': province?.code ?? '',
        'city': city?.name ?? '',
        'city_code': city?.code ?? '',
        'barangay': barangay?.name ?? '',
        'barangay_code': barangay?.code ?? '',
      };
  @override
  void dispose() {
    _disposed = true;
    _revision++;
    super.dispose();
  }
}
