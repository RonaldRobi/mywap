import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../auth/application/auth_controller.dart';
import '../application/profile_providers.dart';
import '../data/models/profile_data.dart';
import 'profile_format.dart';

List<(String, String)> _genderOptions(AppLocalizations l10n) => [
  ('lelaki', l10n.genderMale),
  ('perempuan', l10n.genderFemale),
];

List<(String, String)> _maritalOptions(AppLocalizations l10n) => [
  ('bujang', l10n.maritalSingle),
  ('berkahwin', l10n.maritalMarried),
  ('bercerai', l10n.maritalDivorced),
  ('duda/janda', l10n.maritalWidowed),
];

class CompleteProfileScreen extends ConsumerStatefulWidget {
  const CompleteProfileScreen({super.key});

  @override
  ConsumerState<CompleteProfileScreen> createState() =>
      _CompleteProfileScreenState();
}

class _CompleteProfileScreenState extends ConsumerState<CompleteProfileScreen> {
  final _formKey = GlobalKey<FormState>();

  final Map<String, TextEditingController> _fields = {
    'education_level': TextEditingController(),
    'current_profession': TextEditingController(),
    'phone': TextEditingController(),
    'address_1': TextEditingController(),
    'address_2': TextEditingController(),
    'postcode': TextEditingController(),
    'city': TextEditingController(),
    'state': TextEditingController(),
    'emergency_contact_name': TextEditingController(),
    'emergency_contact_phone': TextEditingController(),
    'topics': TextEditingController(),
  };

  DateTime? _dob;
  String? _gender;
  String? _maritalStatus;
  int? _branchId;
  List<ProfileBranch> _branches = const [];
  bool _metaLoaded = false;
  bool _saving = false;
  final Map<String, String> _serverErrors = {};

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final metaAsync = ref.watch(profileCompleteMetaProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(fallback: '/profile'),
        title: Text(context.l10n.completeProfileTitle),
        actions: [
          IconButton(
            tooltip: context.l10n.logout,
            icon: const Icon(Icons.logout),
            onPressed: () => ref.read(authControllerProvider.notifier).logout(),
          ),
        ],
      ),
      body: metaAsync.when(
        data: (meta) {
          _initFromMeta(meta);
          return _buildForm();
        },
        loading: () => const _FormSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : context.l10n.unexpectedError,
              onRetry: () => ref.invalidate(profileCompleteMetaProvider),
            ),
      ),
    );
  }

  void _initFromMeta(CompleteMeta meta) {
    if (_metaLoaded) return;
    _metaLoaded = true;
    _dob =
        meta.parsedDob == null
            ? null
            : ProfileFormat.parseDate(meta.parsedDob!);
    _gender = meta.parsedGender;
    _branches = meta.branches;
    _branchId = meta.currentBranchId;
  }

  Widget _buildForm() {
    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          Spacing.lg,
          Spacing.lg,
          Spacing.lg,
          Spacing.xl,
        ),
        children: [
          Container(
            margin: const EdgeInsets.only(bottom: Spacing.lg),
            padding: const EdgeInsets.all(Spacing.lg),
            decoration: BoxDecoration(
              color: AppColors.movementSoftGreen.withValues(alpha: 0.18),
              borderRadius: AppRadius.md,
            ),
            child: Row(
              children: [
                const Icon(Icons.info_outline, color: AppColors.movementGreen),
                const SizedBox(width: Spacing.md),
                Expanded(child: Text(context.l10n.completeProfilePrompt2)),
              ],
            ),
          ),
          if (_serverErrors.isNotEmpty)
            Container(
              margin: const EdgeInsets.only(bottom: Spacing.lg),
              padding: const EdgeInsets.all(Spacing.md),
              decoration: BoxDecoration(
                color: AppColors.error.withValues(alpha: 0.08),
                borderRadius: AppRadius.md,
              ),
              child: Text(
                context.l10n.checkMarkedFields,
                style: const TextStyle(color: AppColors.error),
              ),
            ),
          _section(context.l10n.requiredInfoSection, [
            _branchField(),
            _textField(
              'education_level',
              label: context.l10n.educationLevelLabel,
              required: true,
            ),
            _textField(
              'current_profession',
              label: context.l10n.currentProfessionLabel,
              required: true,
            ),
            _textField(
              'phone',
              label: context.l10n.phoneLabel,
              required: true,
              keyboardType: TextInputType.phone,
            ),
          ]),
          _section(context.l10n.moreInfoSection, [
            _dobField(),
            _optionField(
              key: 'gender',
              label: context.l10n.genderLabel,
              value: _gender,
              options: _genderOptions(context.l10n),
              onChanged: (value) => setState(() => _gender = value),
            ),
            _optionField(
              key: 'marital_status',
              label: context.l10n.maritalStatusLabel,
              value: _maritalStatus,
              options: _maritalOptions(context.l10n),
              onChanged: (value) => setState(() => _maritalStatus = value),
            ),
          ]),
          _section(context.l10n.addressSection, [
            _textField('address_1', label: context.l10n.addressLine1),
            _textField('address_2', label: context.l10n.addressLine2),
            _textField(
              'postcode',
              label: context.l10n.postcodeLabel,
              keyboardType: TextInputType.number,
            ),
            _textField('city', label: context.l10n.cityLabel),
            _textField('state', label: context.l10n.stateLabel),
          ]),
          _section(context.l10n.emergencySection, [
            _textField('emergency_contact_name', label: context.l10n.nameLabel),
            _textField(
              'emergency_contact_phone',
              label: context.l10n.phoneLabel,
              keyboardType: TextInputType.phone,
            ),
          ]),
          _section(context.l10n.interestsSection, [
            _textField(
              'topics',
              label: context.l10n.topicsLabel,
              hint: context.l10n.commaSeparated,
            ),
          ]),
          const SizedBox(height: Spacing.md),
          ElevatedButton.icon(
            onPressed: _saving ? null : _submit,
            icon:
                _saving
                    ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: AppColors.white,
                      ),
                    )
                    : const Icon(Icons.check_circle_outline),
            label: Text(
              _saving ? context.l10n.saving : context.l10n.saveAndFinish,
            ),
          ),
        ],
      ),
    );
  }

  Widget _section(String title, List<Widget> children) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: Spacing.md),
        ...children,
        const SizedBox(height: Spacing.lg),
      ],
    );
  }

  Widget _textField(
    String key, {
    required String label,
    bool required = false,
    TextInputType? keyboardType,
    String? hint,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: Spacing.md),
      child: TextFormField(
        controller: _fields[key],
        keyboardType: keyboardType,
        textCapitalization: TextCapitalization.sentences,
        decoration: InputDecoration(
          labelText: required ? '$label *' : label,
          hintText: hint,
          errorText: _serverErrors[key],
        ),
        validator: (value) {
          if (required && (value == null || value.trim().isEmpty)) {
            return context.l10n.fieldRequired(label);
          }
          return null;
        },
        onChanged: (_) {
          if (_serverErrors.containsKey(key)) {
            setState(() => _serverErrors.remove(key));
          }
        },
      ),
    );
  }

  Widget _branchField() {
    return Padding(
      padding: const EdgeInsets.only(bottom: Spacing.md),
      child: DropdownButtonFormField<int>(
        value: _branchId,
        decoration: InputDecoration(
          labelText: context.l10n.branchRequired,
          errorText: _serverErrors['branch_id'],
        ),
        hint: Text(context.l10n.selectBranch),
        items: [
          for (final branch in _branches)
            if (branch.id != null)
              DropdownMenuItem(
                value: branch.id,
                child: Text(
                  branch.state != null &&
                          branch.state!.isNotEmpty &&
                          branch.state != branch.name
                      ? '${branch.name ?? ''} — ${branch.state}'
                      : (branch.name ?? ''),
                ),
              ),
        ],
        validator: (value) {
          if (value == null) return context.l10n.branchRequiredError;
          return null;
        },
        onChanged: (selected) {
          setState(() => _branchId = selected);
          if (_serverErrors.containsKey('branch_id')) {
            setState(() => _serverErrors.remove('branch_id'));
          }
        },
      ),
    );
  }

  Widget _optionField({
    required String key,
    required String label,
    required String? value,
    required List<(String, String)> options,
    required ValueChanged<String?> onChanged,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: Spacing.md),
      child: DropdownButtonFormField<String>(
        value: value,
        decoration: InputDecoration(
          labelText: label,
          errorText: _serverErrors[key],
        ),
        hint: Text(context.l10n.choose),
        items: [
          for (final (optionValue, optionLabel) in options)
            DropdownMenuItem(value: optionValue, child: Text(optionLabel)),
        ],
        onChanged: (selected) {
          onChanged(selected);
          if (_serverErrors.containsKey(key)) {
            setState(() => _serverErrors.remove(key));
          }
        },
      ),
    );
  }

  Widget _dobField() {
    return Padding(
      padding: const EdgeInsets.only(bottom: Spacing.md),
      child: InkWell(
        onTap: _pickDob,
        borderRadius: AppRadius.md,
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: context.l10n.dobLabel,
            errorText: _serverErrors['dob'],
          ),
          child: Row(
            children: [
              const Icon(
                Icons.calendar_today_outlined,
                size: 20,
                color: AppColors.textSecondary,
              ),
              const SizedBox(width: Spacing.sm),
              Expanded(
                child: Text(
                  _dob == null
                      ? context.l10n.selectDate
                      : ProfileFormat.date(_dob!),
                  style: TextStyle(
                    fontSize: 16,
                    color:
                        _dob == null
                            ? AppColors.textSecondary
                            : AppColors.textPrimary,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _pickDob() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _dob ?? DateTime(now.year - 25, now.month, now.day),
      firstDate: DateTime(1940),
      lastDate: now,
    );
    if (picked != null && mounted) {
      setState(() {
        _dob = picked;
        _serverErrors.remove('dob');
      });
    }
  }

  Map<String, dynamic> _payload() {
    String? valueOf(String key) {
      final text = _fields[key]!.text.trim();
      return text.isEmpty ? null : text;
    }

    return <String, dynamic>{
      'education_level': valueOf('education_level'),
      'current_profession': valueOf('current_profession'),
      'phone': valueOf('phone'),
      'branch_id': _branchId,
      'dob': _dob == null ? null : ProfileFormat.apiDate(_dob!),
      'gender': _gender,
      'marital_status': _maritalStatus,
      'address_1': valueOf('address_1'),
      'address_2': valueOf('address_2'),
      'postcode': valueOf('postcode'),
      'city': valueOf('city'),
      'state': valueOf('state'),
      'emergency_contact_name': valueOf('emergency_contact_name'),
      'emergency_contact_phone': valueOf('emergency_contact_phone'),
      'topics': valueOf('topics'),
    };
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);

    try {
      await ref.read(profileRepositoryProvider).completeProfile(_payload());
      if (!mounted) return;
      ref.invalidate(profileProvider);
      // Muat semula pengguna supaya pengawal penghalaan berhenti memaksa
      // pengguna kembali ke skrin ini.
      await ref.read(authControllerProvider.notifier).refreshCurrentUser();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.l10n.profileCompleted)));
      context.go('/profile');
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _serverErrors
          ..clear()
          ..addAll(_mapErrors(error.errors));
      });
      if (_serverErrors.isEmpty) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.unexpectedErrorRetry)),
      );
    }
  }

  Map<String, String> _mapErrors(Map<String, List<String>>? errors) {
    final mapped = <String, String>{};
    errors?.forEach((key, value) {
      if (value.isNotEmpty) mapped[key] = value.first;
    });
    return mapped;
  }
}

class _FormSkeleton extends StatelessWidget {
  const _FormSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: const [
        SkeletonBox(height: 56),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 56),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 56),
        SizedBox(height: Spacing.xl),
        SkeletonBox(height: 20, width: 160),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 56),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 56),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 56),
      ],
    );
  }
}
