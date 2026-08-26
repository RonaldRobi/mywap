import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/list_card.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/admin_providers.dart';
import '../data/models/admin_models.dart';

/// Admin fee tracking (`/admin/fees`).
class AdminFeesScreen extends ConsumerWidget {
  const AdminFeesScreen({super.key});

  static const _filters = <(String, String)>[
    ('', 'Semua'),
    ('paid', 'Lunas'),
    ('pending', 'Belum Bayar'),
  ];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final status = ref.watch(_statusProvider);
    final feesAsync = ref.watch(adminFeesProvider(status));

    return Scaffold(
      appBar: AppBar(title: const Text('Yuran')),
      body: Column(
        children: [
          SizedBox(
            height: 52,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              children: [
                for (final (value, label) in _filters)
                  Padding(
                    padding: const EdgeInsets.only(
                      right: Spacing.sm,
                      top: Spacing.sm,
                      bottom: Spacing.sm,
                    ),
                    child: ChoiceChip(
                      label: Text(label),
                      selected: status == value,
                      onSelected: (_) =>
                          ref.read(_statusProvider.notifier).state = value,
                      showCheckmark: false,
                      selectedColor: AppColors.movementDarkGreen,
                      labelStyle: TextStyle(
                        color: status == value ? AppColors.white : AppColors.textPrimary,
                        fontWeight: FontWeight.w600,
                      ),
                      backgroundColor: AppColors.surface,
                      side: const BorderSide(color: AppColors.divider),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: feesAsync.when(
              data: (data) => _FeesContent(data: data),
              loading: () => const _FeesSkeleton(),
              error: (error, _) => ErrorRetry(
                message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
                onRetry: () => ref.invalidate(adminFeesProvider(status)),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

final _statusProvider = StateProvider<String>((ref) => '');

class _FeesContent extends StatelessWidget {
  const _FeesContent({required this.data});

  final FeesData data;

  @override
  Widget build(BuildContext context) {
    if (data.fees.isEmpty) {
      return const EmptyState(
        icon: Icons.receipt_long_outlined,
        message: 'Tiada rekod yuran.',
      );
    }
    final summary = data.summary;
    return ListView(
      padding: const EdgeInsets.only(bottom: Spacing.xl),
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(Spacing.lg, Spacing.sm, Spacing.lg, Spacing.xs),
          child: Row(
            children: [
              Expanded(
                child: _SummaryCard(
                  label: 'Lunas',
                  value: '${summary.paidCount}',
                  icon: Icons.check_circle_outline,
                  color: AppColors.success,
                ),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: _SummaryCard(
                  label: 'Belum Bayar',
                  value: '${summary.pendingCount}',
                  icon: Icons.schedule,
                  color: AppColors.warning,
                ),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: _SummaryCard(
                  label: 'Hasil',
                  value: Formatters.currency(summary.revenue),
                  icon: Icons.payments_outlined,
                  color: AppColors.movementGreen,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: Spacing.sm),
        for (final fee in data.fees) _FeeCard(fee: fee),
      ],
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({
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
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: color, size: 22),
            const SizedBox(height: Spacing.sm),
            Text(value, style: theme.textTheme.titleMedium),
            const SizedBox(height: 2),
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
    );
  }
}

class _FeeCard extends StatelessWidget {
  const _FeeCard({required this.fee});

  final AdminFee fee;

  @override
  Widget build(BuildContext context) {
    return ListCard(
      title: fee.name.isEmpty ? 'Tanpa Nama' : fee.name,
      subtitle: [
        if (fee.memberNo.isNotEmpty) 'No. ${fee.memberNo}',
        if (fee.year.isNotEmpty) 'Yuran ${fee.year}',
      ].join(' · '),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            Formatters.currency(fee.amount),
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: fee.isPaid ? AppColors.success : AppColors.warning,
                ),
          ),
          const SizedBox(width: Spacing.sm),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: fee.isPaid
                  ? AppColors.movementSoftGreen
                  : AppColors.warning.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              fee.isPaid ? 'Lunas' : 'Belum Bayar',
              style: TextStyle(
                color: fee.isPaid ? AppColors.movementNavy : AppColors.warning,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FeesSkeleton extends StatelessWidget {
  const _FeesSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: const [
        SkeletonBox(height: 96),
        SizedBox(height: Spacing.lg),
        SkeletonBox(height: 76),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 76),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 76),
      ],
    );
  }
}
