import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../application/auth_controller.dart';
import 'widgets/auth_scaffold.dart';

/// Lupa No. Ahli — dua langkah: (1) IC, (2) sahkan tarikh lahir.
/// Sepadan dengan web `/forgot-id` (AuthenticatedSessionController::forgotId).
class ForgotIdScreen extends ConsumerStatefulWidget {
  const ForgotIdScreen({super.key});

  @override
  ConsumerState<ForgotIdScreen> createState() => _ForgotIdScreenState();
}

class _ForgotIdScreenState extends ConsumerState<ForgotIdScreen> {
  final _formKey = GlobalKey<FormState>();
  final _icController = TextEditingController();
  DateTime? _dob;
  bool _submitting = false;
  bool _needsDob = false;
  String? _error;
  String? _memberNo;
  String? _maskedEmail;

  @override
  void dispose() {
    _icController.dispose();
    super.dispose();
  }

  Future<void> _pickDob() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime(now.year - 20),
      firstDate: DateTime(now.year - 100),
      lastDate: now,
      helpText: 'Pilih Tarikh Lahir',
    );
    if (picked != null) setState(() => _dob = picked);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_needsDob && _dob == null) {
      setState(() => _error = 'Sila pilih tarikh lahir.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final repo = ref.read(authRepositoryProvider);
      final dobString = _dob != null
          ? '${_dob!.year.toString().padLeft(4, '0')}-${_dob!.month.toString().padLeft(2, '0')}-${_dob!.day.toString().padLeft(2, '0')}'
          : null;
      final result = await repo.forgotId(
        _icController.text.trim(),
        dob: dobString,
      );
      if (!mounted) return;

      if (result['needs_verification'] == true) {
        setState(() => _needsDob = true);
        return;
      }

      if (result['verified'] == true) {
        setState(() {
          _memberNo = result['member_no'] as String?;
          _maskedEmail = result['masked_email'] as String?;
        });
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_memberNo != null) {
      return AuthScaffold(
        title: 'No. Ahli Ditemui',
        subtitle: 'Berikut adalah No. Ahli anda yang berdaftar dengan sistem.',
        children: [
          Container(
            padding: const EdgeInsets.all(Spacing.xl),
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: AppColors.heroGradient),
              borderRadius: AppRadius.hero,
              boxShadow: AppShadows.card,
            ),
            child: Column(
              children: [
                Text(
                  'NO. AHLI',
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: AppColors.textOnDark,
                    letterSpacing: 1.2,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _memberNo!,
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    color: AppColors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (_maskedEmail != null) ...[
                  const SizedBox(height: Spacing.sm),
                  Text(
                    'Emel berdaftar: $_maskedEmail',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textOnDark,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: Spacing.xl),
          FilledButton(
            onPressed: () => context.go('/login'),
            child: const Text('Log Masuk'),
          ),
        ],
      );
    }

    return AuthScaffold(
      title: 'Lupa No. Ahli',
      subtitle: _needsDob
          ? 'Sahkan tarikh lahir anda untuk memaparkan No. Ahli.'
          : 'Masukkan No. Kad Pengenalan untuk mencari No. Ahli anda.',
      onBack: () => context.pop(),
      footer: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Text('Ingat No. Ahli?'),
          TextButton(
            onPressed: () => context.go('/login'),
            child: const Text('Log Masuk'),
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
              if (_needsDob) ...[
                Text(
                  'Tarikh Lahir',
                  style: Theme.of(
                    context,
                  ).textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: Spacing.sm),
                InkWell(
                  onTap: _pickDob,
                  borderRadius: AppRadius.md,
                  child: InputDecorator(
                    decoration: const InputDecoration(),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.calendar_month_outlined,
                          color: AppColors.movementGreen,
                        ),
                        const SizedBox(width: Spacing.md),
                        Text(
                          _dob == null
                              ? 'Pilih tarikh lahir'
                              : '${_dob!.day}/${_dob!.month}/${_dob!.year}',
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: Spacing.lg),
              ],
              FilledButton(
                onPressed: _submitting ? null : _submit,
                child: _submitting
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.5,
                          color: AppColors.white,
                        ),
                      )
                    : Text(_needsDob ? 'Sahkan' : 'Seterusnya'),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
