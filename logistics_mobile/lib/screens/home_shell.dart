import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../widgets/common.dart';
import 'dashboard_screen.dart';
import 'deliveries_screen.dart';
import 'logistics/directory_screen.dart';
import 'account_screen.dart';
import 'notifications_screen.dart';

class HomeShell extends StatefulWidget {
  final ApiClient api;
  const HomeShell({required this.api, super.key});
  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int tab = 0;
  @override
  Widget build(BuildContext context) {
    final api = widget.api;
    final names = api.manager
        ? ['Dashboard', 'Deliveries', 'Riders', 'Providers', 'Account']
        : ['Home', 'Deliveries', 'History', 'Notifications', 'Account'];
    final icons = api.manager
        ? [
            Icons.dashboard_outlined,
            Icons.inventory_2_outlined,
            Icons.two_wheeler,
            Icons.business_outlined,
            Icons.person_outline
          ]
        : [
            Icons.home_outlined,
            Icons.inventory_2_outlined,
            Icons.history,
            Icons.notifications_outlined,
            Icons.person_outline
          ];
    final pages = <Widget>[
      DashboardScreen(api: api),
      DeliveriesScreen(api: api),
      api.manager
          ? DirectoryScreen(api: api, riders: true)
          : DeliveriesScreen(api: api, history: true),
      api.manager
          ? DirectoryScreen(api: api, riders: false)
          : NotificationsScreen(api: api),
      AccountScreen(api: api)
    ];
    return Scaffold(
      appBar: AppBar(title: const Wordmark(), actions: [
        IconButton(
            tooltip: 'Notifications',
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                    builder: (_) => Scaffold(
                        appBar: AppBar(title: const Text('Notifications')),
                        body: NotificationsScreen(api: api)))))
      ]),
      body:
          SafeArea(child: KeyedSubtree(key: ValueKey(tab), child: pages[tab])),
      bottomNavigationBar: NavigationBar(
          selectedIndex: tab,
          labelBehavior: NavigationDestinationLabelBehavior.onlyShowSelected,
          onDestinationSelected: (value) => setState(() => tab = value),
          destinations: [
            for (int i = 0; i < names.length; i++)
              NavigationDestination(icon: Icon(icons[i]), label: names[i])
          ]),
    );
  }
}
