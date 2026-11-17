import 'package:flutter/widgets.dart';
import 'package:intl/intl.dart';

/// Display helpers for Malaysian users (RM currency, dates).
abstract final class Formatters {
  static String _locale = 'ms_MY';

  static NumberFormat _currency = NumberFormat.currency(
    locale: _locale,
    symbol: 'RM',
    decimalDigits: 0,
  );

  /// Ikut bahasa paparan semasa. Dipanggil oleh `MyWapApp` setiap rebuild.
  static void setLocale(Locale locale) {
    final code = locale.languageCode == 'en' ? 'en' : 'ms_MY';
    if (code == _locale) return;
    _locale = code;
    _currency = NumberFormat.currency(
      locale: code,
      symbol: 'RM',
      decimalDigits: 0,
    );
  }

  /// Formats a value (num or String) as RM, e.g. 50 → "RM50".
  static String currency(dynamic value) {
    final num? n =
        value is num ? value : double.tryParse(value?.toString() ?? '');
    if (n == null) return 'RM0';
    return _currency.format(n);
  }

  static String date(DateTime? date) {
    if (date == null) return '-';
    String pad(int value) => value.toString().padLeft(2, '0');
    return '${pad(date.day)}/${pad(date.month)}/${date.year}';
  }

  static String datetime(DateTime? value) {
    if (value == null) return '-';
    String pad(int value) => value.toString().padLeft(2, '0');
    return '${date(value)} ${pad(value.hour)}:${pad(value.minute)}';
  }

  static String dateFromIso(String? value) {
    if (value == null || value.isEmpty) return '-';
    return date(DateTime.tryParse(value));
  }

  static String datetimeFromIso(String? value) {
    if (value == null || value.isEmpty) return '-';
    return datetime(DateTime.tryParse(value));
  }
}
