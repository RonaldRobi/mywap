import 'package:flutter/material.dart';

/// Design system tokens (ported from resources/css/app.css §10.1).
abstract final class AppColors {
  static const Color movementNavy = Color(0xFF071525);
  static const Color movementDarkGreen = Color(0xFF123d2a);
  static const Color movementGreen = Color(0xFF2f6b32);
  static const Color movementSoftGreen = Color(0xFF6fbf8a);
  static const Color movementOffWhite = Color(0xFFf4f6f1);
  static const Color pageBackground = Color(0xFFF5F7F6);
  static const Color softGreenSurface = Color(0xFFEDF5EE);
  static const Color paleGreen = Color(0xFFDCECDF);

  static const Color white = Color(0xFFFFFFFF);

  static const Color textPrimary = movementNavy;
  static const Color textSecondary = Color(0xFF5b6b7a);
  static const Color textOnDark = Color(0xFFF4F6F1);

  static const Color background = movementOffWhite;
  static const Color surface = white;
  static const Color divider = Color(0xFFE3E8E0);
  static const Color inputBorder = Color(0xFF6FBF8A);

  static const Color error = Color(0xFFB3261E);
  static const Color success = Color(0xFF2F6B32);
  static const Color warning = Color(0xFF9A6A00);
}
