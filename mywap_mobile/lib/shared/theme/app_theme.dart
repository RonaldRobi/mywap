import 'package:flutter/material.dart';

import 'app_colors.dart';
import 'app_text_theme.dart';

/// Design system spacing (4/8/12/16/24/32 — Tailwind-aligned §10.3).
abstract final class Spacing {
  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 24;
  static const double xxl = 32;
}

/// Corner radius tokens (§10.3) — Apple-inspired, generous rounding.
abstract final class AppRadius {
  static const BorderRadius xs = BorderRadius.all(Radius.circular(6));
  static const BorderRadius sm = BorderRadius.all(Radius.circular(10));
  static const BorderRadius md = BorderRadius.all(Radius.circular(14));
  static const BorderRadius lg = BorderRadius.all(Radius.circular(18));
  static const BorderRadius xl = BorderRadius.all(Radius.circular(26));
  static const BorderRadius card = BorderRadius.all(Radius.circular(22));
  static const BorderRadius hero = BorderRadius.all(Radius.circular(32));
  static const BorderRadius sheet = BorderRadius.only(
    topLeft: Radius.circular(28),
    topRight: Radius.circular(28),
  );

  /// Pil / kapsul penuh. Dulunya 17 tempat hand-roll `circular(99)` atau
  /// `circular(999)` — dua nilai berbeza untuk rupa yang sama.
  static const BorderRadius pill = BorderRadius.all(Radius.circular(999));
}

/// Saiz sasaran sentuh minimum (WCAG 2.5.5 / Material).
///
/// Sasaran warga emas: jangan sekali-kali kecil daripada ini untuk elemen
/// yang boleh ditekan.
abstract final class AppSizes {
  static const double tapTarget = 48;
  static const double buttonHeight = 56;
  static const double navBarHeight = 76;
  static const double iconSm = 20;
  static const double iconMd = 24;
  static const double iconLg = 28;
}

/// Soft, low-opacity shadows — Apple-style elevation (diffuse, not harsh).
/// Warna bayang mengikut `movementNavy` baharu (#12241C).
abstract final class AppShadows {
  static const List<BoxShadow> subtle = [
    BoxShadow(
      color: Color(0x0A12241C),
      blurRadius: 12,
      offset: Offset(0, 4),
    ),
  ];

  static const List<BoxShadow> card = [
    BoxShadow(
      color: Color(0x0F12241C),
      blurRadius: 24,
      offset: Offset(0, 10),
    ),
  ];

  static const List<BoxShadow> floating = [
    BoxShadow(
      color: Color(0x2212241C),
      blurRadius: 28,
      offset: Offset(0, 14),
    ),
  ];
}

/// App-wide [ThemeData].
abstract final class AppTheme {
  static ThemeData get light {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: AppColors.movementGreen,
      primary: AppColors.movementGreen,
      secondary: AppColors.movementDarkGreen,
      surface: AppColors.surface,
      error: AppColors.error,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      fontFamily: AppTextTheme.fontFamily,
      textTheme: AppTextTheme.base,
      scaffoldBackgroundColor: AppColors.pageBackground,
      splashFactory: InkSparkle.splashFactory,
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.pageBackground,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
        scrolledUnderElevation: 0,
        surfaceTintColor: Colors.transparent,
        centerTitle: false,
        titleTextStyle: TextStyle(
          fontFamily: AppTextTheme.fontFamily,
          color: AppColors.textPrimary,
          fontSize: 20,
          fontWeight: FontWeight.w700,
          letterSpacing: -0.2,
        ),
      ),
      cardTheme: CardThemeData(
        color: AppColors.surface,
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: AppRadius.card),
        margin: const EdgeInsets.symmetric(
          horizontal: Spacing.lg,
          vertical: Spacing.sm,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.movementGreen,
          foregroundColor: AppColors.white,
          disabledBackgroundColor: AppColors.movementGreen.withValues(
            alpha: .4,
          ),
          minimumSize: const Size.fromHeight(AppSizes.buttonHeight),
          textStyle: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
          shape: RoundedRectangleBorder(borderRadius: AppRadius.lg),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.movementGreen,
          side: const BorderSide(color: AppColors.inputBorder, width: 1.5),
          minimumSize: const Size.fromHeight(AppSizes.buttonHeight),
          textStyle: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
          shape: RoundedRectangleBorder(borderRadius: AppRadius.lg),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.movementGreen,
          minimumSize: const Size(
            AppSizes.tapTarget,
            AppSizes.tapTarget,
          ),
          textStyle: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.white,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: Spacing.lg,
          vertical: Spacing.lg,
        ),
        border: OutlineInputBorder(
          borderRadius: AppRadius.md,
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: AppRadius.md,
          borderSide: const BorderSide(color: AppColors.inputBorder),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: AppRadius.md,
          borderSide: const BorderSide(
            color: AppColors.movementGreen,
            width: 1.8,
          ),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: AppRadius.md,
          borderSide: const BorderSide(color: AppColors.error, width: 1.5),
        ),
        labelStyle: const TextStyle(
          fontSize: 16,
          color: AppColors.textSecondary,
        ),
      ),
      snackBarTheme: const SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: AppColors.movementNavy,
        contentTextStyle: TextStyle(fontSize: 16, color: AppColors.white),
        shape: RoundedRectangleBorder(borderRadius: AppRadius.md),
      ),
      navigationBarTheme: const NavigationBarThemeData(
        backgroundColor: AppColors.white,
        indicatorColor: AppColors.paleGreen,
        elevation: 0,
        height: AppSizes.navBarHeight,
        labelTextStyle: WidgetStatePropertyAll(
          TextStyle(fontSize: AppTextTheme.minSize, fontWeight: FontWeight.w600),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.white,
        shape: const RoundedRectangleBorder(borderRadius: AppRadius.card),
        titleTextStyle: AppTextTheme.base.titleLarge,
        contentTextStyle: AppTextTheme.base.bodyMedium?.copyWith(
          color: AppColors.textSecondary,
        ),
      ),
      dividerTheme: const DividerThemeData(
        color: AppColors.divider,
        thickness: 1,
      ),
    );
  }
}
