import 'package:flutter/services.dart';

abstract final class MalaysianPhone {
  static final List<TextInputFormatter> inputFormatters = [
    FilteringTextInputFormatter.digitsOnly,
    LengthLimitingTextInputFormatter(11),
  ];

  static bool isValid(String? value) {
    final digits = normalize(value);
    return RegExp(r'^0[0-9]{8,10}$').hasMatch(digits);
  }

  static String normalize(String? value) {
    var digits = (value ?? '').replaceAll(RegExp(r'\D'), '');

    if (digits.startsWith('60')) {
      digits = digits.substring(2);
      if (digits.isNotEmpty && !digits.startsWith('0')) {
        digits = '0$digits';
      }
    } else if (digits.isNotEmpty && !digits.startsWith('0')) {
      digits = '0$digits';
    }

    return digits;
  }
}
