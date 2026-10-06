part of '../main.dart';

String trackingDate(dynamic value) {
  final d = DateTime.tryParse('$value')?.toLocal();
  return d == null
      ? 'Not recorded'
      : '${date(value)} ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
}

String trackingStatus(dynamic value) => switch (value) {
      'at_sorting_center' => 'Received at sorting center',
      'assigned_to_rider' || 'assigned' => 'Rider assigned',
      'pickup_arrived' => 'Arrived at sorting center',
      'delivery_failed' ||
      'delivery_attempted' =>
        'Delivery attempt unsuccessful',
      _ => label(value),
    };

class TrackingTimeline extends StatelessWidget {
  final List<Map<String, dynamic>> events;
  const TrackingTimeline({required this.events, super.key});
  @override
  Widget build(BuildContext context) =>
      Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (events.isEmpty) const Text('Waiting for courier update...'),
        for (final event in events)
          ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(
                  trackingCoordinate(event['coordinates']) == null
                      ? Icons.history
                      : Icons.location_on_outlined,
                  color: teal),
              title: Text(trackingStatus(event['status'])),
              subtitle: Text(
                  '${trackingDate(event['created_at'])}\n${trackingCoordinate(event['coordinates']) == null ? 'Status only — no recorded coordinates' : 'Recorded map location'}')),
      ]);
}

class TrackingEntry extends StatelessWidget {
  final String orderNumber;
  final List<dynamic> shipments;
  final bool details;
  const TrackingEntry(
      {required this.orderNumber,
      required this.shipments,
      this.details = false,
      super.key});
  @override
  Widget build(BuildContext context) =>
      Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (shipments.isNotEmpty)
          FilledButton.icon(
              onPressed: () =>
                  open(context, TrackingMapScreen(orderNumber: orderNumber)),
              icon: const Icon(Icons.route),
              label: Text(details ? 'Track Order on Map' : 'Track Order')),
        if (details && shipments.isEmpty)
          const Text('No shipment created yet.'),
        if (details)
          for (final shipment in shipments) ...[
            SectionTitle(
                '${shipment['shop'] ?? 'Shipment'} • ${shipment['tracking_number']}'),
            Text(trackingStatus(shipment['status'])),
            TrackingTimeline(
                events: (shipment['events'] as List? ?? [])
                    .map((e) => Map<String, dynamic>.from(e))
                    .toList()),
          ],
      ]);
}

class TrackingMapScreen extends StatefulWidget {
  final String orderNumber;
  final int? sellerOrderId;
  final Future<dynamic> Function()? fetch;
  final Future<bool> Function()? mapsConfigured;
  const TrackingMapScreen(
      {required this.orderNumber,
      this.sellerOrderId,
      this.fetch,
      this.mapsConfigured,
      super.key});
  bool get seller => sellerOrderId != null;
  @override
  State<TrackingMapScreen> createState() => _TrackingMapScreenState();
}

class _TrackingMapScreenState extends State<TrackingMapScreen>
    with WidgetsBindingObserver {
  List<ShipmentTrackingModel> shipments = [];
  int? selected;
  String? error;
  bool loading = true,
      fetching = false,
      configured = false,
      foreground = true,
      canPoll = false;
  Timer? poll;
  int sessionGeneration = 0;
  GoogleMapController? controller;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    ApiService.session.addListener(sessionChanged);
    initialize();
  }

  void sessionChanged() {
    if (ApiService.session.value == SessionStatus.signedOut) {
      sessionGeneration++;
      poll?.cancel();
      canPoll = false;
      if (mounted) {
        setState(() {
          shipments = [];
          error = 'Your session has expired. Please sign in again.';
        });
      }
    }
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
    if (mounted) await refresh();
  }

  void schedulePolling() {
    poll?.cancel();
    if (mounted && foreground && canPoll) {
      poll = Timer.periodic(const Duration(seconds: 20), (_) => refresh());
    }
  }

  Future<void> refresh() async {
    if (!mounted ||
        !foreground ||
        fetching ||
        ModalRoute.of(context)?.isCurrent == false) {
      return;
    }
    fetching = true;
    final generation = sessionGeneration;
    try {
      final response = await (widget.fetch?.call() ??
          ApiService().request(widget.seller
              ? 'seller/orders/${widget.sellerOrderId}/tracking'
              : 'orders/${Uri.encodeComponent(widget.orderNumber)}/tracking'));
      if (!mounted || !foreground || generation != sessionGeneration) return;
      shipments = (response['shipments'] as List? ?? [])
          .map((s) => ShipmentTrackingModel(Map<String, dynamic>.from(s)))
          .toList();
      if (!shipments.any((s) => s.id == selected)) {
        selected = shipments.firstOrNull?.id;
      }
      canPoll = response['poll'] == true;
      error = null;
      schedulePolling();
    } catch (e) {
      poll?.cancel();
      canPoll = false;
      if (mounted) {
        shipments = [];
        error = e is ApiException
            ? e.message
            : 'Unable to load tracking. Please try again.';
      }
    } finally {
      fetching = false;
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    foreground = state == AppLifecycleState.resumed;
    if (!foreground) {
      poll?.cancel();
    } else if (canPoll) {
      refresh();
    }
  }

  @override
  void dispose() {
    poll?.cancel();
    controller?.dispose();
    WidgetsBinding.instance.removeObserver(this);
    ApiService.session.removeListener(sessionChanged);
    super.dispose();
  }

  Set<Marker> markers(ShipmentTrackingModel s) {
    final items = <Marker>{};
    void add(String id, LatLng? p, String title, double hue) {
      if (p != null) {
        items.add(Marker(
            markerId: MarkerId(id),
            position: p,
            infoWindow: InfoWindow(title: title),
            icon: BitmapDescriptor.defaultMarkerWithHue(hue)));
      }
    }

    add('pickup', trackingCoordinate(s.json['origin']), 'Seller / pickup',
        BitmapDescriptor.hueOrange);
    add('destination', trackingCoordinate(s.json['destination']),
        'Delivery destination', BitmapDescriptor.hueRed);
    final seen = <LatLng>{};
    for (final e in s.events.reversed) {
      final p = trackingCoordinate(e['coordinates']);
      if (p != null && seen.add(p) && seen.length <= 15) {
        add('event-${e['id']}', p, trackingStatus(e['status']),
            BitmapDescriptor.hueCyan);
      }
    }
    add('rider', s.current, 'Rider — last reported position',
        BitmapDescriptor.hueAzure);
    return items;
  }

  Future<void> fit(List<LatLng> points) async {
    if (controller == null || points.isEmpty) return;
    try {
      final south = points.map((p) => p.latitude).reduce(math.min),
          north = points.map((p) => p.latitude).reduce(math.max);
      final west = points.map((p) => p.longitude).reduce(math.min),
          east = points.map((p) => p.longitude).reduce(math.max);
      if (south == north && east == west) {
        await controller!
            .animateCamera(CameraUpdate.newLatLngZoom(points.first, 15));
      } else {
        await controller!.animateCamera(CameraUpdate.newLatLngBounds(
            LatLngBounds(
                southwest: LatLng(south, west), northeast: LatLng(north, east)),
            48));
      }
    } catch (_) {
      /* The timeline remains available if platform map rendering fails. */
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = shipments.where((s) => s.id == selected).firstOrNull;
    final items = s == null ? <Marker>{} : markers(s);
    final positions = [...items.map((m) => m.position), ...?s?.path];
    return Scaffold(
        appBar: AppBar(
            title: ShopPickBrand(
                compact: true,
                subtitle: widget.seller ? 'Track Shipment' : 'Track Order'),
            actions: [
              IconButton(
                  onPressed: refresh,
                  icon: const Icon(Icons.refresh),
                  tooltip: 'Refresh tracking')
            ]),
        body: loading
            ? const Center(
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                CircularProgressIndicator(),
                Text('Loading tracking...')
              ]))
            : error != null
                ? Center(
                    child: Padding(
                        padding: const EdgeInsets.all(24),
                        child:
                            Column(mainAxisSize: MainAxisSize.min, children: [
                          Text(error!, textAlign: TextAlign.center),
                          FilledButton(
                              onPressed: refresh, child: const Text('Retry'))
                        ])))
                : RefreshIndicator(
                    onRefresh: refresh,
                    child: ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(16),
                        children: [
                          SectionTitle(widget.orderNumber),
                          if (shipments.isEmpty)
                            const Text('No shipment created yet.'),
                          if (shipments.length > 1)
                            DropdownButtonFormField<int>(
                                initialValue: selected,
                                isExpanded: true,
                                decoration: const InputDecoration(
                                    labelText: 'Choose shipment'),
                                items: [
                                  for (final shipment in shipments)
                                    DropdownMenuItem(
                                        value: shipment.id,
                                        child: Text(
                                            '${shipment.json['shop'] ?? 'Shop'} • ${shipment.number}',
                                            overflow: TextOverflow.ellipsis))
                                ],
                                onChanged: (v) => setState(() => selected = v)),
                          if (s != null) ...[
                            Text(s.number,
                                style: const TextStyle(
                                    fontWeight: FontWeight.bold)),
                            Text(trackingStatus(s.status),
                                style: const TextStyle(
                                    color: teal,
                                    fontSize: 18,
                                    fontWeight: FontWeight.w700)),
                            Text(
                                'Provider: ${s.json['provider'] ?? 'Not assigned'}'),
                            Text(
                                'Last tracking update: ${trackingDate(s.json['last_tracking_update'])}'),
                            if (s.json['estimated_delivery_at'] != null)
                              Text(
                                  'Expected delivery: ${trackingDate(s.json['estimated_delivery_at'])}'),
                            if (['delivered', 'completed'].contains(s.status))
                              Text(
                                  'Delivered • ${trackingDate(s.json['delivered_at'])}'),
                            const SizedBox(height: 12),
                            if (positions.isEmpty)
                              const Card(
                                  child: Padding(
                                      padding: EdgeInsets.all(16),
                                      child: Text(
                                          'Map location is not available for this shipment yet.'))),
                            if (!configured)
                              const Card(
                                  child: Padding(
                                      padding: EdgeInsets.all(16),
                                      child: Text(
                                          'Google Maps is not configured on this device. Your tracking timeline remains available.'))),
                            if (configured && positions.isNotEmpty) ...[
                              SizedBox(
                                  height: 320,
                                  child: GoogleMap(
                                      key: ValueKey(s.id),
                                      initialCameraPosition: CameraPosition(
                                          target: positions.first, zoom: 14),
                                      markers: items,
                                      myLocationButtonEnabled: false,
                                      zoomControlsEnabled: false,
                                      polylines: s.path.length < 2
                                          ? {}
                                          : {
                                              Polyline(
                                                  polylineId: const PolylineId(
                                                      'recorded'),
                                                  points: s.path,
                                                  color: teal,
                                                  width: 4)
                                            },
                                      onMapCreated: (c) {
                                        controller = c;
                                        fit(positions);
                                      })),
                              TextButton(
                                  onPressed: () => fit(positions),
                                  child: const Text('Fit recorded locations')),
                              const Text(
                                  'The line connects recorded delivery GPS points; it is not a calculated road route. If map tiles are unavailable, use the timeline.'),
                            ],
                            if (s.live && s.current == null)
                              const Text('Waiting for Rider location...'),
                            if (s.current != null)
                              Text(
                                  'Rider reported: ${trackingDate(s.json['current_rider_location']['recorded_at'])}'),
                            const SectionTitle('Shipment details'),
                            Text('Pickup: ${s.json['pickup_address'] ?? ''}'),
                            Text(
                                'Destination: ${s.json['destination_address'] ?? ''}'),
                            const Text(
                                'Courier names identify providers. Updates come from SHOPPICK shipment records.'),
                            const SectionTitle('Tracking timeline'),
                            TrackingTimeline(events: s.events),
                          ],
                        ])));
  }
}

class SellerOrdersScreen extends StatefulWidget {
  const SellerOrdersScreen({super.key});
  @override
  State<SellerOrdersScreen> createState() => _SellerOrdersScreenState();
}

class _SellerOrdersScreenState extends State<SellerOrdersScreen> {
  int page = 1;
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Seller outgoing orders')),
      body: DataList(
          path: 'seller/orders?page=$page',
          title: 'Outgoing orders',
          builder: (d) => ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(16),
                  children: [
                    if ((d['data'] as List).isEmpty)
                      const Text('No outgoing orders yet.'),
                    for (final order in d['data'])
                      Card(
                          child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    SectionTitle(order['seller_order_number']),
                                    Text(order['shop'] ?? ''),
                                    StatusBadge(order['status']),
                                    for (final shipment
                                        in order['shipments'] as List? ?? [])
                                      Text(
                                          '${shipment['tracking_number']} • ${trackingStatus(shipment['status'])}'),
                                    if ((order['shipments'] as List? ?? [])
                                        .isNotEmpty)
                                      FilledButton.icon(
                                          onPressed: () => open(
                                              context,
                                              TrackingMapScreen(
                                                  orderNumber: order[
                                                      'seller_order_number'],
                                                  sellerOrderId: order['id'])),
                                          icon: const Icon(Icons.route),
                                          label: const Text('Track Shipment')),
                                    OutlinedButton(
                                        onPressed: () => openWebsite(
                                            context, '/seller/orders'),
                                        child: const Text(
                                            'Manage Order on Website')),
                                  ]))),
                    if (d['last_page'] > 1)
                      Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            TextButton(
                                onPressed: page > 1
                                    ? () => setState(() => page--)
                                    : null,
                                child: const Text('Previous')),
                            Text('$page / ${d['last_page']}'),
                            TextButton(
                                onPressed: page < d['last_page']
                                    ? () => setState(() => page++)
                                    : null,
                                child: const Text('Next'))
                          ]),
                  ])));
}
