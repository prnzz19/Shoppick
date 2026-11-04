part of '../main.dart';

class CatalogSearchScreen extends StatefulWidget {
  final String initialQuery;
  const CatalogSearchScreen({super.key, this.initialQuery = ''});
  @override
  State<CatalogSearchScreen> createState() => _CatalogSearchScreenState();
}

class _CatalogSearchScreenState extends State<CatalogSearchScreen> {
  late final search = TextEditingController(text: widget.initialQuery);
  Timer? debounce;
  int revision = 0;
  Map results = const {};
  String? error;
  bool busy = false;
  @override
  void initState() {
    super.initState();
    if (widget.initialQuery.isNotEmpty) {
      suggest(widget.initialQuery);
    }
  }

  @override
  void dispose() {
    debounce?.cancel();
    search.dispose();
    super.dispose();
  }

  Future<void> suggest(String value) async {
    final query = value.trim();
    final current = ++revision;
    if (query.isEmpty) {
      setState(() {
        results = const {};
        error = null;
        busy = false;
      });
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final data = await ApiService().request(
          Uri(path: 'search/suggestions', queryParameters: {'q': query})
              .toString());
      if (mounted && current == revision) {
        setState(() => results = data as Map);
      }
    } catch (e) {
      if (mounted && current == revision) {
        setState(() => error = e.toString());
      }
    } finally {
      if (mounted && current == revision) {
        setState(() => busy = false);
      }
    }
  }

  void find() {
    debounce?.cancel();
    if (search.text.trim().isNotEmpty) {
      open(context, ProductsScreen(query: search.text.trim()));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Search SHOPPICK')),
      body: ListView(padding: const EdgeInsets.all(18), children: [
        TextField(
            controller: search,
            autofocus: true,
            maxLength: 100,
            textInputAction: TextInputAction.search,
            decoration: InputDecoration(
                labelText: 'Products, categories or shops',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(
                    tooltip: 'Search',
                    onPressed: find,
                    icon: const Icon(Icons.arrow_forward))),
            onSubmitted: (_) => find(),
            onChanged: (value) {
              debounce?.cancel();
              revision++;
              debounce = Timer(
                  const Duration(milliseconds: 300), () => suggest(value));
            }),
        if (busy) const LinearProgressIndicator(),
        if (error != null) ...[
          Text(error!),
          TextButton(
              onPressed: () => suggest(search.text),
              child: const Text('Try again'))
        ],
        for (final category in results['categories'] as List? ?? [])
          ListTile(
              leading: const Icon(Icons.category_outlined, color: teal),
              title: Text(category['name']),
              onTap: () => open(
                  context, ProductsScreen(categoryId: category['id'] as int))),
        for (final product in results['products'] as List? ?? [])
          ListTile(
              leading: SizedBox(
                  width: 48,
                  height: 48,
                  child: ProductImage(url: product['image'])),
              title: Text(product['name']),
              subtitle: Text(money(product['price'])),
              onTap: () => open(context, ProductDetail(data: product))),
        if (search.text.trim().isNotEmpty)
          TextButton(
              onPressed: find, child: const Text('View all search results')),
      ]));
}
