import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../ic/malaysian_ic.dart';
import '../l10n/l10n.dart';
import '../theme/app_colors.dart';
import '../theme/app_theme.dart';

const _monthsMs = [
  'Januari',
  'Februari',
  'Mac',
  'April',
  'Mei',
  'Jun',
  'Julai',
  'Ogos',
  'September',
  'Oktober',
  'November',
  'Disember',
];

const _monthsEn = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
];

class DobPicker extends StatelessWidget {
  const DobPicker({
    super.key,
    required this.label,
    required this.value,
    required this.onChanged,
    this.errorText,
    this.inferredFromIc = false,
    this.labelAbove = false,
  });

  final String label;
  final DateTime? value;
  final ValueChanged<DateTime> onChanged;
  final String? errorText;
  final bool inferredFromIc;
  final bool labelAbove;

  Future<void> _open(BuildContext context) async {
    final picked = await showDobSheet(context, initial: value);
    if (picked != null) onChanged(picked);
  }

  @override
  Widget build(BuildContext context) {
    final field = InkWell(
      onTap: () => _open(context),
      borderRadius: AppRadius.md,
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: labelAbove ? null : label,
          errorText: errorText,
          suffixIcon: const Icon(
            Icons.calendar_month_outlined,
            color: AppColors.movementGreen,
          ),
        ),
        child: Align(
          alignment: Alignment.centerLeft,
          child: Text(
            value == null ? 'DD/MM/YYYY' : formatDob(value!),
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color:
                  value == null
                      ? AppColors.textTertiary
                      : AppColors.textPrimary,
            ),
          ),
        ),
      ),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (labelAbove) ...[
          Text(
            label,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
        ],
        Semantics(button: true, label: label, child: field),
        if (inferredFromIc && value != null) ...[
          const SizedBox(height: 6),
          Text(
            context.l10n.dobFromIcHint,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: AppColors.textSecondary,
            ),
          ),
        ],
      ],
    );
  }
}

Future<DateTime?> showDobSheet(BuildContext context, {DateTime? initial}) {
  return showModalBottomSheet<DateTime>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surface,
    shape: const RoundedRectangleBorder(borderRadius: AppRadius.sheet),
    builder: (context) => _DobSheet(initial: initial),
  );
}

class _DobSheet extends StatefulWidget {
  const _DobSheet({this.initial});

  final DateTime? initial;

  @override
  State<_DobSheet> createState() => _DobSheetState();
}

class _DobSheetState extends State<_DobSheet> {
  late int? _day = widget.initial?.day;
  late int? _month = widget.initial?.month;
  late final TextEditingController _year = TextEditingController(
    text: widget.initial == null ? '' : '${widget.initial!.year}',
  );

  @override
  void dispose() {
    _year.dispose();
    super.dispose();
  }

  List<String> get _months =>
      Localizations.localeOf(context).languageCode == 'en'
          ? _monthsEn
          : _monthsMs;

  DateTime? get _parsed {
    final year = int.tryParse(_year.text);
    final month = _month;
    final day = _day;
    if (year == null || year < 1900 || month == null || day == null) {
      return null;
    }
    final date = DateTime(year, month, day);
    if (date.year != year || date.month != month || date.day != day) {
      return null;
    }
    final today = DateTime.now();
    final end = DateTime(today.year, today.month, today.day);
    if (date.isAfter(end)) return null;
    return date;
  }

  bool get _showInvalid {
    if (_day == null || _month == null || _year.text.length < 4) return false;
    return _parsed == null;
  }

  @override
  Widget build(BuildContext context) {
    final parsed = _parsed;
    final bottom = MediaQuery.viewInsetsOf(context).bottom;

    return Padding(
      padding: EdgeInsets.fromLTRB(Spacing.lg, Spacing.md, Spacing.lg, bottom),
      child: SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              context.l10n.dobLabel,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'DD/MM/YYYY',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: AppColors.textSecondary,
              ),
            ),
            const SizedBox(height: Spacing.lg),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  flex: 4,
                  child: _numberMenu(
                    label: context.l10n.dobDay,
                    value: _day,
                    count: 31,
                    onChanged: (value) => setState(() => _day = value),
                  ),
                ),
                const SizedBox(width: Spacing.sm),
                Expanded(
                  flex: 7,
                  child: DropdownButtonFormField<int>(
                    value: _month,
                    isExpanded: true,
                    decoration: InputDecoration(
                      labelText: context.l10n.dobMonth,
                    ),
                    hint: Text(context.l10n.dobMonth),
                    items: [
                      for (var month = 1; month <= 12; month++)
                        DropdownMenuItem(
                          value: month,
                          child: Text(
                            '${month.toString().padLeft(2, '0')}  ${_months[month - 1]}',
                          ),
                        ),
                    ],
                    selectedItemBuilder:
                        (context) => [
                          for (var month = 1; month <= 12; month++)
                            Align(
                              alignment: Alignment.centerLeft,
                              child: Text(month.toString().padLeft(2, '0')),
                            ),
                        ],
                    onChanged: (value) => setState(() => _month = value),
                  ),
                ),
                const SizedBox(width: Spacing.sm),
                Expanded(
                  flex: 5,
                  child: TextField(
                    controller: _year,
                    keyboardType: TextInputType.number,
                    textInputAction: TextInputAction.done,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    maxLength: 4,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textPrimary,
                    ),
                    decoration: InputDecoration(
                      labelText: context.l10n.dobYear,
                      hintText: '1994',
                      counterText: '',
                    ),
                    onChanged: (_) => setState(() {}),
                  ),
                ),
              ],
            ),
            const SizedBox(height: Spacing.sm),
            Text(
              parsed != null
                  ? formatDob(parsed)
                  : _showInvalid
                  ? context.l10n.dobInvalid
                  : 'DD/MM/YYYY',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color:
                    _showInvalid
                        ? AppColors.error
                        : AppColors.movementDarkGreen,
              ),
            ),
            const SizedBox(height: Spacing.lg),
            FilledButton(
              onPressed:
                  parsed == null ? null : () => Navigator.pop(context, parsed),
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(AppSizes.buttonHeight),
                backgroundColor: AppColors.movementGreen,
                foregroundColor: AppColors.white,
                disabledBackgroundColor: AppColors.surfaceMuted,
                disabledForegroundColor: AppColors.textSecondary,
              ),
              child: Text(context.l10n.dobUseDate),
            ),
            const SizedBox(height: Spacing.md),
          ],
        ),
      ),
    );
  }

  Widget _numberMenu({
    required String label,
    required int? value,
    required int count,
    required ValueChanged<int?> onChanged,
  }) {
    return DropdownButtonFormField<int>(
      value: value,
      isExpanded: true,
      decoration: InputDecoration(labelText: label),
      hint: Text(label),
      items: [
        for (var item = 1; item <= count; item++)
          DropdownMenuItem(
            value: item,
            child: Text(item.toString().padLeft(2, '0')),
          ),
      ],
      onChanged: onChanged,
    );
  }
}
