import 'package:flutter/material.dart';
import '../models/delivery.dart';
import '../services/api_client.dart';
import '../widgets/common.dart';
import 'delivery_detail_screen.dart';

class DeliveriesScreen extends StatefulWidget {
  final ApiClient api;
  final bool history;
  final String? initialStatus;
  final int? riderId, providerId;
  const DeliveriesScreen(
      {required this.api,
      this.history = false,
      this.initialStatus,
      this.riderId,
      this.providerId,
      super.key});
  @override
  State<DeliveriesScreen> createState() => _DeliveriesScreenState();
}

class _DeliveriesScreenState extends State<DeliveriesScreen> {
  final search = TextEditingController();
  List<Delivery> items = [];
  List<String> statuses = [];
  String? status, error;
  bool loading = true, more = false;
  int page = 1, last = 1, generation = 0;
  @override
  void initState() {
    super.initState();
    status = widget.initialStatus;
    load();
  }

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  Future<void> load({bool next = false}) async {
    final ticket = ++generation;
    setState(() {
      if (next) {
        more = true;
      } else {
        loading = true;
      }
      error = null;
    });
    final target = next ? page + 1 : 1;
    try {
      final response = await widget.api
          .request('GET', '${widget.api.prefix}/deliveries', query: {
        'page': '$target',
        if (search.text.trim().isNotEmpty) 'q': search.text.trim(),
        if (status != null) 'status': status!,
        if (!widget.api.manager) 'history': widget.history ? '1' : '0',
        if (widget.riderId != null) 'rider_id': '${widget.riderId}',
        if (widget.providerId != null) 'provider_id': '${widget.providerId}'
      });
      if (!mounted || ticket != generation) return;
      final result = response['deliveries'];
      final found = (result['data'] as List)
          .map((e) => Delivery(Map<String, dynamic>.from(e)))
          .toList();
      items = next ? [...items, ...found] : found;
      page = target;
      last = result['last_page'];
      statuses = List<String>.from(response['statuses']);
    } catch (e) {
      if (mounted && ticket == generation) {
        if (next) {
          showError(context, e);
        } else {
          error = friendly(e);
        }
      }
    }
    if (mounted && ticket == generation) {
      setState(() {
        loading = false;
        more = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) => Column(children: [
        Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: TextField(
                controller: search,
                textInputAction: TextInputAction.search,
                onSubmitted: (_) => load(),
                decoration: InputDecoration(
                    labelText: widget.history
                        ? 'Search delivery history'
                        : 'Search deliveries',
                    hintText: 'Tracking, order, buyer, rider, shop',
                    suffixIcon: IconButton(
                        tooltip: 'Search',
                        onPressed: () => load(),
                        icon: const Icon(Icons.search))))),
        SizedBox(
            height: 52,
            child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                children: [
                  Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                          label: const Text('All'),
                          selected: status == null,
                          onSelected: (_) {
                            setState(() => status = null);
                            load();
                          })),
                  for (final s in statuses)
                    Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                            label: Text(label(s)),
                            selected: status == s,
                            onSelected: (_) {
                              setState(() => status = s);
                              load();
                            }))
                ])),
        Expanded(
            child: LoadView(
                loading: loading,
                error: error,
                message: 'Loading deliveries…',
                retry: () => load(),
                child: RefreshIndicator(
                    onRefresh: () => load(),
                    child:
                        ListView(padding: const EdgeInsets.all(16), children: [
                      if (items.isEmpty)
                        Padding(
                            padding: const EdgeInsets.all(24),
                            child: Text(widget.history
                                ? 'No delivery history yet.'
                                : 'No deliveries found.')),
                      for (final d in items)
                        DeliveryCard(d, onTap: () async {
                          await Navigator.push(
                              context,
                              MaterialPageRoute(
                                  builder: (_) => DeliveryDetailScreen(
                                      api: widget.api, id: d.id)));
                          if (mounted) load();
                        }),
                      if (page < last)
                        OutlinedButton(
                            onPressed: more ? null : () => load(next: true),
                            child: Text(more ? 'Loading…' : 'Load more')),
                    ])))),
      ]);
}
