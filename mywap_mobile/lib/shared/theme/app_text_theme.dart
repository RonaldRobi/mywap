import 'package:flutter/material.dart';

/// Typography scale for the app.
///
/// Font: Inter — humanist sans, x-height tinggi, bentuk huruf terbuka.
/// Dipilih khusus untuk sasaran warga emas & kurang mahir IT (audit fasa 2).
///
/// Peraturan skala (JANGAN langgar):
/// - Lantai mutlak 13sp. Tiada teks di bawah ini — 9-12sp dalam kod lama
///   adalah punca utama teks tidak boleh dibaca.
/// - Body utama 17sp (dulunya 14sp). Komen asal mendakwa "minimum 16sp"
///   tetapi kod sebenarnya 14sp.
/// - Setiap tahap mesti ada hierarki saiz yang jelas. Dulunya
///   bodySmall == bodyMedium == bodyLarge == 14 (tiada hierarki langsung).
/// - Berat maksimum w800. TIADA fail w900 diship — jangan guna w900.
abstract final class AppTextTheme {
  static const String fontFamily = 'Inter';

  /// Saiz terendah yang dibenarkan dalam app.
  static const double minSize = 13;

  static TextTheme get base {
    final textTheme = ThemeData.light().textTheme.apply(
      fontFamily: fontFamily,
    );
    return textTheme.copyWith(
      displayLarge: textTheme.displayLarge?.copyWith(
        fontSize: 52,
        fontWeight: FontWeight.w700,
        height: 1.15,
        letterSpacing: -0.5,
      ),
      displayMedium: textTheme.displayMedium?.copyWith(
        fontSize: 42,
        fontWeight: FontWeight.w700,
        height: 1.18,
        letterSpacing: -0.4,
      ),
      displaySmall: textTheme.displaySmall?.copyWith(
        fontSize: 34,
        fontWeight: FontWeight.w700,
        height: 1.2,
        letterSpacing: -0.3,
      ),
      headlineLarge: textTheme.headlineLarge?.copyWith(
        fontSize: 30,
        fontWeight: FontWeight.w700,
        height: 1.22,
        letterSpacing: -0.2,
      ),
      headlineMedium: textTheme.headlineMedium?.copyWith(
        fontSize: 26,
        fontWeight: FontWeight.w700,
        height: 1.25,
        letterSpacing: -0.2,
      ),
      headlineSmall: textTheme.headlineSmall?.copyWith(
        fontSize: 22,
        fontWeight: FontWeight.w700,
        height: 1.3,
      ),
      titleLarge: textTheme.titleLarge?.copyWith(
        fontSize: 20,
        fontWeight: FontWeight.w700,
        height: 1.3,
      ),
      titleMedium: textTheme.titleMedium?.copyWith(
        fontSize: 18,
        fontWeight: FontWeight.w600,
        height: 1.35,
      ),
      titleSmall: textTheme.titleSmall?.copyWith(
        fontSize: 17,
        fontWeight: FontWeight.w600,
        height: 1.35,
      ),
      bodyLarge: textTheme.bodyLarge?.copyWith(
        fontSize: 17,
        fontWeight: FontWeight.w400,
        height: 1.5,
      ),
      bodyMedium: textTheme.bodyMedium?.copyWith(
        fontSize: 16,
        fontWeight: FontWeight.w400,
        height: 1.5,
      ),
      bodySmall: textTheme.bodySmall?.copyWith(
        fontSize: 15,
        fontWeight: FontWeight.w400,
        height: 1.45,
      ),
      labelLarge: textTheme.labelLarge?.copyWith(
        fontSize: 17,
        fontWeight: FontWeight.w600,
        height: 1.4,
      ),
      labelMedium: textTheme.labelMedium?.copyWith(
        fontSize: 15,
        fontWeight: FontWeight.w500,
        height: 1.4,
      ),
      labelSmall: textTheme.labelSmall?.copyWith(
        fontSize: minSize,
        fontWeight: FontWeight.w600,
        height: 1.35,
      ),
    );
  }
}
