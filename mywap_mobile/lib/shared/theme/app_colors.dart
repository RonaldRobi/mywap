import 'package:flutter/material.dart';

/// Design system tokens (ported from resources/css/app.css §10.1).
///
/// Apple-inspired refresh: same brand hues, softer neutrals, and a few
/// additional surface/gradient tokens used by the redesigned navigation
/// shell, cards and hero sections.
abstract final class AppColors {
  static const Color movementNavy = Color(0xFF071525);
  static const Color movementDarkGreen = Color(0xFF123d2a);
  static const Color movementGreen = Color(0xFF2f6b32);
  static const Color movementSoftGreen = Color(0xFF6fbf8a);
  static const Color movementOffWhite = Color(0xFFf4f6f1);
  static const Color pageBackground = Color(0xFFF7F8FA);
  static const Color softGreenSurface = Color(0xFFEDF5EE);
  static const Color paleGreen = Color(0xFFDCECDF);

  static const Color white = Color(0xFFFFFFFF);

  static const Color textPrimary = movementNavy;
  static const Color textSecondary = Color(0xFF6B7684);
  static const Color textTertiary = Color(0xFF9AA5B1);
  static const Color textOnDark = Color(0xFFF4F6F1);

  static const Color background = pageBackground;
  static const Color surface = white;
  static const Color surfaceMuted = Color(0xFFF1F3F5);
  static const Color divider = Color(0xFFE7EAEE);
  static const Color inputBorder = Color(0xFFE1E5EA);

  static const Color error = Color(0xFFE0483F);
  static const Color success = Color(0xFF2F6B32);
  static const Color warning = Color(0xFFB4780C);

  /// Frosted / glassy overlay tint used on hero headers & sheets.
  static const Color glassTint = Color(0x14071525);

  /// Signature hero gradient — dark green → primary green (kad ahli, header).
  static const List<Color> heroGradient = [
    movementDarkGreen,
    movementGreen,
  ];

  /// Soft mint gradient for secondary highlight surfaces (shortcuts, badges).
  static const List<Color> mintGradient = [
    Color(0xFF8FD4A8),
    movementSoftGreen,
  ];
}
