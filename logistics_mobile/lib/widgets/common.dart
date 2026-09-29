import 'package:flutter/material.dart';
import '../config/app_config.dart';
import '../models/delivery.dart';
import '../services/api_client.dart';

class Wordmark extends StatelessWidget {
  const Wordmark({super.key});
  @override
  Widget build(BuildContext context) => const Text.rich(TextSpan(
          style: TextStyle(
              fontWeight: FontWeight.w900, fontSize: 24, letterSpacing: -.8),
          children: [
            TextSpan(text: 'SHOP', style: TextStyle(color: teal)),
            TextSpan(text: 'PICK', style: TextStyle(color: orange))
          ]));
}

class StatusBadge extends StatelessWidget {
  final String status;
  const StatusBadge(this.status, {super.key});
  @override
  Widget build(BuildContext context) {
    final color =
        ['delivered', 'completed', 'active', 'available'].contains(status)
            ? Colors.green.shade700
            : ['delivery_failed', 'exception', 'inactive', 'returned']
                    .contains(status)
                ? Colors.red.shade700
                : status == 'out_for_delivery'
                    ? Colors.blue.shade700
                    : Colors.orange.shade900;
    return Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
            color: color.withValues(alpha: .09),
            borderRadius: BorderRadius.circular(9)),
        child: Text(label(status),
            style: TextStyle(
                color: color, fontWeight: FontWeight.w600, fontSize: 12)));
  }
}

class DeliveryCard extends StatelessWidget {
  final Delivery delivery;
  final VoidCallback onTap;
  const DeliveryCard(this.delivery, {required this.onTap, super.key});
  @override
  Widget build(BuildContext context) => Card(
      child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: onTap,
          child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(delivery.text('tracking_number'),
                        style: const TextStyle(
                            color: navy, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 8),
                    StatusBadge(delivery.status),
                    const SizedBox(height: 12),
                    Text('${delivery.text('shop')} → ${delivery.text('buyer')}',
                        style: const TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 6),
                    Text(delivery.text('destination')),
                    const Divider(height: 24),
                    Text('Order ${delivery.text('order_number')}'),
                    Text(
                        'Provider: ${delivery.text('provider', 'Not assigned')}'),
                    Text(
                        'Rider: ${delivery.text('rider', delivery.text('pickup_rider', 'Unassigned'))}'),
                    if (delivery.json['cod_amount'] != null)
                      Text(
                          'COD: ₱${(delivery.json['cod_amount'] as num).toStringAsFixed(2)}',
                          style: const TextStyle(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    Text('Updated ${dateText(delivery.json['updated_at'])}',
                        style: Theme.of(context).textTheme.bodySmall),
                    const SizedBox(height: 8),
                    Text(
                        delivery.actions.isEmpty
                            ? 'View delivery →'
                            : '${label(delivery.actions.first)} →',
                        style: const TextStyle(
                            color: teal, fontWeight: FontWeight.bold)),
                  ]))));
}

String dateText(dynamic value) {
  final date = DateTime.tryParse(value?.toString() ?? '')?.toLocal();
  if (date == null) return 'Not recorded';
  return '${date.month}/${date.day}/${date.year} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
}

class LoadView extends StatelessWidget {
  final bool loading;
  final String? error;
  final String message;
  final VoidCallback retry;
  final Widget child;
  const LoadView(
      {required this.loading,
      this.error,
      required this.message,
      required this.retry,
      required this.child,
      super.key});
  @override
  Widget build(BuildContext context) {
    if (loading) {
      return Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
        const CircularProgressIndicator(),
        const SizedBox(height: 16),
        Text(message)
      ]));
    }
    if (error != null) {
      return Center(
          child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.cloud_off_outlined, size: 40, color: teal),
                const SizedBox(height: 16),
                Text(error!, textAlign: TextAlign.center),
                const SizedBox(height: 16),
                FilledButton(onPressed: retry, child: const Text('Try Again'))
              ])));
    }
    return child;
  }
}

String friendly(Object e) => e is ApiFailure
    ? e.message
    : 'Unable to complete this request. Please try again.';
void showError(BuildContext context, Object error) =>
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(friendly(error))));
Future<bool> confirm(BuildContext context, String title,
        {String? message}) async =>
    await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
                title: Text(title),
                content: Text(message ??
                    'Confirm this action for the selected delivery.'),
                actions: [
                  TextButton(
                      onPressed: () => Navigator.pop(context, false),
                      child: const Text('Cancel')),
                  FilledButton(
                      onPressed: () => Navigator.pop(context, true),
                      child: const Text('Confirm'))
                ])) ??
    false;

class SectionTitle extends StatelessWidget {
  final String title;
  const SectionTitle(this.title, {super.key});
  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 14),
      child: Text(title,
          style: const TextStyle(
              color: navy, fontSize: 20, fontWeight: FontWeight.w800)));
}
