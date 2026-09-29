import 'package:flutter/material.dart';
import '../../models/delivery.dart';
import '../../services/api_client.dart';
import '../../widgets/common.dart';
import '../deliveries_screen.dart';

class DirectoryScreen extends StatefulWidget {
  final ApiClient api;
  final bool riders;
  final int? assignmentId;
  final String initialFilter;
  final bool selectProvider;
  const DirectoryScreen(
      {required this.api,
      required this.riders,
      this.assignmentId,
      this.initialFilter = 'active',
      this.selectProvider = false,
      super.key});
  @override
  State<DirectoryScreen> createState() => _DirectoryScreenState();
}

class _DirectoryScreenState extends State<DirectoryScreen> {
  List<dynamic> items = [];
  bool loading = true, busy = false;
  String? error;
  late String filter;
  int page = 1, last = 1;
  @override
  void initState() {
    super.initState();
    filter = widget.initialFilter;
    load();
  }

  Future<void> load({bool next = false}) async {
    setState(() {
      loading = !next;
      busy = next;
      error = null;
    });
    try {
      final key = widget.riders ? 'riders' : 'providers';
      final result = (await widget.api.request('GET', 'logistics/$key', query: {
        'page': '${next ? page + 1 : 1}',
        if (widget.riders) 'filter': filter,
        if (widget.assignmentId != null) 'delivery_id': '${widget.assignmentId}'
      }))[key];
      items = next ? [...items, ...result['data']] : result['data'];
      page = result['current_page'];
      last = result['last_page'];
    } catch (e) {
      error = friendly(e);
    }
    if (mounted) {
      setState(() {
        loading = false;
        busy = false;
      });
    }
  }

  Future<void> assign(Map<String, dynamic> item) async {
    if (busy ||
        !await confirm(context, 'Assign ${item['name']}?',
            message:
                'This rider will receive the selected parcel assignment.')) {
      return;
    }
    setState(() => busy = true);
    try {
      await widget.api.request(
          'PATCH', 'logistics/deliveries/${widget.assignmentId}/assign-rider',
          body: {'rider_id': item['id']});
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> createProvider() async {
    final result = await showDialog<Map<String, dynamic>>(
        context: context, builder: (_) => const ProviderForm());
    if (result == null || !mounted) return;
    setState(() => busy = true);
    try {
      await widget.api.request('POST', 'logistics/providers', body: result);
      await load();
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  void details(Map<String, dynamic> item) async {
    await Navigator.push(
        context,
        MaterialPageRoute(
            builder: (_) => DirectoryDetail(
                api: widget.api, item: item, rider: widget.riders)));
    if (mounted) load();
  }

  @override
  Widget build(BuildContext context) => Column(children: [
        if (widget.riders && widget.assignmentId == null)
          SizedBox(
              height: 60,
              child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  children: [
                    for (final f in [
                      'active',
                      'available',
                      'on_delivery',
                      'inactive'
                    ])
                      Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: ChoiceChip(
                              label: Text(label(f)),
                              selected: filter == f,
                              onSelected: busy
                                  ? null
                                  : (_) {
                                      setState(() => filter = f);
                                      load();
                                    }))
                  ])),
        if (!widget.riders &&
            !widget.selectProvider &&
            widget.api.can('manage_logistics_settings'))
          Padding(
              padding: const EdgeInsets.all(12),
              child: FilledButton.icon(
                  onPressed: busy ? null : createProvider,
                  icon: const Icon(Icons.add),
                  label: const Text('Add provider'))),
        Expanded(
            child: LoadView(
                loading: loading,
                error: error,
                message:
                    widget.riders ? 'Loading riders…' : 'Loading providers…',
                retry: load,
                child: RefreshIndicator(
                    onRefresh: load,
                    child:
                        ListView(padding: const EdgeInsets.all(16), children: [
                      if (!widget.riders)
                        const Padding(
                            padding: EdgeInsets.only(bottom: 16),
                            child: Text(
                                'Courier records managed by SHOPPICK. Statuses reflect internal delivery operations.')),
                      if (items.isEmpty)
                        Padding(
                            padding: const EdgeInsets.all(24),
                            child: Text(widget.riders
                                ? 'No riders available.'
                                : 'No providers recorded yet.')),
                      for (final raw in items)
                        Builder(builder: (context) {
                          final item = Map<String, dynamic>.from(raw);
                          final photo = item[widget.riders ? 'avatar' : 'logo'];
                          return Card(
                              child: InkWell(
                                  onTap: busy
                                      ? null
                                      : () {
                                          if (widget.selectProvider) {
                                            Navigator.pop(context, item);
                                          } else if (widget.assignmentId !=
                                              null) {
                                            assign(item);
                                          } else {
                                            details(item);
                                          }
                                        },
                                  child: Padding(
                                      padding: const EdgeInsets.all(16),
                                      child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Row(
                                                crossAxisAlignment:
                                                    CrossAxisAlignment.start,
                                                children: [
                                                  CircleAvatar(
                                                      child: photo == null
                                                          ? Icon(widget.riders
                                                              ? Icons
                                                                  .two_wheeler
                                                              : Icons
                                                                  .business_outlined)
                                                          : ClipOval(
                                                              child: Image.network(
                                                                  photo,
                                                                  width: 40,
                                                                  height: 40,
                                                                  fit: BoxFit
                                                                      .cover,
                                                                  errorBuilder: (context,
                                                                          error,
                                                                          stack) =>
                                                                      const Icon(
                                                                          Icons
                                                                              .business_outlined)))),
                                                  const SizedBox(width: 12),
                                                  Expanded(
                                                      child: Text(item['name'],
                                                          style:
                                                              const TextStyle(
                                                                  fontWeight:
                                                                      FontWeight
                                                                          .w800,
                                                                  fontSize:
                                                                      18)))
                                                ]),
                                            const SizedBox(height: 12),
                                            Wrap(
                                                spacing: 8,
                                                runSpacing: 8,
                                                children: [
                                                  StatusBadge(item['status']),
                                                  if (widget.riders)
                                                    StatusBadge(
                                                        item['availability'])
                                                ]),
                                            const SizedBox(height: 12),
                                            if (widget.riders) ...[
                                              Text(item['phone'] ??
                                                  'No phone recorded'),
                                              Text(
                                                  'Provider: ${item['provider'] ?? 'Not assigned'}')
                                            ],
                                            Text(
                                                '${item['active_deliveries']} active deliveries'),
                                            Text(
                                                '${item['completed_deliveries']} completed deliveries'),
                                            if (!widget.riders)
                                              Text(
                                                  '${item['active_riders']} active riders'),
                                            const SizedBox(height: 8),
                                            Text(widget.assignmentId != null
                                                ? 'Assign rider →'
                                                : widget.selectProvider
                                                    ? 'Select provider →'
                                                    : 'View details →'),
                                          ]))));
                        }),
                      if (page < last)
                        OutlinedButton(
                            onPressed: busy ? null : () => load(next: true),
                            child: const Text('Load more')),
                    ])))),
      ]);
}

class DirectoryDetail extends StatefulWidget {
  final ApiClient api;
  final Map<String, dynamic> item;
  final bool rider;
  const DirectoryDetail(
      {required this.api, required this.item, required this.rider, super.key});
  @override
  State<DirectoryDetail> createState() => _DirectoryDetailState();
}

class _DirectoryDetailState extends State<DirectoryDetail> {
  bool busy = false;
  late Map<String, dynamic> item = Map.of(widget.item);
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: Text(item['name'])),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        for (final key in [
          'status',
          if (widget.rider) ...[
            'phone',
            'provider',
            'availability'
          ] else ...[
            'code',
            'contact_email',
            'contact_phone',
            'active_riders'
          ],
          'active_deliveries',
          'completed_deliveries'
        ])
          Card(
              child: Padding(
                  padding: const EdgeInsets.all(16),
                  child:
                      Text('${label(key)}: ${item[key] ?? 'Not recorded'}'))),
        FilledButton(
            onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                    builder: (_) => Scaffold(
                        appBar: AppBar(title: const Text('Deliveries')),
                        body: DeliveriesScreen(
                            api: widget.api,
                            riderId: widget.rider ? item['id'] : null,
                            providerId: widget.rider ? null : item['id'])))),
            child: const Text('View deliveries')),
        if (widget.rider && widget.api.can('manage_riders'))
          OutlinedButton(
              onPressed: busy
                  ? null
                  : () async {
                      final provider =
                          await Navigator.push<Map<String, dynamic>>(
                              context,
                              MaterialPageRoute(
                                  builder: (_) => Scaffold(
                                      appBar: AppBar(
                                          title: const Text('Choose provider')),
                                      body: DirectoryScreen(
                                          api: widget.api,
                                          riders: false,
                                          selectProvider: true))));
                      if (provider == null ||
                          !context.mounted ||
                          !await confirm(context, 'Change rider provider?',
                              message:
                                  'Assign ${item['name']} to ${provider['name']}.')) {
                        return;
                      }
                      if (!mounted) return;
                      setState(() => busy = true);
                      try {
                        await widget.api.request(
                            'PATCH', 'logistics/riders/${item['id']}/provider',
                            body: {'provider_id': provider['id']});
                        if (mounted) {
                          setState(() => item['provider'] = provider['name']);
                        }
                      } catch (e) {
                        if (context.mounted) showError(context, e);
                      } finally {
                        if (mounted) setState(() => busy = false);
                      }
                    },
              child: const Text('Set provider')),
      ]));
}

class ProviderForm extends StatefulWidget {
  const ProviderForm({super.key});
  @override
  State<ProviderForm> createState() => _ProviderFormState();
}

class _ProviderFormState extends State<ProviderForm> {
  final form = GlobalKey<FormState>();
  final fields = {
    for (final k in ['name', 'code', 'contact_email', 'contact_phone'])
      k: TextEditingController()
  };
  @override
  void dispose() {
    for (final f in fields.values) {
      f.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
          title: const Text('Add provider'),
          content: SingleChildScrollView(
              child: Form(
                  key: form,
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    for (final e in fields.entries)
                      Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: TextFormField(
                              controller: e.value,
                              decoration:
                                  InputDecoration(labelText: label(e.key)),
                              validator: (value) =>
                                  ['name', 'code'].contains(e.key) &&
                                          (value?.trim().isEmpty ?? true)
                                      ? 'Required'
                                      : null))
                  ]))),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Cancel')),
            FilledButton(
                onPressed: () {
                  if (form.currentState!.validate()) {
                    Navigator.pop(context, {
                      for (final e in fields.entries)
                        e.key: e.value.text.trim().isEmpty
                            ? null
                            : e.value.text.trim()
                    });
                  }
                },
                child: const Text('Create'))
          ]);
}
