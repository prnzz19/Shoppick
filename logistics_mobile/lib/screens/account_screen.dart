import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../widgets/common.dart';
import 'notifications_screen.dart';

class AccountScreen extends StatefulWidget {
  final ApiClient api;
  const AccountScreen({required this.api, super.key});
  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  bool busy = false;
  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    try {
      final result = await widget.api.request('GET', 'logistics/profile');
      if (mounted) setState(() => widget.api.user = result['user']);
    } catch (error) {
      if (mounted) showError(context, error);
    }
  }

  @override
  Widget build(BuildContext context) {
    final u = widget.api.user ?? {};
    return ListView(padding: const EdgeInsets.all(20), children: [
      const SectionTitle('Your account'),
      if (u['avatar'] != null)
        Center(
            child: ClipOval(
                child: Image.network(u['avatar'],
                    width: 80,
                    height: 80,
                    fit: BoxFit.cover,
                    errorBuilder: (context, error, stack) =>
                        const Icon(Icons.person_outline, size: 70)))),
      for (final key in [
        'name',
        'email',
        'phone',
        'role',
        if (!widget.api.manager) ...[
          'provider',
          'status',
          'availability',
          'completed_deliveries'
        ]
      ])
        Card(
            child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(key.replaceAll('_', ' ').toUpperCase(),
                          style: Theme.of(context).textTheme.labelSmall),
                      const SizedBox(height: 6),
                      Text('${u[key] ?? 'Not recorded'}')
                    ]))),
      const SectionTitle('Settings'),
      ListTile(
          leading: const Icon(Icons.notifications_outlined),
          title: const Text('Notifications'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.push(
              context,
              MaterialPageRoute(
                  builder: (_) => Scaffold(
                      appBar: AppBar(title: const Text('Notifications')),
                      body: NotificationsScreen(api: widget.api))))),
      const SizedBox(height: 24),
      FilledButton.tonal(
          onPressed: busy
              ? null
              : () async {
                  if (!await confirm(context, 'Log out?',
                      message:
                          'You will need to sign in to view your deliveries again.')) {
                    return;
                  }
                  setState(() => busy = true);
                  try {
                    await widget.api.logout();
                  } catch (e) {
                    if (context.mounted) showError(context, e);
                  } finally {
                    if (mounted) setState(() => busy = false);
                  }
                },
          child: Text(busy ? 'Logging out…' : 'Logout')),
    ]);
  }
}
