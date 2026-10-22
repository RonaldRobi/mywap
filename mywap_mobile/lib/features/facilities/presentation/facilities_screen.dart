import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../member/presentation/widgets/notification_bell.dart';
import '../application/facility_providers.dart';
import '../data/models/facility.dart';
import 'widgets/booking_status_chips.dart';
import '../../../shared/theme/app_text_theme.dart';

class FacilitiesScreen extends ConsumerWidget {
  const FacilitiesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final facilitiesAsync = ref.watch(facilitiesProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.facTitle),
        actions: const [NotificationBell(), SizedBox(width: Spacing.sm)],
      ),
      body: facilitiesAsync.when(
        data:
            (data) => _FacilitiesBody(
              data: data,
              onRefresh: () async => ref.invalidate(facilitiesProvider),
            ),
        loading: () => const _FacilitiesSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException ? error.message : l10n.unexpectedError,
              onRetry: () => ref.invalidate(facilitiesProvider),
            ),
      ),
    );
  }
}

class _FacilitiesBody extends StatelessWidget {
  const _FacilitiesBody({required this.data, required this.onRefresh});

  final FacilityListData data;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          const SliverToBoxAdapter(child: _FacilitiesIntro()),
          if (data.facilities.isEmpty)
            SliverToBoxAdapter(
              child: EmptyState(
                icon: Icons.apartment,
                message: l10n.facNoFacilities,
              ),
            )
          else
            SliverList.builder(
              itemCount: data.facilities.length,
              itemBuilder:
                  (context, index) =>
                      _FacilityCard(facility: data.facilities[index]),
            ),
          SliverToBoxAdapter(child: SectionHeader(l10n.facMyBookings)),
          if (data.myBookings.isEmpty)
            SliverToBoxAdapter(
              child: EmptyState(
                icon: Icons.event_note_outlined,
                message: l10n.facNoBookings,
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
      ),
    );
  }
}

class _FacilityCard extends StatelessWidget {
  const _FacilityCard({required this.facility});

  final Facility facility;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final price = facility.pricePerUnit;
    final memberPrice = facility.memberPricePerUnit;
    final unitLabel =
        facility.type == 'daily'
            ? l10n.facUnitDay
            : facility.type == 'halfday'
            ? l10n.facUnitHalfDay
            : l10n.facUnitHour;

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
                    label: facility.organizationName ?? l10n.organizationLabel,
                  ),
                ),
                Positioned(
                  top: Spacing.md,
                  right: Spacing.md,
                  child: _FacilityTag(
                    label:
                        facility.type == 'daily'
                            ? l10n.facTypeDaily
                            : facility.type == 'halfday'
                            ? l10n.facTypeHalfDay
                            : l10n.facTypeHourly,
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
                      text: l10n.facCapacity(facility.capacity.toString()),
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
                        ' / $unitLabel',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                      if (memberPrice != null) ...[
                        const Spacer(),
                        Text(
                          l10n.facMemberPrice(Formatters.currency(memberPrice)),
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
                    child: Text(l10n.facViewBook),
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
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(
      Spacing.lg,
      Spacing.lg,
      Spacing.lg,
      Spacing.xl,
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.l10n.facIntroTitle,
          style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: Spacing.xs),
        Text(context.l10n.facIntroSubtitle),
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
      style: const TextStyle(
        fontSize: AppTextTheme.minSize,
        fontWeight: FontWeight.w700,
      ),
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
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    booking.facilityName ?? '-',
                    style: theme.textTheme.titleMedium,
                  ),
                ),
                const SizedBox(width: Spacing.sm),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    BookingStatusChip(status: booking.bookingStatus),
                    const SizedBox(height: 4),
                    PaymentStatusChip(status: booking.paymentStatus),
                  ],
                ),
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
              context.l10n.ecomTotalLabel(
                Formatters.currency(booking.totalPrice),
              ),
              style: theme.textTheme.titleSmall?.copyWith(
                color: AppColors.movementGreen,
              ),
            ),
            if (booking.adminRemarks?.isNotEmpty == true) ...[
              const SizedBox(height: Spacing.sm),
              Text(
                context.l10n.facAdminRemarks(booking.adminRemarks!),
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ],
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
