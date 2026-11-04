import 'package:flutter/material.dart';

import '../../models/delivery.dart';
import '../../services/api_client.dart';
import '../../widgets/common.dart';
import 'assignment_detail_screen.dart';
import 'availability_card.dart';

class RiderHomeScreen extends StatefulWidget {
  final ApiClient api;

  const RiderHomeScreen({required this.api, super.key});

  @override
  State<RiderHomeScreen> createState() => _RiderHomeScreenState();
}

class _RiderHomeScreenState extends State<RiderHomeScreen> {
  List<Delivery> assignments = [];
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
      await widget.api.refreshProfile();
      final result = await widget.api.riderAssignments();
      assignments = (result['deliveries']['data'] as List? ?? [])
          .map((value) => Delivery(Map<String, dynamic>.from(value)))
          .toList();
    } catch (e) {
      error = friendly(e);
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> open(Delivery assignment) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => RiderAssignmentDetailScreen(
          api: widget.api,
          id: assignment.id,
        ),
      ),
    );
    if (mounted) load();
  }

  @override
  Widget build(BuildContext context) => LoadView(
        loading: loading,
        error: error,
        message: 'Loading Rider home…',
        retry: load,
        child: RefreshIndicator(
          onRefresh: load,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              const Text(
                'YOUR DELIVERY DAY',
                style: TextStyle(
                  letterSpacing: 1.4,
                  fontWeight: FontWeight.w800,
                ),
              ),
              SectionTitle('Hello, ${widget.api.user?['name'] ?? ''}'),
              AvailabilityCard(
                api: widget.api,
                hasAssignment: assignments.isNotEmpty,
                onChanged: () => setState(() {}),
              ),
              const SectionTitle('Assignment overview'),
              Row(
                children: [
                  Expanded(
                    child: _CountCard(
                      count: assignments.where((item) => item.pending).length,
                      label: 'Pending',
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _CountCard(
                      count: assignments.where((item) => !item.pending).length,
                      label: 'Active',
                    ),
                  ),
                ],
              ),
              const SectionTitle('Next assignments'),
              if (assignments.isEmpty)
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(20),
                    child: Text(
                      'No assignments yet. Keep your status online when you are ready.',
                    ),
                  ),
                ),
              for (final assignment in assignments.take(4))
                Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.all(16),
                    title: Text(
                      assignment.text('tracking_number'),
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    subtitle: Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Text(
                        '${label(assignment.text('assignment_type'))} • ${label(assignment.status)}\n${assignment.text('shop')} → ${assignment.text('buyer')}',
                      ),
                    ),
                    isThreeLine: true,
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => open(assignment),
                  ),
                ),
            ],
          ),
        ),
      );
}

class _CountCard extends StatelessWidget {
  final int count;
  final String label;

  const _CountCard({required this.count, required this.label});

  @override
  Widget build(BuildContext context) => Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '$count',
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
              ),
              Text(label),
            ],
          ),
        ),
      );
}
