import 'package:flutter/material.dart';

import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_back_button.dart';

class FaqScreen extends StatelessWidget {
  const FaqScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final items = <_FaqItem>[
      _FaqItem(question: l10n.faqQ1, answer: l10n.faqA1),
      _FaqItem(question: l10n.faqQ2, answer: l10n.faqA2),
      _FaqItem(question: l10n.faqQ3, answer: l10n.faqA3),
      _FaqItem(question: l10n.faqQ4, answer: l10n.faqA4),
      _FaqItem(question: l10n.faqQ5, answer: l10n.faqA5),
      _FaqItem(question: l10n.faqQ6, answer: l10n.faqA6),
      _FaqItem(question: l10n.faqQ7, answer: l10n.faqA7),
    ];

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(fallback: '/home'),
        title: Text(l10n.faqTitle),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          Spacing.lg,
          Spacing.md,
          Spacing.lg,
          Spacing.xxl,
        ),
        children: [
          Container(
            padding: const EdgeInsets.all(Spacing.lg),
            decoration: BoxDecoration(
              color: AppColors.softGreenSurface,
              borderRadius: AppRadius.lg,
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(
                  Icons.help_outline,
                  color: AppColors.movementDarkGreen,
                  size: AppSizes.iconMd,
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: Text(
                    l10n.faqIntro,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.textPrimary,
                      height: 1.5,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: Spacing.xl),
          for (final item in items) _FaqTile(item: item),
        ],
      ),
    );
  }
}

class _FaqItem {
  const _FaqItem({required this.question, required this.answer});

  final String question;
  final String answer;
}

class _FaqTile extends StatelessWidget {
  const _FaqTile({required this.item});

  final _FaqItem item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      margin: const EdgeInsets.only(bottom: Spacing.sm),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: AppRadius.md,
        border: Border.all(color: AppColors.divider),
      ),
      clipBehavior: Clip.antiAlias,
      child: Theme(
        data: theme.copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(
            horizontal: Spacing.lg,
            vertical: Spacing.xs,
          ),
          childrenPadding: const EdgeInsets.fromLTRB(
            Spacing.lg,
            0,
            Spacing.lg,
            Spacing.lg,
          ),
          expandedCrossAxisAlignment: CrossAxisAlignment.start,
          iconColor: AppColors.movementDarkGreen,
          collapsedIconColor: AppColors.textSecondary,
          title: Text(
            item.question,
            style: theme.textTheme.titleSmall?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w700,
              height: 1.4,
            ),
          ),
          children: [
            Text(
              item.answer,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: AppColors.textSecondary,
                height: 1.55,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
