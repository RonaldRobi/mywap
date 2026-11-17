import 'package:flutter_test/flutter_test.dart';
import 'package:mywap_mobile/shared/ic/malaysian_ic.dart';

void main() {
  final now = DateTime(2026, 11, 17);

  test('reads YYMMDD with a sliding 100-year window', () {
    expect(parseDobFromIc('900101-14-5678', now: now), DateTime(1990, 1, 1));
    expect(parseDobFromIc('000101145678', now: now), DateTime(2000, 1, 1));
    expect(parseDobFromIc('261117145678', now: now), DateTime(2026, 11, 17));
    expect(parseDobFromIc('271117145678', now: now), DateTime(1927, 11, 17));
    expect(parseDobFromIc('900101', now: now), DateTime(1990, 1, 1));
  });

  test('rejects dates that are not on the calendar', () {
    expect(parseDobFromIc('900230145678', now: now), isNull);
    expect(parseDobFromIc('901301145678', now: now), isNull);
    expect(parseDobFromIc('310229145678', now: now), isNull);
    expect(parseDobFromIc('320229145678', now: now), DateTime(1932, 2, 29));
  });

  test('ignores passport and short values', () {
    expect(parseDobFromIc('A1234567', now: now), isNull);
    expect(parseDobFromIc('', now: now), isNull);
    expect(parseDobFromIc(null, now: now), isNull);
  });

  test('formats display as DD/MM/YYYY and API as YYYY-MM-DD', () {
    expect(formatDob(DateTime(1990, 1, 9)), '09/01/1990');
    expect(formatDobApi(DateTime(1990, 1, 9)), '1990-01-09');
  });
}
