import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'delivery.dart';

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

class ShipmentTracking {
  final Map<String, dynamic> json;
  ShipmentTracking(this.json);
  Delivery get delivery => Delivery(json['delivery']);
  List<Map<String, dynamic>> get events => (json['events'] as List? ?? [])
      .map((e) => Map<String, dynamic>.from(e))
      .toList();
  List<Map<String, dynamic>> get points => (json['points'] as List? ?? [])
      .map((e) => Map<String, dynamic>.from(e))
      .toList();
  bool get canShare => json['can_share_location'] == true;
  bool get active => json['active'] == true;
}
