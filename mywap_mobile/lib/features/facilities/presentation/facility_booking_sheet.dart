import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../application/facility_providers.dart';
import '../data/models/facility.dart';

/// Opens the facility booking form as a modal bottom sheet.
void showFacilityBookingSheet(BuildContext context, FacilityDetailData detail) {
  showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (_) => FacilityBookingSheet(detail: detail),
  );
}

class FacilityBookingSheet extends ConsumerStatefulWidget {
  const FacilityBookingSheet({super.key, required this.detail});

  final FacilityDetailData detail;

  @override
  ConsumerState<FacilityBookingSheet> createState() => _FacilityBookingSheetState();
}

class _FacilityBookingSheetState extends ConsumerState<FacilityBookingSheet> {
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  DateTime? _start;
  DateTime? _end;
  bool _submitting = false;
  String? _error;
  BookingResult? _result;

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _pickStart() async {
    final picked = await _pickDateTime(context, _start);
    if (picked == null) return;
    setState(() => _start = picked);
  }

  Future<void> _pickEnd() async {
    final picked = await _pickDateTime(context, _end);
    if (picked == null) return;
    setState(() => _end = picked);
  }

  Future<void> _submit() async {
    final start = _start;
    final end = _end;

    if (start == null || end == null) {
      setState(() => _error = 'Sila pilih tarikh dan masa mula serta tamat.');
      return;
    }
    if (!end.isAfter(start)) {
      setState(() => _error = 'Tarikh tamat mesti selepas tarikh mula.');
      return;
    }
    if (!widget.detail.isMember &&
        (_nameController.text.trim().isEmpty ||
            _phoneController.text.trim().isEmpty)) {
      setState(() => _error = 'Sila lengkapkan maklumat hubungi.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final result = await ref
          .read(facilityRepositoryProvider)
          .book(
            widget.detail.facility?.id ?? 0,
            start: start,
            end: end,
            contactName: _nameController.text.trim().isEmpty
                ? null
                : _nameController.text.trim(),
            contactPhone: _phoneController.text.trim().isEmpty
                ? null
                : _phoneController.text.trim(),
          );
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _result = result;
      });
      ref.invalidate(facilitiesProvider);
      if (widget.detail.facility?.id != null) {
        ref.invalidate(facilityDetailProvider(widget.detail.facility!.id!));
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tempahan berjaya dihantar.')),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = _bookingErrorMessage(e);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_result != null) {
      return _BookingSuccess(result: _result!);
    }
    final theme = Theme.of(context);
    final isMember = widget.detail.isMember;

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(Spacing.xl),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Tempah ${widget.detail.facility?.name ?? ''}',
                style: theme.textTheme.titleLarge,
              ),
              const SizedBox(height: Spacing.lg),
              _DateTimeField(
                label: 'Mula',
                icon: Icons.schedule,
                value: _start,
                onTap: _pickStart,
              ),
              const SizedBox(height: Spacing.md),
              _DateTimeField(
                label: 'Tamat',
                icon: Icons.event_available,
                value: _end,
                onTap: _pickEnd,
              ),
              const SizedBox(height: Spacing.lg),
              if (!isMember) ...[
                TextFormField(
                  controller: _nameController,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                    labelText: 'Nama Hubungi',
                    prefixIcon: Icon(Icons.person_outline),
                  ),
                ),
                const SizedBox(height: Spacing.md),
                TextFormField(
                  controller: _phoneController,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Telefon Hubungi',
                    prefixIcon: Icon(Icons.phone_outlined),
                  ),
                ),
                const SizedBox(height: Spacing.lg),
              ],
              if (_error != null) ...[
                Text(
                  _error!,
                  style: const TextStyle(color: AppColors.error),
                ),
                const SizedBox(height: Spacing.md),
              ],
              FilledButton.icon(
                onPressed: _submitting ? null : _submit,
                icon: _submitting
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: AppColors.white,
                        ),
                      )
                    : const Icon(Icons.check),
                label: const Text('Hantar Tempahan'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DateTimeField extends StatelessWidget {
  const _DateTimeField({
    required this.label,
    required this.icon,
    required this.value,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final DateTime? value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final value = this.value;
    return InkWell(
      onTap: onTap,
      borderRadius: AppRadius.md,
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: Icon(icon),
          suffixIcon: const Icon(Icons.calendar_today_outlined, size: 18),
        ),
        child: Text(
          value != null ? _formatDateTime(value) : 'Pilih tarikh & masa',
          style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                color: value == null ? AppColors.textSecondary : null,
              ),
        ),
      ),
    );
  }
}

class _BookingSuccess extends StatelessWidget {
  const _BookingSuccess({required this.result});

  final BookingResult result;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final statusLabel = switch (result.bookingStatus) {
      'approved' => 'Diluluskan',
      'rejected' => 'Ditolak',
      _ => 'Menunggu kelulusan',
    };

    return Padding(
      padding: const EdgeInsets.all(Spacing.xl),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.check_circle, size: 56, color: AppColors.success),
          const SizedBox(height: Spacing.lg),
          Text('Tempahan Dihantar', style: theme.textTheme.titleLarge),
          const SizedBox(height: Spacing.sm),
          Text('Jumlah: ${Formatters.currency(result.totalPrice)}',
              style: theme.textTheme.titleMedium),
          const SizedBox(height: Spacing.xs),
          Text('Status: $statusLabel',
              style: theme.textTheme.bodyMedium
                  ?.copyWith(color: AppColors.textSecondary)),
          const SizedBox(height: Spacing.xl),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Selesai'),
          ),
        ],
      ),
    );
  }
}

String _bookingErrorMessage(ApiException e) {
  final errors = e.errors;
  if (errors != null && errors.isNotEmpty) {
    return errors.values.expand((list) => list).first;
  }
  return e.message;
}

Future<DateTime?> _pickDateTime(BuildContext context, DateTime? initial) async {
  final now = DateTime.now();
  final date = await showDatePicker(
    context: context,
    initialDate: initial ?? now,
    firstDate: DateTime(now.year - 1),
    lastDate: DateTime(now.year + 3),
  );
  if (date == null) return null;
  if (!context.mounted) return null;
  final base = DateTime(
    date.year,
    date.month,
    date.day,
    initial?.hour ?? now.hour,
    initial?.minute ?? 0,
  );
  final time = await showTimePicker(
    context: context,
    initialTime: TimeOfDay.fromDateTime(base),
  );
  if (time == null) return null;
  return DateTime(date.year, date.month, date.day, time.hour, time.minute);
}

const List<String> _months = [
  'Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun',
  'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis',
];

String _formatDateTime(DateTime value) {
  String pad(int v) => v.toString().padLeft(2, '0');
  return '${value.day} ${_months[value.month - 1]} ${value.year}, '
      '${pad(value.hour)}:${pad(value.minute)}';
}
