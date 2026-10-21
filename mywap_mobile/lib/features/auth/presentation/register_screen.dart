import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../application/auth_controller.dart';
import 'widgets/auth_scaffold.dart';

/// Skrin daftar ahli baharu — sepadan dengan web `/register` (RegisteredUserController).
/// Pembayaran pendaftaran (dummy) diproses terus oleh backend selepas daftar
/// berjaya, jadi akaun terus aktif dan ahli hanya perlu log masuk kali pertama.
class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key, this.referralCode});

  final String? referralCode;

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _icController = TextEditingController();
  final _phoneController = TextEditingController();
  static final _nonDigit = RegExp(r'[^0-9]');
  DateTime? _dob;
  bool _submitting = false;
  String? _error;
  String? _memberNo;
  String? _referrerName;

  @override
  void initState() {
    super.initState();
    if (widget.referralCode != null && widget.referralCode!.isNotEmpty) {
      _resolveReferrer();
    }
  }

  Future<void> _resolveReferrer() async {
    final repo = ref.read(authRepositoryProvider);
    final result = await repo.resolveReferral(widget.referralCode!);
    if (result != null && mounted) {
      setState(() => _referrerName = result['name'] as String?);
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _icController.dispose();
    _phoneController.dispose();
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

  /// Semak sama ada No. IC mengandungi tarikh lahir yang boleh dibaca
  /// (format IC Malaysia: YYMMDD di 6 digit pertama).
  bool _icHasReadableDob(String value) {
    final digits = value.replaceAll(_nonDigit, '');
    if (digits.length < 6) return false;

    final yy = int.parse(digits.substring(0, 2));
    final mm = int.parse(digits.substring(2, 4));
    final dd = int.parse(digits.substring(4, 6));

    var year = 2000 + yy;
    if (year > DateTime.now().year) year -= 100;

    final date = DateTime(year, mm, dd);
    return date.year == year && date.month == mm && date.day == dd;
  }

  String? _validateIc(String? value) {
    final v = (value ?? '').trim();
    if (v.isEmpty) {
      return 'Sila masukkan No. Kad Pengenalan atau No. Passport.';
    }
    final cleaned = v.replaceAll(RegExp(r'[\s\-.]'), '');
    if (cleaned.length < 6) {
      return 'No. IC tidak sah. Masukkan 12 digit No. Kad Pengenalan '
          '(cth: 900101011234) atau No. Passport.';
    }
    return null;
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final repo = ref.read(authRepositoryProvider);
      final result = await repo.register(
        name: _nameController.text.trim(),
        email: _emailController.text.trim(),
        icNumber: _icController.text.trim(),
        phone: _phoneController.text.trim().isEmpty
            ? null
            : _phoneController.text.trim(),
        dob: _dob != null
            ? '${_dob!.year.toString().padLeft(4, '0')}-${_dob!.month.toString().padLeft(2, '0')}-${_dob!.day.toString().padLeft(2, '0')}'
            : null,
        referralCode: widget.referralCode,
      );
      if (!mounted) return;
      setState(() => _memberNo = result['member_no'] as String?);
    } on ApiException catch (e) {
      final messages = <String>{};
      e.errors?.forEach((_, values) => messages.addAll(values));
      setState(() =>
          _error = messages.isEmpty ? e.message : messages.join('\n'));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_memberNo != null) {
      return _RegisterSuccess(memberNo: _memberNo!);
    }

    return AuthScaffold(
      title: 'Daftar Ahli Baharu',
      subtitle: 'Isi maklumat di bawah untuk menyertai ekosistem myWAP.',
      onBack: () => context.pop(),
      footer: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Text('Sudah ada akaun?'),
          TextButton(
            onPressed: () => context.go('/login'),
            child: const Text('Log Masuk'),
          ),
        ],
      ),
      children: [
        if (_referrerName != null) ...[
          AuthSuccessBanner(message: 'Dijemput oleh $_referrerName'),
        ],
        if (_error != null) AuthErrorBanner(message: _error!),
        Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AuthField(
                label: 'Nama Penuh',
                controller: _nameController,
                icon: Icons.person_outline,
                hint: 'Contoh: Ahmad Firdaus',
                textInputAction: TextInputAction.next,
                validator: (v) =>
                    v == null || v.trim().isEmpty ? 'Sila masukkan nama.' : null,
              ),
              AuthField(
                label: 'Emel',
                controller: _emailController,
                icon: Icons.mail_outline,
                hint: 'nama@emel.com',
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                validator: (v) {
                  final email = (v ?? '').trim();
                  if (email.isEmpty) return 'Sila masukkan alamat emel.';
                  final valid = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');
                  if (!valid.hasMatch(email)) {
                    return 'Alamat emel tidak sah. Contoh: nama@emel.com';
                  }
                  return null;
                },
              ),
              AuthField(
                label: 'No. Kad Pengenalan / Passport',
                controller: _icController,
                icon: Icons.badge_outlined,
                hint: 'Contoh: 900101011234',
                textInputAction: TextInputAction.next,
                validator: _validateIc,
              ),
              AuthField(
                label: 'No. Telefon (Pilihan)',
                controller: _phoneController,
                icon: Icons.phone_outlined,
                hint: 'Contoh: 0123456789',
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.done,
                validator: (v) {
                  final phone = (v ?? '').trim();
                  if (phone.isEmpty) return null;
                  if (!RegExp(r'^[0-9+\-\s()]{7,20}$').hasMatch(phone)) {
                    return 'No. telefon tidak sah. Contoh: 0123456789';
                  }
                  return null;
                },
              ),
              FormField<DateTime>(
                validator: (_) {
                  if (_validateIc(_icController.text) != null) return null;
                  if (_dob == null && !_icHasReadableDob(_icController.text)) {
                    return 'Sila pilih tarikh lahir kerana No. IC tidak lengkap '
                        'atau bukan format IC Malaysia.';
                  }
                  return null;
                },
                builder: (field) => Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Tarikh Lahir (Diperlukan jika No. IC bukan 12 digit)',
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: Spacing.sm),
                    InkWell(
                      onTap: () async {
                        await _pickDob();
                        field.didChange(_dob);
                        field.validate();
                      },
                      borderRadius: AppRadius.md,
                      child: InputDecorator(
                        decoration: InputDecoration(
                          errorText: field.errorText,
                        ),
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
                  ],
                ),
              ),
              const SizedBox(height: Spacing.xl),
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
                    : const Text('Daftar Sekarang'),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _RegisterSuccess extends StatelessWidget {
  const _RegisterSuccess({required this.memberNo});
  final String memberNo;

  @override
  Widget build(BuildContext context) {
    return AuthScaffold(
      title: 'Pendaftaran Berjaya!',
      subtitle:
          'Akaun anda telah diaktifkan. Sila log masuk kali pertama menggunakan No. IC anda untuk menetapkan kata laluan.',
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
              const Icon(
                Icons.check_circle_outline,
                color: AppColors.white,
                size: 40,
              ),
              const SizedBox(height: Spacing.md),
              Text(
                'NO. AHLI ANDA',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: AppColors.textOnDark,
                  letterSpacing: 1.2,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                memberNo,
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  color: AppColors.white,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: Spacing.xl),
        FilledButton(
          onPressed: () => context.go('/login'),
          child: const Text('Log Masuk Kali Pertama'),
        ),
      ],
    );
  }
}
