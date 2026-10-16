import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shoppick_mobile/config/api_config.dart';
import 'package:shoppick_mobile/main.dart';
import 'package:shoppick_mobile/services/api_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(() => FlutterSecureStorage.setMockInitialValues({}));

  test('image URLs preserve remote hosts and normalize storage paths', () {
    expect(ApiConfig.imageUrl(null), isNull);
    expect(ApiConfig.imageUrl(''), isNull);
    expect(ApiConfig.imageUrl('javascript:alert(1)'), isNull);
    expect(ApiConfig.imageUrl('//untrusted.test/image'), isNull);
    expect(ApiConfig.imageUrl('products/test.jpg'),
        'http://10.0.2.2:8000/storage/products/test.jpg');
    expect(ApiConfig.imageUrl('http://localhost:8000/storage/test.jpg'),
        'http://10.0.2.2:8000/storage/test.jpg');
    expect(ApiConfig.imageUrl('https://cdn.example.com/test.jpg'),
        'https://cdn.example.com/test.jpg');
  });

  test('server failures never expose SQL, tokens or exception traces',
      () async {
    await http.runWithClient(() async {
      await expectLater(
          ApiService().request('products'),
          throwsA(isA<ApiException>().having((e) => e.message, 'safe message',
              'SHOPPICK is temporarily unavailable. Please try again.')));
    },
        () => MockClient((_) async => http.Response(
            jsonEncode({'message': 'SQLSTATE password=secret token=private'}),
            500)));
  });

  test('authenticated cart requests update the real badge count', () async {
    FlutterSecureStorage.setMockInitialValues({'auth_token': 'test-token'});
    await http.runWithClient(() async {
      await ApiService().request('cart');
    },
        () => MockClient((r) async {
              expect(r.headers['Authorization'], 'Bearer test-token');
              return http.Response(
                  jsonEncode({'cart_count': 3, 'items': [], 'subtotal': 0}),
                  200);
            }));
    expect(ApiService.cartCount.value, 3);
    await ApiService().clearToken();
    expect(await ApiService().token(), isNull);
    expect(ApiService.cartCount.value, 0);
  });

  testWidgets('login remains usable on a narrow phone', (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(const MaterialApp(home: LoginScreen()));
    expect(find.text('Sign in'), findsOneWidget);
    expect(find.byType(TextField), findsNWidgets(2));
    expect(tester.takeException(), isNull);
  });

  testWidgets(
      'product cards fit narrow phones with enlarged text and missing images',
      (tester) async {
    tester.view.physicalSize = const Size(320, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(const MaterialApp(
        home: MediaQuery(
            data: MediaQueryData(textScaler: TextScaler.linear(1.5)),
            child: Scaffold(
                body: SingleChildScrollView(
                    child: Padding(
                        padding: EdgeInsets.all(16),
                        child: ProductGrid(items: [
                          {
                            'id': 1,
                            'name':
                                'A long product title for a small mobile display',
                            'shop': 'A very long local SHOPPICK shop name',
                            'price': 123456.78,
                            'original_price': 150000,
                            'stock': 0,
                            'rating_avg': 4.8,
                            'rating_count': 20
                          },
                          {
                            'id': 2,
                            'name': 'Second product',
                            'price': 100,
                            'stock': 5
                          },
                        ])))))));
    expect(tester.takeException(), isNull);
    expect(find.byIcon(Icons.shopping_bag_outlined), findsNWidgets(2));
    expect(find.text('Out of stock'), findsOneWidget);
  });

  testWidgets(
      'order cards show real shops, amount, status and additional items',
      (tester) async {
    await tester.pumpWidget(const MaterialApp(
        home: Scaffold(
            body: OrderCard(data: {
      'order_number': 'SP123',
      'status': 'ready_to_ship',
      'total': 250,
      'created_at': '2026-09-01T12:00:00Z',
      'shops': [
        {
          'shop': {'name': 'Local Shop'}
        }
      ],
      'items': [
        {'product_name': 'First product', 'quantity': 1},
        {'product_name': 'Second product', 'quantity': 1}
      ],
    }))));
    expect(find.text('Ready To Ship'), findsOneWidget);
    expect(find.text('+1 more items'), findsOneWidget);
    expect(find.text('Local Shop'), findsOneWidget);
    expect(find.text(money(250)), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('seller form keeps logo optional and validates required fields',
      (tester) async {
    await tester.pumpWidget(MaterialApp(
        home: Scaffold(
            body: SingleChildScrollView(
                child: SellerForm(categories: const [
      {'id': 1, 'name': 'Electronics'}
    ], onSubmitted: () {})))));
    await tester.ensureVisible(find.text('Shop Logo (Optional)'));
    expect(find.text('Shop Logo (Optional)'), findsOneWidget);
    await tester.ensureVisible(find.text('Submit Application'));
    await tester.tap(find.text('Submit Application'));
    await tester.pump();
    expect(find.text('Enter Business / Store Name'), findsOneWidget);
    expect(find.text('Choose a category'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
