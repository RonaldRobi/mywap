import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../theme/app_colors.dart';
import '../theme/app_theme.dart';

/// Friendly "page not found" — shown by go_router's `errorBuilder` when a
/// route doesn't resolve (unknown path, or a screen that failed to build).
class RouteNotFoundScreen extends StatelessWidget {
  const RouteNotFoundScreen({super.key, this.message});

  final String? message;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Halaman Tidak Dijumpai')),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(Spacing.xl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.explore_off_outlined,
                size: 72,
                color: AppColors.movementSoftGreen,
              ),
              const SizedBox(height: Spacing.lg),
              Text(
                'Halaman ini tidak dijumpai',
                style: Theme.of(context).textTheme.headlineSmall,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: Spacing.sm),
              Text(
                message ??
                    'Sila cuba semula, atau kembali ke laman utama.',
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                  color: AppColors.textSecondary,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: Spacing.xl),
              FilledButton.icon(
                onPressed: () => context.go('/dashboard'),
                icon: const Icon(Icons.home_outlined),
                label: const Text('Ke Laman Utama'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
