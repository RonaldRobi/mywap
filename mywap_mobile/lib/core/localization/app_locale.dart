import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../constants/prefs_keys.dart';

/// Kod bahasa yang disokong oleh aplikasi mudah alih.
const String malayLanguageCode = 'ms';
const String englishLanguageCode = 'en';

/// Nilai awal locale yang dibaca daripada SharedPreferences semasa startup.
///
/// `main()` mengoverride provider ini dengan pilihan pengguna. Lalai `null`
/// bermaksud tiada pilihan tersimpan (atau belum dimuatkan), dan aplikasi
/// memaparkan Bahasa Melayu.
final initialAppLocaleProvider = Provider<Locale?>((ref) => null);

/// Locale paparan semasa. `null` bermaksud pengguna belum memilih bahasa.
final appLocaleProvider = NotifierProvider<AppLocaleController, Locale?>(
  AppLocaleController.new,
);

class AppLocaleController extends Notifier<Locale?> {
  @override
  Locale? build() => ref.watch(initialAppLocaleProvider);

  /// Tukar bahasa serta-merta dan simpan pilihan untuk pelancaran seterusnya.
  Future<void> setLocale(Locale locale) async {
    final code = _supportedCode(locale.languageCode);
    if (code == null) return;

    state = Locale(code);
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(appLanguageKey, code);
    } catch (_) {
      // Kegagalan menyimpan tidak sepatutnya menghalang pertukaran bahasa
      // dalam sesi semasa.
    }
  }
}

/// Pilih locale aplikasi berdasarkan bahasa telefon. Jika telefon guna
/// bahasa yang tidak disokong, kekal dengan Bahasa Melayu sebagai lalai.
Locale supportedLocaleForDevice(Locale deviceLocale) {
  return switch (deviceLocale.languageCode) {
    englishLanguageCode => const Locale(englishLanguageCode),
    malayLanguageCode => const Locale(malayLanguageCode),
    _ => const Locale(malayLanguageCode),
  };
}

String? _supportedCode(String code) {
  return switch (code) {
    malayLanguageCode => malayLanguageCode,
    englishLanguageCode => englishLanguageCode,
    _ => null,
  };
}
