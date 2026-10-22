import 'package:flutter/widgets.dart';

import 'app_localizations.dart';

export 'app_localizations.dart';

AppLocalizations? _currentAppLocalizations;

/// Terjemahan aktif untuk kod bukan-widget (contoh: peta ralat rangkaian).
///
/// `MyWapApp` mengemas kini nilai ini setiap kali locale berubah. Lalai
/// Bahasa Melayu supaya ujian dan panggilan awal kekal deterministik.
AppLocalizations get currentAppLocalizations =>
    _currentAppLocalizations ?? lookupAppLocalizations(const Locale('ms'));

void setCurrentAppLocalizations(AppLocalizations localizations) {
  _currentAppLocalizations = localizations;
}

/// Akses mudah kepada terjemahan, dengan fallback Bahasa Melayu.
///
/// Fallback ini penting untuk widget test yang mengepam skrin di dalam
/// `MaterialApp` tanpa `localizationsDelegates`.
extension AppLocalizationsX on BuildContext {
  AppLocalizations get l10n =>
      Localizations.of<AppLocalizations>(this, AppLocalizations) ??
      lookupAppLocalizations(const Locale('ms'));
}
