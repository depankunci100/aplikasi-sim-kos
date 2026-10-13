import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/main.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('SIM Kos App renders login screen smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(
      const ProviderScope(
        child: SimKosApp(),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('Masuk ke SIM Kos'), findsOneWidget);
    expect(find.text('Masuk'), findsOneWidget);
  });
}
