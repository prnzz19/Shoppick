import 'dart:async';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../config/app_config.dart';
import '../models/shipment_tracking.dart';
import '../models/delivery.dart';
import '../services/api_client.dart';
import '../services/rider_location.dart';
import '../widgets/common.dart';
import '../widgets/shoppick_brand.dart';
import 'delivery_detail_screen.dart';

class TrackingScreen extends StatefulWidget {
  final ApiClient api;
  final int id;
  final RiderLocation? location;
  final Future<bool> Function()? mapsConfigured;
  const TrackingScreen(
      {required this.api,
      required this.id,
      this.location,
      this.mapsConfigured,
      super.key});
  @override
  State<TrackingScreen> createState() => _TrackingScreenState();
}

class _TrackingScreenState extends State<TrackingScreen>
    with WidgetsBindingObserver {
  ShipmentTracking? data;
  String? error, locationMessage;
  bool configured = false,
      loading = true,
      fetching = false,
      sharing = false,
      sending = false,
      visible = true;
  int generation = 0;
  Timer? poll;
  StreamSubscription<Position>? stream;
  GoogleMapController? controller;
  DateTime? lastSent;
  late final RiderLocation location = widget.location ?? DeviceRiderLocation();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    widget.api.addListener(authChanged);
    initialize();
  }

  Future<void> initialize() async {
    try {
      configured = await (widget.mapsConfigured?.call() ??
          const MethodChannel('shoppick/maps')
              .invokeMethod<bool>('configured')
              .then((v) => v ?? false));
    } catch (_) {
      configured = false;
    }
    if (!mounted) return;
    await refresh();
    if (mounted && visible) startPolling();
  }

  void authChanged() {
    if (widget.api.token == null || widget.api.user == null) {
      stopSharing();
      poll?.cancel();
      if (mounted) {
        setState(() {
          data = null;
          error = 'Your session has ended. Sign in again.';
        });
      }
    }
  }

  void startPolling() {
    if (!mounted || !visible || widget.api.token == null) return;
    poll?.cancel();
    poll = Timer.periodic(const Duration(seconds: 20), (_) => refresh());
  }

  Future<void> refresh() async {
    if (fetching ||
        !visible ||
        !mounted ||
        ModalRoute.of(context)?.isCurrent == false) {
      return;
    }
    fetching = true;
    try {
      final result = ShipmentTracking(await widget.api.request(
          'GET', '${widget.api.prefix}/deliveries/${widget.id}/tracking'));
      if (!mounted || !visible) return;
      data = result;
      error = null;
      if (!result.canShare) await stopSharing();
    } catch (e) {
      await stopSharing();
      if (mounted) {
        error = friendly(e);
        data = null;
      }
    } finally {
      fetching = false;
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> stopSharing() async {
    generation++;
    sharing = false;
    final previous = stream;
    stream = null;
    await previous?.cancel();
  }

  Future<void> beginSharing() async {
    if (sharing || data?.canShare != true || !visible) return;
    final attempt = ++generation;
    setState(() {
      sharing = true;
      locationMessage = 'Requesting location permission...';
    });
    try {
      await location.requestAccess();
      if (!mounted ||
          !visible ||
          attempt != generation ||
          data?.canShare != true) {
        return;
      }
      setState(() =>
          locationMessage = 'Sharing location while this screen is open.');
      stream = location.positions().listen(sendPosition, onError: (Object e) {
        stopSharing();
        if (mounted) {
          setState(() => locationMessage =
              'Unable to obtain GPS location. Check device location and try again.');
        }
      });
    } catch (e) {
      if (attempt != generation) return;
      await stopSharing();
      if (mounted) setState(() => locationMessage = friendly(e));
    }
  }

  Future<void> sendPosition(Position position) async {
    if (!sharing ||
        !visible ||
        sending ||
        data?.canShare != true ||
        widget.api.token == null) {
      return;
    }
    final now = DateTime.now();
    if (lastSent != null &&
        now.difference(lastSent!) < const Duration(seconds: 15)) {
      return;
    }
    sending = true;
    lastSent = now;
    try {
      await widget.api
          .request('POST', 'rider/deliveries/${widget.id}/location', body: {
        'latitude': position.latitude,
        'longitude': position.longitude,
        'accuracy': position.accuracy,
        'recorded_at': position.timestamp.toUtc().toIso8601String()
      });
      if (mounted && visible && sharing) {
        setState(() => locationMessage =
            'Location shared ${dateText(now.toIso8601String())}');
        await refresh();
      }
    } catch (e) {
      await stopSharing();
      if (mounted) {
        setState(() => locationMessage = friendly(e));
        await refresh();
      }
    } finally {
      sending = false;
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    visible = state == AppLifecycleState.resumed &&
        ModalRoute.of(context)?.isCurrent != false;
    if (!visible) {
      poll?.cancel();
      stopSharing();
    } else {
      refresh();
      startPolling();
    }
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    visible = false;
    poll?.cancel();
    stopSharing();
    widget.api.removeListener(authChanged);
    WidgetsBinding.instance.removeObserver(this);
    controller?.dispose();
    super.dispose();
  }

  Set<Marker> markers(ShipmentTracking tracking) {
    final result = <Marker>{};
    void add(String id, dynamic value, String title, double hue) {
      final p = trackingCoordinate(value);
      if (p != null) {
        result.add(Marker(
            markerId: MarkerId(id),
            position: p,
            infoWindow: InfoWindow(title: title),
            icon: BitmapDescriptor.defaultMarkerWithHue(hue)));
      }
    }

    add('origin', tracking.json['origin'], 'Pickup / origin',
        BitmapDescriptor.hueOrange);
    add('destination', tracking.json['destination'], 'Customer destination',
        BitmapDescriptor.hueRed);
    for (final e in tracking.events) {
      add(
          'event-${e['id']}',
          e['coordinates'],
          '${label(e['status'])} — ${e['location'] ?? 'Recorded event'}',
          BitmapDescriptor.hueCyan);
    }
    add('rider', tracking.json['current_rider_location'],
        'Rider — last reported position', BitmapDescriptor.hueAzure);
    final latest = tracking.points.isEmpty ? null : tracking.points.last;
    if (tracking.json['current_rider_location'] == null && latest != null) {
      add('last', latest, 'Last recorded position (historical)',
          BitmapDescriptor.hueViolet);
    }
    return result;
  }

  Future<void> fit(Set<Marker> items) async {
    if (controller == null || items.isEmpty) return;
    final positions = items.map((m) => m.position).toList();
    try {
      if (positions.length == 1) {
        await controller!
            .animateCamera(CameraUpdate.newLatLngZoom(positions.first, 15));
        return;
      }
      final south = positions.map((p) => p.latitude).reduce(math.min),
          north = positions.map((p) => p.latitude).reduce(math.max);
      final west = positions.map((p) => p.longitude).reduce(math.min),
          east = positions.map((p) => p.longitude).reduce(math.max);
      if (south == north && west == east) {
        await controller!
            .animateCamera(CameraUpdate.newLatLngZoom(positions.first, 15));
        return;
      }
      await controller!.animateCamera(CameraUpdate.newLatLngBounds(
          LatLngBounds(
              southwest: LatLng(south, west), northeast: LatLng(north, east)),
          48));
    } catch (_) {
      if (mounted) {
        setState(() => locationMessage =
            'Map camera unavailable. You can move the map manually.');
      }
    }
  }

  Future<void> openActions() async {
    visible = false;
    poll?.cancel();
    await stopSharing();
    if (!mounted) return;
    await Navigator.push(
        context,
        MaterialPageRoute(
            builder: (_) => DeliveryDetailScreen(
                api: widget.api, id: widget.id, showTracking: false)));
    if (!mounted) return;
    visible = true;
    await refresh();
    startPolling();
  }

  @override
  Widget build(BuildContext context) {
    final tracking = data;
    final items = tracking == null ? <Marker>{} : markers(tracking);
    final path =
        tracking?.points.map(trackingCoordinate).whereType<LatLng>().toList() ??
            <LatLng>[];
    return Scaffold(
        appBar: AppBar(
            title: const ShopPickBrand(
                subtitle: 'Shipment tracking', compact: true),
            actions: [
              IconButton(
                  onPressed: refresh,
                  icon: const Icon(Icons.refresh),
                  tooltip: 'Refresh')
            ]),
        body: LoadView(
            message: 'Loading shipment tracking...',
            loading: loading,
            error: error,
            retry: refresh,
            child: tracking == null
                ? const SizedBox()
                : ListView(padding: const EdgeInsets.all(16), children: [
                    SectionTitle(tracking.delivery.text('tracking_number')),
                    Text('Order ${tracking.delivery.text('order_number')}'),
                    Align(
                        alignment: Alignment.centerLeft,
                        child: StatusBadge(tracking.delivery.status)),
                    Text(
                        'Provider: ${tracking.delivery.text('provider', 'Not assigned')}'),
                    Text(
                        'Rider: ${tracking.delivery.text('rider', tracking.delivery.text('pickup_rider', 'Unassigned'))}'),
                    Text(
                        'Shipment updated: ${dateText(tracking.delivery.json['updated_at'])}'),
                    if (tracking.json['current_rider_location'] != null)
                      Text(
                          'Rider reported: ${dateText(tracking.json['current_rider_location']['recorded_at'])}'),
                    const SizedBox(height: 12),
                    if (!configured)
                      const Card(
                          child: Padding(
                              padding: EdgeInsets.all(16),
                              child: Text(
                                  'Google Maps is not configured on this device. Tracking history remains available.'))),
                    if (items.isEmpty)
                      const Card(
                          child: Padding(
                              padding: EdgeInsets.all(16),
                              child: Text(
                                  'No map location has been recorded yet.'))),
                    if (configured && items.isNotEmpty) ...[
                      SizedBox(
                          height: 320,
                          child: GoogleMap(
                              initialCameraPosition: CameraPosition(
                                  target: items.first.position, zoom: 14),
                              markers: items,
                              myLocationButtonEnabled: false,
                              zoomControlsEnabled: false,
                              polylines: path.length < 2
                                  ? {}
                                  : {
                                      Polyline(
                                          polylineId:
                                              const PolylineId('tracked'),
                                          points: path,
                                          color: teal,
                                          width: 4)
                                    },
                              onMapCreated: (c) {
                                controller = c;
                                fit(items);
                              })),
                      TextButton(
                          onPressed: () => fit(items),
                          child: const Text('Fit recorded locations')),
                      const Text(
                          'Lines connect recorded GPS points; they are not a road route. If the map tiles are unavailable, use the timeline and refresh.'),
                    ],
                    if (tracking.active &&
                        tracking.json['current_rider_location'] == null)
                      const Text('Waiting for Rider location...'),
                    if (!widget.api.manager) ...[
                      if (tracking.canShare)
                        FilledButton.icon(
                            onPressed: sharing
                                ? () async {
                                    await stopSharing();
                                    if (mounted) {
                                      setState(() => locationMessage =
                                          'Location sharing stopped.');
                                    }
                                  }
                                : beginSharing,
                            icon: const Icon(Icons.my_location),
                            label: Text(sharing
                                ? 'Stop location sharing'
                                : 'Share location for this delivery'))
                      else
                        const Text(
                            'Location sharing is unavailable at this shipment stage.'),
                      if (locationMessage != null) Text(locationMessage!),
                      const Text(
                          'GPS is shared only with permission while this tracking screen is active. Sharing stops when you leave; tap Share again when returning.'),
                      OutlinedButton(
                          onPressed: openActions,
                          child: const Text('Delivery actions / proof')),
                    ],
                    const SectionTitle('Shipment details'),
                    Text('Pickup: ${tracking.delivery.text('pickup_address')}'),
                    Text(
                        'Destination: ${tracking.delivery.text('destination')}'),
                    const Text(
                        'Courier labels identify providers. Progress comes from SHOPPICK records.'),
                    const SectionTitle('Tracking timeline'),
                    if (tracking.events.isEmpty)
                      const Text('No shipment events have been recorded.'),
                    for (final event in tracking.events)
                      Card(
                          child: ListTile(
                              leading: Icon(
                                  trackingCoordinate(event['coordinates']) ==
                                          null
                                      ? Icons.history
                                      : Icons.location_on,
                                  color: teal),
                              title: Text(label(event['status'])),
                              subtitle: Text(
                                  '${event['note'] ?? ''}\n${dateText(event['created_at'])}\n${trackingCoordinate(event['coordinates']) == null ? 'Status only — no recorded coordinates' : 'Recorded map location'}'))),
                  ])));
  }
}
