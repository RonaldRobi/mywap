import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/facility_providers.dart';
import '../data/models/facility.dart';
import 'facility_booking_sheet.dart';

class FacilityDetailScreen extends ConsumerWidget {
  const FacilityDetailScreen({super.key, required this.facilityId});

  final int facilityId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detailAsync = ref.watch(facilityDetailProvider(facilityId));

    return Scaffold(
      appBar: AppBar(title: const Text('Butiran Kemudahan')),
      body: detailAsync.when(
        data: (detail) => _DetailContent(
          detail: detail,
          onBook: () => showFacilityBookingSheet(context, detail),
        ),
        loading: () => const _DetailSkeleton(),
        error: (error, _) => ErrorRetry(
          message:
              error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(facilityDetailProvider(facilityId)),
        ),
      ),
    );
  }
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({required this.detail, required this.onBook});

  final FacilityDetailData detail;
  final VoidCallback onBook;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final facility = detail.facility;
    final price = facility?.pricePerUnit;

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: EdgeInsets.zero,
            children: [
              AppImage(
                facility?.imageUrl,
                height: 220,
                width: double.infinity,
                borderRadius: BorderRadius.zero,
              ),
              Padding(
                padding: const EdgeInsets.all(Spacing.xl),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(facility?.name ?? '-',
                        style: theme.textTheme.headlineSmall),
                    if (facility?.organizationName != null) ...[
                      const SizedBox(height: Spacing.xs),
                      Text(
                        facility!.organizationName!,
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: AppColors.movementGreen,
                        ),
                      ),
                    ],
                    const SizedBox(height: Spacing.md),
                    _InfoRow(
                      icon: Icons.location_on_outlined,
                      text: facility?.location ?? '-',
                    ),
                    _InfoRow(
                      icon: Icons.people_outline,
                      text:
                          'Kapasiti: ${facility?.capacity?.toString() ?? '-'}',
                    ),
                    _InfoRow(
                      icon: Icons.attach_money_outlined,
                      text:
                          '${Formatters.currency(price)} / ${facility?.type == 'daily' ? 'hari' : facility?.type == 'halfday' ? 'separuh hari' : 'jam'}',
                    ),
                    if (facility?.description != null &&
                        (facility!.description!.isNotEmpty)) ...[
                      const SizedBox(height: Spacing.xl),
                      Text('Penerangan', style: theme.textTheme.titleLarge),
                      const SizedBox(height: Spacing.sm),
                      Text(facility.description!,
                          style: theme.textTheme.bodyLarge),
                    ],
                    if (detail.myBookings.isNotEmpty) ...[
                      const SizedBox(height: Spacing.xl),
                      const SectionHeader('Tempahan Saya'),
                      for (final booking in detail.myBookings)
                        _MyBookingTile(booking: booking),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(Spacing.lg),
            child: FilledButton.icon(
              onPressed: onBook,
              icon: const Icon(Icons.event_available),
              label: const Text('Tempah'),
            ),
          ),
        ),
      ],
    );
  }
}

class _MyBookingTile extends StatelessWidget {
  const _MyBookingTile({required this.booking});

  final FacilityBooking booking;

  @override
  Widget build(BuildContext context) {
    final start = _formatDateTime(booking.startDatetime);
    return Card(
      margin: const EdgeInsets.symmetric(vertical: Spacing.sm),
      child: ListTile(
        leading: const Icon(Icons.schedule, color: AppColors.movementGreen),
        title: Text(start ?? '-'),
        subtitle: Text(booking.bookingStatus ?? '-'),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.text});

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
              style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DetailSkeleton extends StatelessWidget {
  const _DetailSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      children: const [
        SkeletonBox(height: 220, radius: 0),
        Padding(
          padding: EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SkeletonBox(height: 28, width: 260),
              SizedBox(height: 16),
              SkeletonBox(height: 18),
              SizedBox(height: 8),
              SkeletonBox(height: 18),
              SizedBox(height: 24),
              SkeletonBox(height: 120),
            ],
          ),
        ),
      ],
    );
  }
}

const List<String> _months = [
  'Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun',
  'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis',
];

String? _formatDateTime(String? iso) {
  final dt = DateTime.tryParse(iso ?? '');
  if (dt == null) return null;
  String pad(int v) => v.toString().padLeft(2, '0');
  return '${dt.day} ${_months[dt.month - 1]} ${dt.year}, '
      '${pad(dt.hour)}:${pad(dt.minute)}';
}
