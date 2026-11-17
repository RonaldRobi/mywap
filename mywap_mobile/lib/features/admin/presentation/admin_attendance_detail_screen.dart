import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_text_theme.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/list_card.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/admin_providers.dart';
import '../data/models/admin_models.dart';

/// Skrin Kehadiran Program & Pengurusan Peserta (`/admin/attendance/:eventId`).
class AdminAttendanceDetailScreen extends ConsumerStatefulWidget {
  const AdminAttendanceDetailScreen({super.key, required this.eventId});

  final int eventId;

  @override
  ConsumerState<AdminAttendanceDetailScreen> createState() =>
      _AdminAttendanceDetailScreenState();
}

class _AdminAttendanceDetailScreenState
    extends ConsumerState<AdminAttendanceDetailScreen> {
  final _searchController = TextEditingController();
  String _searchQuery = '';
  String _filter = 'all'; // 'all' | 'attended' | 'unattended'

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _openFastScanner() {
    context.push('/admin/scan?eventId=${widget.eventId}').then((_) {
      if (mounted) {
        ref.invalidate(adminAttendanceRegistrationsProvider(widget.eventId));
      }
    });
  }

  Future<void> _confirmManualCheckIn(Registration registration) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        title: const Text('Sahkan Kehadiran'),
        content: Text(
          'Tandakan kehadiran peserta ini sekarang?\n\n'
          'Nama: ${registration.name.isEmpty ? "Peserta" : registration.name}\n'
          '${registration.memberNo.isNotEmpty ? "No. Ahli: ${registration.memberNo}" : ""}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogCtx, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogCtx, true),
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.movementGreen,
            ),
            child: const Text('Sahkan Hadir'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    try {
      final identifier = registration.registrationNo.isNotEmpty
          ? registration.registrationNo
          : (registration.memberNo.isNotEmpty
              ? registration.memberNo
              : (registration.icNumber.isNotEmpty
                  ? registration.icNumber
                  : (registration.phone.isNotEmpty
                      ? registration.phone
                      : registration.name)));

      final result = await ref
          .read(adminRepositoryProvider)
          .scan(eventId: widget.eventId, identifier: identifier);

      if (!mounted) return;
      HapticFeedback.mediumImpact();
      ref.invalidate(adminAttendanceRegistrationsProvider(widget.eventId));

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            result.isOk
                ? 'Kehadiran ${registration.name.isEmpty ? "peserta" : registration.name} berjaya disahkan.'
                : result.message,
          ),
          backgroundColor: result.isOk ? AppColors.movementDarkGreen : AppColors.error,
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: AppColors.error,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Ralat merekod kehadiran.'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  void _showAttendedInfo(Registration registration) {
    showModalBottomSheet<void>(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: AppRadius.sheet),
      builder: (sheetCtx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Spacing.xl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(
                    Icons.check_circle_rounded,
                    color: AppColors.success,
                    size: 28,
                  ),
                  const SizedBox(width: Spacing.sm),
                  Text(
                    'Peserta Telah Hadir',
                    style: Theme.of(sheetCtx).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                ],
              ),
              const SizedBox(height: Spacing.md),
              Text(
                registration.name.isEmpty ? '-' : registration.name,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              if (registration.memberNo.isNotEmpty)
                Text(
                  'No. Ahli: ${registration.memberNo}',
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
              const SizedBox(height: Spacing.sm),
              if (registration.attendedAt != null)
                Text(
                  'Waktu Hadir: ${Formatters.datetime(registration.attendedAt)}',
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
              const SizedBox(height: Spacing.lg),
              FilledButton(
                onPressed: () => Navigator.pop(sheetCtx),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(AppSizes.buttonHeight),
                ),
                child: const Text('Tutup'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final dataAsync = ref.watch(
      adminAttendanceRegistrationsProvider(widget.eventId),
    );

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text(context.l10n.adminEventAttendanceTitle),
        actions: [
          IconButton(
            icon: const Icon(Icons.qr_code_scanner_rounded),
            tooltip: 'Buka Pengimbas Pantas',
            onPressed: _openFastScanner,
          ),
        ],
      ),
      body: dataAsync.when(
        data: (data) => _AttendanceContent(
          data: data,
          searchController: _searchController,
          searchQuery: _searchQuery,
          filter: _filter,
          onSearchChanged: (val) => setState(() => _searchQuery = val.trim()),
          onClearSearch: () {
            _searchController.clear();
            setState(() => _searchQuery = '');
          },
          onFilterChanged: (newFilter) => setState(() => _filter = newFilter),
          onScan: _openFastScanner,
          onTapRegistration: (reg) {
            if (reg.attended) {
              _showAttendedInfo(reg);
            } else {
              _confirmManualCheckIn(reg);
            }
          },
        ),
        loading: () => const _AttendanceSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException
              ? error.message
              : context.l10n.adminUnexpectedError,
          onRetry: () => ref.invalidate(
            adminAttendanceRegistrationsProvider(widget.eventId),
          ),
        ),
      ),
    );
  }
}

class _AttendanceContent extends StatelessWidget {
  const _AttendanceContent({
    required this.data,
    required this.searchController,
    required this.searchQuery,
    required this.filter,
    required this.onSearchChanged,
    required this.onClearSearch,
    required this.onFilterChanged,
    required this.onScan,
    required this.onTapRegistration,
  });

  final AttendanceData data;
  final TextEditingController searchController;
  final String searchQuery;
  final String filter;
  final ValueChanged<String> onSearchChanged;
  final VoidCallback onClearSearch;
  final ValueChanged<String> onFilterChanged;
  final VoidCallback onScan;
  final ValueChanged<Registration> onTapRegistration;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final unattendedCount = (data.registrations.length - data.attendedCount)
        .clamp(0, data.registrations.length);

    // Tapis mengikut query dan tab status
    final filtered = data.registrations.where((r) {
      if (filter == 'attended' && !r.attended) return false;
      if (filter == 'unattended' && r.attended) return false;
      if (searchQuery.isNotEmpty) {
        final q = searchQuery.toLowerCase();
        final matches = r.name.toLowerCase().contains(q) ||
            r.memberNo.toLowerCase().contains(q) ||
            r.registrationNo.toLowerCase().contains(q) ||
            r.icNumber.toLowerCase().contains(q) ||
            r.phone.toLowerCase().contains(q);
        if (!matches) return false;
      }
      return true;
    }).toList();

    return Column(
      children: [
        // 1. Maklumat Ringkas Program & Statistik Kehadiran
        Padding(
          padding: const EdgeInsets.fromLTRB(Spacing.lg, Spacing.md, Spacing.lg, Spacing.sm),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                data.event?.title ?? context.l10n.adminProgramFallback,
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (data.event?.startTime != null) ...[
                const SizedBox(height: 2),
                Text(
                  Formatters.datetime(data.event!.startTime),
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
              const SizedBox(height: Spacing.md),
              Row(
                children: [
                  Expanded(
                    child: _StatTile(
                      label: context.l10n.adminRegistrationsLabel,
                      value: '${data.totalRegistered}',
                      icon: Icons.people_outline,
                      color: AppColors.movementNavy,
                    ),
                  ),
                  const SizedBox(width: Spacing.md),
                  Expanded(
                    child: _StatTile(
                      label: context.l10n.adminAttendedLabel,
                      value: '${data.attendedCount}',
                      icon: Icons.check_circle_outline,
                      color: AppColors.success,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),

        // 2. Bar Carian Peserta
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: Spacing.lg, vertical: Spacing.xs),
          child: TextField(
            controller: searchController,
            onChanged: onSearchChanged,
            decoration: InputDecoration(
              hintText: 'Cari nama, No. Ahli atau No. KP...',
              prefixIcon: const Icon(Icons.search, size: 20),
              suffixIcon: searchQuery.isNotEmpty
                  ? IconButton(
                      icon: const Icon(Icons.clear, size: 18),
                      onPressed: onClearSearch,
                    )
                  : null,
              isDense: true,
              contentPadding: const EdgeInsets.symmetric(
                horizontal: Spacing.md,
                vertical: 12,
              ),
              filled: true,
              fillColor: AppColors.white,
              border: OutlineInputBorder(
                borderRadius: AppRadius.card,
                borderSide: const BorderSide(color: AppColors.divider),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: AppRadius.card,
                borderSide: const BorderSide(color: AppColors.divider),
              ),
            ),
          ),
        ),

        // 3. Cip Penapis Status Kehadiran
        SizedBox(
          height: 48,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
            children: [
              _FilterChip(
                label: 'Semua (${data.registrations.length})',
                selected: filter == 'all',
                onTap: () => onFilterChanged('all'),
              ),
              _FilterChip(
                label: 'Hadir (${data.attendedCount})',
                selected: filter == 'attended',
                onTap: () => onFilterChanged('attended'),
              ),
              _FilterChip(
                label: 'Belum Hadir ($unattendedCount)',
                selected: filter == 'unattended',
                onTap: () => onFilterChanged('unattended'),
              ),
            ],
          ),
        ),

        const SizedBox(height: Spacing.xs),

        // 4. Senarai Peserta
        Expanded(
          child: filtered.isEmpty
              ? EmptyState(
                  icon: Icons.event_seat_outlined,
                  message: searchQuery.isNotEmpty
                      ? 'Tiada peserta sepadan dengan carian.'
                      : (filter == 'unattended'
                          ? 'Semua peserta telah hadir!'
                          : context.l10n.adminNoRegistrations),
                )
              : ListView.builder(
                  padding: const EdgeInsets.only(bottom: Spacing.xxl),
                  itemCount: filtered.length,
                  itemBuilder: (context, index) {
                    final registration = filtered[index];
                    return _RegistrationCard(
                      registration: registration,
                      onTap: () => onTapRegistration(registration),
                    );
                  },
                ),
        ),

        // 5. Butang Terapung Buka Pengimbas
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.lg,
              0,
              Spacing.lg,
              Spacing.md,
            ),
            child: FilledButton.icon(
              onPressed: onScan,
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(AppSizes.buttonHeight),
                backgroundColor: AppColors.movementDarkGreen,
              ),
              icon: const Icon(Icons.qr_code_scanner_rounded),
              label: Text(
                context.l10n.adminScanQr,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: EdgeInsets.zero,
      color: AppColors.white,
      shape: RoundedRectangleBorder(
        borderRadius: AppRadius.card,
        side: const BorderSide(color: AppColors.divider),
      ),
      child: Padding(
        padding: const EdgeInsets.all(Spacing.md),
        child: Row(
          children: [
            CircleAvatar(
              radius: 18,
              backgroundColor: color.withValues(alpha: 0.12),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(width: Spacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    value,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    label,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: Spacing.sm),
      child: ChoiceChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) => onTap(),
        showCheckmark: false,
        selectedColor: AppColors.movementDarkGreen,
        labelStyle: TextStyle(
          color: selected ? AppColors.white : AppColors.textPrimary,
          fontWeight: FontWeight.w600,
          fontSize: 12,
        ),
        backgroundColor: AppColors.white,
        side: const BorderSide(color: AppColors.divider),
      ),
    );
  }
}

class _RegistrationCard extends StatelessWidget {
  const _RegistrationCard({
    required this.registration,
    required this.onTap,
  });

  final Registration registration;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final subtitleParts = <String>[
      if (registration.memberNo.isNotEmpty)
        context.l10n.adminMemberNoPrefix(registration.memberNo),
      if (registration.attended && registration.attendedAt != null)
        context.l10n.adminAttendedAt(
          Formatters.datetime(registration.attendedAt),
        )
      else if (!registration.attended)
        'Ketik untuk sahkan hadir',
    ];

    return ListCard(
      title: registration.name.isEmpty
          ? context.l10n.adminNoName
          : registration.name,
      subtitle: subtitleParts.join(' · '),
      onTap: onTap,
      leading: CircleAvatar(
        radius: 20,
        backgroundColor: registration.attended
            ? AppColors.movementSoftGreen
            : AppColors.divider.withValues(alpha: 0.5),
        child: Icon(
          registration.attended ? Icons.check : Icons.person_outline,
          color: registration.attended
              ? AppColors.movementNavy
              : AppColors.textSecondary,
          size: 20,
        ),
      ),
      trailing: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: registration.attended
              ? AppColors.movementSoftGreen
              : AppColors.surfaceMuted,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: registration.attended
                ? AppColors.movementGreen.withValues(alpha: 0.3)
                : AppColors.divider,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (registration.attended) ...[
              const Icon(
                Icons.check_circle_rounded,
                size: 14,
                color: AppColors.movementNavy,
              ),
              const SizedBox(width: 4),
            ],
            Text(
              registration.attended
                  ? context.l10n.adminAttendedLabel
                  : 'Belum Hadir',
              style: TextStyle(
                color: registration.attended
                    ? AppColors.movementNavy
                    : AppColors.textSecondary,
                fontSize: AppTextTheme.minSize,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AttendanceSkeleton extends StatelessWidget {
  const _AttendanceSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: const [
        SkeletonBox(height: 24, width: 240),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 70),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 44),
        SizedBox(height: Spacing.lg),
        SkeletonBox(height: 64),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 64),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 64),
      ],
    );
  }
}
