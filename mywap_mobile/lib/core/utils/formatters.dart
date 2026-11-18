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

  static const List<String> _monthsMs = [
    'Jan',
    'Feb',
    'Mac',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Ogo',
    'Sep',
    'Okt',
    'Nov',
    'Dis',
  ];

  static const List<String> _monthsEn = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];

  static const List<String> _weekdaysMs = [
    'Ahad',
    'Isnin',
    'Selasa',
    'Rabu',
    'Khamis',
    'Jumaat',
    'Sabtu',
  ];

  static const List<String> _weekdaysEn = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
  ];

  static List<String> get months =>
      _locale.startsWith('en') ? _monthsEn : _monthsMs;

  static List<String> get weekdays =>
      _locale.startsWith('en') ? _weekdaysEn : _weekdaysMs;

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

  static String shortDate(DateTime value) =>
      '${value.day} ${months[value.month - 1]} ${value.year}';

  static String longDate(DateTime value) =>
      '${weekdays[value.weekday - 1]}, ${value.day} '
      '${months[value.month - 1]} ${value.year}';

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
