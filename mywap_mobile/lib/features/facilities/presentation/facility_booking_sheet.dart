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
    backgroundColor: AppColors.white,
    builder: (_) => FacilityBookingSheet(detail: detail),
  );
}

class FacilityBookingSheet extends ConsumerStatefulWidget {
  const FacilityBookingSheet({super.key, required this.detail});

  final FacilityDetailData detail;

  @override
  ConsumerState<FacilityBookingSheet> createState() =>
      _FacilityBookingSheetState();
}

class _FacilityBookingSheetState extends ConsumerState<FacilityBookingSheet> {
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  DateTime? _selectedDay;
  int? _startHour;
  int? _durationHours;
  bool _submitting = false;
  String? _error;
  BookingResult? _result;

  Facility? get _facility => widget.detail.facility;
  bool get _isMember => widget.detail.isMember;
  String? get _type => _facility?.type;

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  bool _isPastToday() {
    final day = _selectedDay;
    if (day == null) return false;
    final now = DateTime.now();
    return day.year < now.year ||
        (day.year == now.year && day.month < now.month) ||
        (day.year == now.year && day.month == now.month && day.day < now.day);
  }

  DateTime? get _startDateTime {
    final day = _selectedDay;
    final hour = _startHour;
    if (day == null || hour == null) return null;
    return DateTime(day.year, day.month, day.day, hour);
  }

  DateTime? get _endDateTime {
    final start = _startDateTime;
    final duration = _durationHours;
    if (start == null || duration == null) return null;
    return start.add(Duration(hours: duration));
  }

  Future<void> _submit() async {
    final start = _startDateTime;
    final end = _endDateTime;

    if (start == null || end == null) {
      setState(() => _error = 'Sila lengkapkan tarikh, masa mula dan tempoh.');
      return;
    }
    if (_isPastToday() && start.isBefore(DateTime.now())) {
      setState(
        () => _error = 'Slot yang dipilih telah berlalu. Sila pilih masa lain.',
      );
      return;
    }
    if (!_isMember &&
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
            _facility?.id ?? 0,
            start: start,
            end: end,
            contactName:
                _nameController.text.trim().isEmpty
                    ? null
                    : _nameController.text.trim(),
            contactPhone:
                _phoneController.text.trim().isEmpty
                    ? null
                    : _phoneController.text.trim(),
          );
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _result = result;
      });
      ref.invalidate(facilitiesProvider);
      if (_facility?.id != null) {
        ref.invalidate(facilityDetailProvider(_facility!.id!));
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
    final now = DateTime.now();

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
                'Tempah ${_facility?.name ?? ''}',
                style: theme.textTheme.titleLarge,
              ),
              const SizedBox(height: Spacing.xs),
              Text(
                'Langkah mudah: pilih hari, masa mula dan tempoh. Slot bertindih dengan tempahan lain akan ditolak.',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: Spacing.lg),

              _SectionLabel(step: 1, title: 'Pilih Hari'),
              const SizedBox(height: Spacing.sm),
              SizedBox(
                height: 84,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: 14,
                  separatorBuilder:
                      (_, __) => const SizedBox(width: Spacing.sm),
                  itemBuilder: (context, index) {
                    final day = DateTime(now.year, now.month, now.day + index);
                    final selected =
                        _selectedDay != null && _isSameDay(_selectedDay!, day);
                    return _DayChip(
                      date: day,
                      selected: selected,
                      onTap:
                          () => setState(() {
                            _selectedDay = day;
                            _startHour = null;
                            _durationHours = null;
                            _error = null;
                          }),
                    );
                  },
                ),
              ),
              const SizedBox(height: Spacing.lg),

              _SectionLabel(step: 2, title: 'Masa Mula'),
              const SizedBox(height: Spacing.sm),
              Wrap(
                spacing: Spacing.sm,
                runSpacing: Spacing.sm,
                children: [
                  for (
                    var hour = _startHourRangeStart;
                    hour <= _startHourRangeEnd;
                    hour++
                  )
                    _SelectChip(
                      label: _hourLabel(hour),
                      selected: _startHour == hour,
                      enabled: _hourAllowed(hour),
                      onTap:
                          () => setState(() {
                            _startHour = hour;
                            _durationHours = null;
                            _error = null;
                          }),
                    ),
                ],
              ),
              const SizedBox(height: Spacing.lg),

              _SectionLabel(step: 3, title: 'Tempoh Tempahan'),
              const SizedBox(height: Spacing.sm),
              Wrap(
                spacing: Spacing.sm,
                runSpacing: Spacing.sm,
                children: [
                  for (final hours in _durationOptions)
                    _SelectChip(
                      label: _durationLabel(hours),
                      selected: _durationHours == hours,
                      enabled: _startHour != null,
                      onTap:
                          () => setState(() {
                            _durationHours = hours;
                            _error = null;
                          }),
                    ),
                ],
              ),
              const SizedBox(height: Spacing.md),
              if (_startHour == null)
                Text(
                  'Pilih masa mula dahulu untuk melihat tempoh yang tersedia.',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              if (_startDateTime != null && _durationHours != null) ...[
                const SizedBox(height: Spacing.lg),
                _BookingSummary(
                  start: _startDateTime!,
                  end: _endDateTime!,
                  estimate: _estimatePrice(),
                  unitRate: _unitRate,
                  isMember: _isMember,
                ),
              ],
              const SizedBox(height: Spacing.lg),
              if (!_isMember) ...[
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
                Text(_error!, style: const TextStyle(color: AppColors.error)),
                const SizedBox(height: Spacing.md),
              ],
              FilledButton.icon(
                onPressed: _submitting ? null : _submit,
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
                        : const Icon(Icons.check),
                label: const Text('Hantar Tempahan'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  int get _startHourRangeStart => 7;
  int get _startHourRangeEnd => 22;

  bool _hourAllowed(int hour) {
    final day = _selectedDay;
    if (day == null) return false;
    if (!_isSameDay(day, DateTime.now())) return true;
    return DateTime(day.year, day.month, day.day, hour).isAfter(DateTime.now());
  }

  List<int> get _durationOptions => switch (_type) {
    'daily' => const [24, 48],
    'halfday' => const [6, 12],
    _ => const [1, 2, 3, 4, 6, 8],
  };

  double? get _unitRate {
    final price = _facility?.pricePerUnit;
    final memberPrice = _facility?.memberPricePerUnit;
    if (_isMember && memberPrice != null) return memberPrice;
    return price;
  }

  int get _estimatedUnits {
    final hours = _durationHours ?? 0;
    return switch (_type) {
      'daily' => (hours / 24).ceil(),
      'halfday' => (hours / 12).ceil(),
      _ => hours,
    };
  }

  double? _estimatePrice() {
    final rate = _unitRate;
    if (rate == null) return null;
    return _estimatedUnits * rate;
  }

  String _durationLabel(int hours) {
    if (_type == 'daily') {
      return hours == 24 ? '1 Hari' : '${hours ~/ 24} Hari';
    }
    return '$hours Jam';
  }

  static bool _isSameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel({required this.step, required this.title});

  final int step;
  final String title;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      children: [
        CircleAvatar(
          radius: 11,
          backgroundColor: AppColors.movementGreen,
          child: Text(
            '$step',
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w800,
              color: AppColors.white,
            ),
          ),
        ),
        const SizedBox(width: Spacing.sm),
        Text(
          title,
          style: theme.textTheme.titleSmall?.copyWith(
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _DayChip extends StatelessWidget {
  const _DayChip({
    required this.date,
    required this.selected,
    required this.onTap,
  });

  final DateTime date;
  final bool selected;
  final VoidCallback onTap;

  static const List<String> _weekdays = [
    'Ahad',
    'Isnin',
    'Selasa',
    'Rabu',
    'Khamis',
    'Jumaat',
    'Sabtu',
  ];
  static const List<String> _months = [
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

  @override
  Widget build(BuildContext context) {
    final today = _isSameDay(date, DateTime.now());
    final fg = selected ? AppColors.white : AppColors.textPrimary;
    final bg = selected ? AppColors.movementGreen : AppColors.surfaceMuted;

    return InkWell(
      onTap: onTap,
      borderRadius: AppRadius.md,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        width: 64,
        padding: const EdgeInsets.symmetric(vertical: 6),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: AppRadius.md,
          border: Border.all(
            color: selected ? AppColors.movementGreen : Colors.transparent,
          ),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              today ? 'Hari ini' : _weekdays[date.weekday - 1],
              maxLines: 1,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: fg.withValues(alpha: selected ? .92 : .6),
              ),
            ),
            Text(
              '${date.day}',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: fg,
              ),
            ),
            Text(
              _months[date.month - 1],
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: fg.withValues(alpha: selected ? .9 : .6),
              ),
            ),
          ],
        ),
      ),
    );
  }

  static bool _isSameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;
}

class _SelectChip extends StatelessWidget {
  const _SelectChip({
    required this.label,
    required this.selected,
    required this.onTap,
    this.enabled = true,
  });

  final String label;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: AppRadius.lg,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(
          horizontal: Spacing.lg,
          vertical: 10,
        ),
        decoration: BoxDecoration(
          color:
              selected
                  ? AppColors.movementGreen
                  : enabled
                  ? AppColors.surfaceMuted
                  : AppColors.surfaceMuted.withValues(alpha: .5),
          borderRadius: AppRadius.lg,
          border: Border.all(
            color:
                selected
                    ? AppColors.movementGreen
                    : enabled
                    ? AppColors.divider
                    : Colors.transparent,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w700,
            color:
                selected
                    ? AppColors.white
                    : enabled
                    ? AppColors.textPrimary
                    : AppColors.textTertiary,
          ),
        ),
      ),
    );
  }
}

class _BookingSummary extends StatelessWidget {
  const _BookingSummary({
    required this.start,
    required this.end,
    required this.estimate,
    required this.unitRate,
    required this.isMember,
  });

  final DateTime start;
  final DateTime end;
  final double? estimate;
  final double? unitRate;
  final bool isMember;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(Spacing.lg),
      decoration: BoxDecoration(
        color: AppColors.softGreenSurface,
        borderRadius: AppRadius.md,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.schedule,
                size: 18,
                color: AppColors.movementGreen,
              ),
              const SizedBox(width: Spacing.sm),
              Text('Ringkasan Tempahan', style: theme.textTheme.titleSmall),
            ],
          ),
          const SizedBox(height: Spacing.sm),
          _SummaryRow(
            icon: Icons.event_outlined,
            label: 'Mula',
            value: _formatDateTime(start),
          ),
          const SizedBox(height: Spacing.xs),
          _SummaryRow(
            icon: Icons.event_available,
            label: 'Tamat',
            value: _formatDateTime(end),
          ),
          if (estimate != null) ...[
            const SizedBox(height: Spacing.xs),
            _SummaryRow(
              icon: Icons.payments_outlined,
              label: 'Anggaran',
              value: Formatters.currency(estimate),
              valueColor: AppColors.movementGreen,
            ),
            if (unitRate != null)
              Padding(
                padding: const EdgeInsets.only(left: 26),
                child: Text(
                  isMember
                      ? 'Kadar ahli. Bayaran disahkan di lokasi.'
                      : 'Kadar biasa. Bayaran disahkan di lokasi.',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({
    required this.icon,
    required this.label,
    required this.value,
    this.valueColor,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 16, color: AppColors.textSecondary),
        const SizedBox(width: 6),
        SizedBox(
          width: 76,
          child: Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: valueColor ?? AppColors.textPrimary,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
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
          Text(
            'Jumlah: ${Formatters.currency(result.totalPrice)}',
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: Spacing.xs),
          Text(
            'Status: $statusLabel',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: Spacing.xs),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
            child: Text(
              'Anda akan menerima notifikasi sebaik tempahan disahkan. Bayaran dibuat apabila tiba di lokasi.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ),
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

String _hourLabel(int hour) {
  String pad(int v) => v.toString().padLeft(2, '0');
  return '${pad(hour)}:00';
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
const List<String> _weekdays = [
  'Ahad',
  'Isnin',
  'Selasa',
  'Rabu',
  'Khamis',
  'Jumaat',
  'Sabtu',
];

String _formatDateTime(DateTime value) {
  String pad(int v) => v.toString().padLeft(2, '0');
  return '${_weekdays[value.weekday - 1]}, ${value.day} '
      '${_months[value.month - 1]} ${value.year}, '
      '${pad(value.hour)}:${pad(value.minute)}';
}
