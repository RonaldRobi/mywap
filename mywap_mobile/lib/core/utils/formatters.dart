import 'package:intl/intl.dart';

/// Display helpers for Malaysian users (RM currency, dates).
abstract final class Formatters {
  static final NumberFormat _currency = NumberFormat.currency(
    locale: 'ms_MY',
    symbol: 'RM',
    decimalDigits: 0,
  );

  static final DateFormat _date = DateFormat('d MMM yyyy', 'ms_MY');
  static final DateFormat _datetime = DateFormat('d MMM yyyy, h:mm a', 'ms_MY');

  /// Formats a value (num or String) as RM, e.g. 50 → "RM50".
  static String currency(dynamic value) {
    final num? n = value is num ? value : double.tryParse(value?.toString() ?? '');
    if (n == null) return 'RM0';
    return _currency.format(n);
  }

  static String date(DateTime? date) => date == null ? '-' : _date.format(date);

  static String datetime(DateTime? date) => date == null ? '-' : _datetime.format(date);
}
