import 'dart:io';

import 'package:flutter/material.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../data/models/form_model.dart';

/// Renders the list of [FormQuestion] widgets for a form. Shared between the
/// public form screen and the member event-registration screen so question
/// behaviour (text/select/radio/checkbox/date/file) stays identical.
///
/// The screen owns all state; this widget only forwards callbacks. File
/// questions call [onPickFile] and display the currently chosen [files]
/// filename below the pick button.
class FormQuestionFields extends StatelessWidget {
  const FormQuestionFields({
    super.key,
    required this.questions,
    required this.values,
    required this.files,
    required this.controllerFor,
    required this.onChanged,
    required this.onToggleCheckbox,
    required this.onPickDate,
    required this.onPickFile,
  });

  final List<FormQuestion> questions;
  final Map<int, dynamic> values;
  final Map<int, File> files;
  final TextEditingController Function(int questionId) controllerFor;
  final void Function(int questionId, dynamic value) onChanged;
  final void Function(int questionId, String option, bool checked)
  onToggleCheckbox;
  final void Function(int questionId) onPickDate;
  final Future<void> Function(int questionId) onPickFile;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final question in questions)
          _QuestionField(
            question: question,
            value: values[question.id],
            file: files[question.id],
            controller: controllerFor,
            onChanged: onChanged,
            onToggleCheckbox: onToggleCheckbox,
            onPickDate: onPickDate,
            onPickFile: onPickFile,
          ),
      ],
    );
  }
}

class _QuestionField extends StatelessWidget {
  const _QuestionField({
    required this.question,
    required this.value,
    required this.file,
    required this.controller,
    required this.onChanged,
    required this.onToggleCheckbox,
    required this.onPickDate,
    required this.onPickFile,
  });

  final FormQuestion question;
  final dynamic value;
  final File? file;
  final TextEditingController Function(int questionId) controller;
  final void Function(int questionId, dynamic value) onChanged;
  final void Function(int questionId, String option, bool checked)
  onToggleCheckbox;
  final void Function(int questionId) onPickDate;
  final Future<void> Function(int questionId) onPickFile;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final id = question.id;
    final label =
        question.required
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
                color:
                    value is String && (value as String).isNotEmpty
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
        final selected = List<String>.of((value as List<String>?) ?? const []);
        field = Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: theme.textTheme.titleSmall),
            const SizedBox(height: Spacing.xs),
            for (final option in question.options)
              CheckboxListTile(
                value: selected.contains(option),
                onChanged:
                    (checked) =>
                        onToggleCheckbox(id!, option, checked ?? false),
                title: Text(option),
                dense: true,
                controlAffinity: ListTileControlAffinity.leading,
              ),
          ],
        );
      case 'file':
        field = Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: theme.textTheme.titleSmall),
            const SizedBox(height: Spacing.xs),
            OutlinedButton.icon(
              onPressed: () => onPickFile(id!),
              icon: const Icon(Icons.upload_file),
              label: const Text('Pilih Fail'),
            ),
            if (file != null) ...[
              const SizedBox(height: Spacing.xs),
              Row(
                children: [
                  const Icon(
                    Icons.insert_drive_file_outlined,
                    size: 18,
                    color: AppColors.textSecondary,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      _fileName(file!.path),
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
            ],
          ],
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

String _fileName(String path) {
  final normalized = path.replaceAll('\\', '/');
  final index = normalized.lastIndexOf('/');
  return index == -1 ? normalized : normalized.substring(index + 1);
}
