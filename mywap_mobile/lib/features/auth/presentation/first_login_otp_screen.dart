import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../application/auth_controller.dart';
import 'widgets/auth_scaffold.dart';

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
          password: _passwordController.text.isEmpty
              ? null
              : _passwordController.text,
          passwordConfirmation: _passwordConfirmController.text.isEmpty
              ? null
              : _passwordConfirmController.text,
        );

    if (!mounted) return;
    if (!ok) {
      final auth = ref.read(authControllerProvider);
      setState(
        () => _error = auth is AuthUnauthenticated && auth.error != null
            ? auth.error!
            : 'Pengesahan OTP gagal. Sila cuba lagi.',
      );
    }
    // Kejayaan: go_router redirect akan bawa ke /dashboard secara automatik
    // (lihat app_router.dart) apabila AuthState bertukar ke AuthAuthenticated.
    setState(() => _submitting = false);
  }

  @override
  Widget build(BuildContext context) {
    final isVerifyStep = _step == _OtpStep.verify;
    final needsEmail = _step == _OtpStep.requestEmail;

    return AuthScaffold(
      title: 'Log Masuk Kali Pertama',
      subtitle: isVerifyStep
          ? 'Masukkan kod 6-digit yang dihantar ke emel anda dan tetapkan kata laluan baharu.'
          : needsEmail
          ? 'Kami tidak jumpa emel berdaftar. Sila masukkan emel anda untuk terima kod OTP.'
          : 'Masukkan No. Kad Pengenalan anda untuk menerima kod OTP melalui emel.',
      onBack: () => context.pop(),
      footer: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Text('Sudah pernah log masuk?'),
          TextButton(
            onPressed: () => context.go('/login'),
            child: const Text('Log Masuk Biasa'),
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
                label: 'No. Kad Pengenalan',
                controller: _icController,
                icon: Icons.badge_outlined,
                hint: 'Contoh: 900101011234',
                textInputAction: TextInputAction.next,
                validator: (v) => v == null || v.trim().isEmpty
                    ? 'Sila masukkan no. IC.'
                    : null,
              ),
              if (needsEmail)
                AuthField(
                  label: 'Emel',
                  controller: _emailController,
                  icon: Icons.mail_outline,
                  hint: 'nama@emel.com',
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.done,
                  validator: (v) {
                    if (v == null || v.trim().isEmpty) return 'Sila masukkan emel.';
                    if (!v.contains('@')) return 'Emel tidak sah.';
                    return null;
                  },
                ),
              if (isVerifyStep) ...[
                AuthField(
                  label: 'Kod OTP (6-digit)',
                  controller: _codeController,
                  icon: Icons.pin_outlined,
                  hint: '000000',
                  keyboardType: TextInputType.number,
                  textInputAction: TextInputAction.next,
                  validator: (v) => v == null || v.trim().length != 6
                      ? 'Kod OTP mesti 6 digit.'
                      : null,
                ),
                AuthField(
                  label: 'Kata Laluan Baharu',
                  controller: _passwordController,
                  icon: Icons.lock_outline,
                  hint: 'Minimum 8 aksara',
                  obscureText: _obscurePassword,
                  textInputAction: TextInputAction.next,
                  suffixIcon: IconButton(
                    onPressed: () => setState(
                      () => _obscurePassword = !_obscurePassword,
                    ),
                    icon: Icon(
                      _obscurePassword
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                    ),
                  ),
                  validator: (v) => v == null || v.length < 8
                      ? 'Kata laluan minimum 8 aksara.'
                      : null,
                ),
                AuthField(
                  label: 'Sahkan Kata Laluan',
                  controller: _passwordConfirmController,
                  icon: Icons.lock_outline,
                  hint: 'Masukkan semula kata laluan',
                  obscureText: _obscurePassword,
                  textInputAction: TextInputAction.done,
                  onFieldSubmitted: (_) => _verify(),
                  validator: (v) => v != _passwordController.text
                      ? 'Kata laluan tidak sepadan.'
                      : null,
                ),
              ],
              const SizedBox(height: Spacing.md),
              FilledButton(
                onPressed: _submitting
                    ? null
                    : (isVerifyStep ? _verify : _sendOtp),
                child: _submitting
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.5,
                          color: AppColors.white,
                        ),
                      )
                    : Text(isVerifyStep ? 'Sahkan & Log Masuk' : 'Hantar Kod OTP'),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
