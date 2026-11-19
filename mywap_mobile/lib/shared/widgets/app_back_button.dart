import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../l10n/l10n.dart';
import '../theme/app_colors.dart';

/// Butang 'Back' terapung dalam bentuk bulatan gaya Spotify.
///
/// Digunakan pada AppBar atau sebagai butang overlay pada sub-halaman
/// dan pautan terus (deep link).
class AppBackButton extends StatelessWidget {
  const AppBackButton({
    super.key,
    this.fallback = '/dashboard',
    this.onPressed,
    this.light = false,
  });

  final String fallback;
  final VoidCallback? onPressed;
  final bool light;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Tooltip(
        message: context.l10n.back,
        child: Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: light
                ? Colors.black.withValues(alpha: 0.45)
                : AppColors.white.withValues(alpha: 0.96),
            boxShadow: light
                ? null
                : [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.08),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
            border: Border.all(
              color: light
                  ? Colors.white.withValues(alpha: 0.2)
                  : AppColors.divider.withValues(alpha: 0.6),
              width: 0.8,
            ),
          ),
          child: Material(
            color: Colors.transparent,
            shape: const CircleBorder(),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: onPressed ??
                  () {
                    if (Navigator.of(context).canPop()) {
                      Navigator.of(context).pop();
                    } else {
                      context.go(fallback);
                    }
                  },
              child: Icon(
                Icons.arrow_back_rounded,
                size: 20,
                color: light ? AppColors.white : AppColors.movementNavy,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
