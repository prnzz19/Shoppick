class Delivery {
  final Map<String, dynamic> json;
  const Delivery(this.json);
  int get id => (json['id'] as num).toInt();
  String? get version => json['version'] as String?;
  String get providerName => json['provider'] is Map
      ? (json['provider']['name']?.toString() ?? 'Not supplied')
      : text('provider', 'Not supplied');
  String text(String key, [String fallback = 'Not recorded']) =>
      json[key]?.toString() ?? fallback;
  String get status => text('status');
  List<String> get actions =>
      (json['actions'] as List? ?? json['allowed_actions'] as List? ?? [])
          .whereType<String>()
          .toList();
  List<dynamic> get products => json['products'] as List? ?? [];
  List<dynamic> get events => json['events'] as List? ?? [];
  bool get pending =>
      actions.contains('accept_pickup') || actions.contains('accept_delivery');
  String? get acceptAction => actions.contains('accept_pickup')
      ? 'accept_pickup'
      : actions.contains('accept_delivery')
          ? 'accept_delivery'
          : null;
}

String label(String value) {
  const labels = {
    'ready_for_pickup': 'Ready for pickup',
    'assigned_to_rider': 'Delivery assigned',
    'pickup_assigned': 'Pickup assigned',
    'pickup_accepted': 'Pickup accepted',
    'picked_up': 'Picked up',
    'out_for_delivery': 'On the way',
    'delivery_accepted': 'Delivery accepted',
    'recipient_unavailable': 'Buyer unreachable',
    'incorrect_address': 'Wrong address',
    'parcel_issue': 'Damaged item',
    'cod_collected': 'Collected',
    'accept_pickup': 'Accept pickup',
    'accept_delivery': 'Accept delivery',
    'decline': 'Decline assignment',
    'delivery_failed': 'Failed delivery',
    'arrive_sorting': 'Arrived at sorting hub',
    'proof': 'Upload proof of delivery',
    'collect_cod': 'Confirm COD collected',
    'failed': 'Failed delivery',
    'assign': 'Assign rider',
    'receive': 'Receive at sorting hub',
    'scan': 'Confirm parcel code',
    'sort': 'Sort to delivery area',
    'reschedule': 'Reschedule delivery',
    'return': 'Return to seller'
  };
  if (labels.containsKey(value)) return labels[value]!;
  final text = value.replaceAll('_', ' ');
  return text.isEmpty ? text : '${text[0].toUpperCase()}${text.substring(1)}';
}
