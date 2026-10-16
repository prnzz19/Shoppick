import 'package:google_maps_flutter/google_maps_flutter.dart';

LatLng? trackingCoordinate(dynamic value) {
  if (value is! Map) return null;
  final lat = double.tryParse('${value['latitude']}');
  final lng = double.tryParse('${value['longitude']}');
  if (lat == null ||
      lng == null ||
      !lat.isFinite ||
      !lng.isFinite ||
      lat.abs() > 90 ||
      lng.abs() > 180) {
    return null;
  }
  return LatLng(lat, lng);
}

class ShipmentTrackingModel {
  final Map<String, dynamic> json;
  ShipmentTrackingModel(this.json);
  int get id => (json['id'] as num).toInt();
  String get status => '${json['status'] ?? ''}';
  String get number => '${json['tracking_number'] ?? ''}';
  bool get live => json['live'] == true && status == 'out_for_delivery';
  List<Map<String, dynamic>> get events => (json['events'] as List? ?? [])
      .map((e) => Map<String, dynamic>.from(e))
      .toList();
  List<LatLng> get path => (json['points'] as List? ?? [])
      .map(trackingCoordinate)
      .whereType<LatLng>()
      .toList();
  LatLng? get current =>
      live ? trackingCoordinate(json['current_rider_location']) : null;
}
