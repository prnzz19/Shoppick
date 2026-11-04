part of 'api_client.dart';

Map<String, dynamic> portalRiderUser(dynamic value) {
  if (value is! Map) throw const ApiFailure('Rider profile is unavailable.');
  final roles = value['roles'] as List? ?? [];
  if (!roles.contains('rider') ||
      roles.any(
          (r) => ['admin', 'logistics', 'seller', 'super_admin'].contains(r))) {
    throw const ApiFailure('An authorized Rider account is required.', 403);
  }
  return {...Map<String, dynamic>.from(value), 'role': 'rider'};
}

extension RiderPortalApi on ApiClient {
  Map<String, dynamic> portalDelivery(dynamic value) {
    if (value is! Map || value['id'] is! num) {
      throw const ApiFailure('The assignment response is incomplete.');
    }
    final data = Map<String, dynamic>.from(value);
    data.remove('parcel_code');
    final id = (data['id'] as num).toInt();
    if (data['version'] is String) riderVersions[id] = data['version'];
    final cod = data['cod'] is Map ? data['cod'] as Map : null;
    return {
      ...data,
      'id': id,
      'tracking_number': data['shipment_number'] ?? data['tracking_number'],
      'actions': (data['allowed_actions'] as List? ?? [])
          .map((a) => a == 'submit_pod' ? 'proof' : a)
          .toList(),
      'pickup_address': data['assignment_type'] == 'delivery'
          ? null
          : data['pickup']?['address'],
      'destination': data['dropoff']?['address'],
      'pickup_coordinates': data['assignment_type'] == 'delivery'
          ? null
          : data['pickup']?['snapshot'],
      'dropoff_coordinates': data['dropoff']?['snapshot'],
      'shop': data['store']?['name'],
      'hub': data['assignment_type'] == 'delivery'
          ? (data['destination_center']?['name'])
          : (data['center']?['name']),
      if (cod != null) ...{
        'cod_amount': num.tryParse(cod['amount']?.toString() ?? ''),
        'cod_status': cod['status'],
        'payment_type': 'cod'
      },
    };
  }

  Future<Map<String, dynamic>> portalPage(
      {int page = 1,
      bool history = false,
      Map<String, String> filters = const {}}) async {
    final result = await request(
        'GET', history ? 'rider/history' : 'rider/jobs',
        query: {'page': '$page', 'per_page': '50', if (!history) ...filters});
    if (result['data'] is! List) {
      throw const ApiFailure('The Rider assignment list is incomplete.');
    }
    final meta = result['meta'] as Map? ?? {};
    return {
      'deliveries': {
        'data': (result['data'] as List).map(portalDelivery).toList(),
        'current_page': meta['current_page'] ?? page,
        'last_page': meta['last_page'] ?? page,
        'total': meta['total']
      }
    };
  }

  Future<List<Map<String, dynamic>>> allRiderRecords(
      {bool history = false}) async {
    final rows = <Map<String, dynamic>>[];
    var page = 1;
    var last = 1;
    do {
      final response = history
          ? await riderHistory(page: page)
          : await riderAssignments(page: page);
      final data = response['deliveries'] as Map;
      rows.addAll((data['data'] as List? ?? [])
          .whereType<Map>()
          .map((v) => Map<String, dynamic>.from(v)));
      last = (data['last_page'] as num? ?? page).toInt();
      page++;
      if (page > 100 && page <= last) {
        throw const ApiFailure(
            'Too many assignments to summarize safely. Open My Deliveries.');
      }
    } while (page <= last);
    return rows;
  }

  Future<void> shareRiderLocation(double latitude, double longitude) async {
    if (!portalV1) {
      throw const ApiFailure(
          'This server does not expose Rider pickup-location sharing.');
    }
    if (!latitude.isFinite ||
        !longitude.isFinite ||
        latitude.abs() > 90 ||
        longitude.abs() > 180) {
      throw const ApiFailure('The device did not provide a valid GPS fix.');
    }
    await request('PATCH', 'rider/location',
        body: {'latitude': latitude, 'longitude': longitude});
  }
}
