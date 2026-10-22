import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'app.dart';
import 'core/constants/prefs_keys.dart';
import 'core/localization/app_locale.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('ms_MY');
  await initializeDateFormatting('en');

  Locale initialLocale = supportedLocaleForDevice(
    WidgetsBinding.instance.platformDispatcher.locale,
  );
  try {
    final prefs = await SharedPreferences.getInstance();
    final code = prefs.getString(appLanguageKey);
    if (code == malayLanguageCode || code == englishLanguageCode) {
      initialLocale = Locale(code!);
    }
  } catch (_) {
    // Preferensi tidak dapat dibaca — kekal dengan bahasa telefon.
  }

  runApp(
    ProviderScope(
      overrides: [initialAppLocaleProvider.overrideWithValue(initialLocale)],
      child: const MyWapApp(),
    ),
  );
}
