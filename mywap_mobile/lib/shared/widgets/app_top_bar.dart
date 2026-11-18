import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_theme.dart';
import 'app_back_button.dart';

/// Header skrin tanpa tajuk teks.
///
/// Fokus visual ialah chip ikon seksyen (bukan tajuk), di atas gradien lembut
/// berjenama dengan garis halus di bawah. Label aksesibiliti kekal supaya
/// pembaca skrin masih tahu seksyen semasa walaupun tiada tajuk kelihatan.
class AppTopBar extends StatelessWidget implements PreferredSizeWidget {
  const AppTopBar({
    super.key,
    required this.icon,
    required this.semanticLabel,
    this.showBack = false,
    this.backFallback = '/dashboard',
    this.actions,
  });

  final IconData icon;
  final String semanticLabel;
  final bool showBack;
  final String backFallback;
  final List<Widget>? actions;

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);

  @override
  Widget build(BuildContext context) {
    return AppBar(
      automaticallyImplyLeading: false,
      backgroundColor: Colors.transparent,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      leadingWidth: showBack ? 56 : null,
      leading: showBack ? AppBackButton(fallback: backFallback) : null,
      titleSpacing: showBack ? 0 : Spacing.lg,
      title: Semantics(
        label: semanticLabel,
        header: true,
        child: Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: AppColors.paleGreen,
            borderRadius: AppRadius.sm,
            border: Border.all(
              color: AppColors.movementSoftGreen.withValues(alpha: 0.55),
            ),
          ),
          child: Icon(icon, size: 21, color: AppColors.movementDarkGreen),
        ),
      ),
      actions: actions,
      flexibleSpace: const _TopBarBackdrop(),
    );
  }
}

class _TopBarBackdrop extends StatelessWidget {
  const _TopBarBackdrop();

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        const Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Color(0xFFE9F6EC), AppColors.pageBackground],
              ),
            ),
          ),
        ),
        Align(
          alignment: Alignment.bottomCenter,
          child: Container(height: 1, color: AppColors.divider),
        ),
      ],
    );
  }
}
