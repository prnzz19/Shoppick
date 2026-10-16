part of '../main.dart';

class ProductsScreen extends StatefulWidget {
  final String? query;
  final int? categoryId;
  const ProductsScreen({super.key, this.query, this.categoryId});
  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  late final search = TextEditingController(text: widget.query);
  late String query = widget.query ?? '';
  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Products')),
      body: Column(children: [
        Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
                controller: search,
                textInputAction: TextInputAction.search,
                onSubmitted: (v) => setState(() => query = v.trim()),
                decoration: InputDecoration(
                    hintText: 'Search SHOPPICK...',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: IconButton(
                        onPressed: () =>
                            setState(() => query = search.text.trim()),
                        icon: const Icon(Icons.arrow_forward)),
                    border: const OutlineInputBorder()))),
        Expanded(
            child: PagedProducts(
                path:
                    'products?q=${Uri.encodeQueryComponent(query)}${widget.categoryId == null ? '' : '&category=${widget.categoryId}'}')),
      ]));
}

class PagedProducts extends StatefulWidget {
  final String path;
  final Widget? header;
  const PagedProducts({super.key, required this.path, this.header});
  @override
  State<PagedProducts> createState() => _PagedProductsState();
}

class _PagedProductsState extends State<PagedProducts> {
  final List items = [];
  int page = 0, total = 0;
  bool busy = false, more = true;
  String? error;
  @override
  void initState() {
    super.initState();
    load(reset: true);
  }

  @override
  void didUpdateWidget(PagedProducts oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.path != widget.path) load(reset: true);
  }

  int generation = 0;
  Future<void> load({bool reset = false}) async {
    if (reset) {
      generation++;
      items.clear();
      page = 0;
      more = true;
    }
    final current = generation;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final result = await ApiService().request(
          '${widget.path}${widget.path.contains('?') ? '&' : '?'}page=${page + 1}');
      if (!mounted || current != generation) return;
      final d = result['data'] is Map ? result['data'] : result['products'];
      setState(() {
        final knownIds = items.map((item) => item['id']).toSet();
        items.addAll(
            (d['data'] as List).where((item) => knownIds.add(item['id'])));
        page = d['current_page'];
        total = d['total'];
        more = page < d['last_page'];
      });
    } catch (e) {
      if (mounted && current == generation) {
        setState(() => error = e.toString());
      }
    } finally {
      if (mounted && current == generation) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (busy && items.isEmpty) return const Loading();
    if (error != null && items.isEmpty) {
      return ErrorState(message: error!, retry: () => load(reset: true));
    }
    return RefreshIndicator(
        onRefresh: () => load(reset: true),
        child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            children: [
              if (widget.header != null) widget.header!,
              Text('$total products',
                  style: const TextStyle(color: Colors.black54)),
              const SizedBox(height: 12),
              ProductGrid(items: items),
              if (error != null) Text(error!),
              if (more)
                Padding(
                    padding: const EdgeInsets.all(16),
                    child: OutlinedButton(
                        onPressed: busy ? null : load,
                        child: Text(busy ? 'Loading...' : 'Load more'))),
            ]));
  }
}

class ShopScreen extends StatelessWidget {
  final String slug;
  const ShopScreen({super.key, required this.slug});
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Shop')),
      body: DataList(
          path: 'shops/${Uri.encodeComponent(slug)}',
          title: 'Shop',
          builder: (d) {
            final shop = d['shop'];
            return PagedProducts(
                path: 'products?shop=${shop['id']}',
                header: Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          ShopTile(shop: shop, tappable: false),
                          Text(shop['description'] ?? ''),
                          if (shop['location'] != null) Text(shop['location']),
                          if ((num.tryParse('${shop['rating_count']}') ?? 0) >
                              0)
                            Text(
                                '\u2605 ${shop['rating_avg']} (${shop['rating_count']} ratings)')
                        ])));
          }));
}

class ProductDetail extends StatefulWidget {
  final dynamic data;
  const ProductDetail({super.key, required this.data});
  @override
  State<ProductDetail> createState() => _ProductDetailState();
}

class _ProductDetailState extends State<ProductDetail> {
  int qty = 1;
  dynamic variant;
  bool busy = false;
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Product details')),
      body: DataList(
          path: 'products/${widget.data['id']}',
          title: 'Product',
          builder: (d) {
            final images =
                (d['images'] as List? ?? []).whereType<String>().toList();
            final variants = d['variants'] as List? ?? [];
            final stock =
                num.tryParse('${variant?['stock'] ?? d['stock']}')?.toInt() ??
                    0;
            final priceData = {
              ...d as Map,
              if ((num.tryParse('${variant?['price']}') ?? 0) > 0) ...{
                'price': variant['price'],
                'original_price': variant['price']
              }
            };
            return ListView(padding: const EdgeInsets.all(18), children: [
              SizedBox(
                  height: MediaQuery.sizeOf(context)
                      .width
                      .clamp(220, 380)
                      .toDouble(),
                  child: images.isEmpty
                      ? ProductImage(url: d['image'])
                      : PageView(
                          children: images
                              .map((url) => ProductImage(url: url))
                              .toList())),
              if (images.length > 1)
                Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text('Swipe to view ${images.length} photos',
                        textAlign: TextAlign.center)),
              const SizedBox(height: 20),
              Text(d['name'] ?? '',
                  style: const TextStyle(
                      fontSize: 24, fontWeight: FontWeight.w800, color: navy)),
              const SizedBox(height: 10),
              Price(data: priceData),
              const SizedBox(height: 10),
              Text(stock > 0 ? '$stock available' : 'Out of stock'),
              ShopTile(shop: d['store']),
              const SectionTitle('Description'),
              Text((d['description'] ?? '').toString().isEmpty
                  ? 'No description available.'
                  : d['description']),
              if (variants.isNotEmpty) ...[
                const SizedBox(height: 20),
                DropdownButtonFormField<int>(
                    isExpanded: true,
                    decoration: const InputDecoration(
                        labelText: 'Choose option',
                        border: OutlineInputBorder()),
                    initialValue: variant?['id'],
                    items: variants
                        .map<DropdownMenuItem<int>>((v) => DropdownMenuItem(
                            value: v['id'],
                            child: Text('${v['type']}: ${v['value']}',
                                overflow: TextOverflow.ellipsis)))
                        .toList(),
                    onChanged: busy
                        ? null
                        : (id) => setState(() {
                              variant =
                                  variants.firstWhere((v) => v['id'] == id);
                              qty = 1;
                            }))
              ],
              Row(children: [
                const Expanded(child: Text('Quantity')),
                IconButton(
                    tooltip: 'Decrease quantity',
                    onPressed:
                        !busy && qty > 1 ? () => setState(() => qty--) : null,
                    icon: const Icon(Icons.remove)),
                Text('$qty'),
                IconButton(
                    tooltip: 'Increase quantity',
                    onPressed: !busy && qty < stock && qty < 50
                        ? () => setState(() => qty++)
                        : null,
                    icon: const Icon(Icons.add))
              ]),
              FilledButton.icon(
                  onPressed: busy ||
                          stock < qty ||
                          (variants.isNotEmpty && variant == null)
                      ? null
                      : () async {
                          setState(() => busy = true);
                          try {
                            await ApiService()
                                .request('cart', method: 'POST', body: {
                              'product_id': d['id'],
                              'quantity': qty,
                              if (variant != null)
                                'product_variant_id': variant['id']
                            });
                            if (context.mounted) {
                              tell(context, 'Added to your cart');
                            }
                          } catch (e) {
                            if (context.mounted) tell(context, e);
                          } finally {
                            if (mounted) setState(() => busy = false);
                          }
                        },
                  icon: const Icon(Icons.add_shopping_cart),
                  label: Text(busy ? 'Adding...' : 'Add to Cart')),
            ]);
          }));
}

class CartTab extends StatefulWidget {
  const CartTab({super.key});
  @override
  State<CartTab> createState() => _CartTabState();
}

class _CartTabState extends State<CartTab> {
  final dataKey = GlobalKey<DataListState>();
  bool busy = false;
  @override
  void initState() {
    super.initState();
    ApiService.cartRevision.addListener(refresh);
  }

  void refresh() {
    dataKey.currentState?.reload();
  }

  @override
  void dispose() {
    ApiService.cartRevision.removeListener(refresh);
    super.dispose();
  }

  Future<void> change(dynamic item, {int? quantity, bool? selected}) async {
    setState(() => busy = true);
    try {
      await ApiService().request('cart/${item['id']}',
          method: quantity == null && selected == null ? 'DELETE' : 'PATCH',
          body: {
            if (quantity != null) 'quantity': quantity,
            if (selected != null) 'selected': selected
          });
    } catch (e) {
      if (mounted) tell(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Your cart')),
      body: DataList(
          key: dataKey,
          path: 'cart',
          title: 'Cart',
          builder: (d) {
            final items = d['items'] as List? ?? [];
            if (items.isEmpty) {
              return ListView(children: [
                EmptyState(
                    icon: Icons.shopping_cart_outlined,
                    title: 'Your cart is empty',
                    message: 'Find your next favorite pick on SHOPPICK.',
                    action: () => open(context, const ProductsScreen()))
              ]);
            }
            final groups = <String, List>{};
            for (final item in items) {
              groups
                  .putIfAbsent(
                      '${item['product']?['store']?['id'] ?? 0}', () => [])
                  .add(item);
            }
            final selected = items
                .where((i) => i['selected'] == true || i['selected'] == 1)
                .toList();
            return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: [
                  for (final group in groups.values)
                    Card(
                        color: Colors.white,
                        child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(children: [
                              ShopTile(shop: group.first['product']?['store']),
                              for (final x in group)
                                Column(children: [
                                  CheckboxListTile(
                                    contentPadding: EdgeInsets.zero,
                                    title: const Text('Include in checkout'),
                                    value: x['selected'] == true ||
                                        x['selected'] == 1,
                                    onChanged: busy
                                        ? null
                                        : (value) => change(x, selected: value),
                                  ),
                                  Row(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        SizedBox(
                                            width: 64,
                                            height: 72,
                                            child: ProductImage(
                                                url: x['product']?['image'])),
                                        const SizedBox(width: 12),
                                        Expanded(
                                            child: Column(
                                                crossAxisAlignment:
                                                    CrossAxisAlignment.start,
                                                children: [
                                              Text(
                                                  x['product']?['name'] ??
                                                      'Product',
                                                  style: const TextStyle(
                                                      fontWeight:
                                                          FontWeight.bold)),
                                              if (x['variant'] != null)
                                                Text(x['variant']),
                                              Text(money(x['unit_price'])),
                                              if (x['selected'] != true &&
                                                  x['selected'] != 1)
                                                const Text(
                                                    'Not selected for checkout',
                                                    style: TextStyle(
                                                        color: Colors.black54))
                                            ])),
                                        IconButton(
                                            tooltip: 'Remove item',
                                            onPressed:
                                                busy ? null : () => change(x),
                                            icon: const Icon(
                                                Icons.delete_outline)),
                                      ]),
                                  Row(children: [
                                    Expanded(
                                        child: Text(money(x['line_total']),
                                            style: const TextStyle(
                                                fontWeight: FontWeight.bold))),
                                    IconButton(
                                        tooltip: 'Decrease quantity',
                                        onPressed: busy || x['quantity'] <= 1
                                            ? null
                                            : () => change(x,
                                                quantity: x['quantity'] - 1),
                                        icon: const Icon(Icons.remove)),
                                    Text('${x['quantity']}'),
                                    IconButton(
                                        tooltip: 'Increase quantity',
                                        onPressed: busy || x['quantity'] >= 50
                                            ? null
                                            : () => change(x,
                                                quantity: x['quantity'] + 1),
                                        icon: const Icon(Icons.add))
                                  ]),
                                  const Divider()
                                ]),
                            ]))),
                  SummaryRow('Selected subtotal', money(d['subtotal'])),
                  const Text('Shipping and total are calculated at checkout.'),
                  const SizedBox(height: 16),
                  FilledButton(
                      onPressed: busy || selected.isEmpty
                          ? null
                          : () async {
                              await Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                      builder: (_) => const CheckoutScreen()));
                              refresh();
                            },
                      child: const Text('Proceed to Checkout')),
                ]);
          }));
}

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});
  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  late Future<List<dynamic>> data;
  int? addressId;
  String method = 'cod';
  bool busy = false;
  final note = TextEditingController();
  void load() {
    data = Future.wait(
        [ApiService().request('profile'), ApiService().request('checkout')]);
  }

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: FutureBuilder<List<dynamic>>(
          future: data,
          builder: (_, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Loading();
            }
            if (snapshot.hasError) {
              return ErrorState(
                  message: snapshot.error.toString(),
                  retry: () => setState(load));
            }
            final addresses = snapshot.data![0]['addresses'] as List;
            final quote = snapshot.data![1];
            final totals = quote['totals'];
            final groups = <String, List>{};
            final items = quote['items'] as List;
            for (final item in items) {
              groups
                  .putIfAbsent(
                      (item['product']?['store']?['id'] ?? 0).toString(),
                      () => [])
                  .add(item);
            }
            if (!addresses.any((a) => a['id'] == addressId)) addressId = null;
            if (addresses.isNotEmpty) {
              addressId ??= addresses.firstWhere(
                  (a) => a['is_default'] == true || a['is_default'] == 1,
                  orElse: () => addresses.first)['id'];
            }
            return ListView(padding: const EdgeInsets.all(16), children: [
              const SectionTitle('Delivery Address'),
              if (addresses.isEmpty)
                const Text(
                    'Add a delivery address on the SHOPPICK website, then return and refresh.'),
              TextButton(
                  onPressed: () => openWebsite(context, '/account/addresses'),
                  child: const Text('Manage addresses on website')),
              TextButton(
                  onPressed: busy ? null : () => setState(load),
                  child: const Text('Refresh addresses')),
              for (final a in addresses)
                InkWell(
                    onTap:
                        busy ? null : () => setState(() => addressId = a['id']),
                    child: Row(children: [
                      Icon(
                          addressId == a['id']
                              ? Icons.radio_button_checked
                              : Icons.radio_button_off,
                          color: teal),
                      Expanded(child: AddressCard(a))
                    ])),
              const SectionTitle('Order Items'),
              for (final group in groups.values) ...[
                ShopTile(shop: group.first['product']?['store']),
                for (final item in group) ...[
                  ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: SizedBox(
                          width: 48,
                          height: 56,
                          child: ProductImage(url: item['product']?['image'])),
                      title: Text(item['product']?['name'] ?? ''),
                      subtitle: Text(
                          '${item['product']?['shop'] ?? 'SHOPPICK'}\nQty ${item['quantity']}${item['variant'] == null ? '' : ' • ${item['variant']}'}'),
                      isThreeLine: true),
                  SummaryRow('Item total', money(item['line_total'])),
                ],
              ],
              const SectionTitle('Payment Method'),
              DropdownButtonFormField<String>(
                  initialValue: method,
                  isExpanded: true,
                  items: (quote['payment_methods'] as Map)
                      .entries
                      .map((e) => DropdownMenuItem(
                          value: e.key.toString(),
                          child: Text(e.value.toString())))
                      .toList(),
                  onChanged: busy ? null : (v) => setState(() => method = v!)),
              if (method != 'cod')
                const Padding(
                    padding: EdgeInsets.only(top: 8),
                    child: Text(
                        'Online payments are simulated for this demo. No card data is collected.')),
              const SectionTitle('Order Summary'),
              SummaryRow('Subtotal', money(totals['subtotal'])),
              SummaryRow('Shipping', money(totals['shipping_fee'])),
              SummaryRow('Total', money(totals['total'])),
              const SizedBox(height: 12),
              TextField(
                  controller: note,
                  maxLength: 500,
                  maxLines: 2,
                  decoration: const InputDecoration(
                      labelText: 'Order note (optional)',
                      border: OutlineInputBorder())),
              FilledButton(
                  onPressed: busy || addressId == null || items.isEmpty
                      ? null
                      : () async {
                          setState(() => busy = true);
                          try {
                            final result = await ApiService()
                                .request('checkout', method: 'POST', body: {
                              'address_id': addressId,
                              'payment_method': method,
                              'note': note.text.trim()
                            });
                            if (!context.mounted) return;
                            tell(context, 'Order placed successfully.');
                            Navigator.pushReplacement(
                                context,
                                MaterialPageRoute(
                                    builder: (_) => OrderDetailScreen(
                                        number: result['order']
                                            ['order_number'])));
                          } catch (e) {
                            if (context.mounted) tell(context, e);
                          } finally {
                            if (mounted) setState(() => busy = false);
                          }
                        },
                  child: Text(busy
                      ? 'Placing order...'
                      : 'Place Order • ${money(totals['total'])}')),
            ]);
          }));
}

class OrdersTab extends StatefulWidget {
  const OrdersTab({super.key});
  @override
  State<OrdersTab> createState() => _OrdersTabState();
}

class _OrdersTabState extends State<OrdersTab> {
  int page = 1;
  final key = GlobalKey<DataListState>();
  @override
  void initState() {
    super.initState();
    ApiService.cartRevision.addListener(refresh);
  }

  void refresh() {
    key.currentState?.reload();
  }

  @override
  void dispose() {
    ApiService.cartRevision.removeListener(refresh);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('My Orders')),
      body: DataList(
          key: key,
          path: 'orders?page=$page',
          title: 'Orders',
          builder: (d) {
            final items = d['data'] as List;
            return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: [
                  if (items.isEmpty)
                    EmptyState(
                        icon: Icons.receipt_long_outlined,
                        title: 'No orders yet',
                        message: 'Your purchases will appear here.',
                        action: () => open(context, const ProductsScreen())),
                  ...items.map((o) => OrderCard(data: o)),
                  if (d['last_page'] > 1)
                    Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          TextButton(
                              onPressed: page > 1
                                  ? () => setState(() => page--)
                                  : null,
                              child: const Text('Previous')),
                          Text('$page / ${d['last_page']}'),
                          TextButton(
                              onPressed: page < d['last_page']
                                  ? () => setState(() => page++)
                                  : null,
                              child: const Text('Next'))
                        ]),
                ]);
          }));
}

class OrderCard extends StatelessWidget {
  final dynamic data;
  const OrderCard({super.key, required this.data});
  @override
  Widget build(BuildContext context) {
    final items = data['items'] as List? ?? [];
    final shops = data['shops'] as List? ?? [];
    return Card(
        color: Colors.white,
        margin: const EdgeInsets.only(bottom: 14),
        child: InkWell(
            onTap: () =>
                open(context, OrderDetailScreen(number: data['order_number'])),
            child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Wrap(
                          spacing: 12,
                          runSpacing: 8,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            Text(data['order_number'],
                                style: const TextStyle(
                                    fontWeight: FontWeight.bold)),
                            StatusBadge(data['status'])
                          ]),
                      for (final s in shops)
                        ShopTile(shop: s['shop'], tappable: false),
                      if (items.isNotEmpty)
                        ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: SizedBox(
                                width: 56,
                                height: 64,
                                child: ProductImage(
                                    url: items.first['product_image'])),
                            title: Text(items.first['product_name'] ?? '',
                                maxLines: 2, overflow: TextOverflow.ellipsis),
                            subtitle: Text(items.length > 1
                                ? '+${items.length - 1} more items'
                                : 'Qty ${items.first['quantity']}')),
                      SummaryRow(
                          date(data['created_at']), money(data['total'])),
                      TrackingEntry(
                          orderNumber: data['order_number'],
                          shipments: data['shipments'] as List? ?? []),
                      const Text('View Order →',
                          style: TextStyle(
                              color: Color(0xff0f756d),
                              fontWeight: FontWeight.bold)),
                    ]))));
  }
}

class OrderDetailScreen extends StatelessWidget {
  final String number;
  const OrderDetailScreen({super.key, required this.number});
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Order details')),
      body: DataList(
          path: 'orders/${Uri.encodeComponent(number)}',
          title: 'Order',
          builder: (o) =>
              ListView(padding: const EdgeInsets.all(16), children: [
                Text(o['order_number'],
                    style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 22,
                        color: navy)),
                Text(date(o['created_at'])),
                Align(
                    alignment: Alignment.centerLeft,
                    child: StatusBadge(o['status'])),
                for (final s in o['shops'] as List? ?? []) ...[
                  ShopTile(shop: s['shop']),
                  Align(
                      alignment: Alignment.centerLeft,
                      child: StatusBadge(s['status']))
                ],
                TrackingEntry(
                    orderNumber: number,
                    shipments: o['shipments'] as List? ?? [],
                    details: true),
                const SectionTitle('Products'),
                for (final item in o['items'] as List? ?? []) ...[
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: SizedBox(
                        width: 56,
                        height: 64,
                        child: ProductImage(url: item['product_image'])),
                    title: Text(item['product_name'] ?? ''),
                    subtitle: Text(
                        'Qty ${item['quantity']} • ${money(item['price'])}${item['variant_label'] == null ? '' : '\n${item['variant_label']}'}'),
                  ),
                  SummaryRow('Item total', money(item['total'])),
                ],
                if (o['shipping_address'] is Map) ...[
                  const SectionTitle('Delivery Address'),
                  AddressCard(o['shipping_address'])
                ],
                const SectionTitle('Order Summary'),
                SummaryRow('Subtotal', money(o['subtotal'])),
                SummaryRow('Shipping', money(o['shipping_fee'])),
                if ((num.tryParse('${o['voucher_discount']}') ?? 0) > 0)
                  SummaryRow(
                      'Voucher discount', '-${money(o['voucher_discount'])}'),
                if ((num.tryParse('${o['shipping_discount']}') ?? 0) > 0)
                  SummaryRow(
                      'Shipping discount', '-${money(o['shipping_discount'])}'),
                SummaryRow('Total', money(o['total'])),
                SummaryRow(
                    'Payment',
                    o['payment_method'] == 'cod'
                        ? 'Cash on Delivery'
                        : label(o['payment_method'])),
                SummaryRow('Payment status',
                    o['payment_status_label'] ?? label(o['payment_status'])),
                if ((o['note'] ?? '').toString().isNotEmpty)
                  SummaryRow('Order note', o['note']),
                if ((o['progress'] as List? ?? []).isNotEmpty)
                  const SectionTitle('Order Progress'),
                for (final h in o['progress'] as List? ?? [])
                  ListTile(
                      leading:
                          const Icon(Icons.check_circle_outline, color: teal),
                      title: Text(label(h['status'])),
                      subtitle: Text(date(h['created_at']))),
              ])));
}
