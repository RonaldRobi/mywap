import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';

/// Papar dialog "log masuk diperlukan" kepada tetamu yang menekan ciri ahli
/// (reaksi, komen, RSVP, derma sebagai ahli, dsb.).
Future<void> showLoginPrompt(BuildContext context, {String? message}) {
  return showDialog<void>(
    context: context,
    builder:
        (dialogContext) => AlertDialog(
          title: const Text('Log Masuk Diperlukan'),
          content: Text(
            message ??
                'Ciri ini tersedia untuk ahli. Sila log masuk atau daftar '
                    'akaun untuk meneruskan.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Batal'),
            ),
            FilledButton(
              onPressed: () {
                Navigator.of(dialogContext).pop();
                context.go('/login');
              },
              child: const Text('Log Masuk'),
            ),
          ],
        ),
  );
}

/// CTA banner shown to guests where a member-only action would otherwise be.
class GuestLoginBanner extends StatelessWidget {
  const GuestLoginBanner({
    super.key,
    this.message = 'Log masuk untuk akses penuh sebagai ahli.',
  });

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(Spacing.lg),
      decoration: BoxDecoration(
        color: AppColors.softGreenSurface,
        borderRadius: AppRadius.md,
      ),
      child: Row(
        children: [
          const Icon(Icons.lock_outline, color: AppColors.movementGreen),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Text(message, style: Theme.of(context).textTheme.bodySmall),
          ),
          TextButton(
            onPressed: () => context.go('/login'),
            child: const Text('Log Masuk'),
          ),
        ],
      ),
    );
  }
}
