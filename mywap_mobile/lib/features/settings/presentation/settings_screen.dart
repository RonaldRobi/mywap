import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/localization/app_locale.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_back_button.dart';

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  Future<void> _pickLanguage(BuildContext context, WidgetRef ref) async {
    final current = ref.read(appLocaleProvider) ?? const Locale('ms');
    final l10n = context.l10n;

    final selected = await showModalBottomSheet<Locale>(
      context: context,
      useSafeArea: true,
      showDragHandle: true,
      backgroundColor: AppColors.surface,
      builder:
          (sheetContext) => Padding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.lg,
              0,
              Spacing.lg,
              Spacing.xl,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.only(bottom: Spacing.sm),
                  child: Text(
                    l10n.settingsLanguageSheetTitle,
                    style: Theme.of(sheetContext).textTheme.titleLarge,
                  ),
                ),
                _LanguageTile(
                  label: 'Bahasa Melayu',
                  selected: current.languageCode == malayLanguageCode,
                  onTap:
                      () => Navigator.of(sheetContext).pop(const Locale('ms')),
                ),
                _LanguageTile(
                  label: 'English',
                  selected: current.languageCode == englishLanguageCode,
                  onTap:
                      () => Navigator.of(sheetContext).pop(const Locale('en')),
                ),
              ],
            ),
          ),
    );

    if (selected != null) {
      await ref.read(appLocaleProvider.notifier).setLocale(selected);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final locale = ref.watch(appLocaleProvider) ?? const Locale('ms');
    final languageLabel =
        locale.languageCode == englishLanguageCode
            ? 'English'
            : 'Bahasa Melayu';

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(l10n.settingsTitle),
      ),
      body: ListView(
        padding: const EdgeInsets.all(Spacing.lg),
        children: [
          Material(
            color: AppColors.surface,
            borderRadius: AppRadius.card,
            child: ListTile(
              shape: RoundedRectangleBorder(borderRadius: AppRadius.card),
              leading: Container(
                width: 42,
                height: 42,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.greenTint,
                  borderRadius: AppRadius.md,
                ),
                child: const Icon(
                  Icons.translate_rounded,
                  color: AppColors.movementGreen,
                ),
              ),
              title: Text(l10n.settingsLanguage),
              subtitle: Text(
                '${l10n.settingsLanguageSubtitle} · $languageLabel',
              ),
              trailing: const Icon(
                Icons.chevron_right_rounded,
                color: AppColors.textTertiary,
              ),
              onTap: () => _pickLanguage(context, ref),
            ),
          ),
        ],
      ),
    );
  }
}

class _LanguageTile extends StatelessWidget {
  const _LanguageTile({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(label),
      trailing:
          selected
              ? const Icon(
                Icons.check_circle_rounded,
                color: AppColors.movementGreen,
              )
              : null,
      onTap: onTap,
    );
  }
}
