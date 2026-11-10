// Shared, tolerant JSON parsing helpers for all data models.
//
// Laravel's `decimal:N` cast serializes numbers as strings (e.g. `"0.00"`),
// and some serializers pass booleans back as `0`/`1`. Parsing those fields
// with a raw `as num?` / `as bool?` cast throws a TypeError, which is not an
// [ApiException], so every screen falls back to the generic "unexpected
// error". These helpers accept both the numeric and the string form so a
// backend serialization detail can never crash a screen.
//
// This is the single source of truth: prefer these over hand-written casts in
// `fromJson` factories.

int? parseIntValue(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) {
    final trimmed = value.trim();
    if (trimmed.isEmpty) return null;
    return int.tryParse(trimmed);
  }
  return null;
}

double? parseDoubleValue(dynamic value) {
  if (value is num) return value.toDouble();
  if (value is String) {
    final trimmed = value.trim();
    if (trimmed.isEmpty) return null;
    return double.tryParse(trimmed);
  }
  return null;
}

bool parseBoolValue(dynamic value, {bool fallback = false}) {
  if (value is bool) return value;
  if (value is num) return value != 0;
  if (value is String) {
    final normalized = value.trim().toLowerCase();
    return normalized == '1' || normalized == 'true';
  }
  return fallback;
}

String? parseStringValue(dynamic value) {
  if (value == null) return null;
  if (value is String) return value;
  return value.toString();
}

List<T> parseList<T>(dynamic value, T Function(Map<String, dynamic>) fromJson) {
  if (value is! List) return const [];
  return value
      .whereType<Map<String, dynamic>>()
      .map(fromJson)
      .toList(growable: false);
}

List<String> parseStringList(dynamic value) {
  if (value is! List) return const [];
  return value.whereType<String>().toList(growable: false);
}
