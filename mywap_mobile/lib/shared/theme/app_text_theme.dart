import 'package:flutter/material.dart';

/// Typography scale for the app.
///
/// Font: Inter — humanist sans, x-height tinggi, bentuk huruf terbuka.
///
/// Skala ini mengikut saiz standard aplikasi mudah alih (Material 3 / iOS)
/// supaya kelihatan profesional dan padat:
/// - Body 13-15sp, tajuk 14-20sp, paparan 22-36sp.
/// - Berat: w600 untuk tajuk, w400 untuk body. TIADA fail w900 diship.
/// - Lantai mutlak [minSize] (12sp) — tiada teks lebih kecil.
///
/// Hierarki mesti jelas: bodySmall < bodyMedium < bodyLarge.
abstract final class AppTextTheme {
  static const String fontFamily = 'Inter';

  /// Saiz terendah yang dibenarkan dalam app.
  static const double minSize = 12;

  static TextTheme get base {
    final textTheme = ThemeData.light().textTheme.apply(fontFamily: fontFamily);
    return textTheme.copyWith(
      displayLarge: textTheme.displayLarge?.copyWith(
        fontSize: 57,
        fontWeight: FontWeight.w700,
        height: 1.12,
        letterSpacing: -0.5,
      ),
      displayMedium: textTheme.displayMedium?.copyWith(
        fontSize: 45,
        fontWeight: FontWeight.w700,
        height: 1.16,
        letterSpacing: -0.4,
      ),
      displaySmall: textTheme.displaySmall?.copyWith(
        fontSize: 36,
        fontWeight: FontWeight.w700,
        height: 1.18,
        letterSpacing: -0.3,
      ),
      headlineLarge: textTheme.headlineLarge?.copyWith(
        fontSize: 30,
        fontWeight: FontWeight.w600,
        height: 1.22,
        letterSpacing: -0.2,
      ),
      headlineMedium: textTheme.headlineMedium?.copyWith(
        fontSize: 26,
        fontWeight: FontWeight.w600,
        height: 1.24,
        letterSpacing: -0.2,
      ),
      headlineSmall: textTheme.headlineSmall?.copyWith(
        fontSize: 22,
        fontWeight: FontWeight.w600,
        height: 1.28,
      ),
      titleLarge: textTheme.titleLarge?.copyWith(
        fontSize: 20,
        fontWeight: FontWeight.w600,
        height: 1.3,
      ),
      titleMedium: textTheme.titleMedium?.copyWith(
        fontSize: 16,
        fontWeight: FontWeight.w600,
        height: 1.3,
      ),
      titleSmall: textTheme.titleSmall?.copyWith(
        fontSize: 14,
        fontWeight: FontWeight.w600,
        height: 1.3,
      ),
      bodyLarge: textTheme.bodyLarge?.copyWith(
        fontSize: 15,
        fontWeight: FontWeight.w400,
        height: 1.45,
      ),
      bodyMedium: textTheme.bodyMedium?.copyWith(
        fontSize: 14,
        fontWeight: FontWeight.w400,
        height: 1.45,
      ),
      bodySmall: textTheme.bodySmall?.copyWith(
        fontSize: 13,
        fontWeight: FontWeight.w400,
        height: 1.4,
      ),
      labelLarge: textTheme.labelLarge?.copyWith(
        fontSize: 14,
        fontWeight: FontWeight.w600,
        height: 1.35,
      ),
      labelMedium: textTheme.labelMedium?.copyWith(
        fontSize: 13,
        fontWeight: FontWeight.w500,
        height: 1.35,
      ),
      labelSmall: textTheme.labelSmall?.copyWith(
        fontSize: minSize,
        fontWeight: FontWeight.w500,
        height: 1.3,
      ),
    );
  }
}
