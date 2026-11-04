import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../models/delivery.dart';
import '../../services/api_client.dart';
import '../../widgets/common.dart';
import 'assignment_detail_screen.dart';
import 'portal_shell.dart';
import '../tracking_screen.dart';

class RiderTracking extends StatefulWidget {
  final ApiClient api;
  final int id;
  const RiderTracking({required this.api, required this.id, super.key});
  @override
  State<RiderTracking> createState() => _RiderTrackingState();
}

class _RiderTrackingState extends State<RiderTracking> {
  Delivery? job;
  String? error;
  bool loading = true;
  @override
  void initState() {
    super.initState();
    if (widget.api.portalV1) load();
  }

  Future<void> load() async {
    try {
      final result = await widget.api.riderAssignment(widget.id);
      if (mounted) {
        setState(() {
          job = Delivery(Map<String, dynamic>.from(result['delivery']));
          error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = friendly(e));
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> directions(bool pickup) async {
    final uri = riderNavigationUri(job!, pickup: pickup);
    try {
      if (uri == null ||
          !await launchUrl(uri, mode: LaunchMode.externalApplication)) {
        throw const ApiFailure(
            'No supported maps app or address is available.');
      }
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!widget.api.portalV1) {
      return TrackingScreen(api: widget.api, id: widget.id);
    }
    return riderPage(
        'Tracking',
        LoadView(
            loading: loading,
            error: error,
            message: 'Loading tracking...',
            retry: load,
            child: RefreshIndicator(
                onRefresh: load,
                child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (job != null) ...[
                        Text(job!.text('tracking_number'),
                            style: Theme.of(context).textTheme.titleLarge),
                        const SizedBox(height: 12),
                        Align(
                            alignment: Alignment.centerLeft,
                            child: StatusBadge(job!.status)),
                        const SizedBox(height: 16),
                        riderNotice('Route',
                            'Pickup: ${job!.text('pickup_address')}\n\nDelivery: ${job!.text('destination')}',
                            action: Wrap(spacing: 8, children: [
                              OutlinedButton(
                                  onPressed: () => directions(true),
                                  child: const Text('Pickup directions')),
                              OutlinedButton(
                                  onPressed: () => directions(false),
                                  child: const Text('Delivery directions'))
                            ])),
                        riderNotice('Sorting Centers',
                            'Origin: ${job!.json['origin_center']?['name'] ?? 'Not supplied'}\nNext: ${job!.json['next_center']?['name'] ?? 'Not supplied'}\nDestination: ${job!.json['destination_center']?['name'] ?? 'Not supplied'}'),
                        if (job!.events.isEmpty)
                          riderNotice('Tracking updates',
                              'The current status comes from the server. This API does not supply a complete timeline, live rider position, or delivery ETA.')
                        else ...[
                          const SectionTitle('Status history'),
                          for (final event in job!.events)
                            Card(
                                child: ListTile(
                                    title: Text(label(
                                        event['status']?.toString() ??
                                            'update')),
                                    subtitle: Text(
                                        '${event['note'] ?? ''}\n${dateText(event['created_at'])}')))
                        ],
                        FilledButton(
                            onPressed: () => openRider(
                                context,
                                RiderAssignmentDetailScreen(
                                    api: widget.api, id: widget.id)),
                            child: const Text('View assignment')),
                      ]
                    ]))));
  }
}

class RiderNotifications extends StatefulWidget {
  final ApiClient api;
  const RiderNotifications({required this.api, super.key});
  @override
  State<RiderNotifications> createState() => _RiderNotificationsState();
}

class _RiderNotificationsState extends State<RiderNotifications> {
  List<dynamic> items = [];
  bool loading = false, busy = false;
  String? error;
  int page = 1, last = 1;
  @override
  void initState() {
    super.initState();
    if (!widget.api.portalV1) {
      load();
    } else {
      loading = false;
    }
  }

  Future<void> load({bool next = false}) async {
    if (busy) return;
    setState(() => busy = true);
    try {
      final data = (await widget.api.request('GET', 'rider/notifications',
          query: {'page': '${next ? page + 1 : 1}'}))['notifications'] as Map;
      if (mounted) {
        setState(() {
          items = next ? [...items, ...data['data']] : List.from(data['data']);
          page = data['current_page'];
          last = data['last_page'];
          error = null;
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

  Future<void> readAll() async {
    if (busy) return;
    setState(() => busy = true);
    try {
      await widget.api.request('POST', 'rider/notifications/read-all');
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
    if (mounted) await load();
  }

  Future<void> openNotification(Map item) async {
    final id = item['id'];
    if (item['read_at'] == null && id is num) {
      try {
        await widget.api.markRiderNotificationRead(id.toInt());
        await load();
      } catch (e) {
        if (mounted) showError(context, e);
      }
    }
    final shipmentId = item['shipment_id'];
    if (mounted && shipmentId is num) {
      await openRider(context,
          RiderAssignmentDetailScreen(api: widget.api, id: shipmentId.toInt()));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (widget.api.portalV1) {
      return ListView(padding: const EdgeInsets.all(16), children: [
        const SectionTitle('Notifications'),
        riderNotice('Notifications unavailable',
            'The current Rider API has no notification list or read-status endpoint. Website notifications cannot be loaded in this app yet.',
            action: const OutlinedButton(
                onPressed: null, child: Text('Mark all read')))
      ]);
    }
    return LoadView(
        loading: loading,
        error: error,
        message: 'Loading notifications...',
        retry: load,
        child: RefreshIndicator(
            onRefresh: load,
            child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: [
                  const SectionTitle('Notifications'),
                  Align(
                      alignment: Alignment.centerRight,
                      child: OutlinedButton(
                          onPressed: busy || items.isEmpty ? null : readAll,
                          child: const Text('Mark all read'))),
                  if (items.isEmpty)
                    riderNotice('No notifications',
                        'You have no notifications from this server.'),
                  for (final item in items)
                    Card(
                        child: ListTile(
                            leading: Icon(item['read_at'] == null
                                ? Icons.mark_email_unread_outlined
                                : Icons.drafts_outlined),
                            title: Text(
                                item['title']?.toString() ?? 'Notification'),
                            subtitle: Text(
                                '${item['body'] ?? ''}\n${dateText(item['created_at'])}'),
                            trailing: item['read_at'] == null
                                ? const Text('Unread')
                                : null,
                            onTap: () => openNotification(
                                Map<String, dynamic>.from(item)))),
                  if (page < last)
                    OutlinedButton(
                        onPressed: busy ? null : () => load(next: true),
                        child: const Text('Load more'))
                ])));
  }
}

class RiderSupport extends StatefulWidget {
  final ApiClient api;
  const RiderSupport({required this.api, super.key});

  @override
  State<RiderSupport> createState() => _RiderSupportState();
}

class _RiderSupportState extends State<RiderSupport> {
  final messageController = TextEditingController();
  List<dynamic> messages = [];
  bool loading = false, busy = false;
  String? error;
  int page = 1, lastPage = 1;

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    messageController.dispose();
    super.dispose();
  }

  Future<void> load({bool next = false}) async {
    if (loading) return;
    setState(() => loading = true);
    if (widget.api.portalV1) {
      setState(() {
        loading = false;
        error = 'Messaging is not available in the configured API contract.';
      });
      return;
    }
    try {
      final result = await widget.api.riderMessages(page: next ? page + 1 : 1);
      final data = result['messages'] as Map;
      final incoming = List<dynamic>.from(data['data'] as List? ?? const []);
      if (mounted) {
        setState(() {
          messages = next ? [...messages, ...incoming] : incoming;
          page = (data['current_page'] as num? ?? 1).toInt();
          lastPage = (data['last_page'] as num? ?? page).toInt();
          error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = friendly(e));
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> send() async {
    final message = messageController.text.trim();
    if (busy || message.isEmpty) return;
    setState(() => busy = true);
    try {
      await widget.api.sendRiderMessage(message);
      messageController.clear();
      await load();
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(
      onRefresh: load,
      child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            const SectionTitle('Logistics Support'),
            riderNotice('Contact your Logistics provider',
                'Messages are shared with the Logistics team responsible for Rider support.'),
            if (error != null) riderNotice('Support unavailable', error!),
            for (final item in messages)
              Card(
                  child: ListTile(
                      title: Text(item['sender_name']?.toString() ??
                          'Logistics Support'),
                      subtitle: Text(
                          '${item['message'] ?? ''}\n${dateText(item['created_at'])}'))),
            if (loading) const LinearProgressIndicator(),
            if (page < lastPage)
              OutlinedButton(
                  onPressed: busy || loading ? null : () => load(next: true),
                  child: const Text('Load more messages')),
            Card(
                child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          TextField(
                              controller: messageController,
                              enabled: !busy && !widget.api.portalV1,
                              maxLength: 1000,
                              maxLines: 3,
                              onChanged: (_) => setState(() {}),
                              decoration: const InputDecoration(
                                  labelText: 'Message Logistics Support')),
                          const SizedBox(height: 12),
                          FilledButton(
                              onPressed: busy ||
                                      widget.api.portalV1 ||
                                      messageController.text.trim().isEmpty
                                  ? null
                                  : send,
                              child: Text(busy ? 'Sending...' : 'Send message'))
                        ])))
          ]));
}

class RiderProfile extends StatefulWidget {
  final ApiClient api;
  const RiderProfile({required this.api, super.key});
  @override
  State<RiderProfile> createState() => _RiderProfileState();
}

class _RiderProfileState extends State<RiderProfile> {
  bool loading = false;
  bool saving = false;
  String? error;

  final phone = TextEditingController();
  final emergencyName = TextEditingController();
  final emergencyPhone = TextEditingController();

  @override
  void initState() {
    super.initState();
    _syncFields();
    refresh();
  }

  @override
  void dispose() {
    phone.dispose();
    emergencyName.dispose();
    emergencyPhone.dispose();
    super.dispose();
  }

  void _syncFields() {
    final user = widget.api.user ?? {};
    phone.text = (user['phone'] ?? '').toString();
    emergencyName.text = (user['emergency_contact_name'] ?? '').toString();
    emergencyPhone.text = (user['emergency_contact_phone'] ?? '').toString();
  }

  Future<void> refresh() async {
    setState(() => loading = true);
    try {
      await widget.api.refreshProfile();
      if (mounted) {
        setState(() {
          _syncFields();
          error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = friendly(e));
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> save() async {
    if (saving || widget.api.portalV1) return;
    setState(() => saving = true);
    try {
      await widget.api.updateRiderProfile({
        'phone': phone.text.trim(),
        'emergency_contact_name': emergencyName.text.trim(),
        'emergency_contact_phone': emergencyPhone.text.trim(),
      });
      await refresh();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Contact information saved.')));
      }
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  String supplied(dynamic value) => value == null || value.toString().isEmpty
      ? 'Not supplied by this server'
      : value.toString();
  @override
  Widget build(BuildContext context) {
    final user = widget.api.user ?? {};
    final provider = user['provider'];
    final center = user['sorting_center'];
    final license = user['driver_license'] is Map
        ? Map<String, dynamic>.from(user['driver_license'])
        : <String, dynamic>{};
    final info = <String, dynamic>{
      'Name': user['name'],
      'Email': user['email'],
      'Mobile Number': user['phone'] ?? user['mobile_number'],
      'Logistics Provider': provider is Map ? provider['name'] : provider,
      'Vehicle Type': user['vehicle_type'],
      'Plate Number': user['plate_number'],
      'Sorting Center': center is Map ? center['name'] : center,
      'Account Status': user['status'],
      'Availability': user['availability'],
      'Driver License': license['number'],
      'License Status': license['status'],
      'License Expiration': license['expires_at'],
      'License Front On File':
          license['front_available'] == true ? 'Yes' : 'No',
      'License Back On File': license['back_available'] == true ? 'Yes' : 'No',
    };
    return RefreshIndicator(
        onRefresh: refresh,
        child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            children: [
              const SectionTitle('Your Rider Profile'),
              const Text('Your account, vehicle, and contact information.'),
              const SizedBox(height: 16),
              if (loading) const LinearProgressIndicator(),
              if (error != null)
                riderNotice('Profile could not refresh', error!),
              Card(
                  child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Rider Information',
                                style: TextStyle(fontWeight: FontWeight.w800)),
                            const SizedBox(height: 16),
                            for (final entry in info.entries) ...[
                              Text(entry.key,
                                  style:
                                      Theme.of(context).textTheme.labelSmall),
                              SelectableText(supplied(entry.value)),
                              const SizedBox(height: 14)
                            ],
                            const Divider(),
                            const Text(
                                'Driver license documents are restricted to your authenticated Rider account.')
                          ]))),
              Card(
                  child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            const Text('Contact Information',
                                style: TextStyle(fontWeight: FontWeight.w800)),
                            const SizedBox(height: 12),
                            TextField(
                                controller: phone,
                                enabled: !saving && !widget.api.portalV1,
                                keyboardType: TextInputType.phone,
                                maxLength: 30,
                                decoration: const InputDecoration(
                                    labelText: 'Mobile number')),
                            TextField(
                                controller: emergencyName,
                                enabled: !saving && !widget.api.portalV1,
                                maxLength: 100,
                                decoration: const InputDecoration(
                                    labelText: 'Emergency contact name')),
                            TextField(
                                controller: emergencyPhone,
                                enabled: !saving && !widget.api.portalV1,
                                keyboardType: TextInputType.phone,
                                maxLength: 30,
                                decoration: const InputDecoration(
                                    labelText: 'Emergency contact phone')),
                            const SizedBox(height: 12),
                            FilledButton(
                                onPressed:
                                    saving || widget.api.portalV1 ? null : save,
                                child: Text(saving
                                    ? 'Saving...'
                                    : 'Save Contact Information'))
                          ]))),
              if (widget.api.portalV1)
                const Text(
                    'Contact updates are unavailable with the configured API contract.')
            ]));
  }
}

class RiderRecords extends StatefulWidget {
  final ApiClient api;
  final String kind;
  const RiderRecords({required this.api, required this.kind, super.key});

  @override
  State<RiderRecords> createState() => _RiderRecordsState();
}

class _RiderRecordsState extends State<RiderRecords> {
  Map<String, dynamic> data = {};
  List<dynamic> recordRows = [];
  int page = 1, lastPage = 1;
  bool loading = true, busy = false;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load({bool next = false}) async {
    if (busy) return;
    if (widget.api.portalV1) {
      setState(() {
        loading = false;
        error = 'Records are not available in the configured API contract.';
      });
      return;
    }
    setState(() {
      busy = true;
      if (!next) loading = true;
    });
    try {
      final result = widget.kind == 'cod'
          ? await widget.api.riderCod(page: next ? page + 1 : 1)
          : await widget.api.riderProofs(page: next ? page + 1 : 1);
      final rawPage =
          result[widget.kind == 'cod' ? 'records' : 'submitted'] as Map;
      final incoming = List<dynamic>.from(rawPage['data'] as List? ?? const []);
      if (mounted) {
        setState(() {
          data = result;
          recordRows = next ? [...recordRows, ...incoming] : incoming;
          page = (rawPage['current_page'] as num? ?? 1).toInt();
          lastPage = (rawPage['last_page'] as num? ?? page).toInt();
          error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = friendly(e));
    } finally {
      if (mounted) {
        setState(() {
          loading = false;
          busy = false;
        });
      }
    }
  }

  String money(dynamic value) =>
      '₱${(value is num ? value : num.tryParse('$value') ?? 0).toStringAsFixed(2)}';

  @override
  Widget build(BuildContext context) {
    final cod = widget.kind == 'cod';
    final records = recordRows;
    final pending = data['pending'] as List? ?? const [];
    return RefreshIndicator(
        onRefresh: () => load(),
        child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            children: [
              SectionTitle(cod ? 'COD Collections' : 'Proof of Delivery'),
              if (loading) const LinearProgressIndicator(),
              if (error != null) riderNotice('Records unavailable', error!),
              if (!widget.api.portalV1 && cod) ...[
                Card(
                    color: const Color(0xfffffae9),
                    child: Padding(
                        padding: const EdgeInsets.all(20),
                        child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('COD SUMMARY',
                                  style:
                                      TextStyle(fontWeight: FontWeight.w800)),
                              const SizedBox(height: 8),
                              Text('To collect: ${money(data['to_collect'])}',
                                  style: const TextStyle(
                                      fontSize: 21,
                                      fontWeight: FontWeight.w800)),
                              Text('Collected: ${money(data['collected'])}'),
                              Text(
                                  'Pending remittance: ${money(data['pending_remittance'])}'),
                              const SizedBox(height: 8),
                              const Text(
                                  'Logistics confirms official remittance.')
                            ]))),
                const SectionTitle('COD to collect'),
                if (pending.isEmpty)
                  riderNotice('No COD due',
                      'There are no COD payments awaiting collection on your active deliveries.'),
                for (final item in pending)
                  Card(
                      child: ListTile(
                          title: Text(item['tracking_number']?.toString() ??
                              'Assigned shipment'),
                          subtitle: Text(
                              'Order ${item['order_number'] ?? '—'} • ${label(item['status']?.toString() ?? 'pending')}'),
                          trailing: Text(money(item['amount'])))),
                const SectionTitle('Collection history'),
              ],
              if (!widget.api.portalV1 && !cod) ...[
                const SectionTitle('Pending proof'),
                if (pending.isEmpty)
                  riderNotice('No pending proof',
                      'There are no active deliveries awaiting a proof submission.'),
                for (final item in pending)
                  Card(
                      child: ListTile(
                          title: Text(item['tracking_number']?.toString() ??
                              'Assigned shipment'),
                          subtitle: Text(
                              '${item['destination'] ?? ''}\n${label(item['proof_status']?.toString() ?? 'not submitted')}'))),
                const SectionTitle('Submitted proof'),
              ],
              if (records.isEmpty && !loading && error == null)
                riderNotice(
                    cod ? 'No collection records' : 'No proof records',
                    cod
                        ? 'No COD collections have been recorded for your account.'
                        : 'No Proof of Delivery submissions are recorded for your account.'),
              for (final item in records)
                Card(
                    child: ListTile(
                        title: Text(cod
                            ? item['order_number']?.toString() ??
                                'COD collection'
                            : item['tracking_number']?.toString() ??
                                'Delivery proof'),
                        subtitle: Text(cod
                            ? '${label(item['status']?.toString() ?? 'unknown')} • ${dateText(item['collected_at'])}\nRemittance: ${label(item['remittance_status']?.toString() ?? 'unknown')}'
                            : '${item['recipient_name'] ?? 'Recipient not recorded'} • ${label(item['status']?.toString() ?? 'unknown')}\n${dateText(item['submitted_at'])}${item['review_notes'] == null ? '' : '\n${item['review_notes']}'}'),
                        trailing: cod ? Text(money(item['amount'])) : null)),
              if (page < lastPage)
                OutlinedButton(
                    onPressed: busy ? null : () => load(next: true),
                    child: const Text('Load more records')),
              if (cod)
                const Text(
                    'COD records do not add a charge to the Buyer. Only Logistics can confirm remittance.')
            ]));
  }
}

class RiderSettings extends StatefulWidget {
  final ApiClient api;
  const RiderSettings({required this.api, super.key});
  @override
  State<RiderSettings> createState() => _RiderSettingsState();
}

class _RiderSettingsState extends State<RiderSettings> {
  bool signingOut = false;
  bool loadingPreferences = true, savingPreferences = false;
  String? settingsError;
  Map<String, bool> preferences = {};

  @override
  void initState() {
    super.initState();
    loadPreferences();
  }

  Future<void> loadPreferences() async {
    if (widget.api.portalV1) {
      setState(() {
        loadingPreferences = false;
        settingsError =
            'Settings are not available in the configured API contract.';
      });
      return;
    }
    try {
      final response = await widget.api.riderSettings();
      final raw = Map<String, dynamic>.from(response['preferences'] as Map);
      if (mounted) {
        setState(() {
          preferences = {
            'assignments': raw['assignments'] == true,
            'status': raw['status'] == true,
            'support': raw['support'] == true,
          };
          settingsError = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => settingsError = friendly(e));
    } finally {
      if (mounted) setState(() => loadingPreferences = false);
    }
  }

  Future<void> savePreferences() async {
    if (savingPreferences || widget.api.portalV1) return;
    setState(() => savingPreferences = true);
    try {
      final response = await widget.api.updateRiderSettings(preferences);
      final raw = Map<String, dynamic>.from(response['preferences'] as Map);
      if (mounted) {
        setState(() {
          preferences = {
            'assignments': raw['assignments'] == true,
            'status': raw['status'] == true,
            'support': raw['support'] == true,
          };
        });
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Notification preferences saved.')));
      }
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => savingPreferences = false);
    }
  }

  Future<void> changePassword() async {
    final current = TextEditingController();
    final password = TextEditingController();
    final confirmation = TextEditingController();
    try {
      final values = await showDialog<List<String>>(
          context: context,
          builder: (context) => AlertDialog(
                  title: const Text('Change Password'),
                  content: Column(mainAxisSize: MainAxisSize.min, children: [
                    TextField(
                        controller: current,
                        obscureText: true,
                        decoration: const InputDecoration(
                            labelText: 'Current password')),
                    TextField(
                        controller: password,
                        obscureText: true,
                        decoration:
                            const InputDecoration(labelText: 'New password')),
                    TextField(
                        controller: confirmation,
                        obscureText: true,
                        decoration: const InputDecoration(
                            labelText: 'Confirm new password')),
                  ]),
                  actions: [
                    TextButton(
                        onPressed: () => Navigator.pop(context),
                        child: const Text('Cancel')),
                    FilledButton(
                        onPressed: () => Navigator.pop(context,
                            [current.text, password.text, confirmation.text]),
                        child: const Text('Update password'))
                  ]));
      if (values == null) return;
      await widget.api.changeRiderPassword(values[0], values[1], values[2]);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Password changed successfully.')));
      }
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      current.dispose();
      password.dispose();
      confirmation.dispose();
    }
  }

  Future<void> logout() async {
    final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
                icon: const Icon(Icons.logout, color: riderTeal),
                title: const Text('Log out of Rider Portal?'),
                content: const Text(
                    'Make sure any delivery status updates have been saved before leaving.'),
                actions: [
                  TextButton(
                      onPressed: () => Navigator.pop(context, false),
                      child: const Text('Stay Logged In')),
                  FilledButton(
                      onPressed: () => Navigator.pop(context, true),
                      child: const Text('Logout'))
                ]));
    if (confirmed != true || !mounted) return;
    setState(() => signingOut = true);
    try {
      await widget.api.logout();
      if (mounted) Navigator.of(context).popUntil((route) => route.isFirst);
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => signingOut = false);
    }
  }

  @override
  Widget build(BuildContext context) =>
      ListView(padding: const EdgeInsets.all(16), children: [
        const SectionTitle('Settings'),
        Card(
            child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Text('Notification Preferences',
                          style: TextStyle(fontWeight: FontWeight.w800)),
                      if (loadingPreferences) const LinearProgressIndicator(),
                      if (settingsError != null)
                        Padding(
                            padding: const EdgeInsets.symmetric(vertical: 8),
                            child: Text(settingsError!)),
                      for (final entry in const {
                        'assignments': 'Assignment updates',
                        'status': 'Status and POD updates',
                        'support': 'Support messages',
                      }.entries)
                        CheckboxListTile(
                            value: preferences[entry.key] ?? false,
                            onChanged: loadingPreferences ||
                                    savingPreferences ||
                                    widget.api.portalV1
                                ? null
                                : (value) => setState(() =>
                                    preferences[entry.key] = value ?? false),
                            contentPadding: EdgeInsets.zero,
                            title: Text(entry.value)),
                      FilledButton(
                          onPressed: loadingPreferences ||
                                  savingPreferences ||
                                  widget.api.portalV1
                              ? null
                              : savePreferences,
                          child: Text(savingPreferences
                              ? 'Saving...'
                              : 'Save Preferences'))
                    ]))),
        Card(
            child: Padding(
                padding: EdgeInsets.all(20),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text('Change Password',
                          style: TextStyle(fontWeight: FontWeight.w800)),
                      SizedBox(height: 16),
                      Text(
                          'Confirm your current password before setting a new password.'),
                      SizedBox(height: 12),
                      FilledButton(
                          onPressed:
                              widget.api.portalV1 ? null : changePassword,
                          child: const Text('Change Password'))
                    ]))),
        OutlinedButton.icon(
            onPressed: signingOut ? null : logout,
            icon: const Icon(Icons.logout),
            label: Text(signingOut ? 'Signing out...' : 'Logout'))
      ]);
}
