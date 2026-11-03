import 'package:flutter/material.dart';

import '../models/position_badge.dart';
import '../theme/app_colors.dart';

/// Chip jawatan untuk profil dan kad digital.
///
/// Warna jawatan hanya menjadi aksen bar kecil. Latar dan teks kekal neutral
/// supaya kontras tidak bergantung pada warna yang dipilih admin.
class PositionBadgeChip extends StatelessWidget {
  const PositionBadgeChip({
    super.key,
    required this.badge,
    this.dark = false,
    this.scale = 1,
    this.maxWidth = 320,
  });

  final PositionBadge badge;

  /// `true` bila chip berada atas latar gelap seperti header profil.
  final bool dark;

  final double scale;
  final double maxWidth;

  @override
  Widget build(BuildContext context) {
    if (badge.isEmpty) return const SizedBox.shrink();

    final accent =
        _parseHexColor(badge.color) ??
        (dark ? AppColors.movementSoftGreen : AppColors.movementGreen);
    final background =
        dark ? AppColors.white.withValues(alpha: .16) : AppColors.white;
    final foreground = dark ? AppColors.white : AppColors.cardNameInk;

    return ConstrainedBox(
      constraints: BoxConstraints(maxWidth: maxWidth),
      child: Container(
        padding: EdgeInsets.symmetric(
          horizontal: 10 * scale,
          vertical: 5 * scale,
        ),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(8 * scale),
          border:
              dark
                  ? Border.all(color: AppColors.white.withValues(alpha: .18))
                  : null,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 3 * scale,
              height: 15 * scale,
              decoration: BoxDecoration(
                color: accent,
                borderRadius: BorderRadius.circular(2 * scale),
              ),
            ),
            SizedBox(width: 7 * scale),
            Flexible(
              child: Text(
                badge.name!.trim(),
                maxLines: 1,
                softWrap: false,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 13.5 * scale,
                  fontWeight: FontWeight.w700,
                  letterSpacing: .2 * scale,
                  color: foreground,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

Color? _parseHexColor(String? raw) {
  final value = (raw ?? '').trim().replaceFirst('#', '');
  if (value.length != 6) return null;

  final parsed = int.tryParse(value, radix: 16);
  if (parsed == null) return null;

  return Color(0xFF000000 | parsed);
}
