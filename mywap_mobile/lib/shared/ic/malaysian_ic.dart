DateTime? parseDobFromIc(String? ic, {DateTime? now}) {
  if (ic == null || ic.isEmpty) return null;
  final digits = ic.replaceAll(RegExp(r'[^0-9]'), '');
  if (digits.length < 6) return null;

  final yy = int.tryParse(digits.substring(0, 2));
  final mm = int.tryParse(digits.substring(2, 4));
  final dd = int.tryParse(digits.substring(4, 6));
  if (yy == null || mm == null || dd == null) return null;
  if (mm < 1 || mm > 12 || dd < 1 || dd > 31) return null;

  var year = 2000 + yy;
  final currentYear = (now ?? DateTime.now()).year;
  if (year > currentYear) year -= 100;

  final date = DateTime(year, mm, dd);
  if (date.year != year || date.month != mm || date.day != dd) return null;
  return date;
}

String formatDob(DateTime date) {
  final day = date.day.toString().padLeft(2, '0');
  final month = date.month.toString().padLeft(2, '0');
  return '$day/$month/${date.year}';
}

String formatDobApi(DateTime date) {
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '${date.year}-$month-$day';
}
