import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../member/presentation/main_shell.dart';
import '../application/facility_providers.dart';
import '../data/models/facility.dart';

class FacilitiesScreen extends ConsumerWidget {
  const FacilitiesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final facilitiesAsync = ref.watch(facilitiesProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Kemudahan'),
        actions: const [LogoutIconButton()],
      ),
      body: facilitiesAsync.when(
        data: (data) => _FacilitiesBody(data: data),
        loading: () => const _FacilitiesSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : 'Ralat tidak dijangka.',
              onRetry: () => ref.invalidate(facilitiesProvider),
            ),
      ),
    );
  }
}

class _FacilitiesBody extends StatelessWidget {
  const _FacilitiesBody({required this.data});

  final FacilityListData data;

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      slivers: [
        const SliverToBoxAdapter(child: _FacilitiesIntro()),
        if (data.facilities.isEmpty)
          const SliverToBoxAdapter(
            child: EmptyState(
              icon: Icons.apartment,
              message: 'Tiada kemudahan buat masa ini.',
            ),
          )
        else
          SliverList.builder(
            itemCount: data.facilities.length,
            itemBuilder:
                (context, index) =>
                    _FacilityCard(facility: data.facilities[index]),
          ),
        const SliverToBoxAdapter(child: SectionHeader('Tempahan Saya')),
        if (data.myBookings.isEmpty)
          const SliverToBoxAdapter(
            child: EmptyState(
              icon: Icons.event_note_outlined,
              message: 'Tiada tempahan buat masa ini.',
            ),
          )
        else
          SliverList.builder(
            itemCount: data.myBookings.length,
            itemBuilder:
                (context, index) =>
                    _MyBookingCard(booking: data.myBookings[index]),
          ),
        const SliverToBoxAdapter(child: SizedBox(height: Spacing.xl)),
      ],
    );
  }
}

class _FacilityCard extends StatelessWidget {
  const _FacilityCard({required this.facility});

  final Facility facility;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final price = facility.pricePerUnit;
    final memberPrice = facility.memberPricePerUnit;

    return Card(
      clipBehavior: Clip.antiAlias,
      margin: const EdgeInsets.fromLTRB(Spacing.lg, 0, Spacing.lg, Spacing.lg),
      child: InkWell(
        onTap: () => context.push('/facilities/${facility.id}'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                AppImage(
                  facility.imageUrl,
                  height: 160,
                  width: double.infinity,
                ),
                Positioned(
                  top: Spacing.md,
                  left: Spacing.md,
                  child: _FacilityTag(
                    label: facility.organizationName ?? 'Organisasi',
                  ),
                ),
                Positioned(
                  top: Spacing.md,
                  right: Spacing.md,
                  child: _FacilityTag(
                    label: facility.type == 'daily' ? 'Harian' : 'Sejam',
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    facility.name ?? '-',
                    style: theme.textTheme.titleMedium,
                  ),
                  if (facility.description?.isNotEmpty == true) ...[
                    const SizedBox(height: Spacing.xs),
                    Text(
                      facility.description!,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                  const SizedBox(height: Spacing.sm),
                  if (facility.location != null)
                    _RowIcon(
                      icon: Icons.location_on_outlined,
                      text: facility.location!,
                    ),
                  if (facility.capacity != null)
                    _RowIcon(
                      icon: Icons.people_outline,
                      text: 'Kapasiti: ${facility.capacity}',
                    ),
                  const SizedBox(height: Spacing.sm),
                  Row(
                    children: [
                      Text(
                        Formatters.currency(price),
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: AppColors.movementGreen,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(
                        ' / ${facility.type == 'daily' ? 'hari' : 'jam'}',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                      if (memberPrice != null) ...[
                        const Spacer(),
                        Text(
                          'Ahli: ${Formatters.currency(memberPrice)}',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.movementSoftGreen,
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: Spacing.md),
                  FilledButton.tonal(
                    onPressed: () => context.push('/facilities/${facility.id}'),
                    child: const Text('Lihat & Tempah'),
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

class _FacilitiesIntro extends StatelessWidget {
  const _FacilitiesIntro();
  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.fromLTRB(
      Spacing.lg,
      Spacing.lg,
      Spacing.lg,
      Spacing.xl,
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Perkhidmatan & Fasiliti',
          style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
        ),
        SizedBox(height: Spacing.xs),
        Text('Tempah ruang perkhidmatan dan fasiliti yang tersedia.'),
      ],
    ),
  );
}

class _FacilityTag extends StatelessWidget {
  const _FacilityTag({required this.label});
  final String label;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: Spacing.sm, vertical: 5),
    decoration: BoxDecoration(
      color: AppColors.white.withValues(alpha: .92),
      borderRadius: BorderRadius.circular(99),
    ),
    child: Text(
      label,
      style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700),
    ),
  );
}

class _MyBookingCard extends StatelessWidget {
  const _MyBookingCard({required this.booking});

  final FacilityBooking booking;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final start = _formatDateTime(booking.startDatetime);
    final end = _formatDateTime(booking.endDatetime);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    booking.facilityName ?? '-',
                    style: theme.textTheme.titleMedium,
                  ),
                ),
                _StatusChip(status: booking.bookingStatus),
              ],
            ),
            if (booking.organizationName != null) ...[
              const SizedBox(height: 2),
              Text(
                booking.organizationName!,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.movementGreen,
                ),
              ),
            ],
            const SizedBox(height: Spacing.sm),
            _RowIcon(icon: Icons.schedule, text: start ?? '-'),
            if (end != null) _RowIcon(icon: Icons.event_available, text: end),
            const SizedBox(height: Spacing.sm),
            Text(
              'Jumlah: ${Formatters.currency(booking.totalPrice)}',
              style: theme.textTheme.titleSmall?.copyWith(
                color: AppColors.movementGreen,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});

  final String? status;

  @override
  Widget build(BuildContext context) {
    final label = switch (status) {
      'approved' => 'Diluluskan',
      'rejected' => 'Ditolak',
      'pending' => 'Menunggu',
      _ => status ?? '-',
    };
    final color = switch (status) {
      'approved' => AppColors.success,
      'rejected' => AppColors.error,
      'pending' => AppColors.warning,
      _ => AppColors.textSecondary,
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.sm, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: AppColors.textPrimary,
        ),
      ),
    );
  }
}

class _RowIcon extends StatelessWidget {
  const _RowIcon({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: Spacing.xs),
      child: Row(
        children: [
          Icon(icon, size: 16, color: AppColors.textSecondary),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              text,
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}

class _FacilitiesSkeleton extends StatelessWidget {
  const _FacilitiesSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        for (var i = 0; i < 3; i++) ...[
          const SkeletonBox(height: 200, radius: 16),
          const SizedBox(height: Spacing.xl),
        ],
      ],
    );
  }
}

DateTime? _parse(String? value) {
  if (value == null || value.isEmpty) return null;
  return DateTime.tryParse(value);
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

String? _formatDateTime(String? iso) {
  final dt = _parse(iso);
  if (dt == null) return null;
  String pad(int v) => v.toString().padLeft(2, '0');
  return '${dt.day} ${_months[dt.month - 1]} ${dt.year}, '
      '${pad(dt.hour)}:${pad(dt.minute)}';
}
