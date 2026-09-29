import 'package:flutter/material.dart';
import '../config/app_config.dart';
import '../models/delivery.dart';
import '../services/api_client.dart';
import '../widgets/common.dart';
import 'delivery_detail_screen.dart';
import 'deliveries_screen.dart';
import 'logistics/directory_screen.dart';

class DashboardScreen extends StatefulWidget {
  final ApiClient api;
  const DashboardScreen({required this.api, super.key});
  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Map<String, dynamic> data = {};
  bool loading = true;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      data = await widget.api.request('GET', '${widget.api.prefix}/dashboard');
    } catch (e) {
      error = friendly(e);
    }
    if (mounted) setState(() => loading = false);
  }

  void open(Widget page, String title) => Navigator.push(
      context,
      MaterialPageRoute(
          builder: (_) =>
              Scaffold(appBar: AppBar(title: Text(title)), body: page)));
  @override
  Widget build(BuildContext context) {
    final api = widget.api;
    final summary = Map<String, dynamic>.from(data['summary'] ?? {});
    final deliveries = (data['deliveries'] as List? ?? [])
        .map((e) => Delivery(Map<String, dynamic>.from(e)))
        .toList();
    return LoadView(
        loading: loading,
        error: error,
        message: 'Loading dashboard…',
        retry: load,
        child: RefreshIndicator(
            onRefresh: load,
            child: ListView(padding: const EdgeInsets.all(16), children: [
              Text(api.manager ? 'LOGISTICS OPERATIONS' : 'YOUR DELIVERY DAY',
                  style: const TextStyle(
                      color: teal,
                      letterSpacing: 1.4,
                      fontWeight: FontWeight.w700)),
              SectionTitle('Hello, ${api.user?['name'] ?? ''}'),
              Text(api.manager
                  ? 'A clear view of every delivery.'
                  : 'Your assigned parcels, ready for the next step.'),
              const SizedBox(height: 20),
              LayoutBuilder(builder: (context, constraints) {
                final columns = constraints.maxWidth >= 500
                    ? 3
                    : MediaQuery.textScalerOf(context).scale(14) > 20
                        ? 1
                        : 2;
                return Wrap(spacing: 12, runSpacing: 0, children: [
                  for (final entry in summary.entries)
                    SizedBox(
                        width: (constraints.maxWidth - (columns - 1) * 12) /
                            columns,
                        child: Card(
                            child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text('${entry.value}',
                                          style: const TextStyle(
                                              fontSize: 30,
                                              fontWeight: FontWeight.w800,
                                              color: navy)),
                                      Text(entry.key)
                                    ]))))
                ]);
              }),
              Card(
                  child: ListTile(
                      isThreeLine: true,
                      leading: const Icon(Icons.today_outlined, color: teal),
                      title: const Text("Today's deliveries"),
                      subtitle: Text(
                          '${data['today'] ?? 0} scheduled or assigned today\n${data['unread_notifications'] ?? 0} unread notifications'))),
              if (api.manager) ...[
                FilledButton.tonal(
                    onPressed: () => open(
                        DeliveriesScreen(
                            api: api, initialStatus: 'delivery_failed'),
                        'Needs attention'),
                    child: const Text('Needs attention')),
                const SizedBox(height: 8),
                OutlinedButton(
                    onPressed: () => open(
                        DirectoryScreen(
                            api: api, riders: true, initialFilter: 'available'),
                        'Available riders'),
                    child: const Text('Available riders')),
                OutlinedButton(
                    onPressed: () => open(
                        DirectoryScreen(api: api, riders: false),
                        'Provider performance'),
                    child: const Text('Provider performance')),
              ],
              SectionTitle(
                  api.manager ? 'Delivery overview' : 'Next deliveries'),
              if (!api.manager)
                const Padding(
                    padding: EdgeInsets.only(bottom: 12),
                    child: Text(
                        'Ordered by assignment time. Open a delivery for its map and pickup or delivery address.')),
              if (deliveries.isEmpty)
                const Card(
                    child: Padding(
                        padding: EdgeInsets.all(24),
                        child: Text(
                            "You're all caught up. No deliveries assigned."))),
              for (final d in deliveries)
                DeliveryCard(d, onTap: () async {
                  await Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (_) =>
                              DeliveryDetailScreen(api: api, id: d.id)));
                  if (mounted) load();
                }),
            ])));
  }
}
