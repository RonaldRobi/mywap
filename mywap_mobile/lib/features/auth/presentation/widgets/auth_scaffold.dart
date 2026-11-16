import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/localization/app_locale.dart';
import '../../../../shared/theme/app_colors.dart';
import '../../../../shared/theme/app_theme.dart';
import '../../../onboarding/application/onboarding_providers.dart';
import 'auth_canvas_background.dart';

class AuthScaffold extends StatelessWidget {
  const AuthScaffold({
    super.key,
    required this.title,
    required this.subtitle,
    required this.children,
    this.onBack,
    this.footer,
    this.icon = Icons.shield_outlined,
    this.steps,
    this.activeStep,
  });

  final String title;
  final String subtitle;
  final List<Widget> children;
  final VoidCallback? onBack;
  final Widget? footer;
  final IconData icon;
  final List<String>? steps;
  final int? activeStep;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: AuthCanvasBackground(
      child: SafeArea(
        child: SingleChildScrollView(
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          padding: const EdgeInsets.fromLTRB(
            Spacing.lg,
            Spacing.md,
            Spacing.lg,
            Spacing.xl,
          ),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const AuthBrandHeader(),
                  const SizedBox(height: Spacing.lg),
                  if (onBack != null) ...[
                    Align(
                      alignment: Alignment.centerLeft,
                      child: IconButton.filledTonal(
                        onPressed: onBack,
                        tooltip:
                            MaterialLocalizations.of(context).backButtonTooltip,
                        icon: const Icon(Icons.arrow_back_rounded),
                      ),
                    ),
                    const SizedBox(height: Spacing.md),
                  ],
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: const BoxDecoration(
                          color: AppColors.greenTint,
                          borderRadius: AppRadius.md,
                        ),
                        child: Icon(icon, color: AppColors.movementDarkGreen),
                      ),
                      const SizedBox(width: Spacing.md),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              title,
                              style: Theme.of(
                                context,
                              ).textTheme.headlineSmall?.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w800,
                                height: 1.15,
                              ),
                            ),
                            const SizedBox(height: Spacing.xs),
                            Text(
                              subtitle,
                              style: Theme.of(
                                context,
                              ).textTheme.bodyMedium?.copyWith(
                                color: AppColors.textSecondary,
                                height: 1.4,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  if (steps != null && steps!.isNotEmpty) ...[
                    const SizedBox(height: Spacing.lg),
                    AuthStepStrip(steps: steps!, activeStep: activeStep ?? 0),
                  ],
                  const SizedBox(height: Spacing.lg),
                  AuthCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        ...children,
                        if (footer != null) ...[
                          const SizedBox(height: Spacing.md),
                          const Divider(height: 1),
                          const SizedBox(height: Spacing.md),
                          footer!,
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

class AuthBrandHeader extends ConsumerWidget {
  const AuthBrandHeader({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final locale = ref.watch(appLocaleProvider) ?? const Locale('ms');
    final logoUrl =
        ref.watch(mobileAuthConfigurationProvider).valueOrNull?.login.logoUrl;

    return Container(
      padding: const EdgeInsets.all(Spacing.sm),
      decoration: BoxDecoration(
        color: AppColors.white.withValues(alpha: .86),
        borderRadius: AppRadius.lg,
        border: Border.all(color: AppColors.white),
        boxShadow: AppShadows.subtle,
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: AppColors.movementDarkGreen,
              borderRadius: const BorderRadius.all(Radius.circular(4)),
              border: Border.all(color: AppColors.movementNavy),
            ),
            child:
                logoUrl?.isNotEmpty == true
                    ? ClipRRect(
                      borderRadius: const BorderRadius.all(Radius.circular(3)),
                      child: Image.network(
                        logoUrl!,
                        fit: BoxFit.contain,
                        errorBuilder: (_, __, ___) => const _FallbackLogo(),
                      ),
                    )
                    : const _FallbackLogo(),
          ),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      'myWAP',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.movementDarkGreen,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(width: Spacing.xs),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 6,
                        vertical: 3,
                      ),
                      decoration: const BoxDecoration(
                        color: AppColors.greenTint,
                        borderRadius: AppRadius.xs,
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.verified_rounded,
                            size: 12,
                            color: AppColors.movementGreen,
                          ),
                          const SizedBox(width: 3),
                          Text(
                            locale.languageCode == 'en'
                                ? 'myWAP Official'
                                : 'Rasmi myWAP',
                            style: const TextStyle(
                              color: AppColors.movementDarkGreen,
                              fontSize: 9,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 2),
                const Text(
                  'WADAH | ABIM | PKPIM',
                  style: TextStyle(
                    color: AppColors.textSecondary,
                    fontSize: 9,
                    fontWeight: FontWeight.w700,
                    letterSpacing: .6,
                  ),
                ),
              ],
            ),
          ),
          _LanguageToggle(locale: locale),
        ],
      ),
    );
  }
}

class _FallbackLogo extends StatelessWidget {
  const _FallbackLogo();

  @override
  Widget build(BuildContext context) => const Column(
    mainAxisAlignment: MainAxisAlignment.center,
    children: [
      Icon(Icons.volunteer_activism_outlined, color: AppColors.white, size: 21),
      SizedBox(height: 1),
      Text(
        'myWAP',
        style: TextStyle(
          color: AppColors.white,
          fontSize: 8,
          fontWeight: FontWeight.w800,
        ),
      ),
    ],
  );
}

class _LanguageToggle extends ConsumerWidget {
  const _LanguageToggle({required this.locale});

  final Locale locale;

  @override
  Widget build(BuildContext context, WidgetRef ref) => Container(
    padding: const EdgeInsets.all(2),
    decoration: BoxDecoration(
      color: AppColors.surfaceMuted,
      borderRadius: AppRadius.md,
      border: Border.all(color: AppColors.divider),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        _LanguageButton(
          label: 'EN',
          selected: locale.languageCode == 'en',
          onPressed:
              () => ref
                  .read(appLocaleProvider.notifier)
                  .setLocale(const Locale('en')),
        ),
        _LanguageButton(
          label: 'BM',
          selected: locale.languageCode != 'en',
          onPressed:
              () => ref
                  .read(appLocaleProvider.notifier)
                  .setLocale(const Locale('ms')),
        ),
      ],
    ),
  );
}

class _LanguageButton extends StatelessWidget {
  const _LanguageButton({
    required this.label,
    required this.selected,
    required this.onPressed,
  });

  final String label;
  final bool selected;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 44,
    height: 44,
    child: TextButton(
      onPressed: onPressed,
      style: TextButton.styleFrom(
        padding: EdgeInsets.zero,
        minimumSize: const Size(44, 44),
        foregroundColor: selected ? AppColors.white : AppColors.textSecondary,
        backgroundColor: selected ? AppColors.movementDarkGreen : null,
        shape: const RoundedRectangleBorder(borderRadius: AppRadius.sm),
        textStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
      ),
      child: Text(label),
    ),
  );
}

class AuthCard extends StatelessWidget {
  const AuthCard({super.key, required this.child, this.padding});

  final Widget child;
  final EdgeInsetsGeometry? padding;

  @override
  Widget build(BuildContext context) => Container(
    padding: padding ?? const EdgeInsets.all(Spacing.lg),
    decoration: BoxDecoration(
      color: AppColors.white.withValues(alpha: .96),
      borderRadius: AppRadius.xl,
      border: Border.all(color: AppColors.white),
      boxShadow: AppShadows.card,
    ),
    child: child,
  );
}

class AuthStepStrip extends StatelessWidget {
  const AuthStepStrip({
    super.key,
    required this.steps,
    required this.activeStep,
  });

  final List<String> steps;
  final int activeStep;

  @override
  Widget build(BuildContext context) => Row(
    children: List.generate(steps.length, (index) {
      final active = index == activeStep;
      final complete = index < activeStep;
      return Expanded(
        child: Container(
          constraints: const BoxConstraints(minHeight: 46),
          margin: EdgeInsets.only(right: index == steps.length - 1 ? 0 : 6),
          padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 7),
          decoration: BoxDecoration(
            color:
                active
                    ? AppColors.accentTint
                    : complete
                    ? AppColors.greenTint
                    : AppColors.surfaceMuted,
            borderRadius: AppRadius.sm,
            border: Border.all(
              color:
                  active
                      ? AppColors.accent.withValues(alpha: .45)
                      : AppColors.divider,
            ),
          ),
          child: Center(
            child: Text(
              steps[index],
              textAlign: TextAlign.center,
              maxLines: 2,
              style: TextStyle(
                color:
                    active
                        ? AppColors.accent
                        : complete
                        ? AppColors.movementDarkGreen
                        : AppColors.textSecondary,
                fontSize: 10,
                fontWeight:
                    active || complete ? FontWeight.w800 : FontWeight.w600,
              ),
            ),
          ),
        ),
      );
    }),
  );
}

class AuthField extends StatelessWidget {
  const AuthField({
    super.key,
    required this.label,
    required this.controller,
    this.hint,
    this.icon,
    this.keyboardType,
    this.obscureText = false,
    this.suffixIcon,
    this.validator,
    this.textInputAction,
    this.onFieldSubmitted,
    this.autofillHints,
    this.onChanged,
  });

  final String label;
  final TextEditingController controller;
  final String? hint;
  final IconData? icon;
  final TextInputType? keyboardType;
  final bool obscureText;
  final Widget? suffixIcon;
  final String? Function(String?)? validator;
  final TextInputAction? textInputAction;
  final void Function(String)? onFieldSubmitted;
  final Iterable<String>? autofillHints;
  final ValueChanged<String>? onChanged;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        label,
        style: Theme.of(context).textTheme.labelLarge?.copyWith(
          color: AppColors.textPrimary,
          fontWeight: FontWeight.w700,
        ),
      ),
      const SizedBox(height: 6),
      TextFormField(
        controller: controller,
        keyboardType: keyboardType,
        obscureText: obscureText,
        textInputAction: textInputAction,
        onFieldSubmitted: onFieldSubmitted,
        autofillHints: autofillHints,
        validator: validator,
        onChanged: onChanged,
        style: const TextStyle(
          color: AppColors.textPrimary,
          fontSize: 14,
          fontWeight: FontWeight.w600,
        ),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: const TextStyle(
            color: AppColors.textTertiary,
            fontSize: 13,
            fontWeight: FontWeight.w400,
          ),
          prefixIcon:
              icon != null
                  ? Icon(icon, color: AppColors.textTertiary, size: 21)
                  : null,
          suffixIcon: suffixIcon,
          fillColor: AppColors.surfaceMuted,
          filled: true,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: Spacing.md,
            vertical: 14,
          ),
          border: OutlineInputBorder(
            borderRadius: AppRadius.md,
            borderSide: const BorderSide(color: AppColors.inputBorder),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: AppRadius.md,
            borderSide: const BorderSide(color: AppColors.inputBorder),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: AppRadius.md,
            borderSide: const BorderSide(
              color: AppColors.movementDarkGreen,
              width: 1.8,
            ),
          ),
        ),
      ),
      const SizedBox(height: Spacing.lg),
    ],
  );
}

class AuthErrorBanner extends StatelessWidget {
  const AuthErrorBanner({super.key, required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => _AuthBanner(
    message: message,
    color: AppColors.error,
    icon: Icons.error_outline,
  );
}

class AuthSuccessBanner extends StatelessWidget {
  const AuthSuccessBanner({super.key, required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => _AuthBanner(
    message: message,
    color: AppColors.success,
    icon: Icons.check_circle_outline,
  );
}

class _AuthBanner extends StatelessWidget {
  const _AuthBanner({
    required this.message,
    required this.color,
    required this.icon,
  });

  final String message;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: Spacing.lg),
    padding: const EdgeInsets.all(Spacing.md),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .08),
      borderRadius: AppRadius.md,
      border: Border.all(color: color.withValues(alpha: .3)),
    ),
    child: Row(
      children: [
        Icon(icon, color: color),
        const SizedBox(width: Spacing.md),
        Expanded(child: Text(message, style: TextStyle(color: color))),
      ],
    ),
  );
}
