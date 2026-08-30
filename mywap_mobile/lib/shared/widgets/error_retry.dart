import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_theme.dart';

/// Friendly error state with a retry action.
class ErrorRetry extends StatelessWidget {
  const ErrorRetry({super.key, required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.xl),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(Spacing.xxl),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: AppRadius.hero,
            border: Border.all(color: AppColors.divider),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: AppColors.softGreenSurface,
                  borderRadius: AppRadius.lg,
                ),
                child: const Icon(
                  Icons.cloud_off_outlined,
                  size: 28,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: Spacing.lg),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: Spacing.lg),
              FilledButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: const Text('Cuba Semula'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
