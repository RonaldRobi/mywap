import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_theme.dart';

/// Full-screen loading overlay shown over the current content.
class LoadingOverlay extends StatelessWidget {
  const LoadingOverlay({
    super.key,
    this.message = 'Sila tunggu...',
    this.transparent = false,
  });

  final String message;
  final bool transparent;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color:
          transparent
              ? Colors.transparent
              : Colors.black.withValues(alpha: 0.35),
      child: Center(
        child: Card(
          margin: const EdgeInsets.all(Spacing.xl),
          shape: const RoundedRectangleBorder(borderRadius: AppRadius.card),
          child: Padding(
            padding: const EdgeInsets.all(Spacing.xl),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const SizedBox(
                  width: 32,
                  height: 32,
                  child: CircularProgressIndicator(
                    strokeWidth: 3,
                    color: AppColors.movementGreen,
                  ),
                ),
                const SizedBox(height: 16),
                Text(message, style: Theme.of(context).textTheme.bodyLarge),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
