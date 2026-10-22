import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/app.dart';
import 'package:mywap_mobile/core/constants/prefs_keys.dart';
import 'package:mywap_mobile/core/network/providers.dart';
import 'package:mywap_mobile/core/storage/token_storage.dart';
import 'package:mywap_mobile/features/onboarding/presentation/onboarding_screen.dart';
import 'package:mywap_mobile/features/settings/presentation/language_screen.dart';

class _FakeTokenStorage extends TokenStorage {
  _FakeTokenStorage() : super();
  String? _value;

  @override
  Future<String?> read() async => _value;

  @override
  Future<void> write(String token) async => _value = token;

  @override
  Future<void> delete() async => _value = null;
}

Future<void> _pumpApp(WidgetTester tester) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [tokenStorageProvider.overrideWithValue(_FakeTokenStorage())],
      child: const MyWapApp(),
    ),
  );
  await tester.pumpAndSettle(const Duration(milliseconds: 200));
}

void main() {
  testWidgets('app boots to the language picker on a fresh install', (
    tester,
  ) async {
    SharedPreferences.setMockInitialValues({});

    await _pumpApp(tester);

    expect(find.byType(LanguageScreen), findsOneWidget);
    expect(find.text('Pilih Bahasa'), findsOneWidget);
    expect(find.text('Bahasa Melayu'), findsOneWidget);
    expect(find.text('English'), findsOneWidget);
  });

  testWidgets('choosing a language is saved and continues to onboarding', (
    tester,
  ) async {
    SharedPreferences.setMockInitialValues({});

    await _pumpApp(tester);
    await tester.tap(find.text('English'));
    await tester.pumpAndSettle(const Duration(milliseconds: 200));

    expect(find.byType(OnboardingScreen), findsOneWidget);

    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString(appLanguageKey), 'en');
  });
}
