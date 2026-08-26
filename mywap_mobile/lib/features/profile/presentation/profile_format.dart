/// Local formatting helpers for the Profile screens.
///
/// Kept intl-free on purpose: backend date strings are pre-localized to Malay
/// (Carbon `locale('ms')`) so they are shown verbatim, and the few values we
/// format ourselves (dates, RM amounts) avoid loading `ms_MY` locale data at
/// runtime and in widget tests.
abstract final class ProfileFormat {
  static const List<String> _months = [
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

  /// `20 Mei 1990` — for display only.
  static String date(DateTime value) {
    return '${value.day} ${_months[value.month - 1]} ${value.year}';
  }

  /// `1990-05-20` — API payload format.
  static String apiDate(DateTime value) {
    String pad(int n) => n.toString().padLeft(2, '0');
    return '${value.year}-${pad(value.month)}-${pad(value.day)}';
  }

  /// Tolerant date parsing — accepts ISO `1990-05-20` (from the IC parser)
  /// and Carbon `20 May 1990` (from `serializeProfile`'s `d M Y`).
  static DateTime? parseDate(String value) {
    final trimmed = value.trim();
    final iso = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(trimmed);
    if (iso != null) {
      final year = int.tryParse(iso.group(1)!);
      final month = int.tryParse(iso.group(2)!);
      final day = int.tryParse(iso.group(3)!);
      if (year != null && month != null && day != null && month >= 1 && month <= 12 && day >= 1 && day <= 31) {
        return DateTime(year, month, day);
      }
    }
    final carbon = RegExp(r'^(\d{1,2}) ([A-Za-z]+) (\d{4})$').firstMatch(trimmed);
    if (carbon != null) {
      final day = int.tryParse(carbon.group(1)!);
      final month = _monthIndex(carbon.group(2)!);
      final year = int.tryParse(carbon.group(3)!);
      if (day != null && month != null && year != null && day >= 1 && day <= 31) {
        return DateTime(year, month, day);
      }
    }
    return null;
  }

  static int? _monthIndex(String name) {
    final lower = name.toLowerCase();
    for (var i = 0; i < _months.length; i++) {
      if (lower == _months[i].toLowerCase()) return i + 1;
    }
    const english = [
      'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec',
    ];
    final idx = english.indexOf(lower);
    return idx < 0 ? null : idx + 1;
  }

  /// `RM50` / `RM50.50` — no currency locale data required.
  static String money(num? value) {
    if (value == null) return 'RM0';
    final text = value % 1 == 0 ? value.toInt().toString() : value.toStringAsFixed(2);
    return 'RM$text';
  }
}
