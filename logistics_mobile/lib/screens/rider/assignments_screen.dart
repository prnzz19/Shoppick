import 'package:flutter/material.dart';

import '../../models/delivery.dart';
import '../../services/api_client.dart';
import '../../widgets/common.dart';
import 'assignment_detail_screen.dart';

class RiderAssignmentsScreen extends StatefulWidget {
  final ApiClient api;

  const RiderAssignmentsScreen({required this.api, super.key});

  @override
  State<RiderAssignmentsScreen> createState() => _RiderAssignmentsScreenState();
}

class _RiderAssignmentsScreenState extends State<RiderAssignmentsScreen> {
  List<Delivery> items = [];
  bool loading = true;
  int? busyId;
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
      final response = await widget.api.riderAssignments();
      final page = response['deliveries'];
      items = (page['data'] as List? ?? [])
          .map((value) => Delivery(Map<String, dynamic>.from(value)))
          .toList();
    } catch (e) {
      error = friendly(e);
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> accept(Delivery delivery) async {
    final action = delivery.acceptAction;
    if (action == null) return;
    setState(() => busyId = delivery.id);
    try {
      await widget.api.acceptRiderAssignment(delivery.id, action);
      await widget.api.refreshProfile();
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => busyId = null);
      await load();
    }
  }

  Future<void> open(Delivery delivery) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => RiderAssignmentDetailScreen(
          api: widget.api,
          id: delivery.id,
        ),
      ),
    );
    if (mounted) load();
  }

  @override
  Widget build(BuildContext context) {
    final pending = items.where((item) => item.pending).toList();
    final active = items.where((item) => !item.pending).toList();
    return LoadView(
      loading: loading,
      error: error,
      message: 'Loading assignments…',
      retry: load,
      child: RefreshIndicator(
        onRefresh: load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'MY ASSIGNMENTS',
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    letterSpacing: 1.4,
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SectionTitle('Pending response'),
            if (pending.isEmpty)
              const _EmptyCard('No assignments are waiting for your response.'),
            for (final delivery in pending)
              _AssignmentCard(
                delivery: delivery,
                busy: busyId == delivery.id,
                onOpen: () => open(delivery),
                onAccept: () => accept(delivery),
              ),
            const SectionTitle('Active'),
            if (active.isEmpty) const _EmptyCard('No active assignments.'),
            for (final delivery in active)
              _AssignmentCard(
                delivery: delivery,
                busy: false,
                onOpen: () => open(delivery),
              ),
          ],
        ),
      ),
    );
  }
}

class _EmptyCard extends StatelessWidget {
  final String message;

  const _EmptyCard(this.message);

  @override
  Widget build(BuildContext context) => Card(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Text(message),
        ),
      );
}

class _AssignmentCard extends StatelessWidget {
  final Delivery delivery;
  final bool busy;
  final VoidCallback onOpen;
  final VoidCallback? onAccept;

  const _AssignmentCard({
    required this.delivery,
    required this.busy,
    required this.onOpen,
    this.onAccept,
  });

  @override
  Widget build(BuildContext context) => Card(
        margin: const EdgeInsets.only(bottom: 12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      delivery.text('tracking_number'),
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
                  StatusBadge(delivery.status),
                ],
              ),
              const SizedBox(height: 12),
              if (delivery.text('assignment_type') == 'pickup' &&
                  (delivery.json['pickup_stop_count'] as num? ?? 0) > 1) ...[
                Text(
                  'Stop ${delivery.json['pickup_stop_number']} of ${delivery.json['pickup_stop_count']}',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
              ],
              Text('${delivery.text('shop')} → ${delivery.text('buyer')}'),
              const SizedBox(height: 6),
              Text(
                delivery.text(
                  delivery.text('assignment_type') == 'pickup'
                      ? 'pickup_address'
                      : 'destination',
                ),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  OutlinedButton(
                    onPressed: busy ? null : onOpen,
                    child: const Text('View details'),
                  ),
                  if (onAccept != null)
                    FilledButton(
                      onPressed: busy ? null : onAccept,
                      child: Text(busy ? 'Accepting…' : 'Accept'),
                    ),
                  if (delivery.pending)
                    TextButton(
                      onPressed: busy ? null : onOpen,
                      child: const Text('Decline'),
                    ),
                ],
              ),
            ],
          ),
        ),
      );
}
