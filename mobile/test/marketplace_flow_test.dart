import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shoppick_mobile/main.dart';

final shop = {
  'id': 1,
  'name': 'Local SHOPPICK Store',
  'slug': 'local',
  'description': 'Products from our local shop',
  'location': 'Manila'
};
final product = {
  'id': 1,
  'name': 'Long product name for accessible mobile shopping',
  'price': 100,
  'original_price': 120,
  'stock': 10,
  'store': shop,
  'shop': shop['name'],
  'images': [],
  'variants': []
};
final item = {
  'id': 1,
  'product': product,
  'quantity': 2,
  'selected': true,
  'unit_price': 100,
  'line_total': 200
};
final address = {
  'id': 1,
  'full_name': 'Test Buyer',
  'phone': '09171234567',
  'address_line': '123 Sample Street',
  'city': 'Manila',
  'country': 'PH',
  'is_default': true
};
final order = {
  'order_number': 'SP123',
  'status': 'ready_to_ship',
  'created_at': '2026-09-01',
  'subtotal': 200,
  'shipping_fee': 50,
  'total': 250,
  'payment_method': 'cod',
  'payment_status': 'unpaid',
  'shipping_address': address,
  'shops': [
    {'shop': shop, 'status': 'ready_to_ship'}
  ],
  'items': [
    {'product_name': product['name'], 'quantity': 2, 'price': 100, 'total': 200}
  ],
  'progress': [
    {'status': 'pending', 'created_at': '2026-09-01'}
  ]
};
final category = {'id': 1, 'name': 'Electronics', 'children': []};
Map<String, dynamic> page(List items) =>
    {'data': items, 'current_page': 1, 'last_page': 1, 'total': items.length};

dynamic responseFor(String path) {
  if (path.endsWith('/home')) {
    return {
      'categories': [category],
      'featured': [product],
      'latest': [product],
      'deals': []
    };
  }
  if (path.endsWith('/categories')) return [category];
  if (path.endsWith('/products/1')) return product;
  if (path.endsWith('/products')) {
    return {
      'data': page([product])
    };
  }
  if (path.endsWith('/shops/local')) {
    return {
      'shop': shop,
      'products': page([product])
    };
  }
  if (path.endsWith('/cart')) {
    return {
      'items': [item],
      'subtotal': 200,
      'cart_count': 2
    };
  }
  if (path.endsWith('/checkout')) {
    return {
      'items': [item],
      'totals': {'subtotal': 200, 'shipping_fee': 50, 'total': 250},
      'payment_methods': {'cod': 'Cash on Delivery'}
    };
  }
  if (path.endsWith('/orders/SP123')) return order;
  if (path.endsWith('/orders')) return page([order]);
  if (path.endsWith('/profile')) {
    return {
      'user': {
        'name': 'Test Buyer',
        'email': 'buyer@example.test',
        'phone': '09171234567',
        'created_at': '2026-01-01',
        'is_seller': false
      },
      'addresses': [address]
    };
  }
  if (path.endsWith('/seller/application')) {
    return {
      'application': null,
      'is_seller': false,
      'seller_access': false,
      'categories': [category]
    };
  }
  throw StateError('Unexpected route $path');
}

void main() {
  setUp(
      () => FlutterSecureStorage.setMockInitialValues({'auth_token': 'test'}));
  testWidgets('checkout cannot place an order without an address',
      (tester) async {
    await http.runWithClient(() async {
      await tester.pumpWidget(const MaterialApp(home: CheckoutScreen()));
      await tester.pumpAndSettle();
      final button =
          find.widgetWithText(FilledButton, 'Place Order • ${money(250)}');
      await tester.scrollUntilVisible(button, 400,
          scrollable: find.byType(Scrollable).first);
      expect(tester.widget<FilledButton>(button).onPressed, isNull);
    },
        () => MockClient((r) async {
              expect(r.method, 'GET');
              final data = responseFor(r.url.path);
              if (r.url.path.endsWith('/profile')) data['addresses'] = [];
              return http.Response(jsonEncode(data), 200);
            }));
  });
  testWidgets('order card navigates to its order details', (tester) async {
    await http.runWithClient(() async {
      await tester.pumpWidget(const MaterialApp(home: OrdersTab()));
      await tester.pumpAndSettle();
      await tester.tap(find.text('SP123'));
      await tester.pumpAndSettle();
      expect(find.byType(OrderDetailScreen), findsOneWidget);
      expect(find.text('SP123'), findsOneWidget);
    },
        () => MockClient((r) async =>
            http.Response(jsonEncode(responseFor(r.url.path)), 200)));
  });
  testWidgets('failed network image displays the fallback', (tester) async {
    await tester.pumpWidget(const MaterialApp(
        home: Scaffold(
            body: SizedBox(
                height: 100,
                child:
                    ProductImage(url: 'https://example.test/missing.png')))));
    await tester.pumpAndSettle();
    expect(find.byIcon(Icons.shopping_bag_outlined), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  final screens = <String, Widget Function()>{
    'Home': () => const Scaffold(body: HomeTab()),
    'Categories': () => const Scaffold(body: CategoriesTab()),
    'Products': () => const ProductsScreen(),
    'Product details': () => ProductDetail(data: product),
    'Shop': () => const ShopScreen(slug: 'local'),
    'Cart': () => const CartTab(),
    'Checkout': () => const CheckoutScreen(),
    'Orders': () => const OrdersTab(),
    'Order details': () => const OrderDetailScreen(number: 'SP123'),
    'Account': () => const AccountTab(),
    'Seller application': () => const SellerApplicationScreen(),
    'Navigation': () => const MarketplaceScreen(),
  };
  for (final width in [320.0, 360.0, 390.0]) {
    for (final scale in [1.0, 1.5, 2.0]) {
      for (final screen in screens.entries) {
        testWidgets('${screen.key} fits $width at text scale $scale',
            (tester) async {
          tester.view.physicalSize = Size(width, 800);
          tester.view.devicePixelRatio = 1;
          addTearDown(tester.view.resetPhysicalSize);
          addTearDown(tester.view.resetDevicePixelRatio);
          await http.runWithClient(() async {
            await tester.pumpWidget(MaterialApp(
                builder: (context, child) => MediaQuery(
                    data: MediaQuery.of(context)
                        .copyWith(textScaler: TextScaler.linear(scale)),
                    child: child!),
                home: screen.value()));
            await tester.pumpAndSettle();
            expect(tester.takeException(), isNull);
            // Visit lazy list content, including bottom actions.
            for (var i = 0; i < 8; i++) {
              final scrolls = find.byType(Scrollable).hitTestable();
              if (scrolls.evaluate().isEmpty) break;
              await tester.drag(scrolls.first, const Offset(0, -500));
              await tester.pumpAndSettle();
              expect(tester.takeException(), isNull);
            }
            await tester.pumpWidget(const SizedBox());
          },
              () => MockClient((r) async =>
                  http.Response(jsonEncode(responseFor(r.url.path)), 200)));
        });
      }
    }
  }
  for (final status in [
    null,
    'pending',
    'needs_resubmission',
    'rejected',
    'approved'
  ]) {
    for (final access in [false, true]) {
      if (access && status != 'approved') continue;
      testWidgets('seller state $status with access $access', (tester) async {
        await http.runWithClient(() async {
          await tester
              .pumpWidget(const MaterialApp(home: SellerApplicationScreen()));
          await tester.pumpAndSettle();
          expect(find.text('Open Seller Center on Website'),
              access ? findsOneWidget : findsNothing);
          expect(
              find.byType(SellerForm),
              status == null ||
                      ['needs_resubmission', 'rejected'].contains(status)
                  ? findsOneWidget
                  : findsNothing);
          expect(tester.takeException(), isNull);
        },
            () => MockClient((r) async => http.Response(
                jsonEncode({
                  'application': status == null
                      ? null
                      : {
                          'status': status,
                          'store_name': 'My Store',
                          'created_at': '2026-09-01'
                        },
                  'is_seller': status == 'approved',
                  'seller_access': access,
                  'categories': [category]
                }),
                200)));
      });
    }
  }
  testWidgets('checkout only posts after confirmation and opens real order',
      (tester) async {
    var posts = 0;
    await http.runWithClient(() async {
      await tester.pumpWidget(const MaterialApp(home: CheckoutScreen()));
      await tester.pumpAndSettle();
      expect(posts, 0);
      final button =
          find.widgetWithText(FilledButton, 'Place Order • ${money(250)}');
      await tester.scrollUntilVisible(button, 400,
          scrollable: find.byType(Scrollable).first);
      await tester.tap(button);
      await tester.pumpAndSettle();
      expect(posts, 1);
      expect(find.byType(OrderDetailScreen), findsOneWidget);
      expect(find.text('SP123'), findsOneWidget);
    },
        () => MockClient((r) async {
              if (r.method == 'POST') {
                expect(r.url.path, '/api/v1/checkout');
                expect(jsonDecode(r.body)['address_id'], 1);
                posts++;
                return http.Response(jsonEncode({'order': order}), 201);
              }
              return http.Response(jsonEncode(responseFor(r.url.path)), 200);
            }));
  });
}
