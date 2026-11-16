import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../application/auth_controller.dart';
import 'widgets/auth_canvas_background.dart';
import 'widgets/auth_scaffold.dart';
import 'widgets/biometric_prompt.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _identifierController = TextEditingController();
  final _passwordController = TextEditingController();

  bool _obscurePassword = true;
  bool _bioSubmitting = false;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _identifierController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_busy || !(_formKey.currentState?.validate() ?? false)) return;
    setState(() {
      _error = null;
      _busy = true;
    });
    final identifier = _identifierController.text.trim();

    try {
      final info = await ref
          .read(authRepositoryProvider)
          .checkMember(identifier);
      if (!mounted) return;
      if (info['is_first_login'] == true) {
        setState(() => _busy = false);
        await context.push(
          '/first-login?ic=${Uri.encodeQueryComponent(identifier)}',
        );
        return;
      }
    } catch (_) {
      // Login still works when the preliminary member check is unavailable.
    }

    if (!mounted) return;
    final ok = await ref
        .read(authControllerProvider.notifier)
        .login(
          email: identifier,
          icNumber: identifier,
          password: _passwordController.text,
        );
    if (!mounted) return;
    if (!ok) {
      final auth = ref.read(authControllerProvider);
      setState(() {
        _busy = false;
        _error =
            auth is AuthUnauthenticated && auth.error != null
                ? auth.error!
                : context.l10n.loginFailed;
      });
      return;
    }
    setState(() => _busy = false);
    await BiometricPrompt.offerEnable(context, ref);
  }

  Future<void> _loginWithBiometrics() async {
    setState(() {
      _bioSubmitting = true;
      _error = null;
    });
    final ok =
        await ref.read(authControllerProvider.notifier).loginWithBiometrics();
    if (!ok && mounted) {
      setState(() => _error = context.l10n.biometricAuthFailed);
    }
    if (mounted) setState(() => _bioSubmitting = false);
  }

  Future<void> _showRecoveryOptions() async {
    final english = Localizations.localeOf(context).languageCode == 'en';
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      backgroundColor: AppColors.white,
      shape: const RoundedRectangleBorder(borderRadius: AppRadius.sheet),
      builder:
          (sheetContext) => SafeArea(
            top: false,
            child: Padding(
              padding: EdgeInsets.fromLTRB(
                Spacing.xl,
                Spacing.sm,
                Spacing.xl,
                Spacing.xl + MediaQuery.viewInsetsOf(sheetContext).bottom,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: const BoxDecoration(
                          color: AppColors.greenTint,
                          borderRadius: AppRadius.md,
                        ),
                        child: const Icon(
                          Icons.shield_outlined,
                          color: AppColors.movementDarkGreen,
                        ),
                      ),
                      const SizedBox(width: Spacing.md),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              english ? 'Account recovery' : 'Pemulihan akaun',
                              style: Theme.of(sheetContext)
                                  .textTheme
                                  .titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w800),
                            ),
                            Text(
                              english
                                  ? 'Choose what you need to recover.'
                                  : 'Pilih maklumat yang anda mahu pulihkan.',
                              style: Theme.of(sheetContext).textTheme.bodySmall
                                  ?.copyWith(color: AppColors.textSecondary),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: Spacing.lg),
                  _RecoveryOption(
                    icon: Icons.lock_reset_outlined,
                    title: context.l10n.forgotPasswordTitle,
                    subtitle: context.l10n.forgotPasswordSubtitle,
                    onPressed: () {
                      Navigator.pop(sheetContext);
                      context.push('/forgot-password');
                    },
                  ),
                  const SizedBox(height: Spacing.sm),
                  _RecoveryOption(
                    icon: Icons.badge_outlined,
                    title: context.l10n.forgotMemberNoTitle,
                    subtitle: context.l10n.forgotMemberNoEnterIcSubtitle,
                    onPressed: () {
                      Navigator.pop(sheetContext);
                      context.push('/forgot-id');
                    },
                  ),
                ],
              ),
            ),
          ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final english = Localizations.localeOf(context).languageCode == 'en';
    final isSubmitting =
        _busy || ref.watch(authControllerProvider) is AuthLoading;
    final identity = _identityType(english);

    return Scaffold(
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
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            english ? 'Welcome back,' : 'Selamat kembali,',
                            style: Theme.of(
                              context,
                            ).textTheme.headlineSmall?.copyWith(
                              color: AppColors.textPrimary,
                              fontWeight: FontWeight.w800,
                              height: 1.1,
                            ),
                          ),
                          Text(
                            english ? 'myWAP Member' : 'Ahli myWAP',
                            style: Theme.of(
                              context,
                            ).textTheme.headlineSmall?.copyWith(
                              color: AppColors.movementDarkGreen,
                              fontWeight: FontWeight.w800,
                              height: 1.1,
                            ),
                          ),
                          const SizedBox(height: Spacing.sm),
                          Text(
                            english
                                ? 'Log in to access your membership account.'
                                : 'Log masuk untuk mengakses akaun keahlian anda.',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(color: AppColors.textSecondary),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: Spacing.lg),
                    _FirstLoginBanner(
                      english: english,
                      onPressed: () => context.push('/first-login'),
                    ),
                    const SizedBox(height: Spacing.md),
                    AuthCard(
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            if (_error != null) ...[
                              AuthErrorBanner(message: _error!),
                            ],
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    context.l10n.loginIdentifierLabel,
                                    style: Theme.of(context)
                                        .textTheme
                                        .labelLarge
                                        ?.copyWith(fontWeight: FontWeight.w700),
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 7,
                                    vertical: 4,
                                  ),
                                  decoration: BoxDecoration(
                                    color: identity.color.withValues(alpha: .1),
                                    borderRadius: AppRadius.xs,
                                  ),
                                  child: Text(
                                    identity.label,
                                    style: TextStyle(
                                      color: identity.color,
                                      fontSize: 9,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            TextFormField(
                              controller: _identifierController,
                              textInputAction: TextInputAction.next,
                              autofillHints: const [AutofillHints.username],
                              onChanged: (_) => setState(() {}),
                              style: const TextStyle(
                                color: AppColors.textPrimary,
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                              ),
                              decoration: _fieldDecoration(
                                hint: context.l10n.loginIdentifierHint,
                                icon: Icons.person_outline,
                                suffix:
                                    _identifierController.text.isEmpty
                                        ? null
                                        : IconButton(
                                          tooltip:
                                              english
                                                  ? 'Clear input'
                                                  : 'Padam input',
                                          onPressed:
                                              () => setState(
                                                _identifierController.clear,
                                              ),
                                          icon: const Icon(
                                            Icons.close_rounded,
                                            size: 18,
                                          ),
                                        ),
                              ),
                              validator:
                                  (value) =>
                                      value == null || value.trim().isEmpty
                                          ? context.l10n.loginIdentifierRequired
                                          : null,
                            ),
                            const SizedBox(height: Spacing.lg),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  context.l10n.passwordLabel,
                                  style: Theme.of(context).textTheme.labelLarge
                                      ?.copyWith(fontWeight: FontWeight.w700),
                                ),
                                TextButton(
                                  onPressed:
                                      () => context.push('/forgot-password'),
                                  style: TextButton.styleFrom(
                                    minimumSize: const Size(44, 44),
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 4,
                                    ),
                                    textStyle: const TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                  child: Text(context.l10n.forgotPassword),
                                ),
                              ],
                            ),
                            const SizedBox(height: 2),
                            TextFormField(
                              controller: _passwordController,
                              obscureText: _obscurePassword,
                              textInputAction: TextInputAction.done,
                              autofillHints: const [AutofillHints.password],
                              onFieldSubmitted: (_) => _submit(),
                              style: const TextStyle(
                                color: AppColors.textPrimary,
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                              ),
                              decoration: _fieldDecoration(
                                hint: context.l10n.passwordHint,
                                icon: Icons.lock_outline,
                                suffix: IconButton(
                                  tooltip:
                                      _obscurePassword
                                          ? context.l10n.show
                                          : context.l10n.hide,
                                  onPressed:
                                      () => setState(
                                        () =>
                                            _obscurePassword =
                                                !_obscurePassword,
                                      ),
                                  icon: Icon(
                                    _obscurePassword
                                        ? Icons.visibility_outlined
                                        : Icons.visibility_off_outlined,
                                    size: 20,
                                  ),
                                ),
                              ),
                              validator:
                                  (value) =>
                                      value == null || value.isEmpty
                                          ? context.l10n.passwordRequired
                                          : null,
                            ),
                            const SizedBox(height: Spacing.lg),
                            Consumer(
                              builder: (context, ref, _) {
                                final bioAvailable = ref.watch(
                                  biometricAvailableProvider,
                                );
                                final hasFaceId =
                                    ref.watch(hasFaceIdProvider).valueOrNull ??
                                    false;
                                final available =
                                    bioAvailable.valueOrNull ?? false;
                                return Row(
                                  children: [
                                    Expanded(
                                      child: SizedBox(
                                        height: 52,
                                        child: FilledButton(
                                          onPressed:
                                              isSubmitting ? null : _submit,
                                          style: FilledButton.styleFrom(
                                            backgroundColor:
                                                AppColors.movementDarkGreen,
                                            shape: const RoundedRectangleBorder(
                                              borderRadius: AppRadius.md,
                                            ),
                                          ),
                                          child:
                                              isSubmitting
                                                  ? const SizedBox(
                                                    width: 21,
                                                    height: 21,
                                                    child:
                                                        CircularProgressIndicator(
                                                          strokeWidth: 2.5,
                                                          color:
                                                              AppColors.white,
                                                        ),
                                                  )
                                                  : Row(
                                                    mainAxisAlignment:
                                                        MainAxisAlignment
                                                            .spaceBetween,
                                                    children: [
                                                      Text(context.l10n.login),
                                                      const Icon(
                                                        Icons
                                                            .arrow_forward_rounded,
                                                        size: 19,
                                                      ),
                                                    ],
                                                  ),
                                        ),
                                      ),
                                    ),
                                    if (available) ...[
                                      const SizedBox(width: Spacing.sm),
                                      SizedBox(
                                        width: 52,
                                        height: 52,
                                        child: OutlinedButton(
                                          onPressed:
                                              _bioSubmitting
                                                  ? null
                                                  : _loginWithBiometrics,
                                          style: OutlinedButton.styleFrom(
                                            padding: EdgeInsets.zero,
                                            backgroundColor:
                                                AppColors.greenTint,
                                            side: const BorderSide(
                                              color: AppColors.inputBorder,
                                            ),
                                            shape: const RoundedRectangleBorder(
                                              borderRadius: AppRadius.md,
                                            ),
                                          ),
                                          child:
                                              _bioSubmitting
                                                  ? const SizedBox(
                                                    width: 19,
                                                    height: 19,
                                                    child:
                                                        CircularProgressIndicator(
                                                          strokeWidth: 2,
                                                        ),
                                                  )
                                                  : Icon(
                                                    hasFaceId
                                                        ? Icons
                                                            .face_retouching_natural
                                                        : Icons.fingerprint,
                                                    semanticLabel:
                                                        hasFaceId
                                                            ? context
                                                                .l10n
                                                                .biometricFaceIdLogin
                                                            : context
                                                                .l10n
                                                                .biometricFingerprintLogin,
                                                  ),
                                        ),
                                      ),
                                    ],
                                  ],
                                );
                              },
                            ),
                            const SizedBox(height: Spacing.lg),
                            const Divider(height: 1),
                            const SizedBox(height: Spacing.md),
                            _RecoveryHub(
                              english: english,
                              onPressed: _showRecoveryOptions,
                            ),
                            const SizedBox(height: Spacing.md),
                            SizedBox(
                              height: 48,
                              child: OutlinedButton.icon(
                                onPressed: () => context.go('/home'),
                                icon: const Icon(
                                  Icons.public_rounded,
                                  size: 19,
                                ),
                                label: Text(context.l10n.browseAsGuest),
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: AppColors.textSecondary,
                                  side: const BorderSide(
                                    color: AppColors.inputBorder,
                                  ),
                                  shape: const RoundedRectangleBorder(
                                    borderRadius: AppRadius.md,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: Spacing.md),
                    _RegistrationTile(
                      english: english,
                      onPressed: () => context.push('/register'),
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

  _IdentityType _identityType(bool english) {
    final value = _identifierController.text.trim();
    if (value.isEmpty) {
      return _IdentityType(
        english ? 'Auto-detect' : 'Kesan auto',
        AppColors.textSecondary,
      );
    }
    if (value.contains('@')) {
      return _IdentityType(
        english ? 'Email address' : 'Alamat e-mel',
        AppColors.movementGreen,
      );
    }
    if (RegExp(r'^\d[\d\s-]*$').hasMatch(value)) {
      return _IdentityType(
        english ? 'IC number' : 'No. Kad Pengenalan',
        AppColors.movementGreen,
      );
    }
    return _IdentityType(english ? 'Member ID' : 'ID Ahli', AppColors.accent);
  }
}

class _IdentityType {
  const _IdentityType(this.label, this.color);

  final String label;
  final Color color;
}

class _FirstLoginBanner extends StatelessWidget {
  const _FirstLoginBanner({required this.english, required this.onPressed});

  final bool english;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    child: InkWell(
      onTap: onPressed,
      borderRadius: AppRadius.lg,
      child: Ink(
        padding: const EdgeInsets.all(Spacing.md),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [
              AppColors.accentTint,
              AppColors.white,
              AppColors.greenTint,
            ],
          ),
          borderRadius: AppRadius.lg,
          border: Border.all(
            color: AppColors.accent.withValues(alpha: .45),
            width: 1.5,
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: const BoxDecoration(
                color: AppColors.accent,
                borderRadius: AppRadius.md,
              ),
              child: const Icon(
                Icons.key_rounded,
                color: AppColors.white,
                size: 22,
              ),
            ),
            const SizedBox(width: Spacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    english ? 'NEW APP USER?' : 'PENGGUNA BAHARU?',
                    style: const TextStyle(
                      color: AppColors.accent,
                      fontSize: 9,
                      fontWeight: FontWeight.w800,
                      letterSpacing: .5,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    english
                        ? 'First time logging in?'
                        : 'Log masuk kali pertama?',
                    style: const TextStyle(
                      color: AppColors.textPrimary,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    english
                        ? 'Activate your existing member account'
                        : 'Aktifkan akaun ahli sedia ada anda',
                    maxLines: 2,
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 10,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: Spacing.sm),
            Container(
              constraints: const BoxConstraints(minHeight: 40),
              padding: const EdgeInsets.symmetric(horizontal: Spacing.sm),
              decoration: const BoxDecoration(
                color: AppColors.movementDarkGreen,
                borderRadius: AppRadius.sm,
              ),
              alignment: Alignment.center,
              child: Text(
                english ? 'Activate' : 'Aktifkan',
                style: const TextStyle(
                  color: AppColors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _RecoveryHub extends StatelessWidget {
  const _RecoveryHub({required this.english, required this.onPressed});

  final bool english;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(Spacing.sm),
    decoration: BoxDecoration(
      color: AppColors.greenTint,
      borderRadius: AppRadius.md,
      border: Border.all(color: AppColors.paleGreen),
    ),
    child: Row(
      children: [
        const SizedBox(
          width: 32,
          height: 32,
          child: Icon(
            Icons.help_outline_rounded,
            color: AppColors.movementDarkGreen,
            size: 20,
          ),
        ),
        const SizedBox(width: 6),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                english
                    ? 'Forgot password or Member No.?'
                    : 'Lupa kata laluan atau No. Ahli?',
                style: const TextStyle(
                  color: AppColors.textPrimary,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                english
                    ? 'Recover your account using your IC number'
                    : 'Pulihkan akaun menggunakan No. KP',
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  fontSize: 9,
                ),
              ),
            ],
          ),
        ),
        TextButton(
          onPressed: onPressed,
          style: TextButton.styleFrom(
            minimumSize: const Size(52, 44),
            padding: const EdgeInsets.symmetric(horizontal: 6),
            backgroundColor: AppColors.white,
            shape: const RoundedRectangleBorder(borderRadius: AppRadius.sm),
            textStyle: const TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
          child: Text(english ? 'Recover' : 'Pulihkan'),
        ),
      ],
    ),
  );
}

class _RegistrationTile extends StatelessWidget {
  const _RegistrationTile({required this.english, required this.onPressed});

  final bool english;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(Spacing.md),
    decoration: BoxDecoration(
      color: AppColors.white.withValues(alpha: .86),
      borderRadius: AppRadius.lg,
      border: Border.all(color: AppColors.white),
      boxShadow: AppShadows.subtle,
    ),
    child: Row(
      children: [
        Container(
          width: 42,
          height: 42,
          decoration: const BoxDecoration(
            color: AppColors.movementGreen,
            borderRadius: AppRadius.md,
          ),
          child: const Icon(
            Icons.person_add_alt_1_rounded,
            color: AppColors.white,
            size: 21,
          ),
        ),
        const SizedBox(width: Spacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                english ? 'Not a member yet?' : 'Belum menjadi ahli?',
                style: const TextStyle(
                  color: AppColors.textPrimary,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                english
                    ? 'Register with WADAH, ABIM or PKPIM'
                    : 'Daftar bersama WADAH, ABIM atau PKPIM',
                maxLines: 2,
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  fontSize: 10,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: Spacing.sm),
        FilledButton(
          onPressed: onPressed,
          style: FilledButton.styleFrom(
            minimumSize: const Size(74, 44),
            padding: const EdgeInsets.symmetric(horizontal: Spacing.sm),
            backgroundColor: AppColors.movementDarkGreen,
            shape: const RoundedRectangleBorder(borderRadius: AppRadius.md),
            textStyle: const TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
          child: Text(english ? 'Register' : 'Daftar'),
        ),
      ],
    ),
  );
}

class _RecoveryOption extends StatelessWidget {
  const _RecoveryOption({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onPressed,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => OutlinedButton(
    onPressed: onPressed,
    style: OutlinedButton.styleFrom(
      padding: const EdgeInsets.all(Spacing.md),
      alignment: Alignment.centerLeft,
      side: const BorderSide(color: AppColors.inputBorder),
      shape: const RoundedRectangleBorder(borderRadius: AppRadius.md),
    ),
    child: Row(
      children: [
        Icon(icon, color: AppColors.movementDarkGreen),
        const SizedBox(width: Spacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  fontSize: 11,
                  fontWeight: FontWeight.w400,
                ),
              ),
            ],
          ),
        ),
        const Icon(Icons.chevron_right_rounded, size: 20),
      ],
    ),
  );
}

InputDecoration _fieldDecoration({
  required String hint,
  required IconData icon,
  Widget? suffix,
}) => InputDecoration(
  hintText: hint,
  hintStyle: const TextStyle(
    color: AppColors.textTertiary,
    fontSize: 13,
    fontWeight: FontWeight.w400,
  ),
  prefixIcon: Icon(icon, color: AppColors.textTertiary, size: 21),
  suffixIcon: suffix,
  filled: true,
  fillColor: AppColors.surfaceMuted,
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
);
