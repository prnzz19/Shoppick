import 'package:geolocator/geolocator.dart';
import 'api_client.dart';

abstract class RiderLocation {
  Future<void> requestAccess();
  Stream<Position> positions();
}

class DeviceRiderLocation implements RiderLocation {
  @override
  Future<void> requestAccess() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const ApiFailure(
          'GPS is turned off. Enable device location and try again.');
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw const ApiFailure(
          'Location permission is required while delivering this shipment. Enable it in app settings if previously denied.');
    }
  }

  @override
  Stream<Position> positions() => Geolocator.getPositionStream(
      locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high, distanceFilter: 25));
}
