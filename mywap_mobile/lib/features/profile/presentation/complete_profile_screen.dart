import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/profile_providers.dart';
import '../data/models/profile_data.dart';
import 'profile_format.dart';

const _genderOptions = <(String, String)>[
  ('lelaki', 'Lelaki'),
  ('perempuan', 'Perempuan'),
];

const _maritalOptions = <(String, String)>[
  ('bujang', 'Bujang'),
  ('berkahwin', 'Berkahwin'),
  ('bercerai', 'Bercerai'),
  ('duda/janda', 'Duda / Janda'),
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
      appBar: AppBar(title: const Text('Lengkapkan Profil')),
      body: metaAsync.when(
        data: (meta) {
          _initFromMeta(meta);
          return _buildForm();
        },
        loading: () => const _FormSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(profileCompleteMetaProvider),
        ),
      ),
    );
  }

  void _initFromMeta(CompleteMeta meta) {
    if (_metaLoaded) return;
    _metaLoaded = true;
    _dob = meta.parsedDob == null ? null : ProfileFormat.parseDate(meta.parsedDob!);
    _gender = meta.parsedGender;
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
            child: const Row(
              children: [
                Icon(Icons.info_outline, color: AppColors.movementGreen),
                SizedBox(width: Spacing.md),
                Expanded(
                  child: Text(
                    'Lengkapkan maklumat berikut untuk mengaktifkan profil anda.',
                  ),
                ),
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
              child: const Text(
                'Sila semak semula maklumat yang bertanda merah.',
                style: TextStyle(color: AppColors.error),
              ),
            ),
          _section('Maklumat Wajib', [
            _textField('education_level', label: 'Tahap Pendidikan', required: true),
            _textField('current_profession', label: 'Profesion Semasa', required: true),
            _textField('phone', label: 'No. Telefon', required: true, keyboardType: TextInputType.phone),
          ]),
          _section('Maklumat Lanjut', [
            _dobField(),
            _optionField(
              key: 'gender',
              label: 'Jantina',
              value: _gender,
              options: _genderOptions,
              onChanged: (value) => setState(() => _gender = value),
            ),
            _optionField(
              key: 'marital_status',
              label: 'Status Perkahwinan',
              value: _maritalStatus,
              options: _maritalOptions,
              onChanged: (value) => setState(() => _maritalStatus = value),
            ),
          ]),
          _section('Alamat', [
            _textField('address_1', label: 'Alamat (Baris 1)'),
            _textField('address_2', label: 'Alamat (Baris 2)'),
            _textField('postcode', label: 'Poskod', keyboardType: TextInputType.number),
            _textField('city', label: 'Bandar'),
            _textField('state', label: 'Negeri'),
          ]),
          _section('Hubungan Kecemasan', [
            _textField('emergency_contact_name', label: 'Nama'),
            _textField(
              'emergency_contact_phone',
              label: 'No. Telefon',
              keyboardType: TextInputType.phone,
            ),
          ]),
          _section('Minat', [
            _textField(
              'topics',
              label: 'Topik Minat',
              hint: 'Pisahkan dengan koma',
            ),
          ]),
          const SizedBox(height: Spacing.md),
          ElevatedButton.icon(
            onPressed: _saving ? null : _submit,
            icon: _saving
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: AppColors.white,
                    ),
                  )
                : const Icon(Icons.check_circle_outline),
            label: Text(_saving ? 'Menyimpan...' : 'Simpan & Selesai'),
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
            return 'Sila isi $label.';
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
        hint: const Text('Pilih'),
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
            labelText: 'Tarikh Lahir',
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
                  _dob == null ? 'Pilih tarikh' : ProfileFormat.date(_dob!),
                  style: TextStyle(
                    fontSize: 16,
                    color: _dob == null
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
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Profil berjaya dilengkapkan.')),
      );
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
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.message)),
        );
      }
    } catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Ralat tidak dijangka. Sila cuba lagi.')),
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
