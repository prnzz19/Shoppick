import 'package:flutter/material.dart';
import '../../services/api_client.dart';
import '../../widgets/common.dart';
import '../../config/app_config.dart';

class LoginScreen extends StatefulWidget {
  final ApiClient api;
  const LoginScreen({required this.api, super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final form = GlobalKey<FormState>();
  final email = TextEditingController();
  final password = TextEditingController();
  bool busy = false, obscure = true;
  String? error;
  @override
  void dispose() {
    email.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> login() async {
    if (busy || !form.currentState!.validate()) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.api.login(email.text, password.text);
    } catch (e) {
      if (mounted) setState(() => error = friendly(e));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      body: SafeArea(
          child: Center(
              child: SingleChildScrollView(
                  padding: const EdgeInsets.all(24),
                  child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 440),
                      child: AutofillGroup(
                          child: Form(
                              key: form,
                              child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.stretch,
                                  children: [
                                    const Align(
                                        alignment: Alignment.centerLeft,
                                        child: Wordmark()),
                                    const SizedBox(height: 10),
                                    const Text('LOGISTICS',
                                        style: TextStyle(
                                            letterSpacing: 3,
                                            color: teal,
                                            fontWeight: FontWeight.w700)),
                                    const SizedBox(height: 40),
                                    const Icon(Icons.local_shipping_outlined,
                                        size: 68, color: teal),
                                    const SizedBox(height: 24),
                                    const Text(
                                        'Every delivery,\none step closer.',
                                        style: TextStyle(
                                            fontSize: 30,
                                            fontWeight: FontWeight.w800,
                                            color: navy)),
                                    const SizedBox(height: 12),
                                    const Text(
                                        'Sign in to manage deliveries or start your rider assignments.'),
                                    const SizedBox(height: 28),
                                    TextFormField(
                                        controller: email,
                                        keyboardType:
                                            TextInputType.emailAddress,
                                        autofillHints: const [
                                          AutofillHints.username
                                        ],
                                        decoration: const InputDecoration(
                                            labelText: 'Email',
                                            prefixIcon:
                                                Icon(Icons.email_outlined)),
                                        validator: (v) =>
                                            v == null || !v.contains('@')
                                                ? 'Enter your email.'
                                                : null),
                                    const SizedBox(height: 16),
                                    TextFormField(
                                        controller: password,
                                        obscureText: obscure,
                                        autofillHints: const [
                                          AutofillHints.password
                                        ],
                                        decoration: InputDecoration(
                                            labelText: 'Password',
                                            prefixIcon:
                                                const Icon(Icons.lock_outline),
                                            suffixIcon: IconButton(
                                                tooltip:
                                                    'Show or hide password',
                                                onPressed: () => setState(
                                                    () => obscure = !obscure),
                                                icon: Icon(obscure
                                                    ? Icons.visibility_outlined
                                                    : Icons
                                                        .visibility_off_outlined))),
                                        validator: (v) => v == null || v.isEmpty
                                            ? 'Enter your password.'
                                            : null,
                                        onFieldSubmitted: (_) => login()),
                                    if (error != null)
                                      Padding(
                                          padding: const EdgeInsets.symmetric(
                                              vertical: 16),
                                          child: Text(error!,
                                              style: TextStyle(
                                                  color: Theme.of(context)
                                                      .colorScheme
                                                      .error))),
                                    const SizedBox(height: 24),
                                    FilledButton(
                                        onPressed: busy ? null : login,
                                        child: Text(
                                            busy ? 'Signing in…' : 'Sign in')),
                                    const SizedBox(height: 20),
                                    const Text(
                                        'For approved Logistics and Rider accounts.',
                                        textAlign: TextAlign.center),
                                  ]))))))));
}
