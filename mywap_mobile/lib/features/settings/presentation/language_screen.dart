import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/localization/app_locale.dart';
import '../../../core/router/startup_destination.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/pattern_backdrop.dart';

/// Skrin pilih bahasa kali pertama.
///
/// Teks sengaja dwibahasa dan tidak bergantung kepada locale semasa supaya
/// pengguna yang belum memilih bahasa tetap memahami pilihan yang ada.
class LanguageScreen extends ConsumerStatefulWidget {
  const LanguageScreen({super.key});

  @override
  ConsumerState<LanguageScreen> createState() => _LanguageScreenState();
}

class _LanguageScreenState extends ConsumerState<LanguageScreen> {
  bool _saving = false;

  Future<void> _choose(String code) async {
    if (_saving) return;
    setState(() => _saving = true);

    await ref.read(appLocaleProvider.notifier).setLocale(Locale(code));
    if (!mounted) return;

    final destination = await resolveStartDestination(ref);
    if (!mounted) return;
    context.go(destination);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final recommended = supportedLocaleForDevice(
      WidgetsBinding.instance.platformDispatcher.locale,
    );

    return Scaffold(
      body: PatternBackdrop(
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Spacer(),
                Text(
                  'myWAP',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.headlineMedium?.copyWith(
                    color: AppColors.white,
                    fontWeight: FontWeight.w700,
                    letterSpacing: .3,
                  ),
                ),
                const SizedBox(height: Spacing.xl),
                Text(
                  'Pilih Bahasa',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.headlineSmall?.copyWith(
                    color: AppColors.white,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: Spacing.xs),
                Text(
                  'Choose Language',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: AppColors.textOnDark,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: Spacing.lg),
                Text(
                  'Pilih bahasa untuk aplikasi ini. Anda boleh tukar kemudian '
                  'di Tetapan.\n'
                  'Choose the app language. You can change it later in Settings.',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: AppColors.textOnDark,
                    height: 1.5,
                  ),
                ),
                const Spacer(),
                _LanguageOption(
                  title: 'Bahasa Melayu',
                  subtitle: 'Malay',
                  recommended: recommended.languageCode == malayLanguageCode,
                  enabled: !_saving,
                  onTap: () => _choose(malayLanguageCode),
                ),
                const SizedBox(height: Spacing.md),
                _LanguageOption(
                  title: 'English',
                  subtitle: 'Inggeris',
                  recommended: recommended.languageCode == englishLanguageCode,
                  enabled: !_saving,
                  onTap: () => _choose(englishLanguageCode),
                ),
                const Spacer(),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _LanguageOption extends StatelessWidget {
  const _LanguageOption({
    required this.title,
    required this.subtitle,
    required this.recommended,
    required this.enabled,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final bool recommended;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Material(
      color: AppColors.white,
      borderRadius: AppRadius.card,
      child: InkWell(
        borderRadius: AppRadius.card,
        onTap: enabled ? onTap : null,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: AppSizes.tapTarget + 16),
          child: Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: Spacing.lg,
              vertical: Spacing.md,
            ),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
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
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        title,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                      if (recommended) ...[
                        const SizedBox(height: 2),
                        Text(
                          'Disyorkan · Recommended',
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: AppColors.movementGreen,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const Icon(
                  Icons.chevron_right_rounded,
                  color: AppColors.textTertiary,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
