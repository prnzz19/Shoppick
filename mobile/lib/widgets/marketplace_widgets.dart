part of '../main.dart';

String money(dynamic value) =>
    '\u20b1${(num.tryParse('$value') ?? 0).toStringAsFixed(2)}';
String label(dynamic value) => (value ?? '')
    .toString()
    .split('_')
    .map((s) => s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}')
    .join(' ');
String date(dynamic value) {
  final d = DateTime.tryParse('$value')?.toLocal();
  return d == null ? '' : '${d.month}/${d.day}/${d.year}';
}

void open(BuildContext context, Widget page) =>
    Navigator.push(context, MaterialPageRoute(builder: (_) => page));
void tell(BuildContext context, Object message) => ScaffoldMessenger.of(context)
    .showSnackBar(SnackBar(content: Text(message.toString())));

class ProductImage extends StatelessWidget {
  final String? url;
  final Widget? fallback;
  const ProductImage({super.key, this.url, this.fallback});
  @override
  Widget build(BuildContext context) {
    final resolved = ApiConfig.imageUrl(url);
    final placeholder = Center(
        child: fallback ??
            const Icon(Icons.shopping_bag_outlined, size: 36, color: teal));
    return Container(
        width: double.infinity,
        color: const Color(0xffeffcf9),
        child: resolved == null
            ? placeholder
            : Image.network(resolved,
                fit: BoxFit.cover,
                loadingBuilder: (_, child, progress) => progress == null
                    ? child
                    : const Center(
                        child: SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(strokeWidth: 2))),
                errorBuilder: (_, error, stack) => placeholder));
  }
}

class ShopAvatar extends StatelessWidget {
  final dynamic shop;
  const ShopAvatar({super.key, this.shop});
  @override
  Widget build(BuildContext context) {
    final name = (shop?['name'] ?? 'SHOPPICK').toString();
    return SizedBox(
        width: 44,
        height: 44,
        child: ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: ProductImage(
                url: shop?['logo'],
                fallback: Text(
                    name.isEmpty ? 'S' : name.substring(0, 1).toUpperCase(),
                    style: const TextStyle(
                        fontWeight: FontWeight.bold, color: teal)))));
  }
}

class ShopTile extends StatelessWidget {
  final dynamic shop;
  final bool tappable;
  const ShopTile({super.key, this.shop, this.tappable = true});
  @override
  Widget build(BuildContext context) => ListTile(
      contentPadding: EdgeInsets.zero,
      leading: ShopAvatar(shop: shop),
      title: Text(shop?['name'] ?? 'SHOPPICK shop'),
      subtitle:
          tappable && shop?['slug'] != null ? const Text('View Shop') : null,
      trailing: tappable && shop?['slug'] != null
          ? const Icon(Icons.chevron_right)
          : null,
      onTap: tappable && shop?['slug'] != null
          ? () => open(context, ShopScreen(slug: shop['slug']))
          : null);
}

class Price extends StatelessWidget {
  final dynamic data;
  const Price({super.key, required this.data});
  @override
  Widget build(BuildContext context) {
    final current = num.tryParse('${data['price']}') ?? 0;
    final original = num.tryParse('${data['original_price']}') ?? 0;
    return Wrap(
        spacing: 6,
        runSpacing: 2,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          Text(money(current),
              style: const TextStyle(
                  color: Color(0xff0f756d),
                  fontSize: 17,
                  fontWeight: FontWeight.w800)),
          if (original > current)
            Text(money(original),
                style: const TextStyle(
                    decoration: TextDecoration.lineThrough,
                    color: Colors.black54,
                    fontSize: 12)),
          if (original > current && original > 0)
            Text('-${((original - current) / original * 100).round()}%',
                style: const TextStyle(color: Color(0xffea580c), fontSize: 12)),
        ]);
  }
}

class ProductCard extends StatelessWidget {
  final dynamic data;
  const ProductCard({super.key, required this.data});
  @override
  Widget build(BuildContext context) => Card(
      color: Colors.white,
      elevation: 0,
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
          onTap: () => open(context, ProductDetail(data: data)),
          child:
              Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Expanded(child: ProductImage(url: data['image'])),
            Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(data['name'] ?? '',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontWeight: FontWeight.w600)),
                      Text(data['shop'] ?? 'SHOPPICK shop',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                              color: Colors.black54, fontSize: 12)),
                      const SizedBox(height: 6),
                      Price(data: data),
                      if ((num.tryParse('${data['rating_count']}') ?? 0) > 0)
                        Text(
                            '\u2605 ${data['rating_avg']} (${data['rating_count']})',
                            style: const TextStyle(fontSize: 12)),
                      if ((num.tryParse('${data['stock']}') ?? 0) == 0)
                        const Text('Out of stock',
                            style:
                                TextStyle(fontSize: 12, color: Colors.black54)),
                    ])),
          ])));
}

class ProductGrid extends StatelessWidget {
  final List items;
  const ProductGrid({super.key, required this.items});
  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const EmptyState(
          icon: Icons.search_off,
          title: 'No products found',
          message: 'Try another search or category.');
    }
    return LayoutBuilder(builder: (_, box) {
      final scale = MediaQuery.textScalerOf(context).scale(14) / 14;
      final columns = box.maxWidth < 400 && scale > 1.25
          ? 1
          : box.maxWidth > 900
              ? 4
              : box.maxWidth > 600
                  ? 3
                  : 2;
      return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: items.length,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: columns,
              mainAxisExtent: (box.maxWidth / columns) + 165 * scale,
              crossAxisSpacing: 12,
              mainAxisSpacing: 12),
          itemBuilder: (_, i) => ProductCard(data: items[i]));
    });
  }
}

class DataList extends StatefulWidget {
  final String path, title;
  final Widget Function(dynamic) builder;
  const DataList(
      {super.key,
      required this.path,
      required this.title,
      required this.builder});
  @override
  State<DataList> createState() => DataListState();
}

class DataListState extends State<DataList> {
  late Future<dynamic> data;
  @override
  void initState() {
    super.initState();
    data = ApiService().request(widget.path);
  }

  @override
  void didUpdateWidget(DataList oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.path != widget.path) reload();
  }

  Future<void> reload() async {
    setState(() {
      data = ApiService().request(widget.path);
    });
    try {
      await data;
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) => FutureBuilder(
      future: data,
      builder: (_, s) {
        if (s.connectionState == ConnectionState.waiting) {
          return const Loading();
        }
        if (s.hasError) {
          return ErrorState(message: s.error.toString(), retry: reload);
        }
        return RefreshIndicator(
            onRefresh: reload, child: widget.builder(s.data));
      });
}

class Loading extends StatelessWidget {
  const Loading({super.key});
  @override
  Widget build(BuildContext context) =>
      const Center(child: CircularProgressIndicator());
}

class ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback retry;
  const ErrorState({super.key, required this.message, required this.retry});
  @override
  Widget build(BuildContext context) => Center(
      child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_outlined, size: 40, color: teal),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            TextButton(onPressed: retry, child: const Text('Try again'))
          ])));
}

class EmptyState extends StatelessWidget {
  final IconData icon;
  final String title, message;
  final VoidCallback? action;
  final String actionLabel;
  const EmptyState(
      {super.key,
      required this.icon,
      required this.title,
      required this.message,
      this.action,
      this.actionLabel = 'Start Shopping'});
  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 36, horizontal: 20),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 52, color: teal),
        const SizedBox(height: 16),
        Text(title,
            textAlign: TextAlign.center,
            style: const TextStyle(
                fontSize: 20, fontWeight: FontWeight.bold, color: navy)),
        const SizedBox(height: 8),
        Text(message, textAlign: TextAlign.center),
        if (action != null)
          Padding(
              padding: const EdgeInsets.only(top: 16),
              child: FilledButton(onPressed: action, child: Text(actionLabel))),
      ]));
}

class SectionTitle extends StatelessWidget {
  final String text;
  const SectionTitle(this.text, {super.key});
  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.only(top: 20, bottom: 12),
      child: Text(text,
          style: const TextStyle(
              fontSize: 20, fontWeight: FontWeight.w800, color: navy)));
}

class StatusBadge extends StatelessWidget {
  final dynamic status;
  const StatusBadge(this.status, {super.key});
  @override
  Widget build(BuildContext context) {
    final color = ['cancelled', 'rejected', 'refunded'].contains(status)
        ? Colors.red.shade700
        : ['completed', 'delivered', 'approved'].contains(status)
            ? const Color(0xff0f756d)
            : const Color(0xff9a5200);
    return Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
            color: color.withValues(alpha: .09),
            borderRadius: BorderRadius.circular(20)),
        child: Text(label(status),
            style: TextStyle(
                color: color, fontWeight: FontWeight.w600, fontSize: 12)));
  }
}

class SummaryRow extends StatelessWidget {
  final String title, value;
  const SummaryRow(this.title, this.value, {super.key});
  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Expanded(child: Text(title)),
        const SizedBox(width: 12),
        Flexible(
            child: Text(value,
                textAlign: TextAlign.end,
                style: const TextStyle(fontWeight: FontWeight.w600)))
      ]));
}

class AddressCard extends StatelessWidget {
  final dynamic data;
  const AddressCard(this.data, {super.key});
  @override
  Widget build(BuildContext context) => Card(
      color: Colors.white,
      child: Padding(
          padding: const EdgeInsets.all(16),
          child:
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${data['full_name'] ?? ''} • ${data['phone'] ?? ''}',
                style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 6),
            Text([
              'address_line',
              'barangay',
              'city',
              'province',
              'postal_code',
              'country'
            ]
                .map((k) => data[k])
                .where((v) => v != null && v.toString().isNotEmpty)
                .join(', ')),
            if (data['is_default'] == true || data['is_default'] == 1)
              const Text('Default address', style: TextStyle(color: teal)),
          ])));
}
