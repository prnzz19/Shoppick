import 'package:flutter/material.dart';

import '../../services/api_client.dart';
import '../../widgets/common.dart';

class AvailabilityCard extends StatefulWidget {
  final ApiClient api;
  final bool hasAssignment;
  final VoidCallback? onChanged;

  const AvailabilityCard({
    required this.api,
    this.hasAssignment = false,
    this.onChanged,
    super.key,
  });

  @override
  State<AvailabilityCard> createState() => _AvailabilityCardState();
}

class _AvailabilityCardState extends State<AvailabilityCard> {
  bool busy = false;

  Future<void> change(bool online) async {
    final previous = widget.api.user?['availability']?.toString();
    setState(() => busy = true);
    try {
      await widget.api.setRiderAvailability(online ? 'available' : 'off_duty');
      widget.onChanged?.call();
    } catch (error) {
      if (previous != null && widget.api.user != null) {
        widget.api.user = {...widget.api.user!, 'availability': previous};
      }
      if (mounted) showError(context, error);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final status = widget.api.user?['availability']?.toString() ?? 'off_duty';
    final locked = widget.hasAssignment ||
        ['assigned', 'on_pickup', 'on_delivery'].contains(status);
    final online = status == 'available';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  locked
                      ? Icons.local_shipping_outlined
                      : online
                          ? Icons.radio_button_checked
                          : Icons.pause_circle_outline,
                  color: locked
                      ? Colors.orange.shade800
                      : online
                          ? Colors.green.shade700
                          : Colors.grey.shade700,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    locked
                        ? 'On assignment'
                        : online
                            ? 'Online for assignments'
                            : 'Offline',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
                Switch.adaptive(
                  value: online || locked,
                  onChanged: busy || locked ? null : change,
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              locked
                  ? 'Availability is locked until your current assignment is finished.'
                  : online
                      ? 'Logistics can assign a pickup or delivery to you.'
                      : 'Go online when you are ready to receive assignments.',
            ),
            if (busy) ...[
              const SizedBox(height: 12),
              const LinearProgressIndicator(),
            ],
          ],
        ),
      ),
    );
  }
}
