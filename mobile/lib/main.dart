import 'package:flutter/material.dart';
import 'dart:async';
import 'dart:math' as math;
import 'package:flutter/services.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'models/shipment_tracking.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'services/api_service.dart';
import 'config/api_config.dart';
import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';
part 'widgets/marketplace_widgets.dart';
part 'widgets/shoppick_brand.dart';
part 'screens/shopping_screens.dart';
part 'screens/account_screens.dart';
part 'screens/tracking_screens.dart';

const teal = Color(0xff14b8a6),
    orange = Color(0xfff97316),
    navy = Color(0xff182245);
void main() => runApp(const ShoppickApp());

class ShoppickApp extends StatefulWidget {
  const ShoppickApp({super.key});
  @override
  State<ShoppickApp> createState() => _ShoppickAppState();
}

class _ShoppickAppState extends State<ShoppickApp> {
  @override
  void initState() {
    super.initState();
    ApiService().restoreSession();
  }

  @override
  Widget build(BuildContext context) => ValueListenableBuilder<SessionStatus>(
      valueListenable: ApiService.session,
      builder: (_, status, __) => MaterialApp(
          // Replacing the Navigator discards every route from the old session.
          key: ValueKey(status),
          title: 'SHOPPICK',
          debugShowCheckedModeBanner: false,
          builder: (_, child) => SafeArea(child: child!),
          theme: ThemeData(
              colorScheme: ColorScheme.fromSeed(
                  seedColor: teal,
                  primary: const Color(0xff0d9488),
                  secondary: orange),
              scaffoldBackgroundColor: const Color(0xfff7f8fa),
              appBarTheme: const AppBarTheme(
                  backgroundColor: Colors.white,
                  foregroundColor: navy,
                  elevation: 0),
              useMaterial3: true),
          home: switch (status) {
            SessionStatus.checking => const SplashScreen(),
            SessionStatus.signedOut => const LoginScreen(),
            SessionStatus.authenticated => const MarketplaceScreen(),
            SessionStatus.unavailable => const SessionRetryScreen(),
          }));
}

class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});
  @override
  Widget build(BuildContext context) => const Scaffold(
          body: Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
        ShopPickBrand(),
        SizedBox(height: 24),
        CircularProgressIndicator(),
      ])));
}

class SessionRetryScreen extends StatelessWidget {
  const SessionRetryScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
      body: Center(
          child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const ShopPickBrand(),
                const SizedBox(height: 24),
                const Text('Unable to connect to SHOPPICK',
                    textAlign: TextAlign.center),
                const Text('Check your connection and try again.',
                    textAlign: TextAlign.center),
                const SizedBox(height: 16),
                FilledButton(
                    onPressed: () => ApiService().restoreSession(),
                    child: const Text('Try Again')),
                TextButton(
                    onPressed: () => ApiService().clearToken(),
                    child: const Text('Sign Out')),
              ]))));
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

  @override
  void dispose() {
    email.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> login() async {
    if (busy) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ApiService().login(email.text, password.text);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
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
                  const ShopPickBrand(),
                  const SizedBox(height: 24),
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
                      child: Text(busy ? 'Signing in...' : 'Sign in')),
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
        body: SafeArea(child: IndexedStack(index: tab, children: pages)),
        bottomNavigationBar: NavigationBar(
            selectedIndex: tab,
            onDestinationSelected: (i) => setState(() => tab = i),
            destinations: [
              const NavigationDestination(
                  icon: Icon(Icons.home_outlined), label: 'Home'),
              const NavigationDestination(
                  icon: Icon(Icons.grid_view_outlined), label: 'Categories'),
              NavigationDestination(
                  icon: ValueListenableBuilder<int>(
                      valueListenable: ApiService.cartCount,
                      builder: (_, count, __) => Badge(
                          isLabelVisible: count > 0,
                          label: Text(count.toString()),
                          child: const Icon(Icons.shopping_cart_outlined))),
                  label: 'Cart'),
              const NavigationDestination(
                  icon: Icon(Icons.receipt_long_outlined), label: 'Orders'),
              const NavigationDestination(
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

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  void find() {
    Navigator.push(context,
        MaterialPageRoute(builder: (_) => ProductsScreen(query: search.text)));
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>>(
      future: data,
      builder: (c, s) {
        if (s.connectionState == ConnectionState.waiting) {
          return const Loading();
        }
        if (s.hasError) {
          return ErrorState(
              message: s.error.toString(),
              retry: () => setState(() {
                    data = api
                        .request('home')
                        .then((v) => Map<String, dynamic>.from(v));
                  }));
        }
        final d = s.data!;
        return RefreshIndicator(
            onRefresh: () async {
              setState(() {
                data = api
                    .request('home')
                    .then((v) => Map<String, dynamic>.from(v));
              });
              try {
                await data;
              } catch (_) {}
            },
            child: ListView(padding: const EdgeInsets.all(18), children: [
              Row(children: [
                const Expanded(child: ShopPickBrand(compact: true)),
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
                      hintText: 'Search SHOPPICK...',
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
                        Text('Shop Smarter, Waddle Your Way to Deals!',
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
                  height: 64,
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
              ProductGrid(items: d['latest'] as List? ?? []),
              if ((d['deals'] as List? ?? []).isNotEmpty) ...[
                const SectionTitle('Flash Deals'),
                ProductGrid(items: d['deals'])
              ],
              TextButton(
                  onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (_) => const ProductsScreen())),
                  child: const Text('View all products'))
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
        final categories = (data as List)
            .expand((c) => [c, ...?c['children'] as List?])
            .toList();
        if (categories.isEmpty) {
          return const EmptyState(
              icon: Icons.category_outlined,
              title: 'No categories yet',
              message: 'Check back for new SHOPPICK categories.');
        }
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
