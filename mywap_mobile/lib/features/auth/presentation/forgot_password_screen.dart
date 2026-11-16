import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../application/auth_controller.dart';
import 'widgets/auth_scaffold.dart';

/// Lupa kata laluan — sepadan dengan web `/forgot-password` (IC-based).
/// Pautan reset dihantar ke emel berdaftar; token/emel diguna semula di
/// [ResetPasswordScreen] apabila pengguna klik pautan tersebut.
class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() =>
      _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _icController = TextEditingController();
  final _contactController = TextEditingController();
  String _channel = 'whatsapp';
  bool _submitting = false;
  String? _error;
  String? _successMessage;

  @override
  void dispose() {
    _icController.dispose();
    _contactController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final result = await ref
          .read(authRepositoryProvider)
          .forgotPassword(
            _icController.text.replaceAll(RegExp(r'[\s-]'), '').trim(),
            channel: _channel,
            contact: _contactController.text.trim(),
          );
      if (!mounted) return;
      setState(
        () =>
            _successMessage =
                (result['message'] as String?) ?? context.l10n.resetLinkSent,
      );
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AuthScaffold(
      title: context.l10n.forgotPasswordTitle,
      subtitle: context.l10n.forgotPasswordSubtitle,
      onBack: () => context.pop(),
      footer: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(context.l10n.rememberPassword),
          TextButton(
            onPressed: () => context.go('/login'),
            child: Text(context.l10n.login),
          ),
        ],
      ),
      children: [
        if (_error != null) AuthErrorBanner(message: _error!),
        if (_successMessage != null)
          AuthSuccessBanner(message: _successMessage!)
        else
          Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AuthField(
                  label: context.l10n.icLabel,
                  controller: _icController,
                  icon: Icons.badge_outlined,
                  hint: context.l10n.icHint,
                  textInputAction: TextInputAction.done,
                  onFieldSubmitted: (_) => _submit(),
                  validator:
                      (v) =>
                          v == null || v.trim().isEmpty
                              ? context.l10n.icRequiredShort
                              : null,
                ),
                Text(
                  'Masukkan No. IC tanpa tanda dash (-). Jika rekod ahli tidak dijumpai, sila hubungi admin.',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: Spacing.sm),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(
                      value: 'whatsapp',
                      label: Text('WhatsApp'),
                      icon: Icon(Icons.chat_outlined),
                    ),
                    ButtonSegment(
                      value: 'email',
                      label: Text('Emel'),
                      icon: Icon(Icons.mail_outline),
                    ),
                  ],
                  selected: {_channel},
                  onSelectionChanged: (selection) {
                    setState(() {
                      _channel = selection.first;
                      _contactController.clear();
                    });
                  },
                ),
                const SizedBox(height: Spacing.md),
                AuthField(
                  label:
                      _channel == 'whatsapp'
                          ? 'Nombor WhatsApp'
                          : context.l10n.emailLabel,
                  controller: _contactController,
                  icon:
                      _channel == 'whatsapp'
                          ? Icons.chat_outlined
                          : Icons.mail_outline,
                  hint:
                      _channel == 'whatsapp'
                          ? '+601157452119'
                          : context.l10n.emailHint,
                  keyboardType:
                      _channel == 'whatsapp'
                          ? TextInputType.phone
                          : TextInputType.emailAddress,
                  textInputAction: TextInputAction.done,
                  onFieldSubmitted: (_) => _submit(),
                  validator: (v) {
                    if (v == null || v.trim().isEmpty) {
                      return 'Ruangan ini diperlukan.';
                    }
                    if (_channel == 'email' && !v.contains('@')) {
                      return context.l10n.emailInvalidShort;
                    }
                    if (_channel == 'whatsapp' &&
                        v.replaceAll(RegExp(r'\D'), '').length < 10) {
                      return 'Nombor WhatsApp tidak sah.';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: Spacing.md),
                FilledButton(
                  onPressed: _submitting ? null : _submit,
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
                          : Text(context.l10n.sendResetLink),
                ),
              ],
            ),
          ),
        if (_successMessage != null) ...[
          const SizedBox(height: Spacing.md),
          OutlinedButton(
            onPressed: () => context.go('/login'),
            child: Text(context.l10n.backToLogin),
          ),
        ],
      ],
    );
  }
}
