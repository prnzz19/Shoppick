import 'dart:async';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import '../../models/delivery.dart';
import '../../services/api_client.dart';
import '../../services/rider_location.dart';
import '../../widgets/common.dart';
import 'assignment_detail_screen.dart';
import 'portal_support.dart';

const riderTeal = Color(0xff08aeb8), riderNavy = Color(0xff101a38);
ThemeData riderTheme(BuildContext context) => Theme.of(context).copyWith(
      scaffoldBackgroundColor: const Color(0xfff7f9fc),
      colorScheme:
          ColorScheme.fromSeed(seedColor: riderTeal, primary: riderTeal),
      appBarTheme: const AppBarTheme(
          backgroundColor: Colors.white,
          foregroundColor: riderNavy,
          surfaceTintColor: Colors.transparent),
      cardTheme: CardThemeData(
          color: Colors.white,
          elevation: 0,
          margin: const EdgeInsets.only(bottom: 14),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
              side: const BorderSide(color: Color(0xffdfe7f0)))),
      inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
          contentPadding: const EdgeInsets.all(14),
          border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xffd7e2eb)))),
      filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
              minimumSize: const Size(48, 48),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12)))),
    );
Future<void> openRider(BuildContext context, Widget page) async {
  final theme = riderTheme(context);
  await Navigator.push(context,
      MaterialPageRoute<void>(builder: (_) => Theme(data: theme, child: page)));
}

Widget riderPage(String title, Widget body) =>
    Scaffold(appBar: AppBar(title: Text(title)), body: SafeArea(child: body));
Widget riderNotice(String title, String message, {Widget? action}) => Card(
    child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title,
              style: const TextStyle(
                  fontWeight: FontWeight.w800, color: riderNavy)),
          const SizedBox(height: 8),
          Text(message),
          if (action != null) ...[const SizedBox(height: 12), action]
        ])));

class RiderPortalShell extends StatefulWidget {
  final ApiClient api;
  const RiderPortalShell({required this.api, super.key});
  @override
  State<RiderPortalShell> createState() => _RiderPortalShellState();
}

class _RiderPortalShellState extends State<RiderPortalShell> {
  int tab = 0;
  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      RiderWorkspace(
          api: widget.api, onDeliveries: () => setState(() => tab = 1)),
      RiderJobList(api: widget.api),
      RiderJobList(api: widget.api, kind: 'scan'),
      RiderJobList(api: widget.api, kind: 'history'),
      RiderMore(api: widget.api),
    ];
    return Theme(
        data: riderTheme(context),
        child: Scaffold(
            appBar: AppBar(
                title: Row(children: [
                  SizedBox(
                      width: 148,
                      height: 36,
                      child: Semantics(
                          label: 'SHOPPICK Rider Portal',
                          image: true,
                          child: Row(children: [
                            Image.asset('assets/images/rider-mark.png',
                                width: 34, excludeFromSemantics: true),
                            Expanded(
                                child: Image.asset(
                                    'assets/images/rider-wordmark.png',
                                    excludeFromSemantics: true))
                          ]))),
                  const SizedBox(width: 10),
                  const Flexible(
                      child: Text('RIDER\nPORTAL',
                          style: TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 1.1)))
                ]),
                actions: [
                  IconButton(
                      tooltip: 'Notifications',
                      onPressed: () => openRider(
                          context,
                          riderPage('Notifications',
                              RiderNotifications(api: widget.api))),
                      icon: const Icon(Icons.notifications_outlined))
                ]),
            body: SafeArea(
                child: KeyedSubtree(key: ValueKey(tab), child: pages[tab])),
            bottomNavigationBar: NavigationBar(
                selectedIndex: tab,
                labelBehavior:
                    NavigationDestinationLabelBehavior.onlyShowSelected,
                onDestinationSelected: (value) => setState(() => tab = value),
                destinations: const [
                  NavigationDestination(
                      icon: Icon(Icons.dashboard_outlined), label: 'Dashboard'),
                  NavigationDestination(
                      icon: Icon(Icons.local_shipping_outlined),
                      label: 'Deliveries'),
                  NavigationDestination(
                      icon: Icon(Icons.qr_code_scanner), label: 'Scan'),
                  NavigationDestination(
                      icon: Icon(Icons.history), label: 'History'),
                  NavigationDestination(icon: Icon(Icons.menu), label: 'More'),
                ])));
  }
}

class RiderJobCard extends StatelessWidget {
  final Delivery job;
  final VoidCallback onTap;
  const RiderJobCard({required this.job, required this.onTap, super.key});
  @override
  Widget build(BuildContext context) => Card(
      child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                        spacing: 12,
                        runSpacing: 8,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          Text(job.text('tracking_number'),
                              style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                  color: riderNavy)),
                          StatusBadge(job.status)
                        ]),
                    const SizedBox(height: 10),
                    Text('${label(job.text('assignment_type'))} assignment'),
                    Text('Pickup: ${job.text('pickup_address')}'),
                    Text('Delivery: ${job.text('destination')}'),
                    Text('Provider: ${job.providerName}'),
                    if (job.actions.isNotEmpty)
                      Padding(
                          padding: const EdgeInsets.only(top: 8),
                          child: Text('Next: ${label(job.actions.first)}',
                              style:
                                  const TextStyle(color: Color(0xff08727a)))),
                    const SizedBox(height: 10),
                    const Text('View Details',
                        style: TextStyle(
                            fontWeight: FontWeight.w700,
                            color: Color(0xff08727a))),
                  ]))));
}

class RiderWorkspace extends StatefulWidget {
  final ApiClient api;
  final VoidCallback onDeliveries;
  const RiderWorkspace(
      {required this.api, required this.onDeliveries, super.key});
  @override
  State<RiderWorkspace> createState() => _RiderWorkspaceState();
}

class _RiderWorkspaceState extends State<RiderWorkspace>
    with WidgetsBindingObserver {
  List<Delivery> jobs = [], history = [];
  bool loading = true, fetching = false, sharing = false, availableBusy = false;
  String? error;
  Timer? timer;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    load();
    timer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted && ModalRoute.of(context)?.isCurrent == true) load();
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    timer?.cancel();
    if (state == AppLifecycleState.resumed) {
      load();
      timer = Timer.periodic(const Duration(seconds: 30), (_) {
        if (mounted && ModalRoute.of(context)?.isCurrent == true) load();
      });
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    timer?.cancel();
    super.dispose();
  }

  Future<void> load() async {
    if (fetching) return;
    fetching = true;
    try {
      await widget.api.refreshProfile();
      final results = await Future.wait([
        widget.api.allRiderRecords(),
        widget.api.allRiderRecords(history: true)
      ]);
      if (!mounted) return;
      setState(() {
        jobs = results[0].map(Delivery.new).toList();
        history = results[1].map(Delivery.new).toList();
        error = null;
      });
    } catch (e) {
      if (mounted) setState(() => error = friendly(e));
    } finally {
      fetching = false;
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> availability(String value) async {
    setState(() => availableBusy = true);
    try {
      await widget.api.setRiderAvailability(value);
      await load();
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => availableBusy = false);
    }
  }

  Future<void> share() async {
    setState(() => sharing = true);
    try {
      await DeviceRiderLocation().requestAccess();
      final position = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
              accuracy: LocationAccuracy.high,
              timeLimit: Duration(seconds: 20)));
      if (widget.api.portalV1) {
        await widget.api
            .shareRiderLocation(position.latitude, position.longitude);
      } else {
        final activeJobs = jobs
            .where((job) => !job.pending && job.actions.isNotEmpty)
            .toList();
        if (activeJobs.isEmpty) {
          throw const ApiFailure(
              'Location sharing is available only during an accepted pickup or active delivery.');
        }
        final recordedAt = DateTime.now().toUtc();
        for (final job in activeJobs) {
          await widget.api.shareRiderLocationForDelivery(
              job.id, position.latitude, position.longitude,
              accuracy: position.accuracy, recordedAt: recordedAt);
        }
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Current location shared with SHOPPICK.')));
      }
    } catch (e) {
      if (mounted) {
        showError(
            context,
            e is ApiFailure
                ? e
                : const ApiFailure(
                    'Unable to obtain your current location. Check GPS and permission.'));
      }
    } finally {
      if (mounted) setState(() => sharing = false);
    }
  }

  Future<void> details(Delivery job) async {
    await openRider(
        context, RiderAssignmentDetailScreen(api: widget.api, id: job.id));
    if (mounted) await load();
  }

  @override
  Widget build(BuildContext context) {
    final current =
        jobs.where((j) => j.actions.isNotEmpty && !j.pending).firstOrNull;
    final now = DateTime.now();
    final today = jobs.where((j) {
      final date = DateTime.tryParse(j.text('assigned_at', ''))?.toLocal();
      return date != null &&
          date.year == now.year &&
          date.month == now.month &&
          date.day == now.day;
    }).toList();
    final counts = {
      'Assigned': jobs.length,
      'To Pick Up':
          jobs.where((j) => j.text('assignment_type') == 'pickup').length,
      'Active Delivery':
          jobs.where((j) => j.status == 'out_for_delivery').length,
      'Completed': history
          .where((j) => ['delivered', 'completed'].contains(j.status))
          .length,
      'Needs Attention': history
          .where((j) => ['delivery_failed', 'delivery_attempted', 'exception']
              .contains(j.status))
          .length
    };
    final status = widget.api.user?['availability']?.toString();
    final suppliedProvider = widget.api.user?['provider'];
    final provider =
        suppliedProvider is Map ? suppliedProvider['name'] : suppliedProvider;
    return LoadView(
        loading: loading,
        error: error,
        retry: load,
        message: 'Loading your delivery workspace...',
        child: RefreshIndicator(
            onRefresh: load,
            child: ListView(padding: const EdgeInsets.all(16), children: [
              Text('Hello, ${widget.api.user?['name'] ?? 'Rider'}',
                  style: const TextStyle(color: Color(0xff607c9f))),
              const SectionTitle("Here's your delivery overview."),
              const Text(
                  'Your assignments, next steps, and deliveries needing attention.'),
              const SizedBox(height: 8),
              Text(
                  'Logistics Provider: ${provider ?? jobs.firstOrNull?.providerName ?? 'Not supplied by this server'}'),
              const SizedBox(height: 18),
              LayoutBuilder(
                  builder: (_, box) =>
                      Wrap(spacing: 10, runSpacing: 10, children: [
                        for (final entry in counts.entries)
                          SizedBox(
                              width: box.maxWidth < 360
                                  ? (box.maxWidth - 10) / 2
                                  : (box.maxWidth - 20) / 3,
                              child: Card(
                                  child: Padding(
                                      padding: const EdgeInsets.all(14),
                                      child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Text(entry.key,
                                                style: const TextStyle(
                                                    fontSize: 12)),
                                            const SizedBox(height: 8),
                                            Text('${entry.value}',
                                                style: const TextStyle(
                                                    fontSize: 26,
                                                    fontWeight: FontWeight.w800,
                                                    color: riderNavy))
                                          ]))))
                      ])),
              const SectionTitle('Current Job'),
              if (current == null)
                riderNotice('No Active Delivery',
                    "You don't have an active delivery right now. Your next assignment will appear here.")
              else
                RiderJobCard(job: current, onTap: () => details(current)),
              const SectionTitle("Today's Tasks"),
              TextButton(
                  onPressed: widget.onDeliveries,
                  child: const Text('View all deliveries')),
              if (today.isEmpty)
                riderNotice('No dated assignments for today.',
                    'Your current assignments remain available in My Deliveries.'),
              for (final job in today.take(3))
                RiderJobCard(job: job, onTap: () => details(job)),
              const SectionTitle('Pickup location'),
              riderNotice('Share current location',
                  'Share one GPS update with your active pickup or delivery assignments. Background tracking is disabled.',
                  action: OutlinedButton.icon(
                      onPressed: sharing ? null : share,
                      icon: const Icon(Icons.my_location),
                      label: Text(
                          sharing ? 'Sharing...' : 'Share current location'))),
              riderNotice(
                  'Availability',
                  jobs.any((j) => j.actions.isNotEmpty)
                      ? 'Finish your current assignment before changing availability.'
                      : 'Current status: ${status == null ? 'Not supplied' : label(status)}',
                  action: Wrap(spacing: 10, runSpacing: 8, children: [
                    for (final value in ['available', 'off_duty'])
                      OutlinedButton(
                          onPressed: availableBusy ||
                                  jobs.any((j) => j.actions.isNotEmpty)
                              ? null
                              : () => availability(value),
                          child: Text(label(value)))
                  ])),
            ])));
  }
}

class RiderJobList extends StatefulWidget {
  final ApiClient api;
  final String kind;
  const RiderJobList({required this.api, this.kind = 'jobs', super.key});
  @override
  State<RiderJobList> createState() => _RiderJobListState();
}

class _RiderJobListState extends State<RiderJobList> {
  List<Delivery> rows = [];
  int page = 1, last = 1;
  bool loading = true, busy = false;
  String? error;
  final search = TextEditingController();
  String status = '';
  bool get history => widget.kind == 'history';
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  Future<void> load({bool next = false}) async {
    if (busy) return;
    setState(() {
      busy = true;
      if (!next) loading = true;
      error = null;
    });
    try {
      final response = history
          ? await widget.api.riderHistory(page: next ? page + 1 : 1)
          : await widget.api.riderAssignments(
              page: next ? page + 1 : 1,
              filters: widget.api.portalV1
                  ? {
                      if (search.text.trim().isNotEmpty)
                        'q': search.text.trim(),
                      if (status.isNotEmpty) 'status': status,
                      if (widget.kind == 'pickups') 'type': 'pickup',
                      if (widget.kind == 'active') 'status': 'out_for_delivery'
                    }
                  : {});
      final data = response['deliveries'] as Map;
      final incoming = (data['data'] as List? ?? [])
          .whereType<Map>()
          .map((r) => Delivery(Map<String, dynamic>.from(r)))
          .toList();
      if (mounted) {
        setState(() {
          rows = next ? [...rows, ...incoming] : incoming;
          page = (data['current_page'] as num? ?? 1).toInt();
          last = (data['last_page'] as num? ?? page).toInt();
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = friendly(e));
    } finally {
      if (mounted) {
        setState(() {
          busy = false;
          loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final titles = {
      'jobs': 'My Deliveries',
      'pickups': 'Pickups',
      'active': 'Active Delivery',
      'history': 'Delivery History',
      'scan': 'Scan assigned parcel',
      'tracking': 'Tracking'
    };
    final shown = rows.where((job) {
      if (widget.kind == 'pickups' && job.text('assignment_type') != 'pickup') {
        return false;
      }
      if (widget.kind == 'active' && job.status != 'out_for_delivery') {
        return false;
      }
      if (widget.kind == 'scan' &&
          !job.actions.contains('confirm_pickup') &&
          !job.actions.contains('start_delivery')) {
        return false;
      }
      if ((history || !widget.api.portalV1) &&
          search.text.trim().isNotEmpty &&
          !job
              .text('tracking_number')
              .toLowerCase()
              .contains(search.text.trim().toLowerCase())) {
        return false;
      }
      if ((history || !widget.api.portalV1) &&
          status.isNotEmpty &&
          job.status != status) {
        return false;
      }
      return true;
    }).toList();
    return LoadView(
        loading: loading,
        error: error,
        retry: load,
        message: 'Loading authorized assignments...',
        child: RefreshIndicator(
            onRefresh: load,
            child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: [
                  SectionTitle(titles[widget.kind] ?? 'My Deliveries'),
                  if (widget.kind == 'scan')
                    const Padding(
                        padding: EdgeInsets.only(bottom: 12),
                        child: Text(
                            'Choose an accepted pickup or hub delivery assignment, then scan its physical waybill from delivery details. SHOPPICK validates the parcel code before changing its status.')),
                  Card(
                      child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(children: [
                            TextField(
                                controller: search,
                                maxLength: 100,
                                decoration: const InputDecoration(
                                    labelText: 'Tracking or parcel number',
                                    prefixIcon: Icon(Icons.search)),
                                onSubmitted: (_) => load()),
                            const SizedBox(height: 12),
                            DropdownButtonFormField<String>(
                                initialValue: status,
                                isExpanded: true,
                                decoration:
                                    const InputDecoration(labelText: 'Status'),
                                items: [
                                  const DropdownMenuItem(
                                      value: '', child: Text('All statuses')),
                                  for (final value in [
                                    'pickup_assigned',
                                    'pickup_accepted',
                                    'picked_up',
                                    'assigned_to_rider',
                                    'out_for_delivery',
                                    'delivered',
                                    'completed',
                                    'delivery_failed',
                                    'returned',
                                    'exception'
                                  ])
                                    DropdownMenuItem(
                                        value: value, child: Text(label(value)))
                                ],
                                onChanged: (value) =>
                                    setState(() => status = value ?? '')),
                            const SizedBox(height: 12),
                            Wrap(spacing: 10, runSpacing: 8, children: [
                              FilledButton(
                                  onPressed: busy ? null : () => load(),
                                  child: const Text('Apply Filters')),
                              OutlinedButton(
                                  onPressed: busy
                                      ? null
                                      : () {
                                          search.clear();
                                          setState(() => status = '');
                                          load();
                                        },
                                  child: const Text('Reset'))
                            ]),
                            const SizedBox(height: 10),
                            Text(
                                history || !widget.api.portalV1
                                    ? 'Search and status filter the loaded records. Load more to include later pages.'
                                    : 'Search and status are applied by the Rider jobs API.',
                                style: const TextStyle(fontSize: 12)),
                            const SizedBox(height: 8),
                            const Text(
                                'Date-range filtering is not provided by this mobile API.',
                                style: TextStyle(
                                    fontSize: 12, color: Color(0xff607c9f))),
                          ]))),
                  if (shown.isEmpty)
                    riderNotice(
                        widget.kind == 'active'
                            ? 'No Active Delivery'
                            : 'No deliveries match this view.',
                        'Pull to refresh or clear the filters. Only your authorized assignments are shown.'),
                  for (final job in shown)
                    RiderJobCard(
                        job: job,
                        onTap: () async {
                          await openRider(
                              context,
                              widget.kind == 'tracking'
                                  ? RiderTracking(api: widget.api, id: job.id)
                                  : RiderAssignmentDetailScreen(
                                      api: widget.api, id: job.id));
                          if (mounted) await load();
                        }),
                  if (page < last)
                    OutlinedButton(
                        onPressed: busy ? null : () => load(next: true),
                        child: Text(busy ? 'Loading...' : 'Load more')),
                ])));
  }
}

class RiderMore extends StatelessWidget {
  final ApiClient api;
  const RiderMore({required this.api, super.key});
  @override
  Widget build(BuildContext context) {
    final modules = <String, Widget>{
      'Pickups': RiderJobList(api: api, kind: 'pickups'),
      'Active Delivery': RiderJobList(api: api, kind: 'active'),
      'Tracking': RiderJobList(api: api, kind: 'tracking'),
      'Notifications': RiderNotifications(api: api),
      'Logistics Support': RiderSupport(api: api),
      'Profile': RiderProfile(api: api),
      'Proof of Delivery': RiderRecords(api: api, kind: 'pod'),
      'COD Collections': RiderRecords(api: api, kind: 'cod'),
      'Settings': RiderSettings(api: api)
    };
    return ListView(padding: const EdgeInsets.all(16), children: [
      const SectionTitle('Rider workspace'),
      for (final entry in modules.entries)
        Card(
            child: ListTile(
                title: Text(entry.key),
                trailing: const Icon(Icons.chevron_right),
                onTap: () =>
                    openRider(context, riderPage(entry.key, entry.value)))),
    ]);
  }
}
