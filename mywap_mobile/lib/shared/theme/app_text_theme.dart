import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Typography scale for the app.
///
/// Accessibility-first (§10.4): minimum body size 16sp, generous tap targets.
abstract final class AppTextTheme {
  static TextTheme get base {
    final textTheme = ThemeData.light().textTheme;
    return GoogleFonts.figtreeTextTheme(textTheme).copyWith(
      displaySmall: textTheme.displaySmall?.copyWith(
        fontSize: 36,
        fontWeight: FontWeight.w700,
        height: 1.2,
      ),
      headlineMedium: textTheme.headlineMedium?.copyWith(
        fontSize: 30,
        fontWeight: FontWeight.w700,
        height: 1.25,
      ),
      headlineSmall: textTheme.headlineSmall?.copyWith(
        fontSize: 26,
        fontWeight: FontWeight.w700,
        height: 1.3,
      ),
      titleLarge: textTheme.titleLarge?.copyWith(
        fontSize: 22,
        fontWeight: FontWeight.w700,
        height: 1.3,
      ),
      titleMedium: textTheme.titleMedium?.copyWith(
        fontSize: 18,
        fontWeight: FontWeight.w600,
        height: 1.4,
      ),
      titleSmall: textTheme.titleSmall?.copyWith(
        fontSize: 16,
        fontWeight: FontWeight.w600,
        height: 1.4,
      ),
      bodyLarge: textTheme.bodyLarge?.copyWith(
        fontSize: 16,
        fontWeight: FontWeight.w400,
        height: 1.5,
      ),
      bodyMedium: textTheme.bodyMedium?.copyWith(
        fontSize: 16,
        fontWeight: FontWeight.w400,
        height: 1.5,
      ),
      bodySmall: textTheme.bodySmall?.copyWith(
        fontSize: 14,
        fontWeight: FontWeight.w400,
        height: 1.4,
      ),
      labelLarge: textTheme.labelLarge?.copyWith(
        fontSize: 16,
        fontWeight: FontWeight.w600,
        height: 1.4,
      ),
      labelMedium: textTheme.labelMedium?.copyWith(
        fontSize: 14,
        fontWeight: FontWeight.w500,
        height: 1.4,
      ),
    );
  }
}
