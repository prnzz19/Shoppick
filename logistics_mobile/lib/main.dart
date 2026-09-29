import 'package:flutter/material.dart';
import 'config/app_config.dart';
import 'services/api_client.dart';
import 'screens/auth/login_screen.dart';
import 'screens/home_shell.dart';
import 'widgets/common.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(LogisticsApp(api: ApiClient()));
}

class LogisticsApp extends StatefulWidget {
  final ApiClient api;
  const LogisticsApp({required this.api, super.key});
  @override
  State<LogisticsApp> createState() => _LogisticsAppState();
}

class _LogisticsAppState extends State<LogisticsApp> {
  bool loading = true;
  String? error;
  final navigator = GlobalKey<NavigatorState>();
  @override
  void initState() {
    super.initState();
    widget.api.addListener(changed);
    restore();
  }

  void changed() {
    if (mounted) {
      if (widget.api.user == null) {
        navigator.currentState?.popUntil((route) => route.isFirst);
      }
      setState(() {});
    }
  }

  Future<void> restore() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      await widget.api.restore();
    } catch (e) {
      error = friendly(e);
    }
    if (mounted) setState(() => loading = false);
  }

  @override
  void dispose() {
    widget.api.removeListener(changed);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
      navigatorKey: navigator,
      title: 'SHOPPICK Logistics',
      debugShowCheckedModeBanner: false,
      theme: appTheme(),
      home: loading || error != null
          ? Scaffold(
              body: LoadView(
                  loading: loading,
                  error: error,
                  message: 'Opening SHOPPICK Logistics…',
                  retry: restore,
                  child: const SizedBox()))
          : widget.api.user == null
              ? LoginScreen(api: widget.api)
              : HomeShell(api: widget.api));
}
