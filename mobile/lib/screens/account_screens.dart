part of '../main.dart';

Future<void> openWebsite(BuildContext context, String path) async {
  try {
    final success = await launchUrl(ApiConfig.website(path),
        mode: LaunchMode.externalApplication);
    if (!success && context.mounted) {
      tell(context, 'Unable to open the website. Please try again.');
    }
  } catch (_) {
    if (context.mounted) {
      tell(context, 'Unable to open the website. Please try again.');
    }
  }
}

Future<void> signOut(BuildContext context) async {
  await ApiService().logout();
}

class NotificationBell extends StatelessWidget {
  const NotificationBell({super.key});
  @override
  Widget build(BuildContext context) => ValueListenableBuilder<int>(
      valueListenable: ApiService.notificationCount,
      builder: (_, count, __) => IconButton(
          tooltip: 'Notifications',
          onPressed: () async {
            await Navigator.push(context,
                MaterialPageRoute(builder: (_) => const NotificationsScreen()));
            ApiService().refreshNotificationCount();
          },
          icon: Stack(clipBehavior: Clip.none, children: [
            const Icon(Icons.notifications_outlined),
            if (count > 0)
              Positioned(
                  right: -7,
                  top: -7,
                  child: Container(
                      constraints:
                          const BoxConstraints(minWidth: 18, minHeight: 18),
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      decoration: BoxDecoration(
                          color: Colors.redAccent,
                          borderRadius: BorderRadius.circular(10)),
                      alignment: Alignment.center,
                      child: Text(count > 99 ? '99+' : '$count',
                          style: const TextStyle(
                              color: Colors.white,
                              fontSize: 10,
                              fontWeight: FontWeight.bold))))
          ])));
}

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});
  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<Map<String, dynamic>> items = [];
  int page = 1, lastPage = 1;
  bool loading = true, markingAll = false;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load({int? targetPage}) async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final result = await ApiService()
          .request('notifications?page=${targetPage ?? page}');
      final notifications = result['notifications'] as Map;
      if (!mounted) return;
      setState(() {
        items = (notifications['data'] as List? ?? [])
            .map((item) => Map<String, dynamic>.from(item as Map))
            .toList();
        page = (notifications['current_page'] as num?)?.toInt() ?? 1;
        lastPage = (notifications['last_page'] as num?)?.toInt() ?? 1;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> markAll() async {
    if (markingAll) return;
    setState(() => markingAll = true);
    try {
      await ApiService().request('notifications/read-all', method: 'POST');
      if (mounted) {
        setState(() {
          for (final item in items) {
            item['read_at'] ??= DateTime.now().toIso8601String();
          }
        });
      }
    } catch (e) {
      if (mounted) tell(context, e);
    } finally {
      if (mounted) setState(() => markingAll = false);
    }
  }

  Future<void> openNotification(Map<String, dynamic> item,
      {bool navigate = true}) async {
    try {
      if (item['read_at'] == null) {
        final result = await ApiService()
            .request('notifications/${item['id']}/read', method: 'POST');
        if (mounted) {
          setState(() => item['read_at'] = result['notification']['read_at']);
        }
      }
      if (!navigate || !mounted) return;
      final destination = item['destination'];
      if (destination is! Map) return;
      switch (destination['type']) {
        case 'order':
          final number = destination['order_number']?.toString();
          if (number != null && number.isNotEmpty) {
            open(context, OrderDetailScreen(number: number));
          }
          break;
        case 'seller_application':
          open(context, const SellerApplicationScreen());
          break;
        case 'account':
          open(context, const ProfileScreen());
          break;
        case 'home':
          Navigator.of(context).popUntil((route) => route.isFirst);
          break;
      }
    } catch (e) {
      if (mounted) tell(context, e);
    }
  }

  IconData notificationIcon(Map<String, dynamic> item) =>
      switch (item['icon']) {
        'package' || 'check' => Icons.inventory_2_outlined,
        'tag' => Icons.local_offer_outlined,
        'user' => Icons.person_outline,
        'message' => Icons.chat_bubble_outline,
        'x' => Icons.cancel_outlined,
        _ => item['type'] == 'buyer_order_progress'
            ? Icons.local_shipping_outlined
            : Icons.notifications_outlined,
      };

  String relativeTime(dynamic value) {
    final time = DateTime.tryParse(value?.toString() ?? '')?.toLocal();
    if (time == null) return '';
    final difference = DateTime.now().difference(time);
    if (difference.inMinutes < 1) return 'Just now';
    if (difference.inMinutes < 60) return '${difference.inMinutes} min ago';
    if (difference.inHours < 24) return '${difference.inHours} hr ago';
    if (difference.inDays < 7) {
      return '${difference.inDays} day${difference.inDays == 1 ? '' : 's'} ago';
    }
    return date(value);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Notifications'), actions: [
        TextButton(
            onPressed: markingAll || items.isEmpty ? null : markAll,
            child: Text(markingAll ? 'Marking...' : 'Mark all read'))
      ]),
      body: loading && items.isEmpty
          ? const Loading()
          : error != null && items.isEmpty
              ? ErrorState(message: error!, retry: load)
              : RefreshIndicator(
                  onRefresh: () => load(targetPage: 1),
                  child: ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.all(14),
                      children: [
                        if (items.isEmpty)
                          const EmptyState(
                              icon: Icons.notifications_none,
                              title: 'No notifications yet',
                              message:
                                  'Order and account updates will appear here.'),
                        for (final item in items)
                          Card(
                              elevation: 0,
                              color: item['read_at'] == null
                                  ? const Color(0xffeffcf9)
                                  : Colors.white,
                              shape: RoundedRectangleBorder(
                                  side: BorderSide(
                                      color: item['read_at'] == null
                                          ? const Color(0xff99e5da)
                                          : const Color(0xffe5e7eb)),
                                  borderRadius: BorderRadius.circular(12)),
                              child: InkWell(
                                  borderRadius: BorderRadius.circular(12),
                                  onTap: () => openNotification(item),
                                  child: Padding(
                                      padding: const EdgeInsets.all(14),
                                      child: Row(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            CircleAvatar(
                                                backgroundColor:
                                                    const Color(0xffdff8f3),
                                                foregroundColor:
                                                    const Color(0xff0f756d),
                                                child: Icon(
                                                    notificationIcon(item))),
                                            const SizedBox(width: 12),
                                            Expanded(
                                                child: Column(
                                                    crossAxisAlignment:
                                                        CrossAxisAlignment
                                                            .start,
                                                    children: [
                                                  Row(
                                                      crossAxisAlignment:
                                                          CrossAxisAlignment
                                                              .start,
                                                      children: [
                                                        Expanded(
                                                            child: Text(
                                                                item['title'] ??
                                                                    '',
                                                                style: TextStyle(
                                                                    fontWeight: item['read_at'] ==
                                                                            null
                                                                        ? FontWeight
                                                                            .w800
                                                                        : FontWeight
                                                                            .w600,
                                                                    color:
                                                                        navy))),
                                                        if (item['read_at'] ==
                                                            null)
                                                          const Padding(
                                                              padding: EdgeInsets
                                                                  .only(
                                                                      left: 8,
                                                                      top: 5),
                                                              child: CircleAvatar(
                                                                  radius: 4,
                                                                  backgroundColor:
                                                                      orange)),
                                                      ]),
                                                  if ((item['body'] ?? '')
                                                      .toString()
                                                      .isNotEmpty)
                                                    Padding(
                                                        padding:
                                                            const EdgeInsets
                                                                .only(top: 3),
                                                        child: Text(
                                                            item['body'],
                                                            maxLines: 3,
                                                            overflow:
                                                                TextOverflow
                                                                    .ellipsis)),
                                                  const SizedBox(height: 5),
                                                  Row(children: [
                                                    Expanded(
                                                        child: Text(
                                                            relativeTime(item[
                                                                'created_at']),
                                                            style: const TextStyle(
                                                                fontSize: 12,
                                                                color: Colors
                                                                    .black54))),
                                                    if (item['read_at'] == null)
                                                      TextButton(
                                                          onPressed: () =>
                                                              openNotification(
                                                                  item,
                                                                  navigate:
                                                                      false),
                                                          child: const Text(
                                                              'Mark read')),
                                                  ])
                                                ]))
                                          ])))),
                        if (error != null)
                          Padding(
                              padding: const EdgeInsets.all(8),
                              child: Text(error!,
                                  style: const TextStyle(color: Colors.red))),
                        if (lastPage > 1)
                          Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                TextButton(
                                    onPressed: page > 1 && !loading
                                        ? () => load(targetPage: page - 1)
                                        : null,
                                    child: const Text('Previous')),
                                Text('$page / $lastPage'),
                                TextButton(
                                    onPressed: page < lastPage && !loading
                                        ? () => load(targetPage: page + 1)
                                        : null,
                                    child: const Text('Next')),
                              ]),
                        if (loading && items.isNotEmpty)
                          const LinearProgressIndicator(),
                      ])));
}

class AccountTab extends StatefulWidget {
  const AccountTab({super.key});
  @override
  State<AccountTab> createState() => _AccountTabState();
}

class _AccountTabState extends State<AccountTab> {
  final dataKey = GlobalKey<DataListState>();
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Buyer Account'), actions: [
        IconButton(
            tooltip: 'Sign out',
            onPressed: () => signOut(context),
            icon: const Icon(Icons.logout))
      ]),
      body: DataList(
          key: dataKey,
          path: 'profile',
          title: 'Account',
          builder: (d) {
            final u = d['user'];
            final status = u['seller_application']?['status'];
            return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(18),
                children: [
                  Card(
                      color: const Color(0xffeffcf9),
                      child: Padding(
                          padding: const EdgeInsets.all(20),
                          child: Column(children: [
                            SizedBox(
                                width: 76,
                                height: 76,
                                child: ClipOval(
                                    child: ProductImage(
                                        url: u['avatar'],
                                        fallback: Text(
                                            (u['name'] as String? ?? 'S')
                                                .characters
                                                .first,
                                            style: const TextStyle(
                                                fontSize: 32, color: teal))))),
                            const SizedBox(height: 12),
                            Text(u['name'] ?? 'SHOPPICK Buyer',
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                    fontSize: 22,
                                    fontWeight: FontWeight.bold,
                                    color: navy)),
                            Text(u['email'] ?? '', textAlign: TextAlign.center),
                            const SizedBox(height: 8),
                            const Text(
                                'Your picks. Your shops. Your SHOPPICK.'),
                          ]))),
                  const SectionTitle('Your account'),
                  menu(Icons.person_outline, 'My Profile',
                      () => open(context, const ProfileScreen())),
                  menu(Icons.receipt_long_outlined, 'My Orders',
                      () => open(context, const OrdersTab())),
                  ValueListenableBuilder<int>(
                      valueListenable: ApiService.messageCount,
                      builder: (_, count, __) => menu(
                          Icons.chat_bubble_outline,
                          'Messages',
                          () => open(context, const MessagesScreen()),
                          badge: count)),
                  menu(Icons.location_on_outlined, 'Addresses',
                      () => open(context, const AddressesScreen())),
                  menu(Icons.favorite_outline, 'Wishlist',
                      () => open(context, const WishlistScreen())),
                  menu(Icons.lock_outline, 'Change Password',
                      () => open(context, const ChangePasswordScreen())),
                  const SectionTitle('Sell with SHOPPICK'),
                  Card(
                      color: Colors.white,
                      child: ListTile(
                          leading: const Icon(Icons.storefront, color: teal),
                          title: Text(status == null && u['is_seller'] != true
                              ? 'Become a Seller'
                              : 'Seller Status'),
                          subtitle: Text(status == null
                              ? 'Apply with your existing Buyer account'
                              : label(status)),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () async {
                            await Navigator.push(
                                context,
                                MaterialPageRoute(
                                    builder: (_) =>
                                        const SellerApplicationScreen()));
                            dataKey.currentState?.reload();
                          })),
                  const SizedBox(height: 20),
                  OutlinedButton.icon(
                      onPressed: () => signOut(context),
                      icon: const Icon(Icons.logout),
                      label: const Text('Logout')),
                  const Text(
                      'Website options open in your browser. Sign in with the same SHOPPICK account.',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 12, color: Colors.black54)),
                ]);
          }));
  Widget menu(IconData icon, String title, VoidCallback action,
          {bool website = false, int badge = 0}) =>
      Card(
          color: Colors.white,
          child: ListTile(
              leading: Icon(icon, color: const Color(0xff0f756d)),
              title: Text(title),
              subtitle: website ? const Text('Open on SHOPPICK website') : null,
              trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                if (badge > 0) Badge(label: Text('$badge')),
                Icon(website ? Icons.open_in_new : Icons.chevron_right)
              ]),
              onTap: action));
}

class WishlistScreen extends StatefulWidget {
  const WishlistScreen({super.key});
  @override
  State<WishlistScreen> createState() => _WishlistScreenState();
}

class _WishlistScreenState extends State<WishlistScreen> {
  final dataKey = GlobalKey<DataListState>();
  int? busyItem;

  Future<void> addToCart(dynamic item) async {
    final product = item['product'];
    final variants = product['variants'] as List? ?? [];
    if (variants.isNotEmpty) {
      tell(context, 'Choose a product option before adding it to your cart.');
      await open(context, ProductDetail(data: product));
      return;
    }
    setState(() => busyItem = item['id'] as int?);
    try {
      await ApiService().request('cart', method: 'POST', body: {
        'product_id': product['id'],
        'quantity': 1,
      });
      if (mounted) tell(context, 'Product added to cart.');
    } catch (error) {
      if (mounted) tell(context, error);
    } finally {
      if (mounted) setState(() => busyItem = null);
    }
  }

  Future<void> remove(dynamic item) async {
    setState(() => busyItem = item['id'] as int?);
    try {
      await ApiService().request('wishlist/${item['id']}', method: 'DELETE');
      await dataKey.currentState?.reload();
    } catch (error) {
      if (mounted) tell(context, error);
    } finally {
      if (mounted) setState(() => busyItem = null);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Wishlist')),
      body: DataList(
          key: dataKey,
          path: 'wishlist',
          title: 'Wishlist',
          builder: (d) {
            final items = (d['items'] as List? ?? []);
            if (items.isEmpty) {
              return const EmptyState(
                  icon: Icons.favorite_border,
                  title: 'Your wishlist is empty',
                  message: 'Save products you want to revisit.');
            }
            return ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: items.length,
                itemBuilder: (_, i) {
                  final item = items[i];
                  final product = item['product'];
                  final busy = busyItem == item['id'];
                  return Card(
                      color: Colors.white,
                      margin: const EdgeInsets.only(bottom: 12),
                      child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                InkWell(
                                    onTap: () => open(
                                        context, ProductDetail(data: product)),
                                    child: SizedBox(
                                        width: 88,
                                        height: 104,
                                        child: ProductImage(
                                            url: product['image']))),
                                const SizedBox(width: 12),
                                Expanded(
                                    child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                      Text(product['name'] ?? 'Product',
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(
                                              fontWeight: FontWeight.w700)),
                                      Text(product['shop'] ?? 'SHOPPICK shop',
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis),
                                      Price(data: product),
                                      const SizedBox(height: 8),
                                      Wrap(
                                          spacing: 8,
                                          runSpacing: 6,
                                          children: [
                                            FilledButton.icon(
                                                onPressed: busy
                                                    ? null
                                                    : () => addToCart(item),
                                                icon: const Icon(
                                                    Icons.add_shopping_cart,
                                                    size: 18),
                                                label: Text(busy
                                                    ? 'Adding...'
                                                    : 'Add to Cart')),
                                            IconButton.outlined(
                                                tooltip: 'Remove from wishlist',
                                                onPressed: busy
                                                    ? null
                                                    : () => remove(item),
                                                icon: const Icon(
                                                    Icons.delete_outline))
                                          ])
                                    ]))
                              ])));
                });
          }));
}

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final dataKey = GlobalKey<DataListState>();
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('My Profile')),
      body: DataList(
          key: dataKey,
          path: 'profile',
          title: 'Profile',
          builder: (d) {
            final u = d['user'];
            return ListView(padding: const EdgeInsets.all(20), children: [
              Center(
                  child: SizedBox(
                      width: 88,
                      height: 88,
                      child: ClipOval(
                          child: ProductImage(
                              url: u['avatar'],
                              fallback: const Icon(Icons.person_outline,
                                  size: 44, color: teal))))),
              const SectionTitle('Personal information'),
              SummaryRow('Name', u['name'] ?? ''),
              SummaryRow('Email', u['email'] ?? ''),
              SummaryRow('Phone', u['phone'] ?? 'Not provided'),
              SummaryRow('Member since', date(u['created_at'])),
              const SizedBox(height: 20),
              FilledButton(
                  onPressed: () async {
                    final changed = await Navigator.push<bool>(
                        context,
                        MaterialPageRoute(
                            builder: (_) => EditProfileScreen(user: u)));
                    if (changed == true) dataKey.currentState?.reload();
                  },
                  child: const Text('Edit Profile')),
            ]);
          }));
}

class EditProfileScreen extends StatefulWidget {
  final dynamic user;
  const EditProfileScreen({super.key, required this.user});
  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  late final name = TextEditingController(text: widget.user['name'] ?? '');
  late final email = TextEditingController(text: widget.user['email'] ?? '');
  late final phone = TextEditingController(text: widget.user['phone'] ?? '');
  String? avatarPath, error;
  bool busy = false;
  @override
  void dispose() {
    name.dispose();
    email.dispose();
    phone.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (busy) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ApiService().updateProfile({
        'name': name.text.trim(),
        'email': email.text.trim(),
        'phone': phone.text.trim()
      }, avatarPath: avatarPath);
      if (mounted) {
        tell(context, 'Profile updated.');
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Edit Profile')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        const Text('Personal information',
            style: TextStyle(
                fontSize: 20, fontWeight: FontWeight.bold, color: navy)),
        const SizedBox(height: 16),
        TextField(
            controller: name,
            textInputAction: TextInputAction.next,
            decoration: const InputDecoration(
                labelText: 'Full Name', border: OutlineInputBorder())),
        const SizedBox(height: 12),
        TextField(
            controller: email,
            keyboardType: TextInputType.emailAddress,
            textInputAction: TextInputAction.next,
            decoration: const InputDecoration(
                labelText: 'Email', border: OutlineInputBorder())),
        const SizedBox(height: 12),
        TextField(
            controller: phone,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(
                labelText: 'Phone', border: OutlineInputBorder())),
        const SizedBox(height: 12),
        OutlinedButton.icon(
            onPressed: busy
                ? null
                : () async {
                    final result = await FilePicker.platform
                        .pickFiles(type: FileType.image);
                    if (result?.files.single.path != null) {
                      setState(() => avatarPath = result!.files.single.path);
                    }
                  },
            icon: const Icon(Icons.photo_camera_outlined),
            label: Text(avatarPath == null
                ? 'Choose Profile Photo'
                : 'Photo selected')),
        const Text('JPG, PNG, GIF, or WEBP up to 2 MB.',
            style: TextStyle(fontSize: 12, color: Colors.black54)),
        if (error != null)
          Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(error!, style: const TextStyle(color: Colors.red))),
        const SizedBox(height: 18),
        FilledButton(
            onPressed: busy ? null : save,
            child: Text(busy ? 'Saving...' : 'Save Changes')),
      ]));
}

class AddressesScreen extends StatefulWidget {
  const AddressesScreen({super.key});
  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  final dataKey = GlobalKey<DataListState>();
  Future<void> form([dynamic address]) async {
    final changed = await Navigator.push<bool>(context,
        MaterialPageRoute(builder: (_) => AddressFormScreen(address: address)));
    if (changed == true) dataKey.currentState?.reload();
  }

  Future<void> action(String path, String method, String success) async {
    try {
      await ApiService().request(path, method: method);
      if (mounted) tell(context, success);
      dataKey.currentState?.reload();
    } catch (e) {
      if (mounted) tell(context, e);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Addresses'), actions: [
        IconButton(
            onPressed: () => form(),
            tooltip: 'Add address',
            icon: const Icon(Icons.add))
      ]),
      body: DataList(
          key: dataKey,
          path: 'addresses',
          title: 'Addresses',
          builder: (d) => ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(16),
                  children: [
                    if ((d['addresses'] as List).isEmpty)
                      const EmptyState(
                          icon: Icons.location_on_outlined,
                          title: 'No addresses yet',
                          message:
                              'Add a delivery address to make checkout quicker.'),
                    for (final a in d['addresses'] as List)
                      Card(
                          color: Colors.white,
                          child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.stretch,
                                  children: [
                                    AddressCard(a),
                                    Wrap(spacing: 8, runSpacing: 8, children: [
                                      TextButton.icon(
                                          onPressed: () => form(a),
                                          icon: const Icon(Icons.edit_outlined),
                                          label: const Text('Edit')),
                                      if (a['is_default'] != true &&
                                          a['is_default'] != 1)
                                        TextButton.icon(
                                            onPressed: () => action(
                                                'addresses/${a['id']}/default',
                                                'POST',
                                                'Default address updated.'),
                                            icon: const Icon(
                                                Icons.check_circle_outline),
                                            label: const Text('Set Default')),
                                      TextButton.icon(
                                          onPressed: () async {
                                            final yes = await showDialog<bool>(
                                                context: context,
                                                builder: (_) => AlertDialog(
                                                        title: const Text(
                                                            'Delete address?'),
                                                        content: const Text(
                                                            'This address will no longer be available at checkout.'),
                                                        actions: [
                                                          TextButton(
                                                              onPressed: () =>
                                                                  Navigator.pop(
                                                                      context,
                                                                      false),
                                                              child: const Text(
                                                                  'Cancel')),
                                                          FilledButton(
                                                              onPressed: () =>
                                                                  Navigator.pop(
                                                                      context,
                                                                      true),
                                                              child: const Text(
                                                                  'Delete'))
                                                        ]));
                                            if (yes == true) {
                                              action('addresses/${a['id']}',
                                                  'DELETE', 'Address deleted.');
                                            }
                                          },
                                          icon:
                                              const Icon(Icons.delete_outline),
                                          label: const Text('Delete')),
                                    ])
                                  ]))),
                    const SizedBox(height: 10),
                    FilledButton.icon(
                        onPressed: () => form(),
                        icon: const Icon(Icons.add),
                        label: const Text('Add Address')),
                  ])));
}

class AddressFormScreen extends StatefulWidget {
  final dynamic address;
  final bool selectOnly;
  const AddressFormScreen({super.key, this.address, this.selectOnly = false});
  @override
  State<AddressFormScreen> createState() => _AddressFormScreenState();
}

class _AddressFormScreenState extends State<AddressFormScreen> {
  late final fields = <String, TextEditingController>{
    for (final key in [
      'full_name',
      'phone',
      'label',
      'postal_code',
      'address_line'
    ])
      key: TextEditingController(text: widget.address?[key]?.toString() ?? '')
  };
  List regions = [], provinces = [], cities = [], barangays = [];
  String? regionCode, provinceCode, cityCode, barangayCode, error;
  bool busy = false, loadingLocations = true, isDefault = false;
  @override
  void initState() {
    super.initState();
    isDefault = widget.address?['is_default'] == true ||
        widget.address?['is_default'] == 1;
    loadInitial();
  }

  @override
  void dispose() {
    for (final c in fields.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<List> locations(String path) async {
    final result = await ApiService().request(path);
    return result['data'] as List? ?? [];
  }

  Future<void> loadInitial() async {
    try {
      regions = await locations('locations/regions');
      regionCode = locationCode(
          regions, widget.address?['region_code'], widget.address?['region']);
      if (regionCode != null) {
        provinces = await locations('locations/regions/$regionCode/provinces');
        provinceCode = locationCode(provinces, widget.address?['province_code'],
            widget.address?['province']);
        cities = await locations(
            provinceCode != null && provinceCode!.isNotEmpty
                ? 'locations/provinces/$provinceCode/cities'
                : 'locations/regions/$regionCode/cities');
        cityCode = locationCode(
            cities, widget.address?['city_code'], widget.address?['city']);
        if (cityCode != null) {
          barangays = await locations('locations/cities/$cityCode/barangays');
          barangayCode = locationCode(barangays,
              widget.address?['barangay_code'], widget.address?['barangay']);
        }
      }
    } catch (e) {
      error = e.toString();
    }
    if (mounted) setState(() => loadingLocations = false);
  }

  dynamic record(List values, String? code) {
    for (final value in values) {
      if (value['code'] == code) return value;
    }
    return null;
  }

  String? locationCode(List values, dynamic existing, dynamic name) {
    final code = existing?.toString();
    if (code != null && values.any((value) => value['code'] == code)) {
      return code;
    }
    final wanted = name?.toString().trim().toLowerCase();
    if (wanted == null || wanted.isEmpty) return null;
    for (final value in values) {
      if (value['name']?.toString().trim().toLowerCase() == wanted) {
        return value['code']?.toString();
      }
    }
    return null;
  }

  Future<void> chooseRegion(String? value) async {
    setState(() {
      regionCode = value;
      provinceCode = cityCode = barangayCode = null;
      provinces = cities = barangays = [];
    });
    if (value == null) return;
    setState(() => loadingLocations = true);
    try {
      provinces = await locations('locations/regions/$value/provinces');
      if (provinces.isEmpty) {
        cities = await locations('locations/regions/$value/cities');
      }
    } catch (e) {
      error = e.toString();
    }
    if (mounted) setState(() => loadingLocations = false);
  }

  Future<void> chooseProvince(String? value) async {
    setState(() {
      provinceCode = value;
      cityCode = barangayCode = null;
      cities = barangays = [];
      loadingLocations = value != null;
    });
    if (value == null) return;
    try {
      cities = await locations('locations/provinces/$value/cities');
    } catch (e) {
      error = e.toString();
    }
    if (mounted) setState(() => loadingLocations = false);
  }

  Future<void> chooseCity(String? value) async {
    setState(() {
      cityCode = value;
      barangayCode = null;
      barangays = [];
      loadingLocations = value != null;
    });
    if (value == null) return;
    try {
      barangays = await locations('locations/cities/$value/barangays');
    } catch (e) {
      error = e.toString();
    }
    if (mounted) setState(() => loadingLocations = false);
  }

  Future<void> save() async {
    if (busy ||
        regionCode == null ||
        cityCode == null ||
        barangayCode == null) {
      setState(() => error = 'Select a complete Philippine address.');
      return;
    }
    final region = record(regions, regionCode),
        province = record(provinces, provinceCode),
        city = record(cities, cityCode),
        barangay = record(barangays, barangayCode);
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final body = <String, dynamic>{
        for (final e in fields.entries) e.key: e.value.text.trim(),
        'region': region?['name'],
        'region_code': regionCode,
        'province': province?['name'] ?? '',
        'province_code': provinceCode,
        'city': city?['name'],
        'city_code': cityCode,
        'barangay': barangay?['name'],
        'barangay_code': barangayCode,
        'is_default': isDefault
      };
      if (widget.selectOnly) {
        if (mounted) Navigator.pop(context, body);
        return;
      }
      final id = widget.address?['id'];
      await ApiService().request(id == null ? 'addresses' : 'addresses/$id',
          method: id == null ? 'POST' : 'PUT', body: body);
      if (mounted) {
        tell(
            context,
            id == null
                ? 'Address added successfully.'
                : 'Address updated successfully.');
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Widget dropdown(String label, String? value, List items,
          ValueChanged<String?> changed) =>
      DropdownButtonFormField<String>(
          isExpanded: true,
          initialValue: items.any((x) => x['code'] == value) ? value : null,
          decoration: InputDecoration(
              labelText: label, border: const OutlineInputBorder()),
          items: items
              .map<DropdownMenuItem<String>>((x) => DropdownMenuItem(
                  value: x['code'],
                  child: Text(x['name'], overflow: TextOverflow.ellipsis)))
              .toList(),
          onChanged: busy || loadingLocations ? null : changed);
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(
          title: Text(widget.address == null ? 'Add Address' : 'Edit Address')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        TextField(
            controller: fields['full_name'],
            decoration: const InputDecoration(
                labelText: 'Full Name', border: OutlineInputBorder())),
        const SizedBox(height: 12),
        TextField(
            controller: fields['phone'],
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(
                labelText: 'Phone', border: OutlineInputBorder())),
        const SizedBox(height: 12),
        TextField(
            controller: fields['label'],
            decoration: const InputDecoration(
                labelText: 'Label (Home, Office)',
                border: OutlineInputBorder())),
        const SizedBox(height: 12),
        dropdown('Region', regionCode, regions, chooseRegion),
        const SizedBox(height: 12),
        if (provinces.isNotEmpty) ...[
          dropdown('Province', provinceCode, provinces, chooseProvince),
          const SizedBox(height: 12)
        ],
        dropdown('City / Municipality', cityCode, cities, chooseCity),
        const SizedBox(height: 12),
        dropdown('Barangay', barangayCode, barangays,
            (v) => setState(() => barangayCode = v)),
        const SizedBox(height: 12),
        TextField(
            controller: fields['postal_code'],
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
                labelText: 'Postal Code', border: OutlineInputBorder())),
        const SizedBox(height: 12),
        TextField(
            controller: fields['address_line'],
            maxLines: 2,
            decoration: const InputDecoration(
                labelText: 'House No. / Street', border: OutlineInputBorder())),
        SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: isDefault,
            onChanged: busy ? null : (v) => setState(() => isDefault = v),
            title: const Text('Set as default address')),
        if (loadingLocations) const LinearProgressIndicator(),
        if (error != null)
          Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Text(error!, style: const TextStyle(color: Colors.red))),
        const SizedBox(height: 16),
        FilledButton(
            onPressed: busy || loadingLocations ? null : save,
            child: Text(busy ? 'Saving...' : 'Save Address')),
      ]));
}

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});
  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final current = TextEditingController(),
      password = TextEditingController(),
      confirmation = TextEditingController();
  bool busy = false;
  String? error;
  @override
  void dispose() {
    current.dispose();
    password.dispose();
    confirmation.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (busy) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ApiService().request('password/change', method: 'POST', body: {
        'current_password': current.text,
        'password': password.text,
        'password_confirmation': confirmation.text
      });
      if (mounted) {
        tell(context, 'Password changed successfully.');
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Widget passwordField(
          TextEditingController controller, String label) =>
      TextField(
          controller: controller,
          obscureText: true,
          decoration: InputDecoration(
              labelText: label, border: const OutlineInputBorder()));
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Change Password')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        passwordField(current, 'Current Password'),
        const SizedBox(height: 12),
        passwordField(password, 'New Password'),
        const SizedBox(height: 12),
        passwordField(confirmation, 'Confirm New Password'),
        if (error != null)
          Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(error!, style: const TextStyle(color: Colors.red))),
        const SizedBox(height: 18),
        FilledButton(
            onPressed: busy ? null : save,
            child: Text(busy ? 'Changing...' : 'Change Password')),
      ]));
}

class SellerApplicationScreen extends StatefulWidget {
  const SellerApplicationScreen({super.key});
  @override
  State<SellerApplicationScreen> createState() =>
      _SellerApplicationScreenState();
}

class _SellerApplicationScreenState extends State<SellerApplicationScreen> {
  final dataKey = GlobalKey<DataListState>();
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(
          title: const ShopPickBrand(
              compact: true, subtitle: 'Seller Application')),
      body: DataList(
          key: dataKey,
          path: 'seller/application',
          title: 'Seller Application',
          builder: (d) {
            final a = d['application'];
            final status = a?['status'];
            final canApply = d['is_seller'] != true &&
                (a == null ||
                    ['rejected', 'needs_resubmission'].contains(status));
            return ListView(padding: const EdgeInsets.all(18), children: [
              const Icon(Icons.storefront_outlined, size: 58, color: teal),
              SectionTitle(d['seller_access'] == true
                  ? 'Your store is approved'
                  : a == null && d['is_seller'] == true
                      ? 'Seller access unavailable'
                      : a == null
                          ? 'Become a SHOPPICK Seller'
                          : status == 'approved'
                              ? 'Your store is approved'
                              : status == 'needs_resubmission'
                                  ? 'Your application needs attention'
                                  : status == 'rejected'
                                      ? 'Application not approved'
                                      : 'Pending Admin review'),
              const Text(
                  'Your Buyer account stays available during review and after approval.'),
              Text(d['seller_access'] == true
                  ? 'Your shop is ready to manage in Seller Center.'
                  : status == 'needs_resubmission'
                      ? 'Review the feedback below, update your information, and attach your documents again.'
                      : status == 'rejected'
                          ? 'Review the reason below. You may update and resubmit your application.'
                          : canApply
                              ? 'Complete your shop information and attach your Valid ID and Business Permit.'
                              : status != 'approved' && d['is_seller'] != true
                                  ? 'Your application is under review. You do not need to submit another application.'
                                  : 'Approval alone does not enable Seller Center; your shop must also be active.'),
              if (a != null) ...[
                const SizedBox(height: 16),
                if (a['logo'] != null)
                  SizedBox(height: 96, child: ProductImage(url: a['logo'])),
                Text(a['store_name'] ?? '',
                    style: const TextStyle(fontWeight: FontWeight.bold)),
                Align(
                    alignment: Alignment.centerLeft,
                    child: StatusBadge(status)),
                Text('Submitted ${date(a['created_at'])}'),
                if (a['review_notes'] != null)
                  Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text(a['review_notes']))
              ],
              if (d['seller_access'] == true) ...[
                const SizedBox(height: 20),
                const Text(
                    'Manage your shop in the SHOPPICK website Seller Center.'),
                FilledButton(
                    onPressed: () => openWebsite(context, '/seller/dashboard'),
                    child: const Text('Open Seller Center on Website')),
                OutlinedButton.icon(
                    onPressed: () => open(context, const SellerOrdersScreen()),
                    icon: const Icon(Icons.local_shipping_outlined),
                    label: const Text('Outgoing Orders / Track Shipments'))
              ],
              if ((d['is_seller'] == true || status == 'approved') &&
                  d['seller_access'] != true)
                const Text(
                    'Seller access is currently unavailable. Your Buyer account remains available. Please check your shop status on the website.'),
              if (canApply)
                SellerForm(
                    application: a,
                    categories: d['categories'] as List,
                    onSubmitted: () => dataKey.currentState?.reload()),
            ]);
          }));
}

class SellerForm extends StatefulWidget {
  final dynamic application;
  final List categories;
  final VoidCallback onSubmitted;
  const SellerForm(
      {super.key,
      this.application,
      required this.categories,
      required this.onSubmitted});
  @override
  State<SellerForm> createState() => _SellerFormState();
}

class _SellerFormState extends State<SellerForm> {
  final formKey = GlobalKey<FormState>();
  final fields = <String, TextEditingController>{};
  final files = <String, PlatformFile>{};
  int? category;
  bool busy = false;
  String? error;
  @override
  void initState() {
    super.initState();
    for (final key in [
      'store_name',
      'store_description',
      'phone',
      'address',
      'business_information'
    ]) {
      fields[key] = TextEditingController(
          text: widget.application?[key]?.toString() ?? '');
    }
    category = widget.application?['category_id'];
    if (!widget.categories.any((c) => c['id'] == category)) category = null;
  }

  @override
  void dispose() {
    for (final c in fields.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> pick(String field) async {
    try {
      final result = await FilePicker.platform.pickFiles(
          type: FileType.custom,
          allowedExtensions: field == 'logo'
              ? ['jpg', 'jpeg', 'png', 'webp']
              : ['jpg', 'jpeg', 'png', 'pdf'],
          withData: true);
      if (!mounted || result == null) return;
      final file = result.files.single;
      final limit = field == 'logo' ? 2 : 5;
      if (file.size > limit * 1024 * 1024) {
        tell(context, 'Choose a file under $limit MB.');
        return;
      }
      if (file.bytes == null) {
        tell(context, 'Unable to read this file. Please choose another.');
        return;
      }
      setState(() => files[field] = file);
    } catch (_) {
      if (mounted) tell(context, 'Unable to open this file. Please try again.');
    }
  }

  Future<void> submit() async {
    if (!formKey.currentState!.validate()) return;
    if (!files.containsKey('valid_id') ||
        !files.containsKey('business_permit')) {
      setState(
          () => error = 'Please attach your Valid ID and Business Permit.');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ApiService().request('seller/application',
          method: 'POST',
          body: {
            ...fields.map((k, v) => MapEntry(k, v.text.trim())),
            'category_id': category
          },
          files: files.entries
              .map((e) => http.MultipartFile.fromBytes(e.key, e.value.bytes!,
                  filename: e.value.name))
              .toList());
      if (!mounted) return;
      tell(context, 'Seller application submitted for review.');
      widget.onSubmitted();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Form(
      key: formKey,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const SectionTitle('Shop information'),
        field('store_name', 'Business / Store Name', max: 120),
        DropdownButtonFormField<int>(
            initialValue: category,
            isExpanded: true,
            decoration: const InputDecoration(
                labelText: 'Line of Business *', border: OutlineInputBorder()),
            items: widget.categories
                .map<DropdownMenuItem<int>>((c) => DropdownMenuItem(
                    value: c['id'],
                    child: Text(c['name'], overflow: TextOverflow.ellipsis)))
                .toList(),
            validator: (v) => v == null ? 'Choose a category' : null,
            onChanged: busy ? null : (v) => setState(() => category = v)),
        const SizedBox(height: 12),
        field('store_description', 'Description (optional)',
            max: 2000, lines: 3, required: false),
        field('phone', 'Contact Number', max: 30),
        field('address', 'Business Address', max: 1000, lines: 3),
        field('business_information', 'Business Information (optional)',
            max: 2000, required: false),
        const SectionTitle('Documents'),
        const Text(
            'Valid ID and Business Permit: JPG, PNG or PDF, up to 5 MB each. Documents are stored privately for Admin review. Shop Logo is optional (up to 2 MB).'),
        for (final entry in {
          'valid_id': 'Valid ID *',
          'business_permit': 'Business Permit *',
          'logo': 'Shop Logo (Optional)'
        }.entries)
          Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: OutlinedButton.icon(
                  onPressed: busy ? null : () => pick(entry.key),
                  icon: const Icon(Icons.attach_file),
                  label: Text(files[entry.key]?.name ?? entry.value,
                      overflow: TextOverflow.ellipsis))),
        if (files.containsKey('logo'))
          SizedBox(
              height: 96,
              child: Image.memory(files['logo']!.bytes!,
                  fit: BoxFit.contain,
                  errorBuilder: (_, error, stack) =>
                      const Center(child: Icon(Icons.storefront)))),
        if (files.containsKey('logo'))
          TextButton(
              onPressed:
                  busy ? null : () => setState(() => files.remove('logo')),
              child: const Text('Remove optional logo')),
        if (error != null)
          Padding(
              padding: const EdgeInsets.all(12),
              child: Text(error!, style: const TextStyle(color: Colors.red))),
        FilledButton(
            onPressed: busy ? null : submit,
            child: Text(busy
                ? 'Submitting...'
                : widget.application == null
                    ? 'Submit Application'
                    : 'Resubmit Application')),
      ]));
  Widget field(String key, String title,
          {int max = 255, int lines = 1, bool required = true}) =>
      Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: TextFormField(
              controller: fields[key],
              enabled: !busy,
              maxLength: max,
              maxLines: lines,
              keyboardType:
                  key == 'phone' ? TextInputType.phone : TextInputType.text,
              decoration: InputDecoration(
                  labelText: '$title${required ? ' *' : ''}',
                  border: const OutlineInputBorder(),
                  counterText: ''),
              validator: (v) => required && (v?.trim().isEmpty ?? true)
                  ? 'Enter $title'
                  : null));
}
