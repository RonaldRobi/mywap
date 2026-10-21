import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../../shared/widgets/app_back_button.dart';
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

class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});

  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();

  final Map<String, TextEditingController> _fields = {
    'name': TextEditingController(),
    'email': TextEditingController(),
    'phone': TextEditingController(),
    'education_level': TextEditingController(),
    'current_profession': TextEditingController(),
    'industry': TextEditingController(),
    'locality': TextEditingController(),
    'expertise': TextEditingController(),
    'linkedin_url': TextEditingController(),
    'topics': TextEditingController(),
    'address_1': TextEditingController(),
    'address_2': TextEditingController(),
    'postcode': TextEditingController(),
    'city': TextEditingController(),
    'state': TextEditingController(),
    'emergency_contact_name': TextEditingController(),
    'emergency_contact_phone': TextEditingController(),
  };

  DateTime? _dob;
  String? _gender;
  String? _maritalStatus;
  String? _organizationName;
  String? _currentBranchName;
  bool _isPublicInDirectory = true;
  bool _loaded = false;
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
    final profileAsync = ref.watch(profileProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(fallback: '/profile'),
        title: const Text('Edit Profil'),
      ),
      body: profileAsync.when(
        data: (data) {
          _initFrom(data.profileUser);
          return _buildForm();
        },
        loading: () => const _FormSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : 'Ralat tidak dijangka.',
              onRetry: () => ref.invalidate(profileProvider),
            ),
      ),
    );
  }

  void _initFrom(ProfileUser? user) {
    if (_loaded || user == null) return;
    _loaded = true;

    void fill(String key, String? value) {
      if (value != null && value.isNotEmpty) {
        _fields[key]!.text = value;
      }
    }

    fill('name', user.name);
    fill('email', user.email);
    fill('phone', user.phone);
    fill('education_level', user.education_level);
    fill('current_profession', user.current_profession);
    fill('industry', user.industry);
    fill('locality', user.locality);
    fill('expertise', user.expertise);
    fill('linkedin_url', user.linkedin_url);
    fill('topics', user.topics);
    fill('address_1', user.address_1);
    fill('address_2', user.address_2);
    fill('postcode', user.postcode);
    fill('city', user.city);
    fill('state', user.state);
    fill('emergency_contact_name', user.emergency_contact_name);
    fill('emergency_contact_phone', user.emergency_contact_phone);

    _dob = user.dob == null ? null : ProfileFormat.parseDate(user.dob!);
    _gender = user.gender;
    _maritalStatus = user.marital_status;
    _organizationName = user.organization?.name;
    _currentBranchName = user.branch_name;
  }

  Widget _buildForm() {
    final editMetaAsync = ref.watch(profileEditMetaProvider);
    final meta = editMetaAsync.valueOrNull;
    final branches = meta?.branches ?? const <ProfileBranch>[];

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
          _section('Maklumat Asas', [
            _organizationBranchCard(meta: meta, branches: branches),
            _textField('name', label: 'Nama Penuh', required: true),
            _textField('email', label: 'Emel', required: true, isEmail: true),
            _textField(
              'phone',
              label: 'No. Telefon',
              keyboardType: TextInputType.phone,
            ),
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
          _section('Pendidikan & Profesion', [
            _textField('education_level', label: 'Tahap Pendidikan'),
            _textField('current_profession', label: 'Profesion Semasa'),
            _textField('industry', label: 'Industri'),
            _textField('locality', label: 'Lokaliti'),
            _textField('expertise', label: 'Kepakaran'),
            _textField(
              'linkedin_url',
              label: 'LinkedIn URL',
              keyboardType: TextInputType.url,
            ),
            _textField(
              'topics',
              label: 'Topik Minat',
              hint: 'Pisahkan dengan koma',
            ),
          ]),
          _section('Direktori', [
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Papar dalam Direktori Awam'),
              subtitle: const Text('Benarkan orang lain melihat profil anda'),
              value: _isPublicInDirectory,
              onChanged:
                  (value) => setState(() => _isPublicInDirectory = value),
            ),
          ]),
          _section('Alamat', [
            _textField('address_1', label: 'Alamat (Baris 1)'),
            _textField('address_2', label: 'Alamat (Baris 2)'),
            _textField(
              'postcode',
              label: 'Poskod',
              keyboardType: TextInputType.number,
            ),
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
                    : const Icon(Icons.save_outlined),
            label: Text(_saving ? 'Menyimpan...' : 'Simpan Perubahan'),
          ),
        ],
      ),
    );
  }

  Widget _organizationBranchCard({
    required EditMeta? meta,
    required List<ProfileBranch> branches,
  }) {
    final organization =
        meta?.organizationName ?? _organizationName ?? 'Tiada organisasi';
    final currentBranch =
        meta?.currentBranchName ?? _currentBranchName ?? 'Tiada cawangan';
    final pending = meta?.pendingBranchRequest;

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: Spacing.lg),
      padding: const EdgeInsets.all(Spacing.lg),
      decoration: BoxDecoration(
        color: AppColors.softGreenSurface,
        borderRadius: AppRadius.lg,
        border: Border.all(color: AppColors.divider),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(
                Icons.business_outlined,
                color: AppColors.movementGreen,
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Organisasi',
                      style: TextStyle(
                        fontSize: 12,
                        color: AppColors.textTertiary,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      organization,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 16,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const Divider(height: Spacing.xl),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(
                Icons.location_on_outlined,
                color: AppColors.movementGreen,
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Cawangan',
                      style: TextStyle(
                        fontSize: 12,
                        color: AppColors.textTertiary,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      currentBranch,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 16,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: Spacing.sm),
          Align(
            alignment: Alignment.centerRight,
            child: OutlinedButton.icon(
              onPressed:
                  branches.any((branch) => branch.id != meta?.currentBranchId)
                      ? () =>
                          _openBranchChangeSheet(meta: meta, branches: branches)
                      : null,
              icon: const Icon(Icons.swap_horiz, size: AppSizes.iconSm),
              label: const Text('Tukar Cawangan'),
            ),
          ),
          if (pending != null) ...[
            const SizedBox(height: Spacing.sm),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(Spacing.md),
              decoration: BoxDecoration(
                color: AppColors.accentTint,
                borderRadius: AppRadius.md,
                border: Border.all(
                  color: AppColors.accent.withValues(alpha: 0.25),
                ),
              ),
              child: Text(
                'Permohonan tukar ke ${pending.to_branch ?? 'cawangan baharu'} sedang menunggu kelulusan admin.',
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.warning,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _openBranchChangeSheet({
    required EditMeta? meta,
    required List<ProfileBranch> branches,
  }) async {
    final changed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: AppColors.white,
      builder:
          (_) => _BranchChangeSheet(
            branches: branches,
            currentBranchId: meta?.currentBranchId,
            currentBranchName: meta?.currentBranchName ?? _currentBranchName,
            organizationName: meta?.organizationName ?? _organizationName,
            pendingBranchRequest: meta?.pendingBranchRequest,
          ),
    );

    if (changed != true || !mounted) return;

    ref.invalidate(profileProvider);
    ref.invalidate(profileEditMetaProvider);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Permohonan tukar cawangan telah dihantar untuk kelulusan admin.',
        ),
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
    bool isEmail = false,
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
          final trimmed = value?.trim() ?? '';
          if (required && trimmed.isEmpty) return 'Sila isi $label.';
          if (isEmail &&
              trimmed.isNotEmpty &&
              !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(trimmed)) {
            return 'Sila masukkan emel yang sah.';
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
      'name': valueOf('name'),
      'email': valueOf('email'),
      'phone': valueOf('phone'),
      'dob': _dob == null ? null : ProfileFormat.apiDate(_dob!),
      'gender': _gender,
      'marital_status': _maritalStatus,
      'education_level': valueOf('education_level'),
      'current_profession': valueOf('current_profession'),
      'industry': valueOf('industry'),
      'locality': valueOf('locality'),
      'expertise': valueOf('expertise'),
      'linkedin_url': valueOf('linkedin_url'),
      'is_public_in_directory': _isPublicInDirectory,
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
      await ref.read(profileRepositoryProvider).updateProfile(_payload());
      if (!mounted) return;
      ref.invalidate(profileProvider);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Profil berjaya dikemas kini.')),
      );
      Navigator.of(context).pop();
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

class _BranchChangeSheet extends ConsumerStatefulWidget {
  const _BranchChangeSheet({
    required this.branches,
    required this.currentBranchId,
    required this.currentBranchName,
    required this.organizationName,
    required this.pendingBranchRequest,
  });

  final List<ProfileBranch> branches;
  final int? currentBranchId;
  final String? currentBranchName;
  final String? organizationName;
  final PendingBranchRequest? pendingBranchRequest;

  @override
  ConsumerState<_BranchChangeSheet> createState() => _BranchChangeSheetState();
}

class _BranchChangeSheetState extends ConsumerState<_BranchChangeSheet> {
  int? _selectedBranchId;
  bool _saving = false;
  String? _error;

  List<ProfileBranch> get _options =>
      widget.branches
          .where((branch) => branch.id != widget.currentBranchId)
          .toList(growable: false);

  Future<void> _submit() async {
    if (_selectedBranchId == null) {
      setState(() => _error = 'Sila pilih cawangan baharu.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await ref
          .read(profileRepositoryProvider)
          .requestBranchChange(_selectedBranchId!);
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = error.errors?['branch_id']?.first ?? error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = 'Ralat tidak dijangka. Sila cuba lagi.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;

    return Padding(
      padding: EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.lg,
        Spacing.lg,
        Spacing.lg + bottomInset,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: const BoxDecoration(
                  color: AppColors.divider,
                  borderRadius: AppRadius.pill,
                ),
              ),
            ),
            const SizedBox(height: Spacing.lg),
            Text(
              'Tukar Cawangan',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: Spacing.sm),
            const Text(
              'Permohonan ini perlu diluluskan oleh admin sebelum cawangan anda bertukar.',
              style: TextStyle(color: AppColors.textSecondary),
            ),
            const SizedBox(height: Spacing.lg),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(Spacing.md),
              decoration: const BoxDecoration(
                color: AppColors.surfaceMuted,
                borderRadius: AppRadius.md,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Cawangan sekarang',
                    style: TextStyle(
                      fontSize: 12,
                      color: AppColors.textTertiary,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    widget.currentBranchName ?? 'Tiada cawangan',
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  if ((widget.organizationName ?? '').isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      widget.organizationName!,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (widget.pendingBranchRequest != null) ...[
              const SizedBox(height: Spacing.md),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(Spacing.md),
                decoration: BoxDecoration(
                  color: AppColors.accentTint,
                  borderRadius: AppRadius.md,
                  border: Border.all(
                    color: AppColors.accent.withValues(alpha: 0.25),
                  ),
                ),
                child: Text(
                  'Permohonan ke ${widget.pendingBranchRequest?.to_branch ?? 'cawangan baharu'} masih menunggu. Menghantar permohonan baharu akan menggantikannya.',
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.warning,
                  ),
                ),
              ),
            ],
            const SizedBox(height: Spacing.lg),
            DropdownButtonFormField<int>(
              value: _selectedBranchId,
              decoration: const InputDecoration(labelText: 'Cawangan baharu'),
              hint: const Text('Pilih cawangan'),
              items: [
                for (final branch in _options)
                  DropdownMenuItem(
                    value: branch.id,
                    child: Text(branch.name ?? 'Cawangan ${branch.id ?? ''}'),
                  ),
              ],
              onChanged:
                  (value) => setState(() {
                    _selectedBranchId = value;
                    _error = null;
                  }),
            ),
            if (_options.isEmpty) ...[
              const SizedBox(height: Spacing.sm),
              const Text(
                'Tiada cawangan lain tersedia untuk organisasi anda.',
                style: TextStyle(color: AppColors.textSecondary),
              ),
            ],
            if (_error != null) ...[
              const SizedBox(height: Spacing.sm),
              Text(_error!, style: const TextStyle(color: AppColors.error)),
            ],
            const SizedBox(height: Spacing.md),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(Spacing.md),
              decoration: const BoxDecoration(
                color: AppColors.softGreenSurface,
                borderRadius: AppRadius.md,
              ),
              child: const Text(
                'Permohonan akan dihantar kepada admin untuk kelulusan. Cawangan hanya bertukar selepas diluluskan.',
                style: TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
              ),
            ),
            const SizedBox(height: Spacing.xl),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed:
                        _saving
                            ? null
                            : () => Navigator.of(context).pop(false),
                    child: const Text('Batal'),
                  ),
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: ElevatedButton(
                    onPressed:
                        _saving || _options.isEmpty ? null : _submit,
                    child:
                        _saving
                            ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: AppColors.white,
                              ),
                            )
                            : const Text('Hantar Permohonan'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _FormSkeleton extends StatelessWidget {
  const _FormSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: const [
        SkeletonBox(height: 20, width: 160),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 56),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 56),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 56),
        SizedBox(height: Spacing.xl),
        SkeletonBox(height: 20, width: 200),
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
