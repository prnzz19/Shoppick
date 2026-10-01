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
                  menu(Icons.location_on_outlined, 'Addresses',
                      () => open(context, const AddressesScreen())),
                  menu(Icons.favorite_outline, 'Wishlist',
                      () => openWebsite(context, '/wishlist'),
                      website: true),
                  menu(Icons.notifications_outlined, 'Notifications',
                      () => openWebsite(context, '/notifications'),
                      website: true),
                  menu(Icons.lock_outline, 'Change Password',
                      () => openWebsite(context, '/account/password'),
                      website: true),
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
          {bool website = false}) =>
      Card(
          color: Colors.white,
          child: ListTile(
              leading: Icon(icon, color: const Color(0xff0f756d)),
              title: Text(title),
              subtitle: website ? const Text('Open on SHOPPICK website') : null,
              trailing: Icon(website ? Icons.open_in_new : Icons.chevron_right),
              onTap: action));
}

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('My Profile')),
      body: DataList(
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
                  onPressed: () => openWebsite(context, '/account'),
                  child: const Text('Edit Profile on Website')),
              const Text(
                  'Profile updates and photo uploads are available on the website. Pull down to refresh after editing.'),
            ]);
          }));
}

class AddressesScreen extends StatelessWidget {
  const AddressesScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Addresses')),
      body: DataList(
          path: 'profile',
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
                              'Add a delivery address on the website before checkout.'),
                    for (final a in d['addresses'] as List) AddressCard(a),
                    FilledButton(
                        onPressed: () =>
                            openWebsite(context, '/account/addresses'),
                        child: const Text('Manage Addresses on Website')),
                    const Text(
                        'Pull down to refresh after updating your addresses.'),
                  ])));
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
                    child: const Text('Open Seller Center on Website'))
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
