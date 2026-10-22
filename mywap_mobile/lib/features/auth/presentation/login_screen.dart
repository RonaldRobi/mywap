import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../onboarding/application/onboarding_providers.dart';
import '../../onboarding/data/onboarding_repository.dart';
import '../application/auth_controller.dart';
import 'widgets/auth_canvas_background.dart';
import 'widgets/biometric_prompt.dart';
import '../../../shared/theme/app_text_theme.dart';

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
  String? _error;
  bool _bioSubmitting = false;

  @override
  void dispose() {
    _identifierController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _error = null);
    final ok = await ref
        .read(authControllerProvider.notifier)
        .login(
          email: _identifierController.text,
          icNumber: _identifierController.text,
          password: _passwordController.text,
        );
    if (!ok && mounted) {
      final auth = ref.read(authControllerProvider);
      setState(
        () =>
            _error =
                auth is AuthUnauthenticated && auth.error != null
                    ? auth.error!
                    : context.l10n.loginFailed,
      );
      return;
    }
    if (mounted) await BiometricPrompt.offerEnable(context, ref);
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

  @override
  Widget build(BuildContext context) {
    final fallback = MobileLoginBranding(
      title: context.l10n.loginWelcomeTitle,
      subtitle: context.l10n.loginWelcomeSubtitle,
      backgroundStart: '#F3F6EF',
      backgroundEnd: '#EDF4EC',
      accent: '#147A3D',
    );
    final branding =
        ref.watch(mobileAuthConfigurationProvider).valueOrNull?.login ??
        fallback;
    final accent = _parseColor(branding.accent);
    final isSubmitting = ref.watch(authControllerProvider) is AuthLoading;

    return Scaffold(
      body: AuthCanvasBackground(
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(Spacing.xl),
            child: ConstrainedBox(
              constraints: BoxConstraints(
                minHeight:
                    MediaQuery.sizeOf(context).height -
                    MediaQuery.paddingOf(context).vertical -
                    Spacing.xxl,
              ),
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 420),
                  child: Container(
                    padding: const EdgeInsets.all(Spacing.xl),
                    decoration: BoxDecoration(
                      color: AppColors.white.withValues(alpha: .88),
                      borderRadius: AppRadius.hero,
                      border: Border.all(color: AppColors.paleGreen),
                      boxShadow: const [
                        BoxShadow(
                          color: AppColors.glassTint,
                          blurRadius: 32,
                          offset: Offset(0, 12),
                        ),
                      ],
                    ),
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _BrandMark(branding: branding, accent: accent),
                          const SizedBox(height: 32),
                          Text(
                            branding.title,
                            style: Theme.of(
                              context,
                            ).textTheme.headlineMedium?.copyWith(
                              color: AppColors.textPrimary,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: Spacing.sm),
                          Text(
                            branding.subtitle,
                            style: Theme.of(
                              context,
                            ).textTheme.bodyLarge?.copyWith(
                              color: AppColors.textSecondary,
                              height: 1.5,
                            ),
                          ),
                          const SizedBox(height: Spacing.xxl),
                          if (_error != null) ...[
                            _ErrorBanner(message: _error!),
                            const SizedBox(height: Spacing.lg),
                          ],
                          Text(
                            context.l10n.loginIdentifierLabel,
                            style: Theme.of(context).textTheme.labelLarge
                                ?.copyWith(color: AppColors.textPrimary),
                          ),
                          const SizedBox(height: Spacing.sm),
                          TextFormField(
                            controller: _identifierController,
                            textInputAction: TextInputAction.next,
                            autofillHints: const [AutofillHints.username],
                            decoration: _inputDecoration(
                              context.l10n.loginIdentifierHint,
                              Icons.person_outline,
                              accent,
                            ),
                            validator:
                                (value) =>
                                    value == null || value.trim().isEmpty
                                        ? context.l10n.loginIdentifierRequired
                                        : null,
                          ),
                          const SizedBox(height: Spacing.lg),
                          Text(
                            context.l10n.passwordLabel,
                            style: Theme.of(context).textTheme.labelLarge
                                ?.copyWith(color: AppColors.textPrimary),
                          ),
                          const SizedBox(height: Spacing.sm),
                          TextFormField(
                            controller: _passwordController,
                            obscureText: _obscurePassword,
                            textInputAction: TextInputAction.done,
                            onFieldSubmitted: (_) => _submit(),
                            decoration: _inputDecoration(
                              context.l10n.passwordHint,
                              Icons.lock_outline,
                              accent,
                            ).copyWith(
                              suffixIcon: IconButton(
                                onPressed:
                                    () => setState(
                                      () =>
                                          _obscurePassword = !_obscurePassword,
                                    ),
                                icon: Icon(
                                  _obscurePassword
                                      ? Icons.visibility_outlined
                                      : Icons.visibility_off_outlined,
                                ),
                              ),
                            ),
                            validator:
                                (value) =>
                                    value == null || value.isEmpty
                                        ? context.l10n.passwordRequired
                                        : null,
                          ),
                          Align(
                            alignment: Alignment.centerRight,
                            child: TextButton(
                              onPressed: () => context.push('/forgot-password'),
                              style: TextButton.styleFrom(
                                foregroundColor: accent,
                              ),
                              child: Text(context.l10n.forgotPassword),
                            ),
                          ),
                          const SizedBox(height: Spacing.sm),
                          SizedBox(
                            height: 54,
                            child: FilledButton(
                              onPressed: isSubmitting ? null : _submit,
                              style: FilledButton.styleFrom(
                                backgroundColor: accent,
                                foregroundColor: AppColors.white,
                                shape: const RoundedRectangleBorder(
                                  borderRadius: AppRadius.xl,
                                ),
                              ),
                              child:
                                  isSubmitting
                                      ? const SizedBox(
                                        width: 22,
                                        height: 22,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2.5,
                                          color: AppColors.white,
                                        ),
                                      )
                                      : Text(context.l10n.login),
                            ),
                          ),
                          Consumer(
                            builder: (context, ref, _) {
                              final bioAvailable = ref.watch(
                                biometricAvailableProvider,
                              );
                              final hasFaceId =
                                  ref.watch(hasFaceIdProvider).valueOrNull ??
                                  false;
                              return bioAvailable.maybeWhen(
                                data: (available) {
                                  if (!available) {
                                    return const SizedBox.shrink();
                                  }
                                  return Padding(
                                    padding: const EdgeInsets.only(
                                      top: Spacing.md,
                                    ),
                                    child: SizedBox(
                                      height: 52,
                                      child: OutlinedButton.icon(
                                        onPressed:
                                            _bioSubmitting
                                                ? null
                                                : _loginWithBiometrics,
                                        style: OutlinedButton.styleFrom(
                                          foregroundColor: accent,
                                          side: BorderSide(color: accent),
                                          shape: const RoundedRectangleBorder(
                                            borderRadius: AppRadius.xl,
                                          ),
                                        ),
                                        icon:
                                            _bioSubmitting
                                                ? const SizedBox(
                                                  width: 18,
                                                  height: 18,
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
                                                ),
                                        label: Text(
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
                                  );
                                },
                                orElse: () => const SizedBox.shrink(),
                              );
                            },
                          ),
                          const SizedBox(height: Spacing.lg),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(context.l10n.firstLoginPrompt),
                              TextButton(
                                onPressed: () => context.push('/first-login'),
                                style: TextButton.styleFrom(
                                  foregroundColor: accent,
                                ),
                                child: Text(context.l10n.clickHere),
                              ),
                            ],
                          ),
                          Center(
                            child: TextButton(
                              onPressed: () => context.push('/forgot-id'),
                              style: TextButton.styleFrom(
                                foregroundColor: AppColors.textSecondary,
                              ),
                              child: Text(context.l10n.forgotMemberNo),
                            ),
                          ),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(context.l10n.noAccount),
                              TextButton(
                                onPressed: () => context.push('/register'),
                                style: TextButton.styleFrom(
                                  foregroundColor: accent,
                                ),
                                child: Text(context.l10n.registerNow),
                              ),
                            ],
                          ),
                          const SizedBox(height: Spacing.sm),
                          SizedBox(
                            height: 48,
                            child: OutlinedButton.icon(
                              onPressed: () => context.go('/home'),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: AppColors.textSecondary,
                                side: const BorderSide(
                                  color: AppColors.divider,
                                ),
                                shape: const RoundedRectangleBorder(
                                  borderRadius: AppRadius.xl,
                                ),
                              ),
                              icon: const Icon(
                                Icons.explore_outlined,
                                size: 20,
                              ),
                              label: Text(context.l10n.browseAsGuest),
                            ),
                          ),
                          const SizedBox(height: Spacing.xl),
                          Text(
                            context.l10n.officialPlatform,
                            textAlign: TextAlign.center,
                            style: Theme.of(
                              context,
                            ).textTheme.labelMedium?.copyWith(
                              color: accent,
                              fontSize: AppTextTheme.minSize,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 1.2,
                            ),
                          ),
                          const SizedBox(height: Spacing.xs),
                          const Text(
                            'PKPIM · ABIM · WADAH',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: AppColors.movementDarkGreen,
                              fontSize: AppTextTheme.minSize,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 1,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _BrandMark extends StatelessWidget {
  const _BrandMark({required this.branding, required this.accent});
  final MobileLoginBranding branding;
  final Color accent;
  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 68,
        height: 68,
        decoration: BoxDecoration(
          color: AppColors.white.withValues(alpha: .82),
          borderRadius: AppRadius.lg,
          boxShadow: const [
            BoxShadow(
              color: AppColors.glassTint,
              blurRadius: 16,
              offset: Offset(0, 6),
            ),
          ],
        ),
        child:
            branding.logoUrl?.isNotEmpty == true
                ? ClipRRect(
                  borderRadius: AppRadius.lg,
                  child: Image.network(
                    branding.logoUrl!,
                    fit: BoxFit.contain,
                    errorBuilder: (_, __, ___) => _fallback(),
                  ),
                )
                : _fallback(),
      ),
      const SizedBox(width: Spacing.md),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'myWAP',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                color: AppColors.movementDarkGreen,
                fontWeight: FontWeight.w800,
              ),
            ),
            Text(
              context.l10n.movementPlatformTagline,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                color: AppColors.textSecondary,
                fontSize: AppTextTheme.minSize,
              ),
            ),
          ],
        ),
      ),
    ],
  );
  Widget _fallback() =>
      Icon(Icons.volunteer_activism_outlined, color: accent, size: 34);
}

InputDecoration _inputDecoration(String hint, IconData icon, Color accent) =>
    InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: AppColors.textSecondary, fontSize: 14),
      prefixIcon: Icon(icon, color: accent),
      filled: true,
      fillColor: AppColors.white,
      contentPadding: const EdgeInsets.symmetric(
        horizontal: Spacing.lg,
        vertical: Spacing.lg,
      ),
      border: OutlineInputBorder(
        borderRadius: AppRadius.md,
        borderSide: const BorderSide(color: AppColors.divider),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: AppRadius.md,
        borderSide: const BorderSide(color: AppColors.divider),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: AppRadius.md,
        borderSide: BorderSide(color: accent, width: 2),
      ),
    );

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});
  final String message;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(Spacing.md),
    decoration: BoxDecoration(
      color: AppColors.error.withValues(alpha: .08),
      borderRadius: AppRadius.md,
      border: Border.all(color: AppColors.error.withValues(alpha: .35)),
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

Color _parseColor(String value) =>
    Color(int.parse('FF${value.replaceFirst('#', '')}', radix: 16));
