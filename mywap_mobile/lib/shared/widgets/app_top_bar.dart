import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import 'app_back_button.dart';

/// Header bar overlay telus bersandar gaya Apple/Spotify.
///
/// - Pada tab utama (`showBack == false`): Saiz ketinggian ialah 0 (tiada gap langsung).
/// - Pada sub-halaman (`showBack == true`): Memaparkan butang 'Back' bulatan terapung
///   di kiri atas bersebelahan tajuk halaman. Belah kanan kosong sepenuhnya.
class AppTopBar extends StatelessWidget implements PreferredSizeWidget {
  const AppTopBar({
    super.key,
    this.icon,
    this.semanticLabel,
    this.title,
    this.showBack = false,
    this.backFallback = '/dashboard',
    this.actions,
    this.light = false,
  });

  final IconData? icon;
  final String? semanticLabel;
  final String? title;
  final bool showBack;
  final String backFallback;
  final List<Widget>? actions;
  final bool light;

  @override
  Size get preferredSize =>
      showBack ? const Size.fromHeight(48) : Size.zero;

  @override
  Widget build(BuildContext context) {
    if (!showBack) {
      return const SizedBox.shrink();
    }

    return AppBar(
      automaticallyImplyLeading: false,
      backgroundColor: Colors.transparent,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      toolbarHeight: 48,
      leadingWidth: 54,
      leading: Padding(
        padding: const EdgeInsets.only(left: 14),
        child: AppBackButton(
          fallback: backFallback,
          light: light,
        ),
      ),
      titleSpacing: 4,
      title: title != null && title!.isNotEmpty
          ? Text(
              title!,
              style: TextStyle(
                color: light ? AppColors.white : AppColors.textPrimary,
                fontSize: 18,
                fontWeight: FontWeight.w700,
                letterSpacing: -0.4,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            )
          : null,
      centerTitle: false,
      actions: null, // Belah kanan kosong sepenuhnya
    );
  }
}
