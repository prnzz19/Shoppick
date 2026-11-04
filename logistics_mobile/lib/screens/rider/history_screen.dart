import 'package:flutter/material.dart';

import '../../models/delivery.dart';
import '../../services/api_client.dart';
import '../../widgets/common.dart';
import 'assignment_detail_screen.dart';

class RiderHistoryScreen extends StatefulWidget {
  final ApiClient api;

  const RiderHistoryScreen({required this.api, super.key});

  @override
  State<RiderHistoryScreen> createState() => _RiderHistoryScreenState();
}

class _RiderHistoryScreenState extends State<RiderHistoryScreen> {
  List<Delivery> items = [];
  bool loading = true;
  bool loadingMore = false;
  int page = 1;
  int lastPage = 1;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load({bool next = false}) async {
    setState(() {
      if (next) {
        loadingMore = true;
      } else {
        loading = true;
        error = null;
      }
    });
    final target = next ? page + 1 : 1;
    try {
      final response = await widget.api.riderHistory(page: target);
      final result = response['deliveries'];
      final found = (result['data'] as List? ?? [])
          .map((value) => Delivery(Map<String, dynamic>.from(value)))
          .toList();
      items = next ? [...items, ...found] : found;
      page = target;
      lastPage = result['last_page'] as int? ?? 1;
    } catch (e) {
      if (next) {
        if (mounted) showError(context, e);
      } else {
        error = friendly(e);
      }
    }
    if (mounted) {
      setState(() {
        loading = false;
        loadingMore = false;
      });
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
  Widget build(BuildContext context) => LoadView(
        loading: loading,
        error: error,
        message: 'Loading delivery history…',
        retry: load,
        child: RefreshIndicator(
          onRefresh: load,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              const Text(
                'DELIVERY HISTORY',
                style: TextStyle(
                  letterSpacing: 1.4,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SectionTitle('Completed and failed deliveries'),
              const Card(
                child: ListTile(
                  leading: Icon(Icons.info_outline),
                  title: Text('Rider earnings are not available'),
                  subtitle: Text(
                    'SHOPPICK has no Rider compensation or delivery-rate source configured, so no earnings amount is estimated here.',
                  ),
                ),
              ),
              if (items.isEmpty)
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(20),
                    child: Text('No completed or failed deliveries yet.'),
                  ),
                ),
              for (final delivery in items)
                Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: InkWell(
                    onTap: () => open(delivery),
                    borderRadius: BorderRadius.circular(14),
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
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                              StatusBadge(delivery.status),
                            ],
                          ),
                          const SizedBox(height: 10),
                          Text(delivery.text('shop')),
                          Text(delivery.text('destination')),
                          Text(
                            'Completed: ${dateText(delivery.json['delivered_at'] ?? delivery.json['updated_at'])}',
                          ),
                          if (delivery.json['cod_amount'] != null)
                            Text(
                              'COD ${label(delivery.text('cod_status'))}: ₱${(delivery.json['cod_amount'] as num).toStringAsFixed(2)}',
                              style:
                                  const TextStyle(fontWeight: FontWeight.w700),
                            ),
                          if (delivery.json['failure_reason'] != null)
                            Text(
                              'Issue: ${delivery.text('failure_reason')}',
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
              if (page < lastPage)
                OutlinedButton(
                  onPressed: loadingMore ? null : () => load(next: true),
                  child: Text(loadingMore ? 'Loading…' : 'Load more'),
                ),
            ],
          ),
        ),
      );
}
