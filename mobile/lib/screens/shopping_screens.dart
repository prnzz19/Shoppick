part of '../main.dart';

class ProductsScreen extends StatefulWidget {
  final String? query;
  final int? categoryId;
  final bool onSale;
  const ProductsScreen(
      {super.key, this.query, this.categoryId, this.onSale = false});
  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  late final search = TextEditingController(text: widget.query);
  late String query = widget.query ?? '';
  String sort = 'relevance';
  String brand = '';
  String minPrice = '';
  String maxPrice = '';
  int? minimumRating;
  bool inStock = false;
  late bool onSale = widget.onSale;

  String get productPath {
    final values = <String, String>{
      if (query.isNotEmpty) 'q': query,
      if (widget.categoryId != null) 'category': '${widget.categoryId}',
      if (sort != 'relevance') 'sort': sort,
      if (brand.isNotEmpty) 'brand': brand,
      if (minPrice.isNotEmpty) 'min_price': minPrice,
      if (maxPrice.isNotEmpty) 'max_price': maxPrice,
      if (minimumRating != null) 'rating': '$minimumRating',
      if (inStock) 'availability': 'in_stock',
      if (onSale) 'discount': '1',
    };
    return Uri(path: 'products', queryParameters: values).toString();
  }

  int get filterCount => [
        sort != 'relevance',
        brand.isNotEmpty,
        minPrice.isNotEmpty,
        maxPrice.isNotEmpty,
        minimumRating != null,
        inStock,
        onSale,
      ].where((active) => active).length;

  Future<void> showFilters() async {
    final minimum = TextEditingController(text: minPrice);
    final maximum = TextEditingController(text: maxPrice);
    final brandName = TextEditingController(text: brand);
    var nextSort = sort;
    var nextRating = minimumRating;
    var nextInStock = inStock;
    var nextOnSale = onSale;
    final applied = await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        builder: (sheetContext) => StatefulBuilder(
            builder: (_, updateSheet) => SafeArea(
                child: SingleChildScrollView(
                    padding: EdgeInsets.fromLTRB(20, 20, 20,
                        20 + MediaQuery.viewInsetsOf(sheetContext).bottom),
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const SectionTitle('Filter & Sort'),
                          DropdownButtonFormField<String>(
                              initialValue: nextSort,
                              isExpanded: true,
                              decoration:
                                  const InputDecoration(labelText: 'Sort by'),
                              items: const {
                                'relevance': 'Relevance',
                                'latest': 'Latest',
                                'popular': 'Popular',
                                'price_asc': 'Price: Low to High',
                                'price_desc': 'Price: High to Low',
                                'rating': 'Highest Rated',
                              }
                                  .entries
                                  .map((entry) => DropdownMenuItem(
                                      value: entry.key,
                                      child: Text(entry.value)))
                                  .toList(),
                              onChanged: (value) => updateSheet(
                                  () => nextSort = value ?? 'relevance')),
                          const SizedBox(height: 12),
                          TextField(
                              controller: brandName,
                              textInputAction: TextInputAction.next,
                              decoration:
                                  const InputDecoration(labelText: 'Brand')),
                          const SizedBox(height: 12),
                          Row(children: [
                            Expanded(
                                child: TextField(
                                    controller: minimum,
                                    keyboardType:
                                        const TextInputType.numberWithOptions(
                                            decimal: true),
                                    decoration: const InputDecoration(
                                        labelText: 'Minimum price'))),
                            const SizedBox(width: 12),
                            Expanded(
                                child: TextField(
                                    controller: maximum,
                                    keyboardType:
                                        const TextInputType.numberWithOptions(
                                            decimal: true),
                                    decoration: const InputDecoration(
                                        labelText: 'Maximum price')))
                          ]),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int?>(
                              initialValue: nextRating,
                              isExpanded: true,
                              decoration: const InputDecoration(
                                  labelText: 'Minimum rating'),
                              items: const [
                                DropdownMenuItem<int?>(
                                    value: null, child: Text('Any rating')),
                                DropdownMenuItem(
                                    value: 4, child: Text('4+ stars')),
                                DropdownMenuItem(
                                    value: 3, child: Text('3+ stars')),
                                DropdownMenuItem(
                                    value: 2, child: Text('2+ stars')),
                                DropdownMenuItem(
                                    value: 1, child: Text('1+ stars')),
                              ],
                              onChanged: (value) =>
                                  updateSheet(() => nextRating = value)),
                          CheckboxListTile(
                              contentPadding: EdgeInsets.zero,
                              value: nextInStock,
                              title: const Text('In stock only'),
                              onChanged: (value) => updateSheet(
                                  () => nextInStock = value ?? false)),
                          CheckboxListTile(
                              contentPadding: EdgeInsets.zero,
                              value: nextOnSale,
                              title: const Text('On sale'),
                              onChanged: (value) => updateSheet(
                                  () => nextOnSale = value ?? false)),
                          const SizedBox(height: 8),
                          Row(children: [
                            TextButton(
                                onPressed: () {
                                  minimum.clear();
                                  maximum.clear();
                                  brandName.clear();
                                  updateSheet(() {
                                    nextSort = 'relevance';
                                    nextRating = null;
                                    nextInStock = false;
                                    nextOnSale = false;
                                  });
                                },
                                child: const Text('Reset')),
                            const Spacer(),
                            FilledButton(
                                onPressed: () =>
                                    Navigator.pop(sheetContext, true),
                                child: const Text('Apply'))
                          ])
                        ])))));
    if (applied == true && mounted) {
      setState(() {
        sort = nextSort;
        brand = brandName.text.trim();
        minPrice = minimum.text.trim();
        maxPrice = maximum.text.trim();
        minimumRating = nextRating;
        inStock = nextInStock;
        onSale = nextOnSale;
      });
    }
    minimum.dispose();
    maximum.dispose();
    brandName.dispose();
  }

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Products'), actions: [
        Badge(
            isLabelVisible: filterCount > 0,
            label: Text('$filterCount'),
            child: IconButton(
                tooltip: 'Filter and sort',
                onPressed: showFilters,
                icon: const Icon(Icons.tune)))
      ]),
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
        Expanded(child: PagedProducts(path: productPath)),
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
            final stock = math.min(
                num.tryParse('${d['stock']}')?.toInt() ?? 0,
                num.tryParse('${variant?['stock'] ?? d['stock']}')?.toInt() ??
                    0);
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
              if (d['store'] is Map)
                Padding(
                    padding: const EdgeInsets.only(top: 10),
                    child: OutlinedButton.icon(
                        onPressed: busy
                            ? null
                            : () async {
                                setState(() => busy = true);
                                try {
                                  final result = await ApiService().request(
                                      'messages/start',
                                      method: 'POST',
                                      body: {'product_id': d['id']});
                                  final conversation =
                                      result['conversation'] as Map;
                                  if (context.mounted) {
                                    await Navigator.push(
                                        context,
                                        MaterialPageRoute(
                                            builder: (_) => ConversationScreen(
                                                conversationId:
                                                    conversation['id'] as int,
                                                initialStore:
                                                    conversation['store']
                                                        as Map?)));
                                  }
                                } catch (exception) {
                                  if (context.mounted) {
                                    tell(context, exception);
                                  }
                                } finally {
                                  if (mounted) setState(() => busy = false);
                                }
                              },
                        icon: const Icon(Icons.chat_bubble_outline),
                        label: const Text('Message Seller'))),
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
              const SizedBox(height: 10),
              FilledButton.tonalIcon(
                  onPressed: busy ||
                          stock < qty ||
                          (variants.isNotEmpty && variant == null)
                      ? null
                      : () => open(
                          context,
                          CheckoutScreen(buyNow: {
                            'mode': 'buy_now',
                            'product_id': d['id'],
                            'quantity': qty,
                            if (variant != null)
                              'product_variant_id': variant['id'],
                          })),
                  icon: const Icon(Icons.flash_on_outlined),
                  label: const Text('Buy Now')),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                  onPressed: busy
                      ? null
                      : () async {
                          setState(() => busy = true);
                          try {
                            await ApiService().toggleWishlist(d['id'] as int);
                            if (context.mounted) {
                              tell(context, 'Wishlist updated');
                            }
                          } catch (e) {
                            if (context.mounted) tell(context, e);
                          } finally {
                            if (mounted) setState(() => busy = false);
                          }
                        },
                  icon: const Icon(Icons.favorite_border),
                  label: const Text('Add to Wishlist')),
              const SizedBox(height: 24),
              ProductReviewsSection(productId: d['id'] as int),
              TextButton.icon(
                  onPressed: () => open(
                      context, ProductReportScreen(productId: d['id'] as int)),
                  icon: const Icon(Icons.flag_outlined),
                  label: const Text('Report product')),
              if ((d['related'] as List? ?? []).isNotEmpty) ...[
                const SectionTitle('You may also like'),
                ProductGrid(items: d['related'])
              ],
            ]);
          }));
}

class ProductReviewsSection extends StatefulWidget {
  final int productId;
  const ProductReviewsSection({super.key, required this.productId});
  @override
  State<ProductReviewsSection> createState() => _ProductReviewsSectionState();
}

class _ProductReviewsSectionState extends State<ProductReviewsSection> {
  int page = 1;
  late Future<dynamic> reviews;

  @override
  void initState() {
    super.initState();
    reviews = load();
  }

  Future<dynamic> load() =>
      ApiService().request('products/${widget.productId}/reviews?page=$page');

  void changePage(int value) => setState(() {
        page = value;
        reviews = load();
      });

  Widget stars(dynamic value, {double size = 18}) {
    final rating = (num.tryParse('$value') ?? 0).round();
    return Row(
        mainAxisSize: MainAxisSize.min,
        children: List.generate(
            5,
            (index) => Icon(index < rating ? Icons.star : Icons.star_border,
                size: size, color: Colors.amber.shade700)));
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<dynamic>(
      future: reviews,
      builder: (_, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()));
        }
        if (snapshot.hasError) {
          return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionTitle('Ratings & Reviews'),
                Text(snapshot.error.toString()),
                TextButton.icon(
                    onPressed: () => changePage(page),
                    icon: const Icon(Icons.refresh),
                    label: const Text('Try again'))
              ]);
        }
        final response = snapshot.data as Map? ?? {};
        final pagination = response['reviews'] as Map? ?? {};
        final items = pagination['data'] as List? ?? [];
        final current = (pagination['current_page'] as num?)?.toInt() ?? 1;
        final last = (pagination['last_page'] as num?)?.toInt() ?? 1;
        final count = (response['rating_count'] as num?)?.toInt() ?? 0;
        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const SectionTitle('Ratings & Reviews'),
          Row(children: [
            Text('${response['rating_avg'] ?? 0}',
                style: const TextStyle(
                    fontSize: 28, fontWeight: FontWeight.w800, color: navy)),
            const SizedBox(width: 10),
            stars(response['rating_avg'], size: 22),
            const SizedBox(width: 8),
            Expanded(child: Text('$count ${count == 1 ? 'review' : 'reviews'}'))
          ]),
          const SizedBox(height: 12),
          if (items.isEmpty)
            const Padding(
                padding: EdgeInsets.symmetric(vertical: 18),
                child: Text('No reviews yet.')),
          for (final item in items) ...[
            const Divider(height: 24),
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              CircleAvatar(
                  radius: 18,
                  backgroundColor: const Color(0xffccfbf1),
                  child: Text(item['reviewer_initial'] ?? 'C',
                      style: const TextStyle(
                          color: navy, fontWeight: FontWeight.bold))),
              const SizedBox(width: 10),
              Expanded(
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                    Text(item['reviewer_name'] ?? 'Customer',
                        style: const TextStyle(fontWeight: FontWeight.w700)),
                    Row(children: [
                      stars(item['rating']),
                      const SizedBox(width: 8),
                      Text(date(item['created_at']))
                    ]),
                    if ((item['comment'] ?? '').toString().trim().isNotEmpty)
                      Padding(
                          padding: const EdgeInsets.only(top: 6),
                          child: Text(item['comment']))
                  ]))
            ])
          ],
          if (last > 1)
            Padding(
                padding: const EdgeInsets.only(top: 16),
                child:
                    Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                  IconButton(
                      tooltip: 'Previous reviews',
                      onPressed:
                          current > 1 ? () => changePage(current - 1) : null,
                      icon: const Icon(Icons.chevron_left)),
                  Text('$current / $last'),
                  IconButton(
                      tooltip: 'Next reviews',
                      onPressed:
                          current < last ? () => changePage(current + 1) : null,
                      icon: const Icon(Icons.chevron_right))
                ]))
        ]);
      });
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
                  CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Select all items'),
                      value: selected.length == items.length,
                      onChanged: busy
                          ? null
                          : (value) async {
                              setState(() => busy = true);
                              try {
                                await ApiService().request('cart/selection',
                                    method: 'PATCH',
                                    body: {
                                      'item_ids': items
                                          .map((item) => item['id'])
                                          .toList(),
                                      'selected': value ?? false
                                    });
                              } catch (e) {
                                if (context.mounted) tell(context, e);
                              } finally {
                                if (mounted) setState(() => busy = false);
                              }
                            }),
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
                                              if (x['warning'] != null)
                                                Text(x['warning'],
                                                    style: const TextStyle(
                                                        color: Colors.red)),
                                              Text(
                                                  '${x['max_stock']} available',
                                                  style: const TextStyle(
                                                      color: Colors.black54,
                                                      fontSize: 12)),
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
                                        onPressed: busy ||
                                                x['quantity'] >= 50 ||
                                                x['quantity'] >= x['max_stock']
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
  final Map<String, dynamic>? buyNow;
  const CheckoutScreen({super.key, this.buyNow});
  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  late Future<List<dynamic>> data;
  int? addressId;
  String method = 'cod';
  bool busy = false;
  final note = TextEditingController();
  final voucher = TextEditingController();
  List<String> voucherCodes = [];
  final checkoutKey = messageClientToken();

  Future<void> recover() async {
    if (busy) return;
    setState(() => busy = true);
    try {
      final result = await ApiService().recoverCheckout();
      if (!mounted) return;
      Navigator.pushReplacement(
          context,
          MaterialPageRoute(
              builder: (_) =>
                  OrderSuccessScreen(number: result['order']['order_number'])));
    } catch (e) {
      if (mounted) tell(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  String get checkoutPath {
    final params = <String, dynamic>{
      ...?widget.buyNow?.map((key, value) => MapEntry(key, value.toString())),
      if (voucherCodes.isNotEmpty) 'voucher_codes[]': voucherCodes,
    };
    return Uri(
            path: 'checkout', queryParameters: params.isEmpty ? null : params)
        .toString();
  }

  Future<List<dynamic>> fetchCheckout() => Future.wait(
      [ApiService().request('profile'), ApiService().request(checkoutPath)]);

  void load() {
    data = fetchCheckout();
  }

  Future<void> updateVouchers(List<String> codes) async {
    final previous = voucherCodes;
    setState(() {
      busy = true;
      voucherCodes = codes;
    });
    try {
      final result = await fetchCheckout();
      if (!mounted) return;
      setState(() {
        data = Future.value(result);
        voucherCodes = (result[1]['voucher_codes'] as List? ?? [])
            .map((code) => code.toString())
            .toList();
        voucher.clear();
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => voucherCodes = previous);
      tell(context, error);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> applyVoucher(String code, List options) {
    dynamic chosen;
    for (final option in options) {
      if (option['code'] == code) {
        chosen = option;
      }
    }
    final retained = voucherCodes.where((current) {
      for (final option in options) {
        if (option['code'] == current && chosen != null) {
          if (chosen['source_type'] == 'platform' &&
              option['source_type'] == 'platform') {
            return false;
          }
          if (chosen['source_type'] == 'shop' &&
              option['source_type'] == 'shop' &&
              chosen['store_id'] == option['store_id']) {
            return false;
          }
        }
      }
      return current != code;
    }).toList();
    return updateVouchers([...retained, code]);
  }

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    note.dispose();
    voucher.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Checkout'), actions: [
        IconButton(
            onPressed: busy ? null : recover,
            tooltip: 'Check last submission',
            icon: const Icon(Icons.history))
      ]),
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
            final availableVouchers =
                quote['available_vouchers'] as List? ?? [];
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
                const Text('Add a delivery address before placing your order.'),
              TextButton(
                  onPressed: busy
                      ? null
                      : () async {
                          await open(context, const AddressesScreen());
                          if (mounted) setState(load);
                        },
                  child: const Text('Manage addresses')),
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
              const SectionTitle('Order Summary'),
              SummaryRow('Subtotal', money(totals['subtotal'])),
              SummaryRow('Shipping', money(totals['shipping_fee'])),
              if ((num.tryParse('${totals['voucher_discount']}') ?? 0) > 0)
                SummaryRow('Voucher discount',
                    '-${money(totals['voucher_discount'])}'),
              if ((num.tryParse('${totals['shipping_discount']}') ?? 0) > 0)
                SummaryRow('Shipping discount',
                    '-${money(totals['shipping_discount'])}'),
              SummaryRow('Total', money(totals['total'])),
              if (availableVouchers.isNotEmpty)
                const SectionTitle('Available Vouchers'),
              for (final option in availableVouchers)
                Card(
                    color: Colors.white,
                    child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(option['title'] ?? option['code'],
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w700)),
                              Text(
                                  '${option['source_type'] == 'platform' ? 'SHOPPICK' : option['shop'] ?? 'Shop'} • ${option['code']}'),
                              Text(option['type'] == 'free_shipping'
                                  ? 'Free shipping'
                                  : option['type'] == 'percent'
                                      ? '${option['value']}% off'
                                      : '${money(option['value'])} off'),
                              if (option['message'] != null &&
                                  option['applied'] != true)
                                Text(option['message'],
                                    style: const TextStyle(
                                        color: Colors.black54, fontSize: 12)),
                              const SizedBox(height: 8),
                              Align(
                                  alignment: Alignment.centerRight,
                                  child: option['applied'] == true
                                      ? OutlinedButton(
                                          onPressed: busy
                                              ? null
                                              : () => updateVouchers(
                                                  voucherCodes
                                                      .where((code) =>
                                                          code !=
                                                          option['code'])
                                                      .toList()),
                                          child: const Text('Remove'))
                                      : FilledButton(
                                          onPressed:
                                              busy || option['eligible'] != true
                                                  ? null
                                                  : () => applyVoucher(
                                                      option['code'].toString(),
                                                      availableVouchers),
                                          child: Text(option['eligible'] == true
                                              ? 'Apply'
                                              : 'Not Eligible')))
                            ]))),
              const SizedBox(height: 12),
              TextField(
                  controller: voucher,
                  enabled: !busy,
                  textCapitalization: TextCapitalization.characters,
                  textInputAction: TextInputAction.done,
                  onSubmitted: (_) {
                    final code = voucher.text.trim().toUpperCase();
                    if (code.isNotEmpty && !voucherCodes.contains(code)) {
                      applyVoucher(code, availableVouchers);
                    }
                  },
                  decoration: InputDecoration(
                      labelText: 'Voucher code',
                      suffixIcon: IconButton(
                          tooltip: 'Apply voucher',
                          onPressed: busy
                              ? null
                              : () {
                                  final code =
                                      voucher.text.trim().toUpperCase();
                                  if (code.isNotEmpty &&
                                      !voucherCodes.contains(code)) {
                                    applyVoucher(code, availableVouchers);
                                  }
                                },
                          icon: const Icon(Icons.check)))),
              for (final code in voucherCodes)
                Align(
                    alignment: Alignment.centerLeft,
                    child: InputChip(
                        label: Text(code),
                        onDeleted: busy
                            ? null
                            : () => updateVouchers(voucherCodes
                                .where((x) => x != code)
                                .toList()))),
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
                            final result = await ApiService().submitCheckout({
                              'address_id': addressId,
                              'payment_method': method,
                              'note': note.text.trim(),
                              'idempotency_key': checkoutKey,
                              'quote_token': quote['quote_token'],
                              ...?widget.buyNow,
                              if (voucherCodes.isNotEmpty)
                                'voucher_codes': voucherCodes
                            });
                            if (!context.mounted) return;
                            Navigator.pushReplacement(
                                context,
                                MaterialPageRoute(
                                    builder: (_) => OrderSuccessScreen(
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

class OrderSuccessScreen extends StatelessWidget {
  final String number;
  const OrderSuccessScreen({super.key, required this.number});
  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Order placed')),
      body: Center(
          child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.check_circle_outline, color: teal, size: 80),
                const SizedBox(height: 20),
                const Text('Thank you for your order!',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                        color: navy)),
                const SizedBox(height: 10),
                Text(number),
                const SizedBox(height: 24),
                FilledButton(
                    onPressed: () => Navigator.pushReplacement(
                        context,
                        MaterialPageRoute(
                            builder: (_) => OrderDetailScreen(number: number))),
                    child: const Text('View order')),
                TextButton(
                    onPressed: () =>
                        Navigator.popUntil(context, (route) => route.isFirst),
                    child: const Text('Continue shopping')),
              ]))));
}

class OrdersTab extends StatefulWidget {
  const OrdersTab({super.key});
  @override
  State<OrdersTab> createState() => _OrdersTabState();
}

class _OrdersTabState extends State<OrdersTab> {
  int page = 1;
  String tab = 'all',
      historyStatus = 'all',
      period = 'all',
      sort = 'newest',
      query = '';
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
          path: Uri(path: 'orders', queryParameters: {
            'page': '$page',
            'tab': tab,
            'history_status': historyStatus,
            'date': period,
            'sort': sort,
            if (query.isNotEmpty) 'q': query
          }).toString(),
          title: 'Orders',
          builder: (d) {
            final items = d['data'] as List;
            return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: [
                  SizedBox(
                      height: 52,
                      child:
                          ListView(scrollDirection: Axis.horizontal, children: [
                        for (final value in const {
                          'all': 'All',
                          'to_pay': 'To Pay',
                          'to_ship': 'To Ship',
                          'to_receive': 'To Receive',
                          'completed': 'Completed',
                          'cancelled': 'Cancelled',
                          'history': 'History'
                        }.entries)
                          Padding(
                              padding: const EdgeInsets.only(right: 8),
                              child: ChoiceChip(
                                  label: Text(value.value),
                                  selected: tab == value.key,
                                  onSelected: (_) => setState(() {
                                        tab = value.key;
                                        page = 1;
                                      }))),
                      ])),
                  TextFormField(
                      initialValue: query,
                      textInputAction: TextInputAction.search,
                      decoration: const InputDecoration(
                          labelText: 'Search orders',
                          prefixIcon: Icon(Icons.search)),
                      onFieldSubmitted: (value) => setState(() {
                            query = value.trim();
                            page = 1;
                          })),
                  if (tab == 'history') ...[
                    DropdownButtonFormField<String>(
                        initialValue: historyStatus,
                        decoration:
                            const InputDecoration(labelText: 'History status'),
                        items: [
                          for (final value in [
                            'all',
                            'completed',
                            'cancelled',
                            'refunded'
                          ])
                            DropdownMenuItem(
                                value: value, child: Text(label(value)))
                        ],
                        onChanged: (v) => setState(() {
                              historyStatus = v!;
                              page = 1;
                            })),
                    DropdownButtonFormField<String>(
                        initialValue: period,
                        decoration:
                            const InputDecoration(labelText: 'Date range'),
                        items: [
                          for (final value in [
                            'all',
                            '30_days',
                            '3_months',
                            '6_months',
                            'this_year'
                          ])
                            DropdownMenuItem(
                                value: value, child: Text(label(value)))
                        ],
                        onChanged: (v) => setState(() {
                              period = v!;
                              page = 1;
                            })),
                    DropdownButtonFormField<String>(
                        initialValue: sort,
                        decoration:
                            const InputDecoration(labelText: 'Sort orders'),
                        items: [
                          for (final value in [
                            'newest',
                            'oldest',
                            'total_high',
                            'total_low'
                          ])
                            DropdownMenuItem(
                                value: value, child: Text(label(value)))
                        ],
                        onChanged: (v) => setState(() {
                              sort = v!;
                              page = 1;
                            })),
                  ],
                  const SizedBox(height: 12),
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

class OrderDetailScreen extends StatefulWidget {
  final String number;
  const OrderDetailScreen({super.key, required this.number});
  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  final dataKey = GlobalKey<DataListState>();
  int? startingSellerOrder;
  String? actionBusy;

  Future<void> runOrderAction(String action,
      {Map<String, dynamic>? body, String? success}) async {
    setState(() => actionBusy = action);
    try {
      await ApiService().request(
          'orders/${Uri.encodeComponent(widget.number)}/$action',
          method: 'POST',
          body: body);
      await dataKey.currentState?.reload();
      if (mounted && success != null) tell(context, success);
    } catch (error) {
      if (mounted) tell(context, error);
    } finally {
      if (mounted) setState(() => actionBusy = null);
    }
  }

  Future<void> cancelOrder() async {
    final reason = TextEditingController();
    final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
                title: const Text('Cancel order?'),
                content: TextField(
                    controller: reason,
                    maxLength: 255,
                    maxLines: 3,
                    decoration:
                        const InputDecoration(labelText: 'Reason (optional)')),
                actions: [
                  TextButton(
                      onPressed: () => Navigator.pop(dialogContext, false),
                      child: const Text('Keep Order')),
                  FilledButton(
                      onPressed: () => Navigator.pop(dialogContext, true),
                      child: const Text('Cancel Order'))
                ]));
    final value = reason.text.trim();
    reason.dispose();
    if (confirmed == true && mounted) {
      await runOrderAction('cancel',
          body: {if (value.isNotEmpty) 'reason': value},
          success: 'Order cancelled.');
    }
  }

  Future<void> confirmReceived() async {
    final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
                title: const Text('Confirm order received?'),
                content: const Text(
                    'This completes the order and cannot be undone.'),
                actions: [
                  TextButton(
                      onPressed: () => Navigator.pop(dialogContext, false),
                      child: const Text('Not Yet')),
                  FilledButton(
                      onPressed: () => Navigator.pop(dialogContext, true),
                      child: const Text('Confirm Received'))
                ]));
    if (confirmed == true && mounted) {
      await runOrderAction('confirm', success: 'Order marked as received.');
    }
  }

  Future<void> buyAgain(dynamic item) async {
    final action = 'items/${item['id']}/buy-again';
    await runOrderAction(action, success: 'Product added to cart.');
    await ApiService().refreshCartCount();
  }

  Future<void> messageSeller(dynamic sellerOrder) async {
    final id = sellerOrder['id'] as int;
    setState(() => startingSellerOrder = id);
    try {
      final result = await ApiService().request('messages/start',
          method: 'POST', body: {'seller_order_id': id});
      final conversation = result['conversation'] as Map;
      if (mounted) {
        await Navigator.push(
            context,
            MaterialPageRoute(
                builder: (_) => ConversationScreen(
                    conversationId: conversation['id'] as int,
                    initialStore: conversation['store'] as Map?)));
      }
    } catch (exception) {
      if (mounted) tell(context, exception);
    } finally {
      if (mounted) setState(() => startingSellerOrder = null);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Order details')),
      body: DataList(
          key: dataKey,
          path: 'orders/${Uri.encodeComponent(widget.number)}',
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
                if (o['actions'] is Map &&
                    (o['actions']['can_cancel'] == true ||
                        o['actions']['can_confirm_received'] == true))
                  Padding(
                      padding: const EdgeInsets.only(top: 12),
                      child: Wrap(spacing: 8, runSpacing: 8, children: [
                        if (o['actions']['can_cancel'] == true)
                          OutlinedButton.icon(
                              onPressed:
                                  actionBusy == null ? cancelOrder : null,
                              icon: const Icon(Icons.cancel_outlined),
                              label: Text(actionBusy == 'cancel'
                                  ? 'Cancelling...'
                                  : 'Cancel Order')),
                        if (o['actions']['can_confirm_received'] == true)
                          FilledButton.icon(
                              onPressed:
                                  actionBusy == null ? confirmReceived : null,
                              icon: const Icon(Icons.inventory_2_outlined),
                              label: Text(actionBusy == 'confirm'
                                  ? 'Confirming...'
                                  : 'Confirm Received'))
                      ])),
                const SectionTitle('Seller deliveries'),
                for (final s in o['shops'] as List? ?? [])
                  Card(
                      margin: const EdgeInsets.only(bottom: 12),
                      child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                ShopTile(shop: s['shop']),
                                Wrap(
                                    spacing: 8,
                                    runSpacing: 8,
                                    crossAxisAlignment:
                                        WrapCrossAlignment.center,
                                    children: [
                                      StatusBadge(
                                          s['workflow_status'] ?? s['status']),
                                      if (s['seller_order_number'] != null)
                                        Text('${s['seller_order_number']}',
                                            style: const TextStyle(
                                                fontWeight: FontWeight.w600))
                                    ]),
                                if (s['shipment'] is Map &&
                                    s['shipment']['tracking_number'] != null)
                                  Padding(
                                      padding: const EdgeInsets.only(top: 8),
                                      child: Text(
                                          'Tracking: ${s['shipment']['tracking_number']}')),
                                for (final item in s['items'] as List? ?? [])
                                  ListTile(
                                      contentPadding: EdgeInsets.zero,
                                      dense: true,
                                      leading: SizedBox(
                                          width: 44,
                                          height: 48,
                                          child: ProductImage(
                                              url: item['product_image'])),
                                      title: Text(item['product_name'] ?? ''),
                                      subtitle: Text(
                                          'Qty ${item['quantity']} • ${money(item['total'])}')),
                                Align(
                                    alignment: Alignment.centerLeft,
                                    child: TextButton.icon(
                                        onPressed: startingSellerOrder == null
                                            ? () => messageSeller(s)
                                            : null,
                                        icon: startingSellerOrder == s['id']
                                            ? const SizedBox(
                                                width: 16,
                                                height: 16,
                                                child:
                                                    CircularProgressIndicator(
                                                        strokeWidth: 2))
                                            : const Icon(
                                                Icons.chat_bubble_outline),
                                        label: Text(
                                            'Message ${s['shop']?['name'] ?? 'Seller'}')))
                              ]))),
                TrackingEntry(
                    orderNumber: widget.number,
                    shipments: o['shipments'] as List? ?? [],
                    details: true),
                const SectionTitle('All products'),
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
                  if (o['actions']?['can_buy_again'] == true)
                    Align(
                        alignment: Alignment.centerLeft,
                        child: OutlinedButton.icon(
                            onPressed: actionBusy == null
                                ? () => buyAgain(item)
                                : null,
                            icon: const Icon(Icons.replay),
                            label: Text(
                                actionBusy == 'items/${item['id']}/buy-again'
                                    ? 'Adding...'
                                    : 'Buy Again'))),
                  if (item['review'] is Map)
                    Container(
                        margin: const EdgeInsets.only(bottom: 10),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                            color: const Color(0xffecfdf5),
                            borderRadius: BorderRadius.circular(12)),
                        child: Row(children: [
                          const Icon(Icons.check_circle, color: teal),
                          const SizedBox(width: 10),
                          Expanded(
                              child: Text(
                                  'Reviewed: ${item['review']['rating']} / 5 stars',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w700)))
                        ])),
                  if (item['review'] == null && item['review_eligible'] == true)
                    Align(
                        alignment: Alignment.centerLeft,
                        child: OutlinedButton.icon(
                            onPressed: () async {
                              final saved = await Navigator.push<bool>(
                                  context,
                                  MaterialPageRoute(
                                      builder: (_) => ReviewFormScreen(
                                          orderNumber: widget.number,
                                          productId: item['product_id'] as int,
                                          productName: item['product_name'] ??
                                              'Product')));
                              if (saved == true) {
                                await dataKey.currentState?.reload();
                              }
                            },
                            icon: const Icon(Icons.rate_review_outlined),
                            label: const Text('Write Review'))),
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

class ReviewFormScreen extends StatefulWidget {
  final String orderNumber;
  final int productId;
  final String productName;
  const ReviewFormScreen(
      {super.key,
      required this.orderNumber,
      required this.productId,
      required this.productName});
  @override
  State<ReviewFormScreen> createState() => _ReviewFormScreenState();
}

class _ReviewFormScreenState extends State<ReviewFormScreen> {
  final comment = TextEditingController();
  int rating = 5;
  bool busy = false;

  @override
  void dispose() {
    comment.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    setState(() => busy = true);
    try {
      await ApiService().request(
          'orders/${Uri.encodeComponent(widget.orderNumber)}/products/${widget.productId}/review',
          method: 'POST',
          body: {
            'rating': rating,
            'comment': comment.text.trim().isEmpty ? null : comment.text.trim()
          });
      if (mounted) {
        tell(context, 'Thank you for your review!');
        Navigator.pop(context, true);
      }
    } catch (error) {
      if (mounted) tell(context, error);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Write a review')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        Text(widget.productName,
            style: const TextStyle(
                fontSize: 21, fontWeight: FontWeight.w800, color: navy)),
        const SizedBox(height: 22),
        const Text('Your rating',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
        const SizedBox(height: 8),
        Wrap(
            spacing: 4,
            children: List.generate(
                5,
                (index) => IconButton(
                    tooltip: '${index + 1} stars',
                    onPressed:
                        busy ? null : () => setState(() => rating = index + 1),
                    iconSize: 38,
                    color: Colors.amber.shade700,
                    icon: Icon(
                        index < rating ? Icons.star : Icons.star_border)))),
        Text('$rating out of 5 stars'),
        const SizedBox(height: 22),
        TextField(
            controller: comment,
            enabled: !busy,
            maxLength: 1000,
            minLines: 4,
            maxLines: 7,
            decoration: const InputDecoration(
                labelText: 'Comment (optional)',
                hintText: 'Share your experience with this product',
                alignLabelWithHint: true,
                border: OutlineInputBorder())),
        const SizedBox(height: 16),
        FilledButton.icon(
            onPressed: busy ? null : submit,
            icon: busy
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.send),
            label: Text(busy ? 'Submitting...' : 'Submit Review'))
      ]));
}
