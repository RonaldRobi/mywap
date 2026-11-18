import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/ic/malaysian_ic.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/validation/malaysian_phone.dart';
import '../../../shared/widgets/dob_picker.dart';
import '../application/auth_controller.dart';
import 'widgets/auth_scaffold.dart';

/// Skrin daftar ahli baharu, sepadan dengan web `/register`
/// (RegisteredUserController). Pendaftaran percuma, akaun terus aktif dan
/// emel selamat datang dihantar oleh backend selepas daftar berjaya.
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
  final _dobFieldKey = GlobalKey<FormFieldState<DateTime>>();
  DateTime? _dob;
  bool _dobEdited = false;
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

  void _onIcChanged(String value) {
    if (_dobEdited) return;
    final parsed = parseDobFromIc(value);
    setState(() => _dob = parsed);
    final field = _dobFieldKey.currentState;
    if (field?.hasError ?? false) {
      field!.didChange(parsed);
      field.validate();
    }
  }

  String? _validateIc(String? value) {
    final v = (value ?? '').trim();
    if (v.isEmpty) {
      return context.l10n.icRequired;
    }
    final cleaned = v.replaceAll(RegExp(r'[\s\-.]'), '');
    if (cleaned.length < 6) {
      return context.l10n.icInvalid;
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
        phone:
            _phoneController.text.trim().isEmpty
                ? null
                : MalaysianPhone.normalize(_phoneController.text),
        dob: _dob == null ? null : formatDobApi(_dob!),
        referralCode: widget.referralCode,
      );
      if (!mounted) return;
      setState(() => _memberNo = result['member_no'] as String?);
    } on ApiException catch (e) {
      final messages = <String>{};
      e.errors?.forEach((_, values) => messages.addAll(values));
      setState(
        () => _error = messages.isEmpty ? e.message : messages.join('\n'),
      );
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
      title: context.l10n.registerTitle,
      subtitle:
          '${context.l10n.registerSubtitle}\n${context.l10n.registerFreeNote}',
      icon: Icons.person_add_alt_1_rounded,
      headingInCard: true,
      onBack: () => context.pop(),
      footer: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(context.l10n.alreadyHaveAccount),
          TextButton(
            onPressed: () => context.go('/login'),
            child: Text(context.l10n.login),
          ),
        ],
      ),
      children: [
        if (_referrerName != null) ...[
          AuthSuccessBanner(message: context.l10n.invitedBy(_referrerName!)),
        ],
        if (_error != null) AuthErrorBanner(message: _error!),
        Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AuthField(
                label: context.l10n.fullNameLabel,
                controller: _nameController,
                icon: Icons.person_outline,
                hint: context.l10n.fullNameHint,
                textInputAction: TextInputAction.next,
                validator:
                    (v) =>
                        v == null || v.trim().isEmpty
                            ? context.l10n.fullNameRequired
                            : null,
              ),
              AuthField(
                label: context.l10n.emailLabel,
                controller: _emailController,
                icon: Icons.mail_outline,
                hint: context.l10n.emailHint,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                validator: (v) {
                  final email = (v ?? '').trim();
                  if (email.isEmpty) return context.l10n.emailRequired;
                  final valid = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');
                  if (!valid.hasMatch(email)) {
                    return context.l10n.emailInvalid;
                  }
                  return null;
                },
              ),
              AuthField(
                label: context.l10n.icPassportLabel,
                controller: _icController,
                icon: Icons.badge_outlined,
                hint: context.l10n.icHint,
                textInputAction: TextInputAction.next,
                onChanged: _onIcChanged,
                validator: _validateIc,
              ),
              FormField<DateTime>(
                key: _dobFieldKey,
                validator: (_) {
                  if (_validateIc(_icController.text) != null) return null;
                  if (_dob == null &&
                      parseDobFromIc(_icController.text) == null) {
                    return context.l10n.dobRequiredForIc;
                  }
                  return null;
                },
                builder:
                    (field) => DobPicker(
                      label: context.l10n.dobLabelRequired,
                      labelAbove: true,
                      value: _dob,
                      inferredFromIc: !_dobEdited && _dob != null,
                      errorText: field.errorText,
                      onChanged: (date) {
                        setState(() {
                          _dob = date;
                          _dobEdited = true;
                        });
                        field.didChange(date);
                        field.validate();
                      },
                    ),
              ),
              const SizedBox(height: Spacing.md),
              AuthField(
                label: context.l10n.phoneOptionalLabel,
                controller: _phoneController,
                icon: Icons.phone_outlined,
                hint: context.l10n.phoneHint,
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.done,
                inputFormatters: MalaysianPhone.inputFormatters,
                validator: (v) {
                  final phone = (v ?? '').trim();
                  if (phone.isEmpty) return null;
                  if (!MalaysianPhone.isValid(phone)) {
                    return context.l10n.phoneInvalid;
                  }
                  return null;
                },
              ),
              const SizedBox(height: Spacing.xl),
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
                        : Text(context.l10n.registerNow),
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
      title: context.l10n.registerSuccessTitle,
      subtitle: context.l10n.registerSuccessSubtitle,
      icon: Icons.check_circle_outline_rounded,
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
                context.l10n.yourMemberNo,
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
          child: Text(context.l10n.firstTimeLogin),
        ),
      ],
    );
  }
}
