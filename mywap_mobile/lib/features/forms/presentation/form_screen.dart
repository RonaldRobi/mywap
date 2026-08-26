import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/form_providers.dart';
import '../data/form_repository.dart';
import '../data/models/form_model.dart';

class FormScreen extends ConsumerStatefulWidget {
  const FormScreen({super.key, required this.token});

  final String token;

  @override
  ConsumerState<FormScreen> createState() => _FormScreenState();
}

class _FormScreenState extends ConsumerState<FormScreen> {
  final Map<int, TextEditingController> _controllers = {};
  final Map<int, dynamic> _values = {};

  bool _submitting = false;
  String? _error;
  FormSubmitResult? _result;

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  TextEditingController _controllerFor(int questionId) {
    return _controllers.putIfAbsent(
      questionId,
      () => TextEditingController(
        text: _values[questionId] as String? ?? '',
      ),
    );
  }

  void _setValue(int questionId, dynamic value) {
    setState(() => _values[questionId] = value);
  }

  void _toggleCheckbox(int questionId, String option, bool checked) {
    final current = List<String>.of(
      (_values[questionId] as List<String>?) ?? const [],
    );
    if (checked) {
      if (!current.contains(option)) current.add(option);
    } else {
      current.remove(option);
    }
    setState(() => _values[questionId] = current);
  }

  Future<void> _pickDate(int questionId) async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: now,
      firstDate: DateTime(now.year - 5),
      lastDate: DateTime(now.year + 5),
    );
    if (date == null) return;
    _setValue(questionId, _formatDate(date));
  }

  Future<void> _submit(FormModel form) async {
    final answers = <String, dynamic>{};

    for (final question in form.questions) {
      if (question.isFile) continue;
      final value = question.type == 'text' ||
              question.type == 'textarea' ||
              question.type == 'email' ||
              question.type == 'number'
          ? _controllerFor(question.id!).text
          : _values[question.id];

      final isEmpty = value is String
          ? value.trim().isEmpty
          : value == null || (value is List && value.isEmpty);

      if (question.required && isEmpty) {
        setState(() => _error = 'Sila lengkapkan semua ruangan yang wajib.');
        return;
      }

      final stringValue = value is String ? value.trim() : value;
      if (stringValue != null && stringValue != '') {
        answers[question.id.toString()] = stringValue;
      }
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final result = await ref
          .read(formRepositoryProvider)
          .submit(widget.token, answers);
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _result = result;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = e.message;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final formAsync = ref.watch(formDetailProvider(widget.token));

    if (_result != null) {
      return _SuccessScreen(form: formAsync.valueOrNull, result: _result!);
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Borang')),
      body: formAsync.when(
        data: (form) => _FormBody(
          form: form,
          values: _values,
          submitting: _submitting,
          error: _error,
          onChanged: _setValue,
          onToggleCheckbox: _toggleCheckbox,
          onPickDate: _pickDate,
          controllerFor: _controllerFor,
          onSubmit: () => _submit(form),
        ),
        loading: () => const _FormSkeleton(),
        error: (error, _) => ErrorRetry(
          message:
              error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(formDetailProvider(widget.token)),
        ),
      ),
    );
  }
}

class _FormBody extends StatelessWidget {
  const _FormBody({
    required this.form,
    required this.values,
    required this.submitting,
    required this.error,
    required this.onChanged,
    required this.onToggleCheckbox,
    required this.onPickDate,
    required this.controllerFor,
    required this.onSubmit,
  });

  final FormModel form;
  final Map<int, dynamic> values;
  final bool submitting;
  final String? error;
  final void Function(int questionId, dynamic value) onChanged;
  final void Function(int questionId, String option, bool checked)
      onToggleCheckbox;
  final void Function(int questionId) onPickDate;
  final TextEditingController Function(int questionId) controllerFor;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        if (form.headerImageUrl != null && form.headerImageUrl!.isNotEmpty)
          AppImage(form.headerImageUrl, height: 160, width: double.infinity),
        if (form.organizationName != null) ...[
          const SizedBox(height: Spacing.sm),
          Text(
            form.organizationName!,
            style: theme.textTheme.bodySmall?.copyWith(
              color: AppColors.movementGreen,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
        const SizedBox(height: Spacing.xs),
        Text(form.title ?? 'Borang', style: theme.textTheme.headlineSmall),
        if (form.description != null && form.description!.isNotEmpty) ...[
          const SizedBox(height: Spacing.sm),
          Text(
            form.description!,
            style: theme.textTheme.bodyLarge?.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
        ],
        if (form.price != null && form.price! > 0) ...[
          const SizedBox(height: Spacing.sm),
          Text(
            'Yuran: ${Formatters.currency(form.price)}',
            style: theme.textTheme.titleSmall?.copyWith(
              color: AppColors.movementGreen,
            ),
          ),
        ],
        const SizedBox(height: Spacing.lg),
        for (final question in form.questions)
          _QuestionField(
            question: question,
            value: values[question.id],
            controller: controllerFor,
            onChanged: onChanged,
            onToggleCheckbox: onToggleCheckbox,
            onPickDate: onPickDate,
          ),
        if (error != null) ...[
          const SizedBox(height: Spacing.md),
          Text(error!, style: const TextStyle(color: AppColors.error)),
        ],
        const SizedBox(height: Spacing.lg),
        FilledButton.icon(
          onPressed: submitting ? null : onSubmit,
          icon: submitting
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.white,
                  ),
                )
              : const Icon(Icons.send),
          label: const Text('Hantar Borang'),
        ),
        if (form.terms != null && form.terms!.isNotEmpty) ...[
          const SizedBox(height: Spacing.md),
          Text(
            'Terma: ${form.terms}',
            style: theme.textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
        ],
        const SizedBox(height: Spacing.xl),
      ],
    );
  }
}

class _QuestionField extends StatelessWidget {
  const _QuestionField({
    required this.question,
    required this.value,
    required this.controller,
    required this.onChanged,
    required this.onToggleCheckbox,
    required this.onPickDate,
  });

  final FormQuestion question;
  final dynamic value;
  final TextEditingController Function(int questionId) controller;
  final void Function(int questionId, dynamic value) onChanged;
  final void Function(int questionId, String option, bool checked)
      onToggleCheckbox;
  final void Function(int questionId) onPickDate;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final id = question.id;
    final label = question.required
        ? '${question.label ?? ''} *'
        : (question.label ?? '');
    final type = question.type ?? 'text';

    Widget field;
    switch (type) {
      case 'textarea':
        field = TextFormField(
          controller: controller(id!),
          maxLines: 4,
          onChanged: (value) => onChanged(id, value),
          decoration: InputDecoration(labelText: label),
        );
      case 'email':
        field = TextFormField(
          controller: controller(id!),
          keyboardType: TextInputType.emailAddress,
          onChanged: (value) => onChanged(id, value),
          decoration: InputDecoration(
            labelText: label,
            hintText: question.placeholder,
          ),
        );
      case 'number':
        field = TextFormField(
          controller: controller(id!),
          keyboardType: TextInputType.number,
          onChanged: (value) => onChanged(id, value),
          decoration: InputDecoration(
            labelText: label,
            hintText: question.placeholder,
          ),
        );
      case 'date':
        field = InkWell(
          onTap: () => onPickDate(id!),
          borderRadius: AppRadius.md,
          child: InputDecorator(
            decoration: InputDecoration(labelText: label),
            child: Text(
              value is String && (value as String).isNotEmpty
                  ? value as String
                  : 'Pilih tarikh',
              style: theme.textTheme.bodyLarge?.copyWith(
                color: value is String && (value as String).isNotEmpty
                    ? null
                    : AppColors.textSecondary,
              ),
            ),
          ),
        );
      case 'select':
        field = DropdownButtonFormField<String>(
          value: value as String?,
          isExpanded: true,
          decoration: InputDecoration(labelText: label),
          hint: Text(question.placeholder ?? 'Pilih pilihan'),
          items: [
            for (final option in question.options)
              DropdownMenuItem(value: option, child: Text(option)),
          ],
          onChanged: (selected) {
            if (selected != null) onChanged(id!, selected);
          },
        );
      case 'radio':
        field = Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: theme.textTheme.titleSmall),
            const SizedBox(height: Spacing.xs),
            for (final option in question.options)
              RadioListTile<String>(
                value: option,
                groupValue: value as String?,
                onChanged: (selected) {
                  if (selected != null) onChanged(id!, selected);
                },
                title: Text(option),
                dense: true,
                controlAffinity: ListTileControlAffinity.leading,
              ),
          ],
        );
      case 'checkbox':
        final selected =
            List<String>.of((value as List<String>?) ?? const []);
        field = Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: theme.textTheme.titleSmall),
            const SizedBox(height: Spacing.xs),
            for (final option in question.options)
              CheckboxListTile(
                value: selected.contains(option),
                onChanged: (checked) =>
                    onToggleCheckbox(id!, option, checked ?? false),
                title: Text(option),
                dense: true,
                controlAffinity: ListTileControlAffinity.leading,
              ),
          ],
        );
      case 'file':
        field = Container(
          padding: const EdgeInsets.all(Spacing.md),
          decoration: BoxDecoration(
            color: AppColors.movementOffWhite,
            borderRadius: AppRadius.md,
          ),
          child: Row(
            children: [
              const Icon(Icons.upload_file, color: AppColors.movementGreen),
              const SizedBox(width: Spacing.sm),
              Expanded(
                child: Text(
                  'Lampiran fail untuk " ${question.label ?? ''} " perlu dimuat naik melalui pelayar web.',
                  style: theme.textTheme.bodyMedium,
                ),
              ),
            ],
          ),
        );
      default:
        field = TextFormField(
          controller: controller(id!),
          onChanged: (value) => onChanged(id, value),
          decoration: InputDecoration(
            labelText: label,
            hintText: question.placeholder,
          ),
        );
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: Spacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          field,
          if (question.helpText != null && question.helpText!.isNotEmpty) ...[
            const SizedBox(height: Spacing.xs),
            Text(
              question.helpText!,
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _SuccessScreen extends StatelessWidget {
  const _SuccessScreen({required this.form, required this.result});

  final FormModel? form;
  final FormSubmitResult result;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final redirectTo = form?.redirectTo;

    return Scaffold(
      appBar: AppBar(title: const Text('Borang')),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(Spacing.xl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.check_circle, size: 64, color: AppColors.success),
              const SizedBox(height: Spacing.lg),
              Text('Borang Dihantar', style: theme.textTheme.headlineSmall),
              const SizedBox(height: Spacing.sm),
              Text(
                'Terima kasih! Respons anda telah diterima.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyLarge?.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
              if (redirectTo != null && redirectTo.isNotEmpty) ...[
                const SizedBox(height: Spacing.xl),
                OutlinedButton.icon(
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Sila buka pautan melalui pelayar web.'),
                      ),
                    );
                  },
                  icon: const Icon(Icons.open_in_new),
                  label: const Text('Sambung Pendaftaran'),
                ),
              ],
              const SizedBox(height: Spacing.lg),
              TextButton(
                onPressed: () => context.go('/dashboard'),
                child: const Text('Kembali ke Utama'),
              ),
            ],
          ),
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
        SkeletonBox(height: 160, radius: 16),
        SizedBox(height: Spacing.lg),
        SkeletonBox(height: 28, width: 260),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 56),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 56),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 120, radius: 12),
      ],
    );
  }
}

const List<String> _months = [
  'Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun',
  'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis',
];

String _formatDate(DateTime value) =>
    '${value.day} ${_months[value.month - 1]} ${value.year}';
