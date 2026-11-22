import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/presentation/widgets/auth_scaffold.dart';
import '../application/profile_providers.dart';

/// Tukar kata laluan akaun (`POST /profile/password`).
class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  ConsumerState<ChangePasswordScreen> createState() =>
      _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _currentController = TextEditingController();
  final _newController = TextEditingController();
  final _confirmController = TextEditingController();

  bool _obscure = true;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _currentController.dispose();
    _newController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;

    final mustChangePassword =
        ref.read(currentUserProvider)?.must_change_password ?? false;

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref
          .read(profileRepositoryProvider)
          .changePassword(
            currentPassword:
                mustChangePassword ? null : _currentController.text.trim(),
            newPassword: _newController.text,
          );
      await ref.read(authControllerProvider.notifier).refreshCurrentUser();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.l10n.passwordUpdated)));
      Navigator.of(context).maybePop();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = context.l10n.unexpectedErrorRetry;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final mustChangePassword =
        ref.watch(currentUserProvider)?.must_change_password ?? false;

    if (mustChangePassword) {
      return AuthScaffold(
        title: context.l10n.changePassword,
        subtitle: context.l10n.changePasswordTemporarySubtitle,
        icon: Icons.lock_reset_rounded,
        footer: TextButton(
          onPressed:
              _saving
                  ? null
                  : () => ref.read(authControllerProvider.notifier).logout(),
          child: Text(context.l10n.logout),
        ),
        children: [_buildFormContent(mustChangePassword: true)],
      );
    }

    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: !mustChangePassword,
        leading: mustChangePassword ? null : const AppBackButton(),
        title: Text(context.l10n.changePassword),
      ),
      body: ListView(
        padding: const EdgeInsets.all(Spacing.lg),
        children: [
          Text(
            mustChangePassword
                ? context.l10n.changePasswordTemporarySubtitle
                : context.l10n.changePasswordSubtitle2,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: Spacing.xl),
          _buildFormContent(mustChangePassword: mustChangePassword),
        ],
      ),
    );
  }

  Widget _buildFormContent({required bool mustChangePassword}) => Column(
    children: [
      Form(
        key: _formKey,
        child: Column(
          children: [
            if (!mustChangePassword) ...[
              TextFormField(
                controller: _currentController,
                obscureText: _obscure,
                decoration: InputDecoration(
                  labelText: context.l10n.currentPasswordLabel,
                  prefixIcon: const Icon(Icons.lock_outline),
                ),
                validator:
                    (value) =>
                        (value == null || value.trim().isEmpty)
                            ? context.l10n.currentPasswordRequired
                            : null,
              ),
              const SizedBox(height: Spacing.md),
            ],
            TextFormField(
              controller: _newController,
              obscureText: _obscure,
              decoration: InputDecoration(
                labelText: context.l10n.newPasswordRequiredLabel,
                prefixIcon: const Icon(Icons.password_outlined),
              ),
              validator: (value) {
                if (value == null || value.isEmpty) {
                  return context.l10n.newPasswordRequired;
                }
                if (value.length < 8) return context.l10n.min8;
                return null;
              },
            ),
            const SizedBox(height: Spacing.md),
            TextFormField(
              controller: _confirmController,
              obscureText: _obscure,
              decoration: InputDecoration(
                labelText: context.l10n.confirmNewPasswordLabel,
                prefixIcon: const Icon(Icons.verified_user_outlined),
              ),
              validator:
                  (value) =>
                      value != _newController.text
                          ? context.l10n.passwordMismatch
                          : null,
            ),
            const SizedBox(height: Spacing.sm),
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
          ],
        ),
      ),
      if (_error != null) ...[
        const SizedBox(height: Spacing.sm),
        Text(
          _error!,
          textAlign: TextAlign.center,
          style: const TextStyle(color: AppColors.error),
        ),
      ],
      const SizedBox(height: Spacing.xl),
      FilledButton.icon(
        onPressed: _saving ? null : _submit,
        style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
        icon:
            _saving
                ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.white,
                  ),
                )
                : const Icon(Icons.check),
        label: Text(_saving ? context.l10n.saving : context.l10n.savePassword),
      ),
    ],
  );
}
