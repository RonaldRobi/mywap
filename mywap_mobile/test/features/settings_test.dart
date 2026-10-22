import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:mywap_mobile/core/localization/app_locale.dart';
import 'package:mywap_mobile/features/settings/presentation/settings_screen.dart';
import 'package:mywap_mobile/shared/l10n/app_localizations.dart';

void main() {
  testWidgets('settings lets the user switch language', (tester) async {
    SharedPreferences.setMockInitialValues({});
    final container = ProviderContainer(
      overrides: [
        initialAppLocaleProvider.overrideWithValue(const Locale('ms')),
      ],
    );
    addTearDown(container.dispose);

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp(
          locale: const Locale('ms'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          home: const SettingsScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Tetapan'), findsOneWidget);
    expect(find.text('Bahasa'), findsOneWidget);
    expect(find.textContaining('Bahasa Melayu'), findsOneWidget);

    await tester.tap(find.text('Bahasa'));
    await tester.pumpAndSettle();
    expect(find.text('Pilih Bahasa'), findsOneWidget);

    await tester.tap(find.text('English'));
    await tester.pumpAndSettle();

    expect(container.read(appLocaleProvider), const Locale('en'));
  });
}
