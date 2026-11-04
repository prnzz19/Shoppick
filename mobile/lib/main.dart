import 'package:flutter/material.dart';
import 'dart:async';
import 'dart:math' as math;
import 'package:flutter/services.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'models/shipment_tracking.dart';
import 'models/buyer_registration.dart';
import 'services/buyer_registration_service.dart';
import 'services/philippine_locations.dart';
import 'services/api_service.dart';
import 'services/google_auth_service.dart';
import 'config/api_config.dart';
import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';
part 'widgets/marketplace_widgets.dart';
part 'widgets/shoppick_brand.dart';
part 'screens/shopping_screens.dart';
part 'screens/account_screens.dart';
part 'screens/message_screens.dart';
part 'screens/tracking_screens.dart';
part 'screens/report_screen.dart';
part 'screens/registration_screen.dart';
part 'screens/search_screen.dart';

const teal = Color(0xff08aeb8),
    orange = Color(0xfff7941d),
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
                  primary: const Color(0xff078f99),
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
            SessionStatus.profileRequired =>
              const RegisterScreen(profileOnly: true),
            SessionStatus.verificationRequired => const RegisterOtpScreen(),
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

  Future<void> loginWithGoogle() async {
    if (busy) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final idToken = await GoogleAuthService().obtainIdToken();
      final result = await ApiService().loginWithGoogle(idToken);
      if (mounted &&
          result.requiresVerification &&
          ApiService.session.value == SessionStatus.signedOut) {
        await Navigator.push(
            context,
            MaterialPageRoute(
                builder: (_) => RegisterOtpScreen(
                      initialMessage: result.message,
                    )));
      }
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
                  const SizedBox(height: 10),
                  OutlinedButton.icon(
                      onPressed: busy ? null : loginWithGoogle,
                      icon: const Icon(Icons.account_circle_outlined),
                      label: const Text('Continue with Google')),
                  TextButton(
                      onPressed: busy
                          ? null
                          : () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                  builder: (_) =>
                                      const ForgotPasswordScreen())),
                      child: const Text('Forgot Password?')),
                  const SizedBox(height: 12),
                  TextButton(
                      onPressed: busy
                          ? null
                          : () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                  builder: (_) => const RegisterScreen())),
                      child:
                          const Text('Don\'t have an account? Create Account')),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});
  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final email = TextEditingController();
  bool busy = false, sent = false;
  String? error;
  @override
  void dispose() {
    email.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (busy) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ApiService().forgotPassword(email.text);
      if (mounted) setState(() => sent = true);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Forgot Password')),
      body: Center(
          child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 440),
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const ShopPickBrand(),
                        const SizedBox(height: 24),
                        Text(sent ? 'Check your email' : 'Reset your password',
                            style: const TextStyle(
                                fontSize: 24,
                                fontWeight: FontWeight.bold,
                                color: navy)),
                        const SizedBox(height: 10),
                        Text(sent
                            ? 'We sent the standard SHOPPICK reset link. The secure link expires in 60 minutes.'
                            : 'Enter the email address used for your SHOPPICK account.'),
                        if (!sent) ...[
                          const SizedBox(height: 18),
                          TextField(
                              controller: email,
                              keyboardType: TextInputType.emailAddress,
                              decoration: const InputDecoration(
                                  labelText: 'Email',
                                  border: OutlineInputBorder())),
                          if (error != null)
                            Padding(
                                padding: const EdgeInsets.only(top: 10),
                                child: Text(error!,
                                    style: const TextStyle(color: Colors.red))),
                          const SizedBox(height: 16),
                          FilledButton(
                              onPressed: busy ? null : submit,
                              child: Text(
                                  busy ? 'Sending...' : 'Send Reset Link')),
                          TextButton(
                              onPressed: busy
                                  ? null
                                  : () => Navigator.push(
                                      context,
                                      MaterialPageRoute(
                                          builder: (_) => ResetPasswordScreen(
                                              initialEmail: email.text))),
                              child: const Text('Already have a reset token?')),
                        ] else ...[
                          const SizedBox(height: 16),
                          FilledButton(
                              onPressed: () => Navigator.pop(context),
                              child: const Text('Back to Login')),
                          TextButton(
                              onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                      builder: (_) => ResetPasswordScreen(
                                          initialEmail: email.text))),
                              child: const Text('Enter Reset Token')),
                          TextButton(
                              onPressed: () => setState(() => sent = false),
                              child: const Text('Send Again')),
                        ],
                      ])))));
}

class ResetPasswordScreen extends StatefulWidget {
  final String initialEmail;
  const ResetPasswordScreen({super.key, this.initialEmail = ''});
  @override
  State<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends State<ResetPasswordScreen> {
  late final email = TextEditingController(text: widget.initialEmail);
  final token = TextEditingController(),
      password = TextEditingController(),
      confirmation = TextEditingController();
  bool busy = false;
  String? error;
  @override
  void dispose() {
    email.dispose();
    token.dispose();
    password.dispose();
    confirmation.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (busy) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ApiService().resetPassword(
          email.text, token.text, password.text, confirmation.text);
      if (mounted) {
        tell(context, 'Password reset successfully. You can now sign in.');
        Navigator.of(context).popUntil((route) => route.isFirst);
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Widget field(TextEditingController controller, String label,
          {bool secret = false}) =>
      TextField(
          controller: controller,
          obscureText: secret,
          decoration: InputDecoration(
              labelText: label, border: const OutlineInputBorder()));
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Reset Password')),
      body: Center(
          child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 440),
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Text(
                            'Paste the secure link or token from your SHOPPICK reset email.',
                            style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                                color: navy)),
                        const SizedBox(height: 16),
                        field(email, 'Email'),
                        const SizedBox(height: 12),
                        field(token, 'Reset link or token'),
                        const SizedBox(height: 12),
                        field(password, 'New Password', secret: true),
                        const SizedBox(height: 12),
                        field(confirmation, 'Confirm New Password',
                            secret: true),
                        if (error != null)
                          Padding(
                              padding: const EdgeInsets.only(top: 12),
                              child: Text(error!,
                                  style: const TextStyle(color: Colors.red))),
                        const SizedBox(height: 18),
                        FilledButton(
                            onPressed: busy ? null : submit,
                            child:
                                Text(busy ? 'Resetting...' : 'Reset Password')),
                      ])))));
}

class RegisterOtpScreen extends StatefulWidget {
  final String? initialMessage;
  const RegisterOtpScreen({super.key, this.initialMessage});
  @override
  State<RegisterOtpScreen> createState() => _RegisterOtpScreenState();
}

class _RegisterOtpScreenState extends State<RegisterOtpScreen> {
  final code = TextEditingController();
  bool busy = false;
  String? error;
  DateTime? resendAt;
  Timer? cooldown;
  Future<void> verify() async {
    if (busy) return;
    setState(() => busy = true);
    try {
      await ApiService().verifyRegistration(code.text.trim());
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> resend() async {
    if (busy) return;
    setState(() => busy = true);
    try {
      await ApiService().resendRegistrationCode();
      if (!mounted) return;
      setState(
          () => resendAt = DateTime.now().add(const Duration(seconds: 60)));
      cooldown?.cancel();
      cooldown = Timer.periodic(const Duration(seconds: 1), (timer) {
        if (!mounted || DateTime.now().isAfter(resendAt!)) timer.cancel();
        if (mounted) setState(() {});
      });
      if (mounted) tell(context, 'A new verification code was sent.');
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  void dispose() {
    cooldown?.cancel();
    code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Verify your email'), actions: [
        IconButton(
            tooltip: 'Sign out',
            onPressed: () => ApiService().logout(),
            icon: const Icon(Icons.logout))
      ]),
      body: Center(
          child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Column(children: [
                const ShopPickBrand(),
                const SizedBox(height: 20),
                const Text(
                    'Enter the 6-digit SHOPPICK code sent to your email.',
                    textAlign: TextAlign.center),
                if (widget.initialMessage != null ||
                    ApiService.sessionMessage != null) ...[
                  const SizedBox(height: 10),
                  Text(widget.initialMessage ?? ApiService.sessionMessage!,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: navy)),
                ],
                const SizedBox(height: 18),
                TextField(
                    controller: code,
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    onChanged: (_) => setState(() {}),
                    maxLength: 6,
                    decoration: const InputDecoration(
                        labelText: 'Verification code',
                        border: OutlineInputBorder())),
                if (error != null)
                  Text(error!, style: const TextStyle(color: Colors.red)),
                const SizedBox(height: 10),
                FilledButton(
                    onPressed: busy || code.text.length != 6 ? null : verify,
                    child: Text(busy ? 'Verifying...' : 'Verify')),
                TextButton(
                    onPressed: busy ||
                            (resendAt != null &&
                                DateTime.now().isBefore(resendAt!))
                        ? null
                        : resend,
                    child: Text(resendAt != null &&
                            DateTime.now().isBefore(resendAt!)
                        ? 'Resend in ${resendAt!.difference(DateTime.now()).inSeconds + 1}s'
                        : 'Resend code'))
              ]))));
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
                const NotificationBell(),
                IconButton(
                    onPressed: () => Navigator.push(context,
                        MaterialPageRoute(builder: (_) => const CartTab())),
                    icon: const Icon(Icons.shopping_cart_outlined))
              ]),
              const SizedBox(height: 18),
              TextField(
                  controller: search,
                  readOnly: true,
                  onTap: () => open(
                      context, CatalogSearchScreen(initialQuery: search.text)),
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
                    gradient: const LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [
                          Color(0xff14b8a6),
                          Color(0xff0d9488),
                          Color(0xff101a38)
                        ],
                        stops: [
                          0,
                          .4,
                          1
                        ]),
                    borderRadius: BorderRadius.circular(18)),
                child: const Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text.rich(
                          TextSpan(children: [
                            TextSpan(text: 'Shop Smarter, '),
                            TextSpan(
                                text: 'Pick Better',
                                style: TextStyle(color: Color(0xfffcd34d))),
                            TextSpan(text: ' Deals!'),
                          ]),
                          style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                              fontSize: 23)),
                      SizedBox(height: 5),
                      Text(
                          'Discover thousands of products with exclusive SHOPPICK discounts. Fresh arrivals, flash sales, and happy shopping every day.',
                          style: TextStyle(color: Colors.white70))
                    ]),
              ),
              const SizedBox(height: 12),
              Wrap(spacing: 10, runSpacing: 8, children: [
                FilledButton(
                    style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xfffbbf24),
                        foregroundColor: navy),
                    onPressed: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                            builder: (_) => const ProductsScreen())),
                    child: const Text('Shop Now')),
                OutlinedButton(
                    style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.white,
                        side: const BorderSide(color: Colors.white70)),
                    onPressed: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                            builder: (_) =>
                                const ProductsScreen(onSale: true))),
                    child: const Text('View Flash Deals'))
              ]),
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
              if ((d['popular'] as List? ?? []).isNotEmpty) ...[
                const SectionTitle('Popular picks'),
                ProductGrid(items: d['popular'])
              ],
              if ((d['deals'] as List? ?? []).isNotEmpty) ...[
                const SectionTitle('Flash Deals'),
                ProductGrid(items: d['deals'])
              ],
              TextButton(
                  onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (_) => const ProductsScreen())),
                  child: const Text('View all products')),
              if ((d['shops'] as List? ?? []).isNotEmpty) ...[
                const SectionTitle('Discover local shops'),
                for (final shop in d['shops']) ShopTile(shop: shop),
              ]
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
