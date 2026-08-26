import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_theme.dart';

/// Reusable tappable list row with leading widget, title, subtitle and chevron.
class ListCard extends StatelessWidget {
  const ListCard({
    super.key,
    required this.title,
    this.leading,
    this.subtitle,
    this.onTap,
    this.trailing,
  });

  final Widget? leading;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final content = Row(
      children: [
        if (leading != null) ...[
          leading!,
          const SizedBox(width: Spacing.lg),
        ],
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: theme.textTheme.titleMedium),
              if (subtitle != null) ...[
                const SizedBox(height: 2),
                Text(
                  subtitle!,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ],
          ),
        ),
        if (trailing != null)
          trailing!
        else if (onTap != null)
          const Icon(Icons.chevron_right, color: AppColors.textSecondary),
      ],
    );

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: Spacing.lg, vertical: Spacing.sm),
      child: onTap == null
          ? Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: content,
            )
          : InkWell(
              onTap: onTap,
              borderRadius: AppRadius.lg,
              child: Padding(
                padding: const EdgeInsets.all(Spacing.lg),
                child: content,
              ),
            ),
    );
  }
}
