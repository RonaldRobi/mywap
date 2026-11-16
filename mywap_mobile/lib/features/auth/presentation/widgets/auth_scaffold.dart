import 'package:flutter/material.dart';

import '../../../../shared/theme/app_colors.dart';
import '../../../../shared/theme/app_theme.dart';
import 'auth_canvas_background.dart';

/// Shared authentication shell. The green wave canvas and white content card
/// keep every account flow visually connected to the login screen.
class AuthScaffold extends StatelessWidget {
  const AuthScaffold({
    super.key,
    required this.title,
    required this.subtitle,
    required this.children,
    this.onBack,
    this.footer,
  });

  final String title;
  final String subtitle;
  final List<Widget> children;
  final VoidCallback? onBack;
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: AuthCanvasBackground(
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(
              Spacing.lg,
              Spacing.md,
              Spacing.lg,
              Spacing.xl,
            ),
            child: ConstrainedBox(
              constraints: BoxConstraints(
                minHeight:
                    MediaQuery.sizeOf(context).height -
                    MediaQuery.paddingOf(context).vertical -
                    Spacing.xxl,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (onBack != null)
                    Align(
                      alignment: Alignment.centerLeft,
                      child: IconButton(
                        onPressed: onBack,
                        style: IconButton.styleFrom(
                          backgroundColor: AppColors.white.withValues(
                            alpha: .7,
                          ),
                          shape: const RoundedRectangleBorder(
                            borderRadius: AppRadius.md,
                          ),
                        ),
                        icon: const Icon(
                          Icons.arrow_back_ios_new_rounded,
                          size: 18,
                        ),
                      ),
                    ),
                  const SizedBox(height: Spacing.sm),
                  Center(
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 420),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const _AuthBrandHeader(),
                          const SizedBox(height: Spacing.lg),
                          Container(
                            padding: const EdgeInsets.all(Spacing.xl),
                            decoration: BoxDecoration(
                              color: AppColors.white.withValues(alpha: .96),
                              borderRadius: AppRadius.hero,
                              border: Border.all(color: AppColors.white),
                              boxShadow: AppShadows.card,
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Text(
                                  title,
                                  style: Theme.of(
                                    context,
                                  ).textTheme.headlineMedium?.copyWith(
                                    color: AppColors.textPrimary,
                                    fontWeight: FontWeight.w800,
                                    height: 1.15,
                                  ),
                                ),
                                const SizedBox(height: Spacing.sm),
                                Text(
                                  subtitle,
                                  style: Theme.of(
                                    context,
                                  ).textTheme.bodyMedium?.copyWith(
                                    color: AppColors.textSecondary,
                                    height: 1.45,
                                  ),
                                ),
                                const SizedBox(height: Spacing.xl),
                                ...children,
                                if (footer != null) ...[
                                  const SizedBox(height: Spacing.lg),
                                  footer!,
                                ],
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _AuthBrandHeader extends StatelessWidget {
  const _AuthBrandHeader();

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 48,
        height: 48,
        decoration: BoxDecoration(
          color: AppColors.movementDarkGreen,
          borderRadius: const BorderRadius.all(Radius.circular(4)),
          border: Border.all(color: AppColors.movementNavy),
        ),
        child: const Icon(
          Icons.volunteer_activism_outlined,
          color: AppColors.white,
          size: 26,
        ),
      ),
      const SizedBox(width: Spacing.md),
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'myWAP',
            style: TextStyle(
              color: AppColors.movementDarkGreen,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          Text(
            'WADAH | ABIM | PKPIM',
            style: TextStyle(
              color: AppColors.textSecondary,
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: .8,
            ),
          ),
        ],
      ),
    ],
  );
}

/// Field label + [TextFormField], matching the login screen's visual rhythm.
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

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: Theme.of(
            context,
          ).textTheme.labelLarge?.copyWith(color: AppColors.textPrimary),
        ),
        const SizedBox(height: Spacing.sm),
        TextFormField(
          controller: controller,
          keyboardType: keyboardType,
          obscureText: obscureText,
          textInputAction: textInputAction,
          onFieldSubmitted: onFieldSubmitted,
          autofillHints: autofillHints,
          validator: validator,
          decoration: InputDecoration(
            hintText: hint,
            prefixIcon:
                icon != null ? Icon(icon, color: AppColors.textTertiary) : null,
            suffixIcon: suffixIcon,
            fillColor: AppColors.surfaceMuted,
            filled: true,
            contentPadding: const EdgeInsets.symmetric(
              horizontal: Spacing.md,
              vertical: Spacing.md,
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
}

class AuthErrorBanner extends StatelessWidget {
  const AuthErrorBanner({super.key, required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: Spacing.lg),
    padding: const EdgeInsets.all(Spacing.md),
    decoration: BoxDecoration(
      color: AppColors.error.withValues(alpha: .08),
      borderRadius: AppRadius.md,
      border: Border.all(color: AppColors.error.withValues(alpha: .3)),
    ),
    child: Row(
      children: [
        const Icon(Icons.error_outline, color: AppColors.error),
        const SizedBox(width: Spacing.md),
        Expanded(
          child: Text(message, style: const TextStyle(color: AppColors.error)),
        ),
      ],
    ),
  );
}

class AuthSuccessBanner extends StatelessWidget {
  const AuthSuccessBanner({super.key, required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: Spacing.lg),
    padding: const EdgeInsets.all(Spacing.md),
    decoration: BoxDecoration(
      color: AppColors.success.withValues(alpha: .08),
      borderRadius: AppRadius.md,
      border: Border.all(color: AppColors.success.withValues(alpha: .3)),
    ),
    child: Row(
      children: [
        const Icon(Icons.check_circle_outline, color: AppColors.success),
        const SizedBox(width: Spacing.md),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(color: AppColors.success),
          ),
        ),
      ],
    ),
  );
}
