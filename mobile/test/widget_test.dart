import 'package:flutter_test/flutter_test.dart';

import 'package:shoppick_mobile/main.dart';

void main() {
  testWidgets('SHOPPICK app builds', (WidgetTester tester) async {
    await tester.pumpWidget(const ShoppickApp());
    expect(find.text('SHOP'), findsOneWidget);
    expect(find.text('PICK'), findsOneWidget);
  });
}
