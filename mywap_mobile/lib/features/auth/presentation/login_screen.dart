import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
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
      setState(() {
        _error =
            auth is AuthUnauthenticated && auth.error != null
                ? auth.error!
                : 'Log masuk gagal. Sila cuba lagi.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final isSubmitting = ref.watch(authControllerProvider) is AuthLoading;
    final theme = Theme.of(context);

    return Scaffold(
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              padding: const EdgeInsets.all(Spacing.lg),
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 420),
                    child: Container(
                      padding: const EdgeInsets.all(Spacing.xl),
                      decoration: BoxDecoration(
                        color: AppColors.movementOffWhite,
                        border: Border.all(color: AppColors.movementSoftGreen),
                        borderRadius: AppRadius.md,
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x1F071525),
                            blurRadius: 28,
                            offset: Offset(0, 10),
                          ),
                        ],
                      ),
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(
                              'PLATFORM DIGITAL EKOSISTEM GERAKAN',
                              style: theme.textTheme.labelMedium?.copyWith(
                                color: AppColors.movementGreen,
                                fontSize: 10,
                                fontWeight: FontWeight.w700,
                                letterSpacing: 1.5,
                              ),
                            ),
                            const SizedBox(height: Spacing.sm),
                            Text(
                              'myWAP',
                              style: theme.textTheme.titleLarge?.copyWith(
                                color: AppColors.movementDarkGreen,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: Spacing.xl),
                            Text(
                              'Log Masuk',
                              style: theme.textTheme.headlineMedium?.copyWith(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: Spacing.sm),
                            Text(
                              'Selamat kembali ke myWAP. Sila log masuk untuk meneruskan.',
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: AppColors.textSecondary,
                              ),
                            ),
                            const SizedBox(height: Spacing.xl),
                            if (_error != null) ...[
                              _ErrorBanner(message: _error!),
                              const SizedBox(height: Spacing.lg),
                            ],
                            TextFormField(
                              controller: _identifierController,
                              textInputAction: TextInputAction.next,
                              autofillHints: const [AutofillHints.email],
                              decoration: const InputDecoration(
                                labelText: 'Emel atau No Kad Pengenalan',
                                prefixIcon: Icon(Icons.person_outline),
                              ),
                              validator:
                                  (value) =>
                                      value == null || value.trim().isEmpty
                                          ? 'Sila masukkan emel atau nombor kad pengenalan.'
                                          : null,
                            ),
                            const SizedBox(height: Spacing.lg),
                            TextFormField(
                              controller: _passwordController,
                              obscureText: _obscurePassword,
                              textInputAction: TextInputAction.done,
                              onFieldSubmitted: (_) => _submit(),
                              decoration: InputDecoration(
                                labelText: 'Kata Laluan',
                                prefixIcon: const Icon(Icons.lock_outline),
                                suffixIcon: IconButton(
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
                                  ),
                                ),
                              ),
                              validator:
                                  (value) =>
                                      value == null || value.isEmpty
                                          ? 'Sila masukkan kata laluan.'
                                          : null,
                            ),
                            const SizedBox(height: Spacing.xl),
                            FilledButton(
                              onPressed: isSubmitting ? null : _submit,
                              child:
                                  isSubmitting
                                      ? const SizedBox(
                                        width: 24,
                                        height: 24,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2.5,
                                          color: AppColors.white,
                                        ),
                                      )
                                      : const Text('Log Masuk'),
                            ),
                            const SizedBox(height: Spacing.md),
                            TextButton(
                              onPressed: () {},
                              child: const Text('Lupa kata laluan?'),
                            ),
                            const Divider(
                              height: Spacing.xxl,
                              color: AppColors.divider,
                            ),
                            Text(
                              'PLATFORM RASMI EKOSISTEM',
                              textAlign: TextAlign.center,
                              style: theme.textTheme.labelMedium?.copyWith(
                                color: AppColors.movementGreen,
                                fontSize: 9,
                                fontWeight: FontWeight.w700,
                                letterSpacing: 1.2,
                              ),
                            ),
                            const SizedBox(height: Spacing.xs),
                            Text(
                              'PKPIM · ABIM · WADAH',
                              textAlign: TextAlign.center,
                              style: theme.textTheme.labelMedium?.copyWith(
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
            );
          },
        ),
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(Spacing.md),
      decoration: BoxDecoration(
        color: AppColors.error.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.error.withValues(alpha: 0.4)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline, color: AppColors.error),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(color: AppColors.error),
            ),
          ),
        ],
      ),
    );
  }
}
