import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/event_providers.dart';
import '../data/models/event_registration.dart';

/// Pendaftaran Saya — senarai pendaftaran acara ahli.
class MyRegistrationsScreen extends ConsumerWidget {
  const MyRegistrationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(myRegistrationsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Pendaftaran Saya')),
      body: async.when(
        data: (items) => _RegistrationsList(
          items: items,
          onRefresh: () async => ref.invalidate(myRegistrationsProvider),
        ),
        loading: () => const _RegistrationsSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(myRegistrationsProvider),
        ),
      ),
    );
  }
}

class _RegistrationsList extends StatelessWidget {
  const _RegistrationsList({required this.items, required this.onRefresh});

  final List<EventRegistration> items;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const EmptyState(
        icon: Icons.event_available_outlined,
        message: 'Anda belum mendaftar mana-mana acara.',
      );
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.builder(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(Spacing.lg),
        itemCount: items.length,
        itemBuilder: (context, index) => _RegistrationCard(item: items[index]),
      ),
    );
  }
}

class _RegistrationCard extends StatelessWidget {
  const _RegistrationCard({required this.item});

  final EventRegistration item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final event = item.event;

    return Card(
      margin: const EdgeInsets.only(bottom: Spacing.lg),
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    event?.title ?? item.formTitle ?? 'Acara',
                    style: theme.textTheme.titleMedium,
                  ),
                ),
                _StatusChip(
                  label: item.statusLabel ?? item.status ?? '',
                  color: item.status == 'confirmed'
                      ? AppColors.success
                      : AppColors.warning,
                ),
              ],
            ),
            const SizedBox(height: Spacing.sm),
            Row(
              children: [
                const Icon(Icons.schedule, size: 16, color: AppColors.textSecondary),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    event?.startFormatted ?? '',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ),
              ],
            ),
            const Divider(height: Spacing.lg * 2),
            Row(
              children: [
                Icon(
                  item.isPaid ? Icons.check_circle : Icons.pending,
                  size: 18,
                  color: item.isPaid ? AppColors.success : AppColors.warning,
                ),
                const SizedBox(width: 6),
                Text(
                  item.isPaid ? 'Bayaran Selesai' : 'Bayaran Belum Selesai',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
                const Spacer(),
                if (item.attended)
                  const Text(
                    'Hadir',
                    style: TextStyle(
                      color: AppColors.movementGreen,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
              ],
            ),
            if (item.registrationNo != null) ...[
              const SizedBox(height: Spacing.sm),
              Text(
                'No. Pendaftaran: ${item.registrationNo}',
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

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w700,
          fontSize: 12,
        ),
      ),
    );
  }
}

class _RegistrationsSkeleton extends StatelessWidget {
  const _RegistrationsSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      itemCount: 4,
      itemBuilder: (_, __) => const Padding(
        padding: EdgeInsets.only(bottom: Spacing.lg),
        child: SkeletonBox(height: 160, radius: 16),
      ),
    );
  }
}
