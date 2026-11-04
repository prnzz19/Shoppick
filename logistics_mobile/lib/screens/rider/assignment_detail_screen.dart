import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../models/delivery.dart';
import '../../services/api_client.dart';
import '../../widgets/common.dart';
import 'proof_screen.dart';
import 'parcel_scan_screen.dart';
import '../tracking_screen.dart';

Uri? riderNavigationUri(Delivery delivery, {required bool pickup}) {
  if (pickup &&
      delivery.text('assignment_type') == 'delivery' &&
      delivery.text('hub_address', '').trim().isNotEmpty) {
    return Uri.https('www.google.com', '/maps/dir/', {
      'api': '1',
      'destination': delivery.text('hub_address', '').trim(),
    });
  }
  final key = pickup ? 'pickup_coordinates' : 'dropoff_coordinates';
  final coordinates = delivery.json[key];
  String destination = '';
  if (coordinates is Map &&
      coordinates['latitude'] is num &&
      coordinates['longitude'] is num) {
    destination = '${coordinates['latitude']},${coordinates['longitude']}';
  } else {
    destination =
        delivery.text(pickup ? 'pickup_address' : 'destination', '').trim();
  }
  if (destination.isEmpty) return null;
  return Uri.https('www.google.com', '/maps/dir/', {
    'api': '1',
    'destination': destination,
  });
}

Uri? riderPhoneUri(String? value) {
  final phone = (value ?? '').replaceAll(RegExp(r'[^0-9+]'), '');
  return phone.isEmpty ? null : Uri(scheme: 'tel', path: phone);
}

class RiderAssignmentDetailScreen extends StatefulWidget {
  final ApiClient api;
  final int id;

  const RiderAssignmentDetailScreen({
    required this.api,
    required this.id,
    super.key,
  });

  @override
  State<RiderAssignmentDetailScreen> createState() =>
      _RiderAssignmentDetailScreenState();
}

class _RiderAssignmentDetailScreenState
    extends State<RiderAssignmentDetailScreen> {
  Delivery? delivery;
  bool loading = true;
  bool busy = false;
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
      final result = await widget.api.riderAssignment(widget.id);
      delivery = Delivery(Map<String, dynamic>.from(result['delivery']));
    } catch (e) {
      error = friendly(e);
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> navigate(bool pickup) async {
    final uri = riderNavigationUri(delivery!, pickup: pickup);
    if (uri == null) {
      showError(context, const ApiFailure('No address is available to open.'));
      return;
    }
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) &&
        mounted) {
      showError(context, const ApiFailure('Unable to open your maps app.'));
    }
  }

  Future<void> call(String? number) async {
    final uri = riderPhoneUri(number);
    if (uri == null) {
      showError(context, const ApiFailure('No phone number is available.'));
      return;
    }
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) &&
        mounted) {
      showError(context, const ApiFailure('Unable to open the phone app.'));
    }
  }

  Future<void> action(
    String action, {
    String? parcelCode,
    String? reason,
    String? details,
  }) async {
    if (busy) return;
    setState(() => busy = true);
    try {
      await widget.api.performRiderDeliveryAction(
        widget.id,
        action,
        parcelCode: parcelCode,
        reason: reason,
        details: details,
      );
      await widget.api.refreshProfile();
      await load();
    } catch (e) {
      if (mounted) showError(context, e);
      await load();
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> confirmPickup() async {
    final code = await Navigator.push<String>(
        context,
        MaterialPageRoute(
            builder: (_) => ParcelScanScreen(
                trackingNumber: delivery!.text('tracking_number'),
                purpose: ParcelScanPurpose.sellerPickup)));
    if (code != null) {
      await action('confirm_pickup', parcelCode: code);
    }
  }

  Future<void> startDelivery() async {
    final code = await Navigator.push<String>(
        context,
        MaterialPageRoute(
            builder: (_) => ParcelScanScreen(
                trackingNumber: delivery!.text('tracking_number'),
                purpose: ParcelScanPurpose.deliveryStart)));
    if (code != null) {
      await action('start_delivery', parcelCode: code);
    }
  }

  Future<void> reportIssue() async {
    final issue = await showDialog<Map<String, String>>(
      context: context,
      builder: (_) => const _DeliveryIssueDialog(),
    );
    if (issue != null) {
      await action(
        'failed',
        reason: issue['reason'],
        details: issue['details'],
      );
    }
  }

  Future<void> openProof() async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ProofScreen(
            api: widget.api, deliveryId: widget.id, version: delivery?.version),
      ),
    );
    if (mounted) load();
  }

  Future<void> confirmAction(
      String action, String title, String message) async {
    if (await confirm(context, title, message: message)) {
      await this.action(action);
    }
  }

  Future<void> accept() async {
    final action = delivery?.acceptAction;
    if (action == null) return;
    if (!await confirm(context, 'Accept this assignment?',
        message: 'Logistics will be notified that you accepted.')) {
      return;
    }
    setState(() => busy = true);
    try {
      await widget.api.acceptRiderAssignment(widget.id, action);
      await widget.api.refreshProfile();
      await load();
    } catch (e) {
      if (mounted) showError(context, e);
      await load();
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> decline() async {
    final result = await showDialog<Map<String, String>>(
      context: context,
      builder: (_) => const _DeclineDialog(),
    );
    if (result == null) return;
    setState(() => busy = true);
    try {
      await widget.api.declineRiderAssignment(
        widget.id,
        result['reason']!,
        result['details'] ?? '',
      );
      await widget.api.refreshProfile();
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) showError(context, e);
      await load();
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Assignment details')),
        body: LoadView(
          loading: loading,
          error: error,
          message: 'Loading assignment…',
          retry: load,
          child: delivery == null
              ? const SizedBox.shrink()
              : RefreshIndicator(
                  onRefresh: load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  delivery!.text('tracking_number'),
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleLarge
                                      ?.copyWith(fontWeight: FontWeight.w800),
                                ),
                                Text(
                                  '${label(delivery!.text('assignment_type'))} assignment',
                                ),
                              ],
                            ),
                          ),
                          StatusBadge(delivery!.status),
                        ],
                      ),
                      if (delivery!.text('assignment_type') == 'pickup' &&
                          (delivery!.json['pickup_stop_count'] as num? ?? 0) >
                              1)
                        Padding(
                          padding: const EdgeInsets.only(top: 12),
                          child: Text(
                            'Pickup stop ${delivery!.json['pickup_stop_number']} of ${delivery!.json['pickup_stop_count']}',
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                        ),
                      _InfoCard(
                        title:
                            delivery!.text('assignment_type') == 'delivery' &&
                                    delivery!.text('hub', '').isNotEmpty
                                ? 'Hub pickup'
                                : 'Pickup',
                        icon: Icons.storefront_outlined,
                        rows: delivery!.text('assignment_type') == 'delivery' &&
                                delivery!.text('hub', '').isNotEmpty
                            ? {
                                'Hub': delivery!.text('hub'),
                                'Address': delivery!.text('hub_address'),
                              }
                            : {
                                'Shop': delivery!.text('shop'),
                                'Address': delivery!.text('pickup_address'),
                                'Phone': delivery!.text('pickup_phone'),
                              },
                        actionLabel: 'Navigate to Pickup',
                        onAction: () => navigate(true),
                      ),
                      _InfoCard(
                        title: 'Drop-off',
                        icon: Icons.location_on_outlined,
                        rows: {
                          'Buyer': delivery!.text('buyer'),
                          'Address': delivery!.text('destination'),
                          'Phone': delivery!.text('phone'),
                          'Instructions': delivery!.text('instructions'),
                        },
                        actionLabel: 'Navigate to Drop-off',
                        onAction: () => navigate(false),
                      ),
                      _InfoCard(
                        title: 'Parcel',
                        icon: Icons.inventory_2_outlined,
                        rows: {
                          'Parcel code':
                              'Scan the physical label to verify. The code is not displayed.',
                          'Order': delivery!.text('order_number'),
                          'Seller order': delivery!.text('seller_order_number'),
                          'Items': delivery!.text('item_count'),
                          'Contents': delivery!.products.isEmpty
                              ? 'Not recorded'
                              : delivery!.products
                                  .map((item) =>
                                      '${item['name']} × ${item['quantity']}')
                                  .join(', '),
                          if (delivery!.json['cod_amount'] != null)
                            'COD amount':
                                '₱${(delivery!.json['cod_amount'] as num).toStringAsFixed(2)}',
                          'Assigned': dateText(delivery!.json['assigned_at']),
                        },
                      ),
                      if (delivery!.json['cod_amount'] != null)
                        _InfoCard(
                          title: 'Cash on delivery',
                          icon: Icons.payments_outlined,
                          rows: {
                            'Amount due':
                                '₱${(delivery!.json['cod_amount'] as num).toStringAsFixed(2)}',
                            'Amount collected': delivery!
                                        .json['cod_collected_amount'] ==
                                    null
                                ? 'Not supplied by this server'
                                : '₱${(delivery!.json['cod_collected_amount'] as num).toStringAsFixed(2)}',
                            'COD status': label(delivery!.text('cod_status')),
                          },
                        ),
                      _ContactCard(
                        buyerPhone: delivery!.json['phone']?.toString(),
                        sellerPhone: delivery!.json['pickup_phone']?.toString(),
                        onCall: call,
                      ),
                      if (!widget.api.portalV1)
                        OutlinedButton.icon(
                            onPressed: () => Navigator.push(
                                context,
                                MaterialPageRoute(
                                    builder: (_) => TrackingScreen(
                                        api: widget.api, id: widget.id))),
                            icon: const Icon(Icons.map_outlined),
                            label: const Text('Open live tracking')),
                      const SectionTitle('Delivery workflow'),
                      if (delivery!.pending) ...[
                        const SizedBox(height: 8),
                        FilledButton.icon(
                          onPressed: busy ? null : accept,
                          icon: const Icon(Icons.check_circle_outline),
                          label:
                              Text(busy ? 'Please wait…' : 'Accept assignment'),
                        ),
                        const SizedBox(height: 8),
                        OutlinedButton.icon(
                          onPressed: busy ? null : decline,
                          icon: const Icon(Icons.cancel_outlined),
                          label: const Text('Decline assignment'),
                        ),
                      ] else ...[
                        _WorkflowActions(
                          delivery: delivery!,
                          busy: busy,
                          canStartDelivery: !widget.api.portalV1,
                          confirmPickup: confirmPickup,
                          startDelivery: startDelivery,
                          openProof: openProof,
                          reportIssue: reportIssue,
                          run: (action) => switch (action) {
                            'arrive_sorting' => confirmAction(
                                action,
                                'Report arrival at Sorting Center?',
                                'This reports your arrival only. Authorized Sorting Center staff must perform the physical inbound scan.',
                              ),
                            'start_delivery' => startDelivery(),
                            'collect_cod' => confirmAction(
                                action,
                                'Confirm COD collection?',
                                'Confirm that you received the exact amount shown. The amount cannot be edited.',
                              ),
                            'delivered' => confirmAction(
                                action,
                                'Mark parcel delivered?',
                                'Confirm successful handoff to the recipient. Required proof and COD must already be recorded.',
                              ),
                            _ => Future.value(),
                          },
                        ),
                      ],
                      if (delivery!.json['proof'] != null)
                        _InfoCard(
                          title: 'Proof of delivery',
                          icon: Icons.verified_outlined,
                          rows: {
                            'Method': 'Photo',
                            'Recipient': delivery!.json['proof']
                                        ['recipient_name']
                                    ?.toString() ??
                                'Not recorded',
                            'Submitted': dateText(
                                delivery!.json['proof']['submitted_at']),
                            'Review status':
                                delivery!.json['proof']['status']?.toString() ??
                                    'Not recorded',
                          },
                        ),
                      if (delivery!.json['failure_reason'] != null)
                        _InfoCard(
                          title: 'Reported issue',
                          icon: Icons.report_problem_outlined,
                          rows: {
                            'Details': delivery!.text('failure_reason'),
                          },
                        ),
                      if (delivery!.events.isNotEmpty) ...[
                        const SectionTitle('Status history'),
                        for (final event in delivery!.events)
                          Card(
                            child: ListTile(
                              title: Text(label(event['status'].toString())),
                              subtitle: Text(
                                '${event['note'] ?? 'Status updated'}\n${dateText(event['created_at'])}',
                              ),
                              isThreeLine: true,
                            ),
                          ),
                      ],
                    ],
                  ),
                ),
        ),
      );
}

class _InfoCard extends StatelessWidget {
  final String title;
  final IconData icon;
  final Map<String, String> rows;
  final String? actionLabel;
  final VoidCallback? onAction;

  const _InfoCard({
    required this.title,
    required this.icon,
    required this.rows,
    this.actionLabel,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) => Card(
        margin: const EdgeInsets.only(top: 16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(children: [
                Icon(icon),
                const SizedBox(width: 8),
                Text(title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        )),
              ]),
              const Divider(height: 24),
              for (final row in rows.entries) ...[
                Text(row.key, style: Theme.of(context).textTheme.labelMedium),
                const SizedBox(height: 2),
                SelectableText(row.value),
                const SizedBox(height: 12),
              ],
              if (actionLabel != null)
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: onAction,
                    icon: const Icon(Icons.directions_outlined),
                    label: Text(actionLabel!),
                  ),
                ),
            ],
          ),
        ),
      );
}

class _ContactCard extends StatelessWidget {
  final String? buyerPhone;
  final String? sellerPhone;
  final Future<void> Function(String? number) onCall;

  const _ContactCard({
    required this.buyerPhone,
    required this.sellerPhone,
    required this.onCall,
  });

  @override
  Widget build(BuildContext context) => Card(
        margin: const EdgeInsets.only(top: 16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Contact',
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              const Text('The phone app opens with the number ready to dial.'),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  OutlinedButton.icon(
                    onPressed: () => onCall(buyerPhone),
                    icon: const Icon(Icons.call_outlined),
                    label: const Text('Call buyer'),
                  ),
                  OutlinedButton.icon(
                    onPressed: () => onCall(sellerPhone),
                    icon: const Icon(Icons.storefront_outlined),
                    label: const Text('Call seller'),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                'Direct buyer or seller chat is not supported by the current SHOPPICK messaging system.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ),
      );
}

class _WorkflowActions extends StatelessWidget {
  final Delivery delivery;
  final bool busy;
  final bool canStartDelivery;
  final Future<void> Function() confirmPickup;
  final Future<void> Function() startDelivery;
  final Future<void> Function() openProof;
  final Future<void> Function() reportIssue;
  final Future<void> Function(String action) run;

  const _WorkflowActions({
    required this.delivery,
    required this.busy,
    required this.canStartDelivery,
    required this.confirmPickup,
    required this.startDelivery,
    required this.openProof,
    required this.reportIssue,
    required this.run,
  });

  @override
  Widget build(BuildContext context) {
    const order = [
      'confirm_pickup',
      'arrive_sorting',
      'start_delivery',
      'proof',
      'collect_cod',
      'delivered',
      'failed',
    ];
    final actions = order.where(delivery.actions.contains).toList();
    if (actions.isEmpty) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Text(
            'No Rider action is available at this stage. Pull down to refresh.',
          ),
        ),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (actions.contains('start_delivery') && !canStartDelivery)
          const Card(
              child: Padding(
                  padding: EdgeInsets.all(16),
                  child: Text(
                      'This API contract does not verify the parcel code at hub handoff. Starting delivery is unavailable.'))),
        for (final action in actions)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: action == 'failed'
                ? OutlinedButton.icon(
                    onPressed: busy ? null : reportIssue,
                    icon: const Icon(Icons.report_problem_outlined),
                    label: const Text('Report delivery issue'),
                  )
                : FilledButton.icon(
                    onPressed: busy ||
                            (action == 'start_delivery' && !canStartDelivery)
                        ? null
                        : action == 'confirm_pickup'
                            ? confirmPickup
                            : action == 'start_delivery'
                                ? startDelivery
                                : action == 'proof'
                                    ? openProof
                                    : () => run(action),
                    icon: Icon(_icon(action)),
                    label: Text(busy ? 'Please wait…' : _title(action)),
                  ),
          ),
      ],
    );
  }

  static String _title(String action) => switch (action) {
        'confirm_pickup' => 'Confirm seller pickup',
        'arrive_sorting' => 'Confirm arrival at hub',
        'start_delivery' => 'Start delivery — on the way',
        'proof' => 'Add delivery photo',
        'collect_cod' => 'Confirm COD collected',
        'delivered' => 'Mark delivered',
        _ => label(action),
      };

  static IconData _icon(String action) => switch (action) {
        'confirm_pickup' => Icons.inventory_2_outlined,
        'arrive_sorting' => Icons.warehouse_outlined,
        'start_delivery' => Icons.two_wheeler_outlined,
        'proof' => Icons.add_a_photo_outlined,
        'collect_cod' => Icons.payments_outlined,
        'delivered' => Icons.check_circle_outline,
        _ => Icons.arrow_forward,
      };
}

class _DeliveryIssueDialog extends StatefulWidget {
  const _DeliveryIssueDialog();

  @override
  State<_DeliveryIssueDialog> createState() => _DeliveryIssueDialogState();
}

class _DeliveryIssueDialogState extends State<_DeliveryIssueDialog> {
  final form = GlobalKey<FormState>();
  final details = TextEditingController();
  String? reason;

  @override
  void dispose() {
    details.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: const Text('Report delivery issue'),
        content: Form(
          key: form,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<String>(
                  initialValue: reason,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Issue'),
                  items: const [
                    DropdownMenuItem(
                      value: 'incorrect_address',
                      child: Text('Wrong address'),
                    ),
                    DropdownMenuItem(
                      value: 'recipient_unavailable',
                      child: Text('Buyer unreachable'),
                    ),
                    DropdownMenuItem(
                      value: 'parcel_issue',
                      child: Text('Damaged item'),
                    ),
                  ],
                  onChanged: (value) => setState(() => reason = value),
                  validator: (value) =>
                      value == null ? 'Select an issue.' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: details,
                  maxLength: 500,
                  maxLines: 3,
                  decoration:
                      const InputDecoration(labelText: 'Note (optional)'),
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              if (form.currentState!.validate()) {
                Navigator.pop(context, {
                  'reason': reason!,
                  'details': details.text.trim(),
                });
              }
            },
            child: const Text('Report issue'),
          ),
        ],
      );
}

class _DeclineDialog extends StatefulWidget {
  const _DeclineDialog();

  @override
  State<_DeclineDialog> createState() => _DeclineDialogState();
}

class _DeclineDialogState extends State<_DeclineDialog> {
  final form = GlobalKey<FormState>();
  final details = TextEditingController();
  String reason = 'unavailable';

  @override
  void dispose() {
    details.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: const Text('Decline assignment'),
        content: Form(
          key: form,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<String>(
                  initialValue: reason,
                  decoration: const InputDecoration(labelText: 'Reason'),
                  items: const [
                    DropdownMenuItem(
                        value: 'unavailable', child: Text('Unavailable')),
                    DropdownMenuItem(
                        value: 'vehicle_issue', child: Text('Vehicle issue')),
                    DropdownMenuItem(
                        value: 'emergency', child: Text('Emergency')),
                    DropdownMenuItem(value: 'other', child: Text('Other')),
                  ],
                  onChanged: (value) => setState(() => reason = value!),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: details,
                  maxLength: 500,
                  maxLines: 3,
                  decoration: const InputDecoration(labelText: 'Details'),
                  validator: (value) => reason == 'other' &&
                          (value == null || value.trim().isEmpty)
                      ? 'Add details for Other.'
                      : null,
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              if (!form.currentState!.validate()) return;
              Navigator.pop(context, {
                'reason': reason,
                'details': details.text.trim(),
              });
            },
            child: const Text('Decline'),
          ),
        ],
      );
}
