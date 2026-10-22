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
  static DateFormat _date = DateFormat('d MMM yyyy', _locale);
  static DateFormat _datetime = DateFormat('d MMM yyyy, h:mm a', _locale);

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
    _date = DateFormat('d MMM yyyy', code);
    _datetime = DateFormat('d MMM yyyy, h:mm a', code);
  }

  /// Formats a value (num or String) as RM, e.g. 50 → "RM50".
  static String currency(dynamic value) {
    final num? n =
        value is num ? value : double.tryParse(value?.toString() ?? '');
    if (n == null) return 'RM0';
    return _currency.format(n);
  }

  static String date(DateTime? date) => date == null ? '-' : _date.format(date);

  static String datetime(DateTime? date) =>
      date == null ? '-' : _datetime.format(date);
}
