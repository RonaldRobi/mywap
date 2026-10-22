import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:mywap_mobile/core/constants/prefs_keys.dart';
import 'package:mywap_mobile/core/localization/app_locale.dart';

void main() {
  test('locale starts from the persisted preference', () {
    final container = ProviderContainer(
      overrides: [
        initialAppLocaleProvider.overrideWithValue(const Locale('en')),
      ],
    );
    addTearDown(container.dispose);

    expect(container.read(appLocaleProvider), const Locale('en'));
  });

  test('setLocale updates state and persists the language code', () async {
    SharedPreferences.setMockInitialValues({});
    final container = ProviderContainer();
    addTearDown(container.dispose);

    expect(container.read(appLocaleProvider), isNull);

    await container
        .read(appLocaleProvider.notifier)
        .setLocale(const Locale('en'));

    expect(container.read(appLocaleProvider), const Locale('en'));
    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString(appLanguageKey), 'en');
  });

  test('device language maps to a supported app locale', () {
    expect(
      supportedLocaleForDevice(const Locale('en', 'US')).languageCode,
      'en',
    );
    expect(
      supportedLocaleForDevice(const Locale('ms', 'MY')).languageCode,
      'ms',
    );
    expect(supportedLocaleForDevice(const Locale('fr')).languageCode, 'ms');
  });

  test('unsupported locales are ignored', () async {
    SharedPreferences.setMockInitialValues({});
    final container = ProviderContainer();
    addTearDown(container.dispose);

    await container
        .read(appLocaleProvider.notifier)
        .setLocale(const Locale('fr'));

    expect(container.read(appLocaleProvider), isNull);
  });
}
