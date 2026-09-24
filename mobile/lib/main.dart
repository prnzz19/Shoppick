import 'package:flutter/material.dart';
import 'services/api_service.dart';

const teal = Color(0xff078b83),
    orange = Color(0xfff28b35),
    navy = Color(0xff132b3a);
void main() => runApp(const ShoppickApp());

class ShoppickApp extends StatelessWidget {
  const ShoppickApp({super.key});
  @override
  Widget build(BuildContext context) => MaterialApp(
      title: 'SHOPPICK',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(seedColor: teal),
          scaffoldBackgroundColor: const Color(0xfff7f8fa),
          appBarTheme: const AppBarTheme(
              backgroundColor: Colors.white,
              foregroundColor: navy,
              elevation: 0),
          useMaterial3: true),
      home: const SplashScreen());
}

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});
  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    Future.delayed(const Duration(milliseconds: 800), () async {
      final token = await ApiService().token();
      if (mounted)
        Navigator.pushReplacement(
            context,
            MaterialPageRoute(
                builder: (_) => token == null
                    ? const LoginScreen()
                    : const MarketplaceScreen()));
    });
  }

  @override
  Widget build(BuildContext context) =>
      const Scaffold(body: Center(child: Brand()));
}

class Brand extends StatelessWidget {
  const Brand({super.key});
  @override
  Widget build(BuildContext context) => RichText(
          text: const TextSpan(
              style: TextStyle(
                  fontSize: 32,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 1.2),
              children: [
            TextSpan(text: 'SHOP', style: TextStyle(color: teal)),
            TextSpan(text: 'PICK', style: TextStyle(color: orange))
          ]));
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final email = TextEditingController();
  final password = TextEditingController();
  bool busy = false;
  String? error;

  Future<void> login() async {
    setState(() => busy = true);
    try {
      final data = await ApiService().request(
        'login',
        method: 'POST',
        body: {'email': email.text.trim(), 'password': password.text},
      );
      await ApiService().saveToken(data['token']);
      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const MarketplaceScreen()),
        );
      }
    } catch (e) {
      setState(() => error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Center(child: Brand()),
                  const SizedBox(height: 36),
                  const Text('Welcome back',
                      style: TextStyle(
                          color: navy,
                          fontSize: 26,
                          fontWeight: FontWeight.bold)),
                  const SizedBox(height: 18),
                  TextField(
                      controller: email,
                      keyboardType: TextInputType.emailAddress,
                      decoration: const InputDecoration(
                          labelText: 'Email', border: OutlineInputBorder())),
                  const SizedBox(height: 12),
                  TextField(
                      controller: password,
                      obscureText: true,
                      decoration: const InputDecoration(
                          labelText: 'Password', border: OutlineInputBorder())),
                  if (error != null)
                    Padding(
                        padding: const EdgeInsets.only(top: 12),
                        child: Text(error!,
                            style: const TextStyle(color: Colors.red))),
                  const SizedBox(height: 20),
                  FilledButton(
                      onPressed: busy ? null : login,
                      child: Text(busy ? 'Signing in…' : 'Sign in')),
                  const SizedBox(height: 12),
                  const Text(
                      'New buyer accounts require administrator approval. Register on the SHOPPICK website.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.black54)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class MarketplaceScreen extends StatefulWidget {
  const MarketplaceScreen({super.key});
  @override
  State<MarketplaceScreen> createState() => _MarketplaceScreenState();
}

class _MarketplaceScreenState extends State<MarketplaceScreen> {
  int tab = 0;
  final api = ApiService();
  @override
  Widget build(BuildContext context) {
    final pages = [
      const HomeTab(),
      const CategoriesTab(),
      const CartTab(),
      const OrdersTab(),
      const AccountTab()
    ];
    return Scaffold(
        body: SafeArea(child: pages[tab]),
        bottomNavigationBar: NavigationBar(
            selectedIndex: tab,
            onDestinationSelected: (i) => setState(() => tab = i),
            destinations: const [
              NavigationDestination(
                  icon: Icon(Icons.home_outlined), label: 'Home'),
              NavigationDestination(
                  icon: Icon(Icons.grid_view_outlined), label: 'Shop'),
              NavigationDestination(
                  icon: Icon(Icons.shopping_cart_outlined), label: 'Cart'),
              NavigationDestination(
                  icon: Icon(Icons.receipt_long_outlined), label: 'Orders'),
              NavigationDestination(
                  icon: Icon(Icons.person_outline), label: 'Account')
            ]));
  }
}

class HomeTab extends StatefulWidget {
  const HomeTab({super.key});
  @override
  State<HomeTab> createState() => _HomeTabState();
}

class _HomeTabState extends State<HomeTab> {
  final api = ApiService();
  final search = TextEditingController();
  Future<Map<String, dynamic>>? data;
  @override
  void initState() {
    super.initState();
    data = api.request('home').then((v) => Map<String, dynamic>.from(v));
  }

  void find() {
    Navigator.push(context,
        MaterialPageRoute(builder: (_) => ProductsScreen(query: search.text)));
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>>(
      future: data,
      builder: (c, s) {
        if (s.connectionState == ConnectionState.waiting)
          return const Loading();
        if (s.hasError)
          return ErrorState(
              message: s.error.toString(),
              retry: () => setState(() => data = api
                  .request('home')
                  .then((v) => Map<String, dynamic>.from(v))));
        final d = s.data!;
        return RefreshIndicator(
            onRefresh: () async => setState(() => data =
                api.request('home').then((v) => Map<String, dynamic>.from(v))),
            child: ListView(padding: const EdgeInsets.all(18), children: [
              Row(children: [
                const Brand(),
                const Spacer(),
                IconButton(
                    onPressed: () => Navigator.push(context,
                        MaterialPageRoute(builder: (_) => const CartTab())),
                    icon: const Icon(Icons.shopping_cart_outlined))
              ]),
              const SizedBox(height: 18),
              TextField(
                  controller: search,
                  onSubmitted: (_) => find(),
                  decoration: InputDecoration(
                      hintText: 'Search products and shops',
                      prefixIcon: const Icon(Icons.search),
                      suffixIcon: IconButton(
                          onPressed: find,
                          icon: const Icon(Icons.arrow_forward)),
                      filled: true,
                      fillColor: Colors.white,
                      border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: BorderSide.none))),
              const SizedBox(height: 18),
              Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                      color: navy, borderRadius: BorderRadius.circular(18)),
                  child: const Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Find your next favorite',
                            style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 23)),
                        SizedBox(height: 5),
                        Text('Great finds from local shops.',
                            style: TextStyle(color: Colors.white70))
                      ])),
              const SizedBox(height: 20),
              const SectionTitle('Categories'),
              SizedBox(
                  height: 90,
                  child: ListView(
                      scrollDirection: Axis.horizontal,
                      children: ((d['categories'] as List?) ?? [])
                          .map<Widget>((x) => Padding(
                              padding: const EdgeInsets.only(right: 10),
                              child: ActionChip(
                                  avatar: const Icon(Icons.sell_outlined,
                                      color: teal),
                                  label: Text(x['name'] ?? ''),
                                  onPressed: () => Navigator.push(
                                      context,
                                      MaterialPageRoute(
                                          builder: (_) => ProductsScreen(
                                              categoryId: x['id']))))))
                          .toList())),
              const SectionTitle('Featured picks'),
              ProductGrid(items: d['featured'] as List? ?? []),
              const SectionTitle('Latest products'),
              ProductGrid(items: d['latest'] as List? ?? [])
            ]));
      });
}

class CategoriesTab extends StatelessWidget {
  const CategoriesTab({super.key});

  @override
  Widget build(BuildContext context) {
    return DataList(
      path: 'categories',
      title: 'Shop by category',
      builder: (data) {
        final categories = data as List;
        return ListView(
          children: categories
              .map<Widget>(
                (category) => ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: Color(0xffe3f3f1),
                    child: Icon(Icons.category_outlined, color: teal),
                  ),
                  title: Text(category['name'] ?? ''),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => ProductsScreen(
                          categoryId: category['id'],
                        ),
                      ),
                    );
                  },
                ),
              )
              .toList(),
        );
      },
    );
  }
}

class ProductsScreen extends StatelessWidget {
  final String? query;
  final int? categoryId;
  const ProductsScreen({super.key, this.query, this.categoryId});
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(
          title:
              Text(query?.isNotEmpty == true ? 'Search results' : 'Products')),
      body: DataList(
          path:
              'products?${query?.isNotEmpty == true ? 'q=${Uri.encodeQueryComponent(query!)}&' : ''}${categoryId != null ? 'category=$categoryId' : ''}',
          title: 'Products',
          builder: (d) =>
              ProductGrid(items: (d['data']?['data'] as List?) ?? [])));
}

class ProductGrid extends StatelessWidget {
  final List items;
  const ProductGrid({super.key, required this.items});
  @override
  Widget build(BuildContext context) {
    if (items.isEmpty)
      return const Padding(
          padding: EdgeInsets.all(20),
          child: Text('No products to show yet.', textAlign: TextAlign.center));
    return LayoutBuilder(
        builder: (c, box) => GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: items.length,
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: box.maxWidth > 650 ? 4 : 2,
                childAspectRatio: .67,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12),
            itemBuilder: (c, i) => ProductCard(data: items[i])));
  }
}

class ProductCard extends StatelessWidget {
  final dynamic data;
  const ProductCard({super.key, required this.data});
  @override
  Widget build(BuildContext context) => InkWell(
      onTap: () => Navigator.push(context,
          MaterialPageRoute(builder: (_) => ProductDetail(data: data))),
      child: Card(
          clipBehavior: Clip.antiAlias,
          child:
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(child: ProductImage(url: data['image'])),
            Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(data['name'] ?? '',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontWeight: FontWeight.w600)),
                      Text(data['shop'] ?? 'SHOPPICK shop',
                          maxLines: 1,
                          style: const TextStyle(
                              color: Colors.black54, fontSize: 12)),
                      Text('â‚±${data['price'] ?? 0}',
                          style: const TextStyle(
                              color: teal, fontWeight: FontWeight.bold))
                    ]))
          ])));
}

class ProductImage extends StatelessWidget {
  final String? url;
  const ProductImage({super.key, this.url});
  @override
  Widget build(BuildContext context) => Container(
      width: double.infinity,
      color: const Color(0xffeef1f3),
      child: url == null
          ? const Icon(Icons.image_outlined, size: 48, color: Colors.black26)
          : Image.network(url!,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => const Center(
                  child: Icon(Icons.image_not_supported_outlined,
                      size: 42, color: Colors.black26))));
}

class ProductDetail extends StatefulWidget {
  final dynamic data;
  const ProductDetail({super.key, required this.data});
  @override
  State<ProductDetail> createState() => _ProductDetailState();
}

class _ProductDetailState extends State<ProductDetail> {
  int qty = 1;
  String? variant;
  String? message;
  @override
  Widget build(BuildContext context) {
    final d = widget.data;
    return Scaffold(
        appBar: AppBar(title: const Text('Product details')),
        body: ListView(padding: const EdgeInsets.all(18), children: [
          SizedBox(height: 300, child: ProductImage(url: d['image'])),
          const SizedBox(height: 15),
          Text(d['name'] ?? '',
              style: const TextStyle(
                  fontSize: 24, fontWeight: FontWeight.bold, color: navy)),
          Text('â‚±${d['price'] ?? 0}',
              style: const TextStyle(
                  fontSize: 22, color: teal, fontWeight: FontWeight.bold)),
          Text('Sold by ${d['shop'] ?? 'SHOPPICK shop'}'),
          const SizedBox(height: 14),
          Text(d['description'] ?? 'No product description available.'),
          if ((d['variants'] as List? ?? []).isNotEmpty)
            DropdownButtonFormField<dynamic>(
                decoration: const InputDecoration(labelText: 'Choose option'),
                items: (d['variants'] as List)
                    .map((v) => DropdownMenuItem(
                        value: v['id'],
                        child: Text('${v['type']}: ${v['value']}')))
                    .toList(),
                onChanged: (v) => setState(() => variant = v?.toString())),
          Row(children: [
            IconButton(
                onPressed: qty > 1 ? () => setState(() => qty--) : null,
                icon: const Icon(Icons.remove)),
            Text('$qty'),
            IconButton(
                onPressed: () => setState(() => qty++),
                icon: const Icon(Icons.add))
          ]),
          if (message != null)
            Text(message!, style: const TextStyle(color: teal)),
          FilledButton.icon(
              onPressed: () async {
                try {
                  await ApiService().request('cart', method: 'POST', body: {
                    'product_id': d['id'],
                    'quantity': qty,
                    ...?variant == null
                        ? null
                        : {'product_variant_id': int.tryParse(variant!)}
                  });
                  setState(() => message = 'Added to your cart');
                } catch (e) {
                  setState(() => message = e.toString());
                }
              },
              icon: const Icon(Icons.add_shopping_cart),
              label: const Text('Add to cart'))
        ]));
  }
}

class CartTab extends StatelessWidget {
  const CartTab({super.key});
  Future<void> checkout(BuildContext context) async {
    try {
      final profile = await ApiService().request('profile');
      final addresses = profile['addresses'] as List? ?? [];
      if (addresses.isEmpty)
        throw Exception(
            'Add a shipping address on the SHOPPICK website before checkout.');
      final address = addresses.firstWhere((a) => a['is_default'] == true,
          orElse: () => addresses.first);
      final result = await ApiService().request('checkout',
          method: 'POST',
          body: {'address_id': address['id'], 'payment_method': 'cod'});
      if (context.mounted)
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text('Order ${result['order']?['order_number']} placed')));
    } catch (e) {
      if (context.mounted)
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text(e.toString().replaceFirst('Exception: ', ''))));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Your cart')),
      body: DataList(
          path: 'cart',
          title: 'Your cart',
          builder: (d) {
            final items = d['items'] as List? ?? [];
            if (items.isEmpty)
              return const Center(child: Text('Your cart is empty.'));
            return ListView(children: [
              ...items.map((x) => ListTile(
                  leading: SizedBox(
                      width: 48,
                      child: ProductImage(url: x['product']?['image'])),
                  title: Text(x['product']?['name'] ?? ''),
                  subtitle:
                      Text('Qty ${x['quantity']} Â· â‚±${x['unit_price']}'),
                  trailing: IconButton(
                      icon: const Icon(Icons.delete_outline),
                      onPressed: () async {
                        await ApiService()
                            .request('cart/${x['id']}', method: 'DELETE');
                        if (context.mounted)
                          ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                  content: Text(
                                      'Item removed. Reopen cart to refresh.')));
                      }))),
              Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text('Subtotal â‚±${d['subtotal'] ?? 0}',
                      style: const TextStyle(
                          fontWeight: FontWeight.bold, fontSize: 18))),
              Padding(
                  padding: const EdgeInsets.all(16),
                  child: FilledButton.icon(
                      onPressed: () => checkout(context),
                      icon: const Icon(Icons.lock_outline),
                      label: const Text('Checkout Â· Cash on Delivery')))
            ]);
          }));
}

class OrdersTab extends StatelessWidget {
  const OrdersTab({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('My orders')),
      body: DataList(
          path: 'orders',
          title: 'Orders',
          builder: (d) {
            final list = d['data'] as List? ?? [];
            if (list.isEmpty)
              return const Center(child: Text('No orders yet.'));
            return ListView(
                children: list
                    .map((o) => Card(
                        child: ListTile(
                            title: Text(o['order_number'] ?? ''),
                            subtitle:
                                Text('${o['status']} Â· â‚±${o['total']}'),
                            trailing: const Icon(Icons.chevron_right))))
                    .toList());
          }));
}

class AccountTab extends StatelessWidget {
  const AccountTab({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Account')),
      body: DataList(
          path: 'profile',
          title: 'Account',
          builder: (d) {
            final u = d['user'] ?? {};
            final application = u['seller_application'];
            final status = application?['status'];
            final label = u['is_seller'] == true
                ? 'Seller Dashboard'
                : status == null
                    ? 'Become a Seller'
                    : status == 'pending' ||
                            status == 'escalated' ||
                            status == 'awaiting_final_review'
                        ? 'Seller Application Pending'
                        : status == 'needs_resubmission'
                            ? 'Update Seller Application'
                            : 'View Seller Application';
            return ListView(padding: const EdgeInsets.all(18), children: [
              const CircleAvatar(
                  radius: 34, child: Icon(Icons.person, size: 34)),
              const SizedBox(height: 12),
              Text(u['name'] ?? 'SHOPPICK buyer',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                      fontSize: 21, fontWeight: FontWeight.bold)),
              Text(u['email'] ?? '', textAlign: TextAlign.center),
              const SizedBox(height: 24),
              Card(
                  child: ListTile(
                      leading: const Icon(Icons.storefront, color: teal),
                      title: Text(label),
                      subtitle:
                          status == null ? null : Text('Status: $status'))),
              Card(
                  child: ListTile(
                      leading: const Icon(Icons.location_on_outlined),
                      title: const Text('Saved addresses'),
                      subtitle: Text(
                          '${(d['addresses'] as List? ?? []).length} address(es)'))),
              FilledButton.tonal(
                  onPressed: () async {
                    await ApiService().request('logout', method: 'POST');
                    await ApiService().clearToken();
                    if (context.mounted)
                      Navigator.pushAndRemoveUntil(
                          context,
                          MaterialPageRoute(
                              builder: (_) => const LoginScreen()),
                          (_) => false);
                  },
                  child: const Text('Log out'))
            ]);
          }));
}

class DataList extends StatelessWidget {
  final String path, title;
  final Widget Function(dynamic) builder;
  const DataList(
      {super.key,
      required this.path,
      required this.title,
      required this.builder});
  @override
  Widget build(BuildContext context) => FutureBuilder(
      future: ApiService().request(path),
      builder: (c, s) {
        if (s.connectionState == ConnectionState.waiting)
          return const Loading();
        if (s.hasError)
          return ErrorState(
              message: s.error.toString(),
              retry: () => (c as Element).markNeedsBuild());
        return builder(s.data);
      });
}

class Loading extends StatelessWidget {
  const Loading({super.key});
  @override
  Widget build(BuildContext context) =>
      const Center(child: CircularProgressIndicator(color: teal));
}

class ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback retry;
  const ErrorState({super.key, required this.message, required this.retry});
  @override
  Widget build(BuildContext context) => Center(
      child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_outlined, size: 40, color: teal),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            TextButton(onPressed: retry, child: const Text('Try again'))
          ])));
}

class SectionTitle extends StatelessWidget {
  final String text;
  const SectionTitle(this.text, {super.key});
  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.only(bottom: 10, top: 6),
      child: Text(text,
          style: const TextStyle(
              fontSize: 19, fontWeight: FontWeight.bold, color: navy)));
}
