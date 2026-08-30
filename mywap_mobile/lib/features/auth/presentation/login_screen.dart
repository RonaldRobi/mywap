import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../onboarding/application/onboarding_providers.dart';
import '../../onboarding/data/onboarding_repository.dart';
import '../application/auth_controller.dart';

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
                    : 'Log masuk gagal. Sila cuba lagi.',
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    const fallback = MobileLoginBranding(
      title: 'Selamat kembali',
      subtitle: 'Log masuk untuk meneruskan ke myWAP.',
      backgroundStart: '#F4F6F1',
      backgroundEnd: '#EDF5EE',
      accent: '#2F6B32',
    );
    final branding =
        ref.watch(mobileAuthConfigurationProvider).valueOrNull?.login ??
        fallback;
    final accent = _parseColor(branding.accent);
    final isSubmitting = ref.watch(authControllerProvider) is AuthLoading;

    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              _parseColor(branding.backgroundStart),
              _parseColor(branding.backgroundEnd),
            ],
          ),
        ),
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
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const SizedBox(height: Spacing.xl),
                        _BrandMark(branding: branding, accent: accent),
                        const SizedBox(height: 40),
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
                          'Emel atau No. Kad Pengenalan',
                          style: Theme.of(context).textTheme.labelLarge
                              ?.copyWith(color: AppColors.textPrimary),
                        ),
                        const SizedBox(height: Spacing.sm),
                        TextFormField(
                          controller: _identifierController,
                          textInputAction: TextInputAction.next,
                          autofillHints: const [AutofillHints.username],
                          decoration: _inputDecoration(
                            'Contoh: nama@emel.com atau 900101...',
                            Icons.person_outline,
                            accent,
                          ),
                          validator:
                              (value) =>
                                  value == null || value.trim().isEmpty
                                      ? 'Sila masukkan emel atau nombor kad pengenalan.'
                                      : null,
                        ),
                        const SizedBox(height: Spacing.lg),
                        Text(
                          'Kata Laluan',
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
                            'Masukkan kata laluan',
                            Icons.lock_outline,
                            accent,
                          ).copyWith(
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
                          ),
                          validator:
                              (value) =>
                                  value == null || value.isEmpty
                                      ? 'Sila masukkan kata laluan.'
                                      : null,
                        ),
                        Align(
                          alignment: Alignment.centerRight,
                          child: TextButton(
                            onPressed: () {},
                            style: TextButton.styleFrom(
                              foregroundColor: accent,
                            ),
                            child: const Text('Lupa kata laluan?'),
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
                                    : const Text('Log Masuk'),
                          ),
                        ),
                        const Spacer(),
                        const SizedBox(height: Spacing.xxl),
                        Text(
                          'PLATFORM RASMI EKOSISTEM',
                          textAlign: TextAlign.center,
                          style: Theme.of(
                            context,
                          ).textTheme.labelMedium?.copyWith(
                            color: accent,
                            fontSize: 9,
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
                            fontSize: 12,
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
              color: Color(0x14071525),
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
                fontWeight: FontWeight.w900,
              ),
            ),
            Text(
              'Platform Digital Ekosistem Gerakan',
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                color: AppColors.textSecondary,
                fontSize: 10,
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
      fillColor: AppColors.white.withValues(alpha: .88),
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
