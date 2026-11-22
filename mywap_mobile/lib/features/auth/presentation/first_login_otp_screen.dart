import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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

enum _OtpStep { requestIc, requestChannel, verify }

class _FirstLoginOtpScreenState extends ConsumerState<FirstLoginOtpScreen> {
  final _formKey = GlobalKey<FormState>();
  final _icController = TextEditingController();
  final _contactController = TextEditingController();
  final _codeController = TextEditingController();
  final _passwordController = TextEditingController();
  final _passwordConfirmController = TextEditingController();
  final _otpFocusNodes = List.generate(6, (_) => FocusNode());
  final _otpControllers = List.generate(6, (_) => TextEditingController());
  Timer? _resendTimer;
  int _resendSeconds = 0;

  _OtpStep _step = _OtpStep.requestIc;
  String _channel = 'email';
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
    _contactController.dispose();
    _codeController.dispose();
    _passwordController.dispose();
    _passwordConfirmController.dispose();
    for (final node in _otpFocusNodes) {
      node.dispose();
    }
    for (final controller in _otpControllers) {
      controller.dispose();
    }
    _resendTimer?.cancel();
    super.dispose();
  }

  Future<void> _pasteOtp() async {
    final data = await Clipboard.getData(Clipboard.kTextPlain);
    final code = _sixDigitCode(data?.text);
    if (!mounted) return;
    if (code == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.l10n.otpPasteMissing)));
      return;
    }
    for (var i = 0; i < 6; i++) {
      _otpControllers[i].text = code[i];
    }
    _otpFocusNodes[5].requestFocus();
    setState(() {});
  }

  Future<void> _sendOtp() async {
    if (!_formKey.currentState!.validate()) return;
    _icController.text = _icController.text.replaceAll(RegExp(r'[\s-]'), '');
    if (_step == _OtpStep.requestIc) {
      setState(() => _step = _OtpStep.requestChannel);
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final repo = ref.read(authRepositoryProvider);
      final result = await repo.updateAndSendOtp(
        _icController.text.trim(),
        _channel,
        _contactController.text.trim(),
      );
      if (!mounted) return;
      setState(() => _step = _OtpStep.verify);
      _startResendCooldown((result['cooldown_seconds'] as num?)?.toInt() ?? 30);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _startResendCooldown([int seconds = 30]) {
    _resendTimer?.cancel();
    setState(() => _resendSeconds = seconds);
    _resendTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      if (_resendSeconds <= 1) {
        timer.cancel();
        setState(() => _resendSeconds = 0);
      } else {
        setState(() => _resendSeconds--);
      }
    });
  }

  Future<void> _resendOtp() async {
    if (_resendSeconds > 0 || _submitting) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final result = await ref
          .read(authRepositoryProvider)
          .updateAndSendOtp(
            _icController.text.trim(),
            _channel,
            _contactController.text.trim(),
          );
      if (mounted) {
        _startResendCooldown(
          (result['cooldown_seconds'] as num?)?.toInt() ?? 30,
        );
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _verify() async {
    _codeController.text =
        _otpControllers.map((controller) => controller.text).join();
    if (_codeController.text.length != 6) {
      setState(() => _error = context.l10n.otpInvalid);
      return;
    }
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
    final needsContact = _step == _OtpStep.requestChannel;
    final english = Localizations.localeOf(context).languageCode == 'en';

    return AuthScaffold(
      title: context.l10n.firstLoginTitle,
      subtitle:
          isVerifyStep
              ? context.l10n.firstLoginVerifySubtitle
              : needsContact
              ? english
                  ? 'Choose where you want to receive the OTP code.'
                  : 'Pilih saluran untuk menerima kod OTP.'
              : context.l10n.firstLoginEnterIcSubtitle,
      icon: Icons.key_rounded,
      headingInCard: true,
      steps:
          english
              ? const ['1. Verify IC', '2. Delivery', '3. OTP & password']
              : const ['1. Sahkan KP', '2. Saluran', '3. OTP & kata laluan'],
      activeStep: switch (_step) {
        _OtpStep.requestIc => 0,
        _OtpStep.requestChannel => 1,
        _OtpStep.verify => 2,
      },
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
              if (_step == _OtpStep.requestIc)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    english
                        ? 'Enter your IC number without dashes. Contact an administrator if your registered record cannot be found.'
                        : 'Masukkan No. IC tanpa tanda sengkang. Hubungi pentadbir jika rekod berdaftar tidak dijumpai.',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      height: 1.35,
                    ),
                  ),
                ),
              if (needsContact) ...[
                const SizedBox(height: Spacing.sm),
                SegmentedButton<String>(
                  segments: [
                    ButtonSegment(
                      value: 'email',
                      label: Text(english ? 'Email' : 'Emel'),
                      icon: const Icon(Icons.mail_outline),
                    ),
                    const ButtonSegment(
                      value: 'whatsapp',
                      label: Text('WhatsApp'),
                      icon: Icon(Icons.chat_outlined),
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
                          ? english
                              ? 'WhatsApp number'
                              : 'Nombor WhatsApp'
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
                  validator: (v) {
                    if (v == null || v.trim().isEmpty) {
                      return english
                          ? 'This field is required.'
                          : 'Ruangan ini diperlukan.';
                    }
                    if (_channel == 'email' && !v.contains('@')) {
                      return context.l10n.emailInvalidShort;
                    }
                    if (_channel == 'whatsapp' &&
                        v.replaceAll(RegExp(r'\D'), '').length < 10) {
                      return english
                          ? 'Enter a valid WhatsApp number.'
                          : 'Nombor WhatsApp tidak sah.';
                    }
                    return null;
                  },
                ),
              ],
              if (isVerifyStep) ...[
                _OtpBoxes(
                  controllers: _otpControllers,
                  focusNodes: _otpFocusNodes,
                  onChanged: () => setState(() {}),
                ),
                Row(
                  children: [
                    TextButton.icon(
                      onPressed: _submitting ? null : _pasteOtp,
                      icon: const Icon(Icons.content_paste_outlined),
                      label: Text(context.l10n.pasteOtpCode),
                    ),
                    const Spacer(),
                    TextButton(
                      onPressed:
                          _resendSeconds == 0 && !_submitting
                              ? _resendOtp
                              : null,
                      child: Text(
                        _resendSeconds > 0
                            ? english
                                ? 'Request again in ${_resendSeconds}s'
                                : 'Minta kod lagi dalam ${_resendSeconds}s'
                            : english
                            ? 'Request another code'
                            : 'Minta kod lagi',
                      ),
                    ),
                  ],
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

String? _sixDigitCode(String? raw) {
  if (raw == null || raw.isEmpty) return null;
  for (final match in RegExp(r'\d+').allMatches(raw)) {
    final digits = match.group(0)!;
    if (digits.length == 6) return digits;
  }
  return null;
}

class _OtpBoxes extends StatelessWidget {
  const _OtpBoxes({
    required this.controllers,
    required this.focusNodes,
    required this.onChanged,
  });

  final List<TextEditingController> controllers;
  final List<FocusNode> focusNodes;
  final VoidCallback onChanged;

  void _handleChange(BuildContext context, int index, String value) {
    final digits = value.replaceAll(RegExp(r'\D'), '');

    if (digits.length > 1) {
      for (
        var offset = 0;
        offset < digits.length && index + offset < 6;
        offset++
      ) {
        controllers[index + offset].text = digits[offset];
      }
      final next = (index + digits.length).clamp(0, 5);
      focusNodes[next].requestFocus();
    } else if (digits.isNotEmpty) {
      controllers[index].text = digits;
      if (index < 5) focusNodes[index + 1].requestFocus();
    } else {
      controllers[index].clear();
    }

    onChanged();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          context.l10n.otpLabel,
          style: Theme.of(context).textTheme.labelLarge,
        ),
        const SizedBox(height: Spacing.sm),
        AutofillGroup(
          child: Row(
            children: List.generate(6, (index) {
              return Expanded(
                child: Padding(
                  padding: EdgeInsets.only(left: index == 0 ? 0 : 6),
                  child: TextField(
                    controller: controllers[index],
                    focusNode: focusNodes[index],
                    keyboardType: TextInputType.number,
                    textAlign: TextAlign.center,
                    autofillHints:
                        index == 0 ? const [AutofillHints.oneTimeCode] : null,
                    enableSuggestions: false,
                    autocorrect: false,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    style: const TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textPrimary,
                    ),
                    decoration: const InputDecoration(
                      counterText: '',
                      contentPadding: EdgeInsets.symmetric(vertical: 14),
                    ),
                    onChanged: (value) => _handleChange(context, index, value),
                  ),
                ),
              );
            }),
          ),
        ),
      ],
    );
  }
}
