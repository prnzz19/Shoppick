import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shoppick_mobile/main.dart';
import 'marketplace_flow_test.dart' as fixtures;

void main() {
  setUp(() => FlutterSecureStorage.setMockInitialValues({}));
  testWidgets('search debounces suggestions and opens the selected category',
      (tester) async {
    var suggestions = 0;
    await http.runWithClient(() async {
      await tester.pumpWidget(const MaterialApp(home: CatalogSearchScreen()));
      await tester.enterText(find.byType(TextField), 'Head');
      await tester.pump(const Duration(milliseconds: 100));
      await tester.enterText(find.byType(TextField), 'Headset');
      await tester.pump(const Duration(milliseconds: 350));
      await tester.pumpAndSettle();
      expect(suggestions, 1);
      expect(find.text('Audio'), findsOneWidget);
      expect(find.text(fixtures.product['name'] as String), findsOneWidget);
      await tester.tap(find.text('Audio'));
      await tester.pumpAndSettle();
      expect(find.byType(ProductsScreen), findsOneWidget);
    },
        () => MockClient((request) async {
              if (request.url.path.endsWith('/search/suggestions')) {
                suggestions++;
                expect(request.url.queryParameters['q'], 'Headset');
                return http.Response(
                    jsonEncode({
                      'products': [fixtures.product],
                      'categories': [
                        {'id': 2, 'name': 'Audio'}
                      ]
                    }),
                    200);
              }
              expect(request.url.queryParameters['category'], '2');
              return http.Response(
                  jsonEncode(fixtures.responseFor(request.url.path)), 200);
            }));
  });
}
