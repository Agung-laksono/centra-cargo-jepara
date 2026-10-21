import 'package:flutter_test/flutter_test.dart';
import 'package:jepara_cargo_mobile/main.dart';

void main() {
  testWidgets('App smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const JeparaCargoApp());
    expect(find.text('JEPARA CARGO'), findsOneWidget);
  });
}
