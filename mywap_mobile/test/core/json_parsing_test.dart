import 'package:flutter_test/flutter_test.dart';
import 'package:mywap_mobile/core/utils/json_parsing.dart';

void main() {
  group('parseDoubleValue', () {
    test('accepts numbers and decimal strings', () {
      expect(parseDoubleValue(10), 10.0);
      expect(parseDoubleValue(10.5), 10.5);
      expect(parseDoubleValue('10.50'), 10.5);
      expect(parseDoubleValue('0.00'), 0.0);
    });

    test('returns null for empty or unparseable values', () {
      expect(parseDoubleValue(null), isNull);
      expect(parseDoubleValue(''), isNull);
      expect(parseDoubleValue('abc'), isNull);
    });
  });

  group('parseIntValue', () {
    test('accepts numbers and numeric strings', () {
      expect(parseIntValue(3), 3);
      expect(parseIntValue('3'), 3);
      expect(parseIntValue(3.9), 3);
    });

    test('returns null for empty or unparseable values', () {
      expect(parseIntValue(null), isNull);
      expect(parseIntValue(''), isNull);
      expect(parseIntValue('x'), isNull);
    });
  });

  group('parseBoolValue', () {
    test('accepts bool, 0/1 and string forms', () {
      expect(parseBoolValue(true), isTrue);
      expect(parseBoolValue(false), isFalse);
      expect(parseBoolValue(1), isTrue);
      expect(parseBoolValue(0), isFalse);
      expect(parseBoolValue('1'), isTrue);
      expect(parseBoolValue('true'), isTrue);
      expect(parseBoolValue('0'), isFalse);
    });

    test('uses fallback only for values that are not bool, num or string', () {
      expect(parseBoolValue(null), isFalse);
      expect(parseBoolValue(null, fallback: true), isTrue);
      expect(parseBoolValue('maybe'), isFalse);
    });
  });
}
