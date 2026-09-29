import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../widgets/common.dart';
import 'delivery_detail_screen.dart';

class NotificationsScreen extends StatefulWidget {
  final ApiClient api;
  const NotificationsScreen({required this.api, super.key});
  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<dynamic> items = [];
  bool loading = true, busy = false;
  int page = 1, last = 1;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load({bool next = false}) async {
    setState(() {
      loading = !next;
      busy = next;
      error = null;
    });
    try {
      final data = (await widget.api.request(
          'GET', '${widget.api.prefix}/notifications',
          query: {'page': '${next ? page + 1 : 1}'}))['notifications'];
      items = next ? [...items, ...data['data']] : data['data'];
      page = data['current_page'];
      last = data['last_page'];
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

  @override
  Widget build(BuildContext context) => LoadView(
      loading: loading,
      error: error,
      message: 'Loading notifications…',
      retry: load,
      child: RefreshIndicator(
          onRefresh: load,
          child: ListView(padding: const EdgeInsets.all(16), children: [
            const SectionTitle('Notifications'),
            OutlinedButton(
                onPressed: busy
                    ? null
                    : () async {
                        setState(() => busy = true);
                        try {
                          await widget.api.request('POST',
                              '${widget.api.prefix}/notifications/read-all');
                          await load();
                        } catch (e) {
                          if (context.mounted) showError(context, e);
                        } finally {
                          if (mounted) setState(() => busy = false);
                        }
                      },
                child: const Text('Mark all as read')),
            if (items.isEmpty)
              const Padding(
                  padding: EdgeInsets.all(24),
                  child: Text("You're all caught up.")),
            for (final n in items)
              Card(
                  child: ListTile(
                      isThreeLine: true,
                      leading: Icon(n['read_at'] == null
                          ? Icons.mark_email_unread_outlined
                          : Icons.drafts_outlined),
                      title: Text(n['title']),
                      subtitle:
                          Text('${n['body']}\n${dateText(n['created_at'])}'),
                      onTap: n['shipment_id'] == null
                          ? null
                          : () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                  builder: (_) => DeliveryDetailScreen(
                                      api: widget.api,
                                      id: (n['shipment_id'] as num)
                                          .toInt()))))),
            if (page < last)
              OutlinedButton(
                  onPressed: busy ? null : () => load(next: true),
                  child: const Text('Load more')),
          ])));
}
