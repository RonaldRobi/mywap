import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/providers.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../application/auth_controller.dart';
import 'widgets/auth_scaffold.dart';

/// Tetapkan kata laluan baharu daripada pautan emel.
///
/// Link: `https://mywap.my/reset-password/{token}?email=...`
/// Route: `/reset-password/:token`
class ResetPasswordScreen extends ConsumerStatefulWidget {
  const ResetPasswordScreen({
    super.key,
    required this.token,
    required this.email,
  });

  final String token;
  final String email;

  @override
  ConsumerState<ResetPasswordScreen> createState() =>
      _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends ConsumerState<ResetPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();

  bool _obscure = true;
  bool _submitting = false;
  bool _success = false;
  String? _error;

  bool get _hasValidLink =>
      widget.token.trim().isNotEmpty && widget.email.trim().isNotEmpty;

  @override
  void dispose() {
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_hasValidLink) return;
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await ref
          .read(authRepositoryProvider)
          .resetPassword(
            token: widget.token,
            email: widget.email,
            password: _passwordController.text,
            passwordConfirmation: _confirmController.text,
          );

      try {
        final storage = ref.read(tokenStorageProvider);
        await storage.delete();
        await storage.setBiometricEnabled(false);
      } catch (_) {
        // Kegagalan membersih token tidak sepatutnya menutup kejayaan reset.
      }
      ref.read(authControllerProvider.notifier).resetToUnauthenticated();

      if (!mounted) return;
      setState(() {
        _submitting = false;
        _success = true;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = context.l10n.unexpectedErrorRetry;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_hasValidLink) {
      return AuthScaffold(
        title: context.l10n.resetPasswordTitle,
        subtitle: context.l10n.resetPasswordInvalidLink,
        footer: TextButton(
          onPressed: () => context.go('/login'),
          child: Text(context.l10n.backToLogin),
        ),
        children: [
          FilledButton(
            onPressed: () => context.go('/forgot-password'),
            child: Text(context.l10n.requestNewLink),
          ),
        ],
      );
    }

    if (_success) {
      return AuthScaffold(
        title: context.l10n.resetPasswordTitle,
        subtitle: context.l10n.resetPasswordSubtitle,
        children: [
          AuthSuccessBanner(message: context.l10n.passwordUpdated),
          FilledButton(
            onPressed: () => context.go('/login'),
            child: Text(context.l10n.backToLogin),
          ),
        ],
      );
    }

    return AuthScaffold(
      title: context.l10n.resetPasswordTitle,
      subtitle: context.l10n.resetPasswordSubtitle,
      footer: TextButton(
        onPressed: () => context.go('/login'),
        child: Text(context.l10n.backToLogin),
      ),
      children: [
        Text(
          context.l10n.registeredEmail(widget.email),
          style: Theme.of(
            context,
          ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
        ),
        const SizedBox(height: Spacing.lg),
        if (_error != null) AuthErrorBanner(message: _error!),
        Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AuthField(
                label: context.l10n.newPasswordLabel,
                controller: _passwordController,
                hint: context.l10n.newPasswordHint,
                icon: Icons.lock_outline,
                obscureText: _obscure,
                autofillHints: const [AutofillHints.newPassword],
                textInputAction: TextInputAction.next,
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return context.l10n.newPasswordRequired;
                  }
                  if (value.length < 8) {
                    return context.l10n.newPasswordMin;
                  }
                  return null;
                },
              ),
              AuthField(
                label: context.l10n.confirmPasswordLabel,
                controller: _confirmController,
                hint: context.l10n.confirmPasswordHint,
                icon: Icons.verified_user_outlined,
                obscureText: _obscure,
                autofillHints: const [AutofillHints.newPassword],
                textInputAction: TextInputAction.done,
                onFieldSubmitted: (_) => _submit(),
                validator:
                    (value) =>
                        value != _passwordController.text
                            ? context.l10n.passwordMismatch
                            : null,
              ),
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton.icon(
                  onPressed: () => setState(() => _obscure = !_obscure),
                  icon: Icon(
                    _obscure ? Icons.visibility_off : Icons.visibility,
                    size: 18,
                  ),
                  label: Text(_obscure ? context.l10n.show : context.l10n.hide),
                ),
              ),
              const SizedBox(height: Spacing.sm),
              FilledButton(
                onPressed: _submitting ? null : _submit,
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(48),
                ),
                child:
                    _submitting
                        ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.5,
                            color: AppColors.white,
                          ),
                        )
                        : Text(context.l10n.savePassword),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
