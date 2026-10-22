import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../application/auth_controller.dart';
import 'widgets/auth_scaffold.dart';
import 'widgets/biometric_prompt.dart';

/// Log Masuk Kali Pertama (OTP) — sepadan dengan web first-login flow:
/// 1) IC (+ emel jika tiada/salah) → hantar OTP ke emel berdaftar.
/// 2) Sahkan OTP 6-digit + tetapkan kata laluan baharu → log masuk terus
///    (backend keluarkan token Sanctum, bukan sesi web).
class FirstLoginOtpScreen extends ConsumerStatefulWidget {
  const FirstLoginOtpScreen({super.key, this.icNumber});

  final String? icNumber;

  @override
  ConsumerState<FirstLoginOtpScreen> createState() =>
      _FirstLoginOtpScreenState();
}

enum _OtpStep { requestIc, requestEmail, verify }

class _FirstLoginOtpScreenState extends ConsumerState<FirstLoginOtpScreen> {
  final _formKey = GlobalKey<FormState>();
  final _icController = TextEditingController();
  final _emailController = TextEditingController();
  final _codeController = TextEditingController();
  final _passwordController = TextEditingController();
  final _passwordConfirmController = TextEditingController();

  _OtpStep _step = _OtpStep.requestIc;
  bool _submitting = false;
  bool _obscurePassword = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    if (widget.icNumber != null) _icController.text = widget.icNumber!;
  }

  @override
  void dispose() {
    _icController.dispose();
    _emailController.dispose();
    _codeController.dispose();
    _passwordController.dispose();
    _passwordConfirmController.dispose();
    super.dispose();
  }

  Future<void> _sendOtp() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final repo = ref.read(authRepositoryProvider);
      if (_step == _OtpStep.requestEmail) {
        await repo.updateAndSendOtp(
          _icController.text.trim(),
          _emailController.text.trim(),
        );
      } else {
        await repo.sendOtp(_icController.text.trim());
      }
      if (!mounted) return;
      setState(() => _step = _OtpStep.verify);
    } on ApiException catch (e) {
      // Tiada emel berdaftar → minta pengguna isikan emel dahulu.
      if (e.message.toLowerCase().contains('tiada emel') &&
          _step == _OtpStep.requestIc) {
        setState(() => _step = _OtpStep.requestEmail);
      } else {
        setState(() => _error = e.message);
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _verify() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _submitting = true;
      _error = null;
    });

    final ok = await ref
        .read(authControllerProvider.notifier)
        .verifyOtp(
          icNumber: _icController.text.trim(),
          code: _codeController.text.trim(),
          password:
              _passwordController.text.isEmpty
                  ? null
                  : _passwordController.text,
          passwordConfirmation:
              _passwordConfirmController.text.isEmpty
                  ? null
                  : _passwordConfirmController.text,
        );

    if (!mounted) return;
    if (!ok) {
      final auth = ref.read(authControllerProvider);
      setState(
        () =>
            _error =
                auth is AuthUnauthenticated && auth.error != null
                    ? auth.error!
                    : context.l10n.otpFailed,
      );
      setState(() => _submitting = false);
      return;
    }
    // Kejayaan: go_router redirect akan bawa ke /dashboard secara automatik
    // (lihat app_router.dart) apabila AuthState bertukar ke AuthAuthenticated.
    setState(() => _submitting = false);
    await BiometricPrompt.offerEnable(context, ref);
  }

  @override
  Widget build(BuildContext context) {
    final isVerifyStep = _step == _OtpStep.verify;
    final needsEmail = _step == _OtpStep.requestEmail;

    return AuthScaffold(
      title: context.l10n.firstLoginTitle,
      subtitle:
          isVerifyStep
              ? context.l10n.firstLoginVerifySubtitle
              : needsEmail
              ? context.l10n.firstLoginNoEmailSubtitle
              : context.l10n.firstLoginEnterIcSubtitle,
      onBack: () => context.pop(),
      footer: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(context.l10n.alreadyLoggedIn),
          TextButton(
            onPressed: () => context.go('/login'),
            child: Text(context.l10n.normalLogin),
          ),
        ],
      ),
      children: [
        if (_error != null) AuthErrorBanner(message: _error!),
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
                textInputAction: TextInputAction.next,
                validator:
                    (v) =>
                        v == null || v.trim().isEmpty
                            ? context.l10n.icRequiredShort
                            : null,
              ),
              if (needsEmail)
                AuthField(
                  label: context.l10n.emailLabel,
                  controller: _emailController,
                  icon: Icons.mail_outline,
                  hint: context.l10n.emailHint,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.done,
                  validator: (v) {
                    if (v == null || v.trim().isEmpty) {
                      return context.l10n.emailRequiredShort;
                    }
                    if (!v.contains('@')) return context.l10n.emailInvalidShort;
                    return null;
                  },
                ),
              if (isVerifyStep) ...[
                AuthField(
                  label: context.l10n.otpLabel,
                  controller: _codeController,
                  icon: Icons.pin_outlined,
                  hint: '000000',
                  keyboardType: TextInputType.number,
                  textInputAction: TextInputAction.next,
                  validator:
                      (v) =>
                          v == null || v.trim().length != 6
                              ? context.l10n.otpInvalid
                              : null,
                ),
                AuthField(
                  label: context.l10n.newPasswordLabel,
                  controller: _passwordController,
                  icon: Icons.lock_outline,
                  hint: context.l10n.newPasswordHint,
                  obscureText: _obscurePassword,
                  textInputAction: TextInputAction.next,
                  suffixIcon: IconButton(
                    onPressed:
                        () => setState(
                          () => _obscurePassword = !_obscurePassword,
                        ),
                    icon: Icon(
                      _obscurePassword
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                    ),
                  ),
                  validator:
                      (v) =>
                          v == null || v.length < 8
                              ? context.l10n.newPasswordMin
                              : null,
                ),
                AuthField(
                  label: context.l10n.confirmPasswordLabel,
                  controller: _passwordConfirmController,
                  icon: Icons.lock_outline,
                  hint: context.l10n.confirmPasswordHint,
                  obscureText: _obscurePassword,
                  textInputAction: TextInputAction.done,
                  onFieldSubmitted: (_) => _verify(),
                  validator:
                      (v) =>
                          v != _passwordController.text
                              ? context.l10n.passwordMismatch
                              : null,
                ),
              ],
              const SizedBox(height: Spacing.md),
              FilledButton(
                onPressed:
                    _submitting ? null : (isVerifyStep ? _verify : _sendOtp),
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
                        : Text(
                          isVerifyStep
                              ? context.l10n.verifyAndLogin
                              : context.l10n.sendOtp,
                        ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
