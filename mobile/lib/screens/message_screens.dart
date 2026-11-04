part of '../main.dart';

String messageRelativeTime(dynamic value) {
  final time = DateTime.tryParse(value?.toString() ?? '')?.toLocal();
  if (time == null) return '';
  final difference = DateTime.now().difference(time);
  if (difference.inMinutes < 1) return 'Now';
  if (difference.inMinutes < 60) return '${difference.inMinutes}m';
  if (difference.inHours < 24) return '${difference.inHours}h';
  if (difference.inDays < 7) return '${difference.inDays}d';
  return date(value);
}

String messageClientToken() {
  final bytes =
      List<int>.generate(16, (_) => math.Random.secure().nextInt(256));
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  final hex =
      bytes.map((value) => value.toRadixString(16).padLeft(2, '0')).join();
  return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
}

class MessagesScreen extends StatefulWidget {
  const MessagesScreen({super.key});
  @override
  State<MessagesScreen> createState() => _MessagesScreenState();
}

class _MessagesScreenState extends State<MessagesScreen> {
  int page = 1;
  late Future<dynamic> inbox;

  @override
  void initState() {
    super.initState();
    inbox = load();
  }

  Future<dynamic> load() => ApiService().request('messages?page=$page');

  Future<void> reload({int? targetPage}) async {
    if (targetPage != null) page = targetPage;
    setState(() => inbox = load());
    try {
      await inbox;
    } catch (_) {}
  }

  Future<void> openThread(dynamic conversation) async {
    await Navigator.push(
        context,
        MaterialPageRoute(
            builder: (_) => ConversationScreen(
                conversationId: conversation['id'] as int,
                initialStore: conversation['store'] as Map?)));
    if (mounted) await reload();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Messages')),
      body: FutureBuilder<dynamic>(
          future: inbox,
          builder: (_, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Loading();
            }
            if (snapshot.hasError) {
              return ErrorState(
                  message: snapshot.error.toString(), retry: reload);
            }
            final response = snapshot.data as Map? ?? {};
            final data = response['conversations'] as Map? ?? {};
            final conversations = data['data'] as List? ?? [];
            final current = (data['current_page'] as num?)?.toInt() ?? 1;
            final last = (data['last_page'] as num?)?.toInt() ?? 1;
            return RefreshIndicator(
                onRefresh: reload,
                child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(14),
                    children: [
                      if (conversations.isEmpty)
                        const SizedBox(
                            height: 420,
                            child: EmptyState(
                                icon: Icons.chat_bubble_outline,
                                title: 'No conversations yet',
                                message:
                                    'Message a Seller from a product or order page to get started.')),
                      for (final conversation in conversations)
                        Card(
                            elevation: 0,
                            color: Colors.white,
                            child: ListTile(
                                contentPadding: const EdgeInsets.symmetric(
                                    horizontal: 14, vertical: 6),
                                leading: SizedBox(
                                    width: 48,
                                    height: 48,
                                    child: ClipOval(
                                        child: ProductImage(
                                            url: conversation['store']?['logo'],
                                            fallback: Text(
                                                (conversation['store']
                                                            ?['name'] ??
                                                        'S')
                                                    .toString()
                                                    .characters
                                                    .first,
                                                style: const TextStyle(
                                                    color: teal,
                                                    fontWeight:
                                                        FontWeight.bold))))),
                                title: Row(children: [
                                  Expanded(
                                      child: Text(
                                          conversation['store']?['name'] ??
                                              'Shop unavailable',
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(
                                              color: navy,
                                              fontWeight: FontWeight.w700))),
                                  Text(
                                      messageRelativeTime(
                                          conversation['last_message_at']),
                                      style: const TextStyle(
                                          fontSize: 12, color: Colors.black54))
                                ]),
                                subtitle: Row(children: [
                                  Expanded(
                                      child: Text(
                                          conversation['latest_preview'] ?? '',
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis)),
                                  if ((conversation['unread_count'] as num? ??
                                          0) >
                                      0)
                                    Badge(
                                        label: Text(
                                            '${conversation['unread_count']}'))
                                ]),
                                onTap: () => openThread(conversation))),
                      if (last > 1)
                        Padding(
                            padding: const EdgeInsets.only(top: 10),
                            child: Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  TextButton(
                                      onPressed: current > 1
                                          ? () =>
                                              reload(targetPage: current - 1)
                                          : null,
                                      child: const Text('Previous')),
                                  Text('$current / $last'),
                                  TextButton(
                                      onPressed: current < last
                                          ? () =>
                                              reload(targetPage: current + 1)
                                          : null,
                                      child: const Text('Next'))
                                ]))
                    ]));
          }));
}

class ConversationScreen extends StatefulWidget {
  final int conversationId;
  final Map? initialStore;
  const ConversationScreen(
      {super.key, required this.conversationId, this.initialStore});
  @override
  State<ConversationScreen> createState() => _ConversationScreenState();
}

class _ConversationScreenState extends State<ConversationScreen>
    with WidgetsBindingObserver {
  final message = TextEditingController();
  final scroll = ScrollController();
  final photos = <PlatformFile>[];
  final messages = <Map<String, dynamic>>[];
  Map? store;
  Map<String, String> imageHeaders = const {};
  String clientToken = messageClientToken();
  String? error;
  bool loading = true;
  bool loadingOlder = false;
  bool sending = false;
  bool refreshing = false;
  bool hasMore = false;
  Timer? refreshTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    store = widget.initialStore;
    loadInitial();
  }

  @override
  void dispose() {
    refreshTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    message.dispose();
    scroll.dispose();
    super.dispose();
  }

  Future<void> loadInitial() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final results = await Future.wait([
        ApiService().request('messages/${widget.conversationId}'),
        ApiService().authorizationHeaders(),
      ]);
      final data = results[0] as Map;
      if (!mounted) return;
      setState(() {
        store = data['conversation']?['store'] as Map? ?? store;
        messages
          ..clear()
          ..addAll((data['messages'] as List? ?? [])
              .map((item) => Map<String, dynamic>.from(item as Map)));
        hasMore = data['has_more'] == true;
        imageHeaders = Map<String, String>.from(results[1] as Map);
        loading = false;
      });
      ApiService.messageCount.value =
          (data['unread_count'] as num?)?.toInt() ?? 0;
      scrollToBottom();
      refreshTimer ??=
          Timer.periodic(const Duration(seconds: 6), (_) => refresh());
    } catch (exception) {
      if (mounted) {
        setState(() {
          error = exception.toString();
          loading = false;
        });
      }
    }
  }

  Future<void> refresh() async {
    if (WidgetsBinding.instance.lifecycleState != null &&
        WidgetsBinding.instance.lifecycleState != AppLifecycleState.resumed) {
      return;
    }
    if (refreshing || sending || !mounted) return;
    refreshing = true;
    try {
      final lastId = messages.isEmpty ? 0 : messages.last['id'];
      final data = await ApiService().request(
          'messages/${widget.conversationId}/updates?after_id=$lastId');
      final incoming = (data['messages'] as List? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .where((item) => !messages.any((known) => known['id'] == item['id']))
          .toList();
      if (mounted && incoming.isNotEmpty) {
        setState(() => messages.addAll(incoming));
        scrollToBottom();
      }
      ApiService.messageCount.value = (data['unread_count'] as num?)?.toInt() ??
          ApiService.messageCount.value;
    } catch (_) {
      // Keep the current thread usable when a background refresh fails.
    } finally {
      refreshing = false;
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) refresh();
  }

  Future<void> loadOlder() async {
    if (loadingOlder || messages.isEmpty) return;
    setState(() => loadingOlder = true);
    try {
      final oldExtent =
          scroll.hasClients ? scroll.position.maxScrollExtent : 0.0;
      final data = await ApiService().request(
          'messages/${widget.conversationId}/older?before_id=${messages.first['id']}');
      final older = (data['messages'] as List? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      if (!mounted) return;
      setState(() {
        messages.insertAll(0, older);
        hasMore = data['has_more'] == true;
      });
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (scroll.hasClients) {
          scroll.jumpTo(scroll.position.maxScrollExtent - oldExtent);
        }
      });
    } catch (exception) {
      if (mounted) tell(context, exception);
    } finally {
      if (mounted) setState(() => loadingOlder = false);
    }
  }

  Future<void> pickPhotos() async {
    try {
      final result = await FilePicker.platform.pickFiles(
          type: FileType.custom,
          allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp'],
          allowMultiple: true,
          withData: true);
      if (!mounted || result == null) return;
      if (photos.length + result.files.length > 5) {
        tell(context, 'You can attach up to 5 photos per message.');
        return;
      }
      if (result.files
          .any((file) => file.size > 5 * 1024 * 1024 || file.bytes == null)) {
        tell(context, 'Each photo must be readable and no larger than 5 MB.');
        return;
      }
      setState(() => photos.addAll(result.files));
    } catch (_) {
      if (mounted) tell(context, 'Unable to choose photos. Please try again.');
    }
  }

  Future<void> send() async {
    final body = message.text.trim();
    if (body.isEmpty && photos.isEmpty) {
      tell(context, 'Write a message or choose a photo before sending.');
      return;
    }
    setState(() => sending = true);
    try {
      final data = await ApiService().request(
          'messages/${widget.conversationId}/send',
          method: 'POST',
          body: {'body': body, 'client_token': clientToken},
          files: photos
              .map((file) => http.MultipartFile.fromBytes(
                  'photos[]', file.bytes!,
                  filename: file.name))
              .toList());
      final sent = Map<String, dynamic>.from(data['message'] as Map);
      if (!mounted) return;
      setState(() {
        if (!messages.any((known) => known['id'] == sent['id'])) {
          messages.add(sent);
        }
        message.clear();
        photos.clear();
        clientToken = messageClientToken();
      });
      scrollToBottom();
    } catch (exception) {
      if (mounted) tell(context, exception);
    } finally {
      if (mounted) setState(() => sending = false);
    }
  }

  void scrollToBottom() => WidgetsBinding.instance.addPostFrameCallback((_) {
        if (scroll.hasClients) {
          scroll.animateTo(scroll.position.maxScrollExtent,
              duration: const Duration(milliseconds: 220),
              curve: Curves.easeOut);
        }
      });

  void openContext(Map contextData) {
    if (contextData['type'] == 'product' && contextData['product_id'] is int) {
      open(context, ProductDetail(data: {'id': contextData['product_id']}));
    } else if (contextData['type'] == 'order' &&
        contextData['order_number'] != null) {
      open(context,
          OrderDetailScreen(number: contextData['order_number'].toString()));
    }
  }

  Widget contextCard(Map contextData) {
    final enabled =
        contextData['product_id'] is int || contextData['order_number'] != null;
    return InkWell(
        onTap: enabled ? () => openContext(contextData) : null,
        child: Container(
            margin: const EdgeInsets.only(bottom: 7),
            padding: const EdgeInsets.all(9),
            decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: .7),
                borderRadius: BorderRadius.circular(8)),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              if (contextData['type'] == 'product' &&
                  contextData['image'] != null)
                SizedBox(
                    width: 42,
                    height: 42,
                    child: ProductImage(url: contextData['image'])),
              if (contextData['type'] == 'product' &&
                  contextData['image'] != null)
                const SizedBox(width: 8),
              Flexible(
                  child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                    Text(
                        '${contextData['type'] == 'product' ? 'Product' : 'Order'}: ${contextData['title']}',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            color: navy, fontWeight: FontWeight.w700)),
                    if (contextData['price'] != null)
                      Text('${money(contextData['price'])} · View Product',
                          style: const TextStyle(fontSize: 12)),
                    if (contextData['status'] != null)
                      Text('${contextData['status']} · View Order',
                          style: const TextStyle(fontSize: 12))
                  ]))
            ])));
  }

  Widget attachment(dynamic attachment) {
    final resolved = ApiConfig.imageUrl(attachment['url']);
    final url = resolved != null &&
            Uri.parse(resolved).origin == Uri.parse(ApiConfig.baseUrl).origin
        ? resolved
        : null;
    final photo = url == null
        ? const Center(child: Icon(Icons.broken_image_outlined))
        : Image.network(url,
            headers: imageHeaders,
            fit: BoxFit.cover,
            errorBuilder: (_, __, ___) =>
                const Center(child: Icon(Icons.broken_image_outlined)));
    return InkWell(
        onTap: url == null
            ? null
            : () => showDialog(
                context: context,
                builder: (_) => Dialog(
                        child: Stack(children: [
                      InteractiveViewer(
                          child: Image.network(url,
                              headers: imageHeaders,
                              fit: BoxFit.contain,
                              errorBuilder: (_, __, ___) => const SizedBox(
                                  height: 240,
                                  child: Center(
                                      child:
                                          Icon(Icons.broken_image_outlined))))),
                      Positioned(
                          right: 4,
                          top: 4,
                          child: IconButton.filledTonal(
                              onPressed: () => Navigator.pop(context),
                              icon: const Icon(Icons.close)))
                    ]))),
        child: Container(
            constraints: const BoxConstraints(minHeight: 120, maxHeight: 240),
            color: const Color(0xffe5e7eb),
            child: photo));
  }

  Widget bubble(Map<String, dynamic> item) {
    final mine = item['mine'] == true;
    final attachments = item['attachments'] as List? ?? [];
    final contextData = item['context'] as Map?;
    return Align(
        alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
        child: LayoutBuilder(
            builder: (_, box) => Container(
                constraints: BoxConstraints(
                    maxWidth: math.min(box.maxWidth * .82, 460.0)),
                margin: const EdgeInsets.symmetric(vertical: 4),
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                    color: mine ? const Color(0xffccfbf1) : Colors.white,
                    border: Border.all(color: const Color(0xffe5e7eb)),
                    borderRadius: BorderRadius.circular(12)),
                child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (contextData != null) contextCard(contextData),
                      if (attachments.isNotEmpty)
                        ClipRRect(
                            borderRadius: BorderRadius.circular(8),
                            child: attachments.length == 1
                                ? attachment(attachments.first)
                                : GridView.builder(
                                    shrinkWrap: true,
                                    physics:
                                        const NeverScrollableScrollPhysics(),
                                    itemCount: attachments.length,
                                    gridDelegate:
                                        const SliverGridDelegateWithFixedCrossAxisCount(
                                            crossAxisCount: 2,
                                            crossAxisSpacing: 3,
                                            mainAxisSpacing: 3),
                                    itemBuilder: (_, index) =>
                                        attachment(attachments[index]))),
                      if ((item['body'] ?? '').toString().isNotEmpty)
                        Padding(
                            padding: EdgeInsets.only(
                                top: attachments.isNotEmpty ? 8 : 0),
                            child: Text(item['body'])),
                      const SizedBox(height: 4),
                      Align(
                          alignment: Alignment.centerRight,
                          child: Text(item['time'] ?? date(item['created_at']),
                              style: const TextStyle(
                                  fontSize: 10, color: Colors.black54)))
                    ]))));
  }

  Widget composer() => Material(
      color: Colors.white,
      elevation: 8,
      child: Padding(
          padding: EdgeInsets.fromLTRB(
              10, 8, 10, math.max(8, MediaQuery.viewPaddingOf(context).bottom)),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            if (photos.isNotEmpty)
              SizedBox(
                  height: 74,
                  child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: photos.length,
                      separatorBuilder: (_, __) => const SizedBox(width: 7),
                      itemBuilder: (_, index) => Stack(children: [
                            Container(
                                width: 74,
                                decoration: BoxDecoration(
                                    color: const Color(0xffe5e7eb),
                                    borderRadius: BorderRadius.circular(8)),
                                child: ClipRRect(
                                    borderRadius: BorderRadius.circular(8),
                                    child: Image.memory(photos[index].bytes!,
                                        fit: BoxFit.cover))),
                            Positioned(
                                right: 0,
                                child: IconButton.filled(
                                    constraints: const BoxConstraints.tightFor(
                                        width: 28, height: 28),
                                    padding: EdgeInsets.zero,
                                    onPressed: sending
                                        ? null
                                        : () => setState(
                                            () => photos.removeAt(index)),
                                    iconSize: 16,
                                    icon: const Icon(Icons.close)))
                          ]))),
            if (photos.isNotEmpty) const SizedBox(height: 7),
            Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
              IconButton(
                  tooltip: 'Add photos',
                  onPressed: sending || photos.length >= 5 ? null : pickPhotos,
                  icon: const Icon(Icons.add_photo_alternate_outlined)),
              Expanded(
                  child: TextField(
                      controller: message,
                      enabled: !sending,
                      minLines: 1,
                      maxLines: 5,
                      maxLength: 2000,
                      buildCounter: (_,
                              {required currentLength,
                              required isFocused,
                              maxLength}) =>
                          isFocused && currentLength > 1800
                              ? Text('$currentLength / $maxLength')
                              : null,
                      decoration: const InputDecoration(
                          hintText: 'Write a message…',
                          isDense: true,
                          border: OutlineInputBorder()))),
              const SizedBox(width: 6),
              IconButton.filled(
                  tooltip: 'Send',
                  onPressed: sending ? null : send,
                  icon: sending
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.send))
            ])
          ])));

  @override
  Widget build(BuildContext context) => Scaffold(
      resizeToAvoidBottomInset: true,
      appBar: AppBar(
          title: Row(children: [
        SizedBox(
            width: 36,
            height: 36,
            child: ClipOval(
                child: ProductImage(
                    url: store?['logo'],
                    fallback: const Icon(Icons.storefront, color: teal)))),
        const SizedBox(width: 10),
        Expanded(
            child: Text(store?['name'] ?? 'Seller',
                overflow: TextOverflow.ellipsis))
      ])),
      body: loading
          ? const Loading()
          : error != null
              ? ErrorState(message: error!, retry: loadInitial)
              : Column(children: [
                  Expanded(
                      child: RefreshIndicator(
                          onRefresh: refresh,
                          child: ListView(
                              controller: scroll,
                              physics: const AlwaysScrollableScrollPhysics(),
                              padding:
                                  const EdgeInsets.fromLTRB(12, 10, 12, 18),
                              children: [
                                if (hasMore)
                                  Center(
                                      child: TextButton.icon(
                                          onPressed:
                                              loadingOlder ? null : loadOlder,
                                          icon: loadingOlder
                                              ? const SizedBox(
                                                  width: 15,
                                                  height: 15,
                                                  child:
                                                      CircularProgressIndicator(
                                                          strokeWidth: 2))
                                              : const Icon(Icons.history),
                                          label: const Text(
                                              'Load older messages'))),
                                if (messages.isEmpty)
                                  const Padding(
                                      padding:
                                          EdgeInsets.symmetric(vertical: 100),
                                      child: Text('No messages yet.',
                                          textAlign: TextAlign.center)),
                                for (final item in messages) bubble(item)
                              ]))),
                  composer()
                ]));
}
