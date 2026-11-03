import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/payment/payment_webview_screen.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../forms/data/models/form_model.dart';
import '../../forms/presentation/form_question_fields.dart';
import '../application/event_providers.dart';
import '../data/models/event.dart';
import '../data/models/event_registration_form.dart';

/// Pendaftaran member untuk satu borang program (dalam aplikasi).
class RegistrationScreen extends ConsumerStatefulWidget {
  const RegistrationScreen({
    super.key,
    required this.eventId,
    required this.formId,
  });

  final int eventId;
  final int formId;

  @override
  ConsumerState<RegistrationScreen> createState() => _RegistrationScreenState();
}

class _RegistrationScreenState extends ConsumerState<RegistrationScreen> {
  final Map<int, TextEditingController> _controllers = {};
  final Map<int, dynamic> _values = {};
  final Map<int, File> _files = {};

  String? _ticketType;
  String _paymentMethod = 'fpx';
  File? _document;

  final Map<int, int> _addonQuantities = {};
  final Map<int, int?> _addonVariants = {};

  bool _submitting = false;
  String? _error;
  EventRegistrationResult? _completed;

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
      () => TextEditingController(text: _values[questionId] as String? ?? ''),
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

  Future<void> _pickFile(int questionId) async {
    final picked = await FilePicker.pickFiles(type: FileType.any);
    if (picked == null || picked.files.isEmpty || !mounted) return;
    final path = picked.files.first.path;
    if (path == null) return;
    setState(() => _files[questionId] = File(path));
  }

  Future<void> _pickDocument() async {
    final picked = await FilePicker.pickFiles(type: FileType.any);
    if (picked == null || picked.files.isEmpty || !mounted) return;
    final path = picked.files.first.path;
    if (path == null) return;
    setState(() => _document = File(path));
  }

  /// Tier terpilih (fallback ke tier default / pertama bila belum dipilih).
  FormTier? _selectedTier(FormModel form) {
    final tiers = form.tiers;
    if (tiers == null || tiers.isEmpty) return null;
    if (_ticketType != null) {
      for (final tier in tiers) {
        if (tier.label == _ticketType) return tier;
      }
    }
    for (final tier in tiers) {
      if (tier.isDefault) return tier;
    }
    return tiers.first;
  }

  double _amount(FormModel form) {
    final tier = _selectedTier(form);
    if (tier != null) return tier.price ?? 0;
    return form.price ?? 0;
  }

  int _addonQty(FormAddon addon) {
    final id = addon.id;
    if (id == null) return 0;
    final stored = _addonQuantities[id];
    if (stored != null) return stored;
    // Mandatory add-ons default to one so they cannot be skipped.
    return addon.isRequired ? 1 : 0;
  }

  int? _addonVariantId(FormAddon addon) {
    final id = addon.id;
    if (id == null) return null;
    final stored = _addonVariants[id];
    if (stored != null) return stored;
    if (addon.isRequired && addon.hasVariants && addon.variants.isNotEmpty) {
      return addon.variants.first.id;
    }
    return null;
  }

  void _toggleAddon(FormAddon addon, bool selected) {
    final id = addon.id;
    if (id == null) return;
    if (addon.isRequired && !selected) return;
    setState(() {
      if (!selected) {
        _addonQuantities[id] = 0;
        return;
      }
      if (addon.hasVariants && _addonVariants[id] == null) {
        _addonVariants[id] = addon.variants.isNotEmpty ? addon.variants.first.id : null;
      }
      if ((_addonQuantities[id] ?? 0) < 1) {
        _addonQuantities[id] = 1;
      }
    });
  }

  void _setAddonVariant(FormAddon addon, int? variantId) {
    final id = addon.id;
    if (id == null) return;
    setState(() => _addonVariants[id] = variantId);
  }

  void _changeAddonQty(FormAddon addon, int delta) {
    final id = addon.id;
    if (id == null) return;
    setState(() {
      if (addon.hasVariants &&
          _addonVariants[id] == null &&
          addon.variants.isNotEmpty) {
        _addonVariants[id] = addon.variants.first.id;
      }
      final current = _addonQuantities[id] ?? _addonQty(addon);
      final min = addon.isRequired ? 1 : 0;
      _addonQuantities[id] = (current + delta).clamp(min, 100);
    });
  }

  double _addonsTotal(FormModel form) {
    var total = 0.0;
    for (final addon in form.addons) {
      final qty = _addonQty(addon);
      if (qty <= 0) continue;
      total += addon.priceFor(_addonVariantId(addon)) * qty;
    }
    return total;
  }

  double _baseAmount(FormModel form) =>
      form.paymentRequired ? _amount(form) : 0;

  double _totalAmount(FormModel form) => _baseAmount(form) + _addonsTotal(form);

  List<Map<String, dynamic>> _selectedAddons(FormModel form) {
    final rows = <Map<String, dynamic>>[];
    for (final addon in form.addons) {
      final qty = _addonQty(addon);
      if (qty <= 0 || addon.id == null) continue;
      rows.add({
        'form_item_id': addon.id,
        if (addon.hasVariants) 'variant_id': _addonVariantId(addon),
        'quantity': qty,
      });
    }
    return rows;
  }

  Future<void> _submit(EventRegistrationData data) async {
    final form = data.form;
    if (form == null) return;

    final answers = <String, dynamic>{};

    for (final question in form.questions) {
      if (question.isFile) {
        if (question.required && _files[question.id] == null) {
          setState(() => _error = context.l10n.eventCompleteRequired);
          return;
        }
        continue;
      }
      final value =
          question.type == 'text' ||
                  question.type == 'textarea' ||
                  question.type == 'email' ||
                  question.type == 'number'
              ? _controllerFor(question.id!).text
              : _values[question.id];

      final isEmpty =
          value is String
              ? value.trim().isEmpty
              : value == null || (value is List && value.isEmpty);

      if (question.required && isEmpty) {
        setState(() => _error = context.l10n.eventCompleteRequired);
        return;
      }

      final stringValue = value is String ? value.trim() : value;
      if (stringValue != null && stringValue != '') {
        answers[question.id.toString()] = stringValue;
      }
    }

    final tier = _selectedTier(form);
    final needsDocument =
        (form.tiers?.isNotEmpty ?? false) && (tier?.requiresDocument ?? false);
    if (needsDocument && _document == null) {
      setState(() => _error = context.l10n.eventUploadSupportDoc);
      return;
    }

    // Every selected variant item must have a variant chosen.
    for (final addon in form.addons) {
      if (_addonQty(addon) > 0 &&
          addon.hasVariants &&
          _addonVariantId(addon) == null) {
        setState(() => _error = context.l10n.eventAddonSelectOption);
        return;
      }
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final result = await ref
          .read(eventRepositoryProvider)
          .submitRegistration(
            eventId: widget.eventId,
            formId: widget.formId,
            answers: answers,
            files: _files,
            ticketType: tier?.label,
            paymentMethod: _paymentMethod,
            document: _document,
            items: _selectedAddons(form),
          );
      if (!mounted) return;
      ref.invalidate(eventDetailProvider(widget.eventId));
      ref.invalidate(myRegistrationsProvider);
      if (result.isRedirect) {
        final paid = await Navigator.of(context).push<bool>(
          MaterialPageRoute(
            builder:
                (_) => PaymentWebviewScreen(paymentUrl: result.paymentUrl!),
          ),
        );
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              paid == true
                  ? context.l10n.eventRegistrationSuccess
                  : context.l10n.eventRegistrationPaymentPending,
            ),
          ),
        );
        setState(() {
          _submitting = false;
          _completed = result;
        });
      } else {
        setState(() {
          _submitting = false;
          _completed = result;
        });
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = e.message;
      });
      ref.invalidate(eventDetailProvider(widget.eventId));
      ref.invalidate(myRegistrationsProvider);
    }
  }

  @override
  Widget build(BuildContext context) {
    final completed = _completed;
    if (completed != null) {
      return _SuccessScreen(eventId: widget.eventId, result: completed);
    }

    final dataAsync = ref.watch(
      eventRegistrationFormProvider((
        eventId: widget.eventId,
        formId: widget.formId,
      )),
    );

    return Scaffold(
      appBar: AppBar(
        leading: AppBackButton(fallback: '/events/${widget.eventId}'),
        title: Text(context.l10n.eventRegisterProgram),
      ),
      body: dataAsync.when(
        data: (data) => _buildData(data),
        loading: () => const _RegistrationSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : context.l10n.unexpectedError,
              onRetry:
                  () => ref.invalidate(
                    eventRegistrationFormProvider((
                      eventId: widget.eventId,
                      formId: widget.formId,
                    )),
                  ),
            ),
      ),
    );
  }

  Widget _buildData(EventRegistrationData data) {
    final form = data.form;
    if (form == null) {
      return Center(child: Text(context.l10n.eventFormNotFound));
    }

    if (data.myRegistration != null) {
      return _AlreadyRegisteredView(
        eventId: widget.eventId,
        summary: data.myRegistration!,
      );
    }

    final needsPayment = _totalAmount(form) > 0;
    final showMethods = needsPayment && data.paymentGateway?.key == 'bayarcash';
    final tier = _selectedTier(form);
    final needsDocument =
        (form.tiers?.isNotEmpty ?? false) && (tier?.requiresDocument ?? false);

    final event = data.event;

    return RefreshIndicator(
      onRefresh:
          () async => ref.invalidate(
            eventRegistrationFormProvider((
              eventId: widget.eventId,
              formId: widget.formId,
            )),
          ),
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          if (form.headerImageUrl != null && form.headerImageUrl!.isNotEmpty)
            AppImage(
              form.headerImageUrl,
              height: 180,
              width: double.infinity,
              borderRadius: BorderRadius.zero,
            ),
          Padding(
            padding: const EdgeInsets.all(Spacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (form.organizationName != null) ...[
                  Text(
                    form.organizationName!,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.movementGreen,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: Spacing.xs),
                ],
                Text(
                  event?.title ?? form.title ?? context.l10n.eventFallbackShort,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                if (event != null) ...[
                  if (event.startFormatted != null &&
                      event.startFormatted!.isNotEmpty) ...[
                    const SizedBox(height: Spacing.sm),
                    _MetaRow(
                      icon: Icons.calendar_today_outlined,
                      text: event.startFormatted!,
                    ),
                  ],
                  if (event.locationOrLink != null &&
                      event.locationOrLink!.isNotEmpty) ...[
                    _MetaRow(
                      icon: Icons.location_on_outlined,
                      text: event.locationOrLink!,
                    ),
                  ],
                ],
                if (form.description != null &&
                    form.description!.isNotEmpty) ...[
                  const SizedBox(height: Spacing.md),
                  Text(
                    form.description!,
                    style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
                if (form.paymentRequired && form.tiers?.isNotEmpty == true) ...[
                  const SizedBox(height: Spacing.lg),
                  _TierSelector(
                    tiers: form.tiers!,
                    selectedLabel: _ticketType,
                    onSelected: (label) => setState(() => _ticketType = label),
                  ),
                ] else if (_baseAmount(form) > 0) ...[
                  const SizedBox(height: Spacing.lg),
                  Text(
                    context.l10n.eventFee(Formatters.currency(_baseAmount(form))),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: AppColors.movementGreen,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
                if (form.addons.isNotEmpty) ...[
                  const SizedBox(height: Spacing.lg),
                  _AddonSelector(
                    addons: form.addons,
                    quantityFor: _addonQty,
                    variantFor: _addonVariantId,
                    onToggle: _toggleAddon,
                    onVariantChanged: _setAddonVariant,
                    onChangeQuantity: _changeAddonQty,
                  ),
                  if (_totalAmount(form) > 0) ...[
                    const SizedBox(height: Spacing.sm),
                    Text(
                      context.l10n.eventTotalPayable(
                        Formatters.currency(_totalAmount(form)),
                      ),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.movementGreen,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ],
                if (showMethods) ...[
                  const SizedBox(height: Spacing.lg),
                  Text(
                    context.l10n.eventPaymentMethod,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: Spacing.xs),
                  RadioListTile<String>(
                    value: 'fpx',
                    groupValue: _paymentMethod,
                    onChanged: (value) {
                      if (value != null) {
                        setState(() => _paymentMethod = value);
                      }
                    },
                    title: Text(context.l10n.eventFpx),
                    dense: true,
                    controlAffinity: ListTileControlAffinity.leading,
                  ),
                  RadioListTile<String>(
                    value: 'duitnow_qr',
                    groupValue: _paymentMethod,
                    onChanged: (value) {
                      if (value != null) {
                        setState(() => _paymentMethod = value);
                      }
                    },
                    title: Text(context.l10n.eventDuitNow),
                    dense: true,
                    controlAffinity: ListTileControlAffinity.leading,
                  ),
                ],
                if (needsDocument) ...[
                  const SizedBox(height: Spacing.lg),
                  _DocumentUpload(file: _document, onPick: _pickDocument),
                ],
                const SizedBox(height: Spacing.lg),
                FormQuestionFields(
                  questions: form.questions,
                  values: _values,
                  files: _files,
                  controllerFor: _controllerFor,
                  onChanged: _setValue,
                  onToggleCheckbox: _toggleCheckbox,
                  onPickDate: _pickDate,
                  onPickFile: _pickFile,
                ),
                if (_error != null) ...[
                  Text(_error!, style: const TextStyle(color: AppColors.error)),
                  const SizedBox(height: Spacing.md),
                ],
                FilledButton.icon(
                  onPressed: _submitting ? null : () => _submit(data),
                  icon:
                      _submitting
                          ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: AppColors.white,
                            ),
                          )
                          : const Icon(Icons.assignment_outlined),
                  label: Text(
                    _submitting
                        ? context.l10n.submitting
                        : context.l10n.eventSubmitRegistration,
                  ),
                ),
                if (form.terms != null && form.terms!.isNotEmpty) ...[
                  const SizedBox(height: Spacing.md),
                  Text(
                    context.l10n.eventTerms(form.terms ?? ''),
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
                const SizedBox(height: Spacing.xl),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MetaRow extends StatelessWidget {
  const _MetaRow({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: Spacing.sm),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: AppColors.textSecondary),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: Theme.of(
                context,
              ).textTheme.bodyLarge?.copyWith(color: AppColors.textSecondary),
            ),
          ),
        ],
      ),
    );
  }
}

class _TierSelector extends StatelessWidget {
  const _TierSelector({
    required this.tiers,
    required this.selectedLabel,
    required this.onSelected,
  });

  final List<FormTier> tiers;
  final String? selectedLabel;
  final ValueChanged<String> onSelected;

  String get _groupValue {
    if (selectedLabel != null) {
      for (final tier in tiers) {
        if (tier.label == selectedLabel) return tier.label!;
      }
    }
    for (final tier in tiers) {
      if (tier.isDefault) return tier.label!;
    }
    return tiers.first.label ?? '';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(context.l10n.eventFeeCategory, style: theme.textTheme.titleMedium),
        const SizedBox(height: Spacing.xs),
        for (final tier in tiers)
          RadioListTile<String>(
            value: tier.label ?? '',
            groupValue: _groupValue,
            onChanged: (value) {
              if (value != null) onSelected(value);
            },
            title: Text(
              '${tier.label ?? ''} — ${Formatters.currency(tier.price)}',
            ),
            subtitle:
                tier.description != null && tier.description!.isNotEmpty
                    ? Text(tier.description!)
                    : null,
            dense: true,
            controlAffinity: ListTileControlAffinity.leading,
          ),
      ],
    );
  }
}

class _AddonSelector extends StatelessWidget {
  const _AddonSelector({
    required this.addons,
    required this.quantityFor,
    required this.variantFor,
    required this.onToggle,
    required this.onVariantChanged,
    required this.onChangeQuantity,
  });

  final List<FormAddon> addons;
  final int Function(FormAddon) quantityFor;
  final int? Function(FormAddon) variantFor;
  final void Function(FormAddon, bool) onToggle;
  final void Function(FormAddon, int?) onVariantChanged;
  final void Function(FormAddon, int) onChangeQuantity;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final hasRequired = addons.any((addon) => addon.isRequired);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(Spacing.md),
      decoration: BoxDecoration(
        color: AppColors.movementOffWhite,
        borderRadius: AppRadius.md,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            hasRequired
                ? context.l10n.eventAddonsTitleRequired
                : context.l10n.eventAddonsTitle,
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: Spacing.xs),
          Text(
            hasRequired
                ? context.l10n.eventAddonsHintRequired
                : context.l10n.eventAddonsHint,
            style: theme.textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: Spacing.sm),
          for (final addon in addons)
            Padding(
              padding: const EdgeInsets.only(bottom: Spacing.sm),
              child: _AddonTile(
                addon: addon,
                quantity: quantityFor(addon),
                variantId: variantFor(addon),
                onToggle: (selected) => onToggle(addon, selected),
                onVariantChanged: (value) => onVariantChanged(addon, value),
                onChangeQuantity: (delta) => onChangeQuantity(addon, delta),
              ),
            ),
        ],
      ),
    );
  }
}

class _AddonTile extends StatelessWidget {
  const _AddonTile({
    required this.addon,
    required this.quantity,
    required this.variantId,
    required this.onToggle,
    required this.onVariantChanged,
    required this.onChangeQuantity,
  });

  final FormAddon addon;
  final int quantity;
  final int? variantId;
  final ValueChanged<bool> onToggle;
  final ValueChanged<int?> onVariantChanged;
  final ValueChanged<int> onChangeQuantity;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final selected = quantity > 0;
    final unitPrice = addon.priceFor(variantId);

    return Container(
      padding: const EdgeInsets.all(Spacing.sm),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: AppRadius.sm,
        border: Border.all(color: AppColors.divider),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (addon.imageUrl != null && addon.imageUrl!.isNotEmpty) ...[
            AppImage(
              addon.imageUrl!,
              height: 140,
              width: double.infinity,
              borderRadius: AppRadius.sm,
            ),
            const SizedBox(height: Spacing.sm),
          ],
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Checkbox(
                value: selected,
                onChanged:
                    addon.isRequired
                        ? null
                        : (value) => onToggle(value ?? false),
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            addon.name ?? '',
                            style: theme.textTheme.titleSmall,
                          ),
                        ),
                        if (addon.isRequired) ...[
                          const SizedBox(width: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 6,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.error.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              context.l10n.eventAddonRequired,
                              style: theme.textTheme.labelSmall?.copyWith(
                                color: AppColors.error,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    if (addon.description != null &&
                        addon.description!.isNotEmpty)
                      Text(
                        addon.description!,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: Spacing.sm),
              Text(
                Formatters.currency(addon.price ?? 0),
                style: theme.textTheme.titleSmall?.copyWith(
                  color: AppColors.movementGreen,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          if (selected)
            Padding(
              padding: const EdgeInsets.only(left: Spacing.sm, top: Spacing.xs),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (addon.hasVariants) ...[
                    Text(
                      context.l10n.eventAddonSelectOption,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                    DropdownButton<int>(
                      isExpanded: true,
                      value: variantId,
                      hint: Text(context.l10n.eventAddonSelectOption),
                      items: [
                        for (final variant in addon.variants)
                          DropdownMenuItem<int>(
                            value: variant.id,
                            enabled: variant.stock == null || variant.stock! > 0,
                            child: Text(
                              '${variant.name ?? ''} — '
                              '${Formatters.currency(variant.price ?? addon.price ?? 0)}',
                            ),
                          ),
                      ],
                      onChanged: onVariantChanged,
                    ),
                    const SizedBox(height: Spacing.xs),
                  ],
                  Row(
                    children: [
                      Text(
                        context.l10n.eventAddonQuantity,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                      const SizedBox(width: Spacing.sm),
                      IconButton(
                        visualDensity: VisualDensity.compact,
                        onPressed: () => onChangeQuantity(-1),
                        icon: const Icon(Icons.remove_circle_outline),
                      ),
                      Text('$quantity', style: theme.textTheme.titleMedium),
                      IconButton(
                        visualDensity: VisualDensity.compact,
                        onPressed: () => onChangeQuantity(1),
                        icon: const Icon(Icons.add_circle_outline),
                      ),
                      const Spacer(),
                      Text(
                        '${context.l10n.eventAddonTotal}: '
                        '${Formatters.currency(unitPrice * quantity)}',
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: AppColors.movementGreen,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _DocumentUpload extends StatelessWidget {
  const _DocumentUpload({required this.file, required this.onPick});

  final File? file;
  final VoidCallback onPick;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final fileName = file == null ? null : _fileNameOf(file!.path);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(Spacing.md),
      decoration: BoxDecoration(
        color: AppColors.movementOffWhite,
        borderRadius: AppRadius.md,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.eventUploadSupportDocTitle,
            style: theme.textTheme.titleSmall,
          ),
          const SizedBox(height: Spacing.xs),
          Text(
            context.l10n.eventUploadSupportDocHint,
            style: theme.textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: Spacing.sm),
          OutlinedButton.icon(
            onPressed: onPick,
            icon: const Icon(Icons.upload_file),
            label: Text(context.l10n.eventChooseFile),
          ),
          if (fileName != null) ...[
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
                    fileName,
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
      ),
    );
  }
}

/// Ahli sudah mendaftar program ini (mis. melalui web / sebelum ini).
class _AlreadyRegisteredView extends StatelessWidget {
  const _AlreadyRegisteredView({required this.eventId, required this.summary});

  final int eventId;
  final EventRegistrationSummary summary;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.xl),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(Spacing.xxl),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: AppRadius.hero,
            border: Border.all(color: AppColors.divider),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.event_available_outlined,
                size: 48,
                color: AppColors.movementGreen,
              ),
              const SizedBox(height: Spacing.lg),
              Text(
                context.l10n.eventAlreadyRegisteredTitle,
                style: theme.textTheme.titleLarge,
              ),
              const SizedBox(height: Spacing.sm),
              if (summary.registrationNo != null)
                Text(
                  context.l10n.eventRegistrationNo(
                    summary.registrationNo ?? '-',
                  ),
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium,
                ),
              if (summary.statusLabel != null)
                Text(
                  context.l10n.eventStatus(summary.statusLabel ?? '-'),
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              const SizedBox(height: Spacing.xl),
              FilledButton.icon(
                onPressed: () => context.go('/events/my-registrations'),
                icon: const Icon(Icons.list_alt_outlined),
                label: Text(context.l10n.eventViewMyRegistrations),
              ),
              const SizedBox(height: Spacing.sm),
              TextButton(
                onPressed: () => _backToEvent(context),
                child: Text(context.l10n.eventBackToProgram),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _backToEvent(BuildContext context) {
    if (context.canPop()) {
      context.pop();
    } else {
      context.go('/events/$eventId');
    }
  }
}

class _SuccessScreen extends StatelessWidget {
  const _SuccessScreen({required this.eventId, required this.result});

  final int eventId;
  final EventRegistrationResult result;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final message = result.message ?? context.l10n.eventRegistrationReceived;

    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.eventRegisterProgram)),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(Spacing.xl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.check_circle,
                size: 64,
                color: AppColors.success,
              ),
              const SizedBox(height: Spacing.lg),
              Text(
                context.l10n.eventRegistrationSuccessfulTitle,
                style: theme.textTheme.headlineSmall,
              ),
              const SizedBox(height: Spacing.sm),
              if (result.registration?.registrationNo != null) ...[
                Text(
                  context.l10n.eventRegistrationNo(
                    result.registration!.registrationNo ?? '-',
                  ),
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: AppColors.movementGreen,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: Spacing.sm),
              ],
              Text(
                message,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyLarge?.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: Spacing.xl),
              FilledButton.icon(
                onPressed: () => context.go('/events/my-registrations'),
                icon: const Icon(Icons.list_alt_outlined),
                label: Text(context.l10n.eventViewMyRegistrations),
              ),
              const SizedBox(height: Spacing.sm),
              OutlinedButton(
                onPressed: () {
                  if (context.canPop()) {
                    context.pop();
                  } else {
                    context.go('/events/$eventId');
                  }
                },
                child: Text(context.l10n.eventBackToProgram),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RegistrationSkeleton extends StatelessWidget {
  const _RegistrationSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      children: const [
        SkeletonBox(height: 180, radius: 0),
        Padding(
          padding: EdgeInsets.all(Spacing.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SkeletonBox(height: 20, width: 200),
              SizedBox(height: Spacing.sm),
              SkeletonBox(height: 28, width: 280),
              SizedBox(height: Spacing.md),
              SkeletonBox(height: 18),
              SizedBox(height: Spacing.sm),
              SkeletonBox(height: 18),
              SizedBox(height: Spacing.xl),
              SkeletonBox(height: 120, radius: 12),
              SizedBox(height: Spacing.md),
              SkeletonBox(height: 56),
              SizedBox(height: Spacing.md),
              SkeletonBox(height: 56),
            ],
          ),
        ),
      ],
    );
  }
}

const List<String> _months = [
  'Jan',
  'Feb',
  'Mac',
  'Apr',
  'Mei',
  'Jun',
  'Jul',
  'Ogo',
  'Sep',
  'Okt',
  'Nov',
  'Dis',
];

String _formatDate(DateTime value) =>
    '${value.day} ${_months[value.month - 1]} ${value.year}';

String _fileNameOf(String path) {
  final normalized = path.replaceAll('\\', '/');
  final index = normalized.lastIndexOf('/');
  return index == -1 ? normalized : normalized.substring(index + 1);
}
