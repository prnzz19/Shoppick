class Delivery {
  final Map<String, dynamic> json;
  const Delivery(this.json);
  int get id => json['id'] as int;
  String text(String key, [String fallback = 'Not recorded']) =>
      json[key]?.toString() ?? fallback;
  String get status => text('status');
  List<String> get actions => List<String>.from(json['actions'] ?? []);
  List<dynamic> get products => json['products'] as List? ?? [];
  List<dynamic> get events => json['events'] as List? ?? [];
}

String label(String value) {
  const labels = {
    'ready_for_pickup': 'Ready for pickup',
    'assigned_to_rider': 'Delivery assigned',
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
