import 'package:flutter_test/flutter_test.dart';
import 'package:rider_app/main.dart';

void main() {
  testWidgets('RiderApp loads', (WidgetTester tester) async {
    await tester.pumpWidget(const RiderApp());
    expect(find.text('UtterLazy Rider'), findsOneWidget);
  });
}
