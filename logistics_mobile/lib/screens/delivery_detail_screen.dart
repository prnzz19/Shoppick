import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/delivery.dart';
import '../services/api_client.dart';
import '../widgets/common.dart';
import 'logistics/directory_screen.dart';
import 'rider/proof_screen.dart';
import 'tracking_screen.dart';

class DeliveryDetailScreen extends StatefulWidget {
  final ApiClient api;
  final int id;
  final bool showTracking;
  const DeliveryDetailScreen(
      {required this.api,
      required this.id,
      this.showTracking = true,
      super.key});
  @override
  State<DeliveryDetailScreen> createState() => _DeliveryDetailScreenState();
}

class _DeliveryDetailScreenState extends State<DeliveryDetailScreen> {
  Delivery? delivery;
  bool loading = true, busy = false;
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
      delivery = Delivery((await widget.api.request(
          'GET', '${widget.api.prefix}/deliveries/${widget.id}'))['delivery']);
    } catch (e) {
      error = friendly(e);
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> external(Uri uri) async {
    try {
      if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
        throw const ApiFailure(
            'No compatible app is available on this device.');
      }
    } catch (_) {
      if (mounted) {
        showError(context,
            const ApiFailure('Unable to open this action on your device.'));
      }
    }
  }

  Future<void> provider() async {
    final p = await Navigator.push<Map<String, dynamic>>(
        context,
        MaterialPageRoute(
            builder: (_) => Scaffold(
                appBar: AppBar(title: const Text('Choose provider')),
                body: DirectoryScreen(
                    api: widget.api, riders: false, selectProvider: true))));
    if (p == null ||
        !mounted ||
        !await confirm(context, 'Set ${p['name']} as provider?')) {
      return;
    }
    if (!mounted) return;
    setState(() => busy = true);
    try {
      await widget.api.request(
          'PATCH', 'logistics/deliveries/${widget.id}/provider',
          body: {'provider_id': p['id']});
      await load();
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> act(String action) async {
    if (busy) return;
    setState(() => busy = true);
    try {
      if (action == 'assign') {
        await Navigator.push(
            context,
            MaterialPageRoute(
                builder: (_) => Scaffold(
                    appBar: AppBar(title: const Text('Assign rider')),
                    body: DirectoryScreen(
                        api: widget.api,
                        riders: true,
                        assignmentId: widget.id))));
        if (mounted) await load();
        return;
      }
      if (action == 'proof') {
        await Navigator.push(
            context,
            MaterialPageRoute(
                builder: (_) =>
                    ProofScreen(api: widget.api, deliveryId: widget.id)));
        if (mounted) await load();
        return;
      }
      Map<String, dynamic> body = {'action': action};
      if (['confirm_pickup', 'scan', 'failed', 'return', 'sort']
          .contains(action)) {
        List<dynamic> areas = [];
        if (action == 'sort') {
          areas = (await widget.api
              .request('GET', 'logistics/delivery-areas'))['areas'];
        }
        if (!mounted) return;
        final result = await showDialog<Map<String, dynamic>>(
            context: context,
            builder: (_) => ActionForm(action: action, areas: areas));
        if (result == null) return;
        body.addAll(result);
      }
      if (!mounted) return;
      final title =
          action == 'delivered' ? 'Mark delivered?' : '${label(action)}?';
      if (!await confirm(context, title,
          message: action == 'collect_cod'
              ? 'Confirm that you physically received the COD amount shown. This records collection for remittance.'
              : 'Apply this action to ${delivery!.text('tracking_number')}?')) {
        return;
      }
      await widget.api.request(
          'PATCH', '${widget.api.prefix}/deliveries/${widget.id}/status',
          body: body);
      if (mounted) await load();
    } catch (e) {
      if (mounted) {
        showError(context, e);
        await load();
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final d = delivery;
    return Scaffold(
        appBar: AppBar(title: const Text('Delivery details')),
        body: LoadView(
            loading: loading,
            error: error,
            message: 'Loading delivery…',
            retry: load,
            child: d == null
                ? const SizedBox()
                : RefreshIndicator(
                    onRefresh: load,
                    child:
                        ListView(padding: const EdgeInsets.all(20), children: [
                      SectionTitle(d.text('tracking_number')),
                      Align(
                          alignment: Alignment.centerLeft,
                          child: StatusBadge(d.status)),
                      const SizedBox(height: 16),
                      for (final key in [
                        'order_number',
                        'parcel_code',
                        'shop',
                        'buyer',
                        'phone',
                        'provider',
                        'rider',
                        'pickup_rider',
                        'pickup_address',
                        'destination',
                        'instructions',
                        'payment_type',
                        'payment_status'
                      ])
                        Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(label(key),
                                      style: Theme.of(context)
                                          .textTheme
                                          .labelMedium),
                                  Text(d.text(key),
                                      style: const TextStyle(fontSize: 16))
                                ])),
                      if (d.json['cod_amount'] != null)
                        Card(
                            child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Text(
                                    'Order COD amount: ₱${(d.json['cod_amount'] as num).toStringAsFixed(2)}\nCollection applies to the order payment.',
                                    style: const TextStyle(
                                        fontWeight: FontWeight.bold)))),
                      if (d.json['failure_reason'] != null)
                        Card(
                            child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Text(
                                    'Needs attention: ${d.text('failure_reason')}'))),
                      Wrap(spacing: 10, runSpacing: 8, children: [
                        if (widget.showTracking)
                          FilledButton.icon(
                              onPressed: () async {
                                await Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                        builder: (_) => TrackingScreen(
                                            api: widget.api, id: widget.id)));
                                if (mounted) await load();
                              },
                              icon: const Icon(Icons.route),
                              label: const Text('Track shipment')),
                        OutlinedButton.icon(
                            onPressed: () {
                              final pickup = [
                                'pickup_assigned',
                                'pickup_accepted'
                              ].contains(d.status);
                              final address = d.text(
                                  pickup ? 'pickup_address' : 'destination',
                                  '');
                              if (address.isNotEmpty) {
                                external(Uri.https(
                                    'www.google.com',
                                    '/maps/search/',
                                    {'api': '1', 'query': address}));
                              }
                            },
                            icon: const Icon(Icons.map_outlined),
                            label: const Text('Open map')),
                        if (d.json['phone'] != null)
                          OutlinedButton.icon(
                              onPressed: () => external(Uri(
                                  scheme: 'tel',
                                  path: d
                                      .text('phone')
                                      .replaceAll(RegExp(r'[^0-9+]'), ''))),
                              icon: const Icon(Icons.call_outlined),
                              label: const Text('Call buyer')),
                      ]),
                      if (widget.api.manager && d.actions.contains('assign'))
                        OutlinedButton(
                            onPressed: busy ? null : provider,
                            child: const Text('Set provider')),
                      const SectionTitle('Products'),
                      for (final product in d.products)
                        Text('${product['quantity']} × ${product['name']}'),
                      const SectionTitle('Next steps'),
                      if (d.actions.isEmpty)
                        const Text(
                            'No actions available at this stage. Pull down to refresh.'),
                      for (final action in d.actions)
                        Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: FilledButton(
                                onPressed: busy ? null : () => act(action),
                                child: Text(busy
                                    ? 'Please wait…'
                                    : action == 'delivered'
                                        ? 'Mark delivered'
                                        : label(action)))),
                      if (d.json['proof'] != null) ...[
                        const SectionTitle('Proof of delivery'),
                        Text('Recipient: ${d.json['proof']['recipient_name']}'),
                        Text(
                            'Submitted: ${dateText(d.json['proof']['submitted_at'])}'),
                        Text('Review: ${d.json['proof']['status']}')
                      ],
                      const SectionTitle('Tracking history'),
                      for (final event in d.events)
                        Card(
                            child: Padding(
                                padding: const EdgeInsets.all(14),
                                child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(label(event['status']),
                                          style: const TextStyle(
                                              fontWeight: FontWeight.bold)),
                                      if (event['note'] != null)
                                        Text(event['note']),
                                      Text(dateText(event['created_at']))
                                    ]))),
                    ]))));
  }
}

class ActionForm extends StatefulWidget {
  final String action;
  final List<dynamic> areas;
  const ActionForm({required this.action, this.areas = const [], super.key});
  @override
  State<ActionForm> createState() => _ActionFormState();
}

class _ActionFormState extends State<ActionForm> {
  final form = GlobalKey<FormState>();
  final text = TextEditingController();
  String? reason;
  int? area;
  @override
  void dispose() {
    text.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final failed = widget.action == 'failed';
    final sort = widget.action == 'sort';
    final code = ['scan', 'confirm_pickup'].contains(widget.action);
    return AlertDialog(
        title: Text(label(widget.action)),
        content: SingleChildScrollView(
            child: Form(
                key: form,
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  if (failed)
                    DropdownButtonFormField<String>(
                        isExpanded: true,
                        decoration:
                            const InputDecoration(labelText: 'Failure reason'),
                        items: [
                          for (final r in [
                            'recipient_unavailable',
                            'incorrect_address',
                            'reschedule_requested',
                            'parcel_issue',
                            'payment_issue',
                            'other'
                          ])
                            DropdownMenuItem(value: r, child: Text(label(r)))
                        ],
                        onChanged: (v) => setState(() => reason = v),
                        validator: (v) =>
                            v == null ? 'Select a reason.' : null),
                  if (sort)
                    DropdownButtonFormField<int>(
                        isExpanded: true,
                        decoration:
                            const InputDecoration(labelText: 'Delivery area'),
                        items: [
                          for (final a in widget.areas)
                            DropdownMenuItem(
                                value: a['id'] as int, child: Text(a['name']))
                        ],
                        onChanged: (v) => area = v,
                        validator: (v) => v == null ? 'Select an area.' : null),
                  if (sort && widget.areas.isEmpty)
                    const Text(
                        'Create delivery areas in SHOPPICK Logistics web first.'),
                  if (!sort) ...[
                    const SizedBox(height: 12),
                    TextFormField(
                        controller: text,
                        maxLength: code ? 100 : 500,
                        maxLines: code ? 1 : 3,
                        decoration: InputDecoration(
                            labelText: code
                                ? 'Parcel code on package'
                                : 'Note / details'),
                        validator: (v) => (code ||
                                    widget.action == 'return' ||
                                    reason == 'other') &&
                                (v?.trim().isEmpty ?? true)
                            ? 'This field is required.'
                            : null)
                  ],
                ]))),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Cancel')),
          FilledButton(
              onPressed: () {
                if (form.currentState!.validate()) {
                  Navigator.pop(context, {
                    if (code) 'parcel_code': text.text.trim(),
                    if (failed) 'reason': reason,
                    if (!code && !sort) 'details': text.text.trim(),
                    if (sort) 'delivery_area_id': area
                  });
                }
              },
              child: const Text('Continue'))
        ]);
  }
}
