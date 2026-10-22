import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:customer_app/main.dart';

void main() {
  testWidgets('Customer App compile and load smoke test', (WidgetTester tester) async {
    // Build our app and trigger a frame.
    await tester.pumpWidget(
      const ProviderScope(
        child: CustomerApp(),
      ),
    );

    // Verify if CustomerApp loads
    expect(find.byType(CustomerApp), findsOneWidget);
  });
}
