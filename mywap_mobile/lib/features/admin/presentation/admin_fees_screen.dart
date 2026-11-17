import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

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

/// Skrin Pemantauan Kutipan Yuran Pentadbir (`/admin/fees`).
class AdminFeesScreen extends ConsumerStatefulWidget {
  const AdminFeesScreen({super.key});

  @override
  ConsumerState<AdminFeesScreen> createState() => _AdminFeesScreenState();
}

class _AdminFeesScreenState extends ConsumerState<AdminFeesScreen> {
  final _searchController = TextEditingController();
  String _searchQuery = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final status = ref.watch(_statusProvider);
    final feesAsync = ref.watch(adminFeesProvider(status));
    final filters = <(String, String)>[
      ('', context.l10n.adminFilterAll),
      ('paid', context.l10n.adminFeePaid),
      ('pending', context.l10n.adminFeeUnpaid),
    ];

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text(context.l10n.adminFeesTitle),
      ),
      body: Column(
        children: [
          // 1. Bar Carian Ahli Yuran
          Padding(
            padding: const EdgeInsets.fromLTRB(Spacing.lg, Spacing.sm, Spacing.lg, Spacing.xs),
            child: TextField(
              controller: _searchController,
              onChanged: (val) => setState(() => _searchQuery = val.trim()),
              decoration: InputDecoration(
                hintText: 'Cari nama atau No. Ahli...',
                prefixIcon: const Icon(Icons.search, size: 20),
                suffixIcon: _searchQuery.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchController.clear();
                          setState(() => _searchQuery = '');
                        },
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

          // 2. Cip Penapis Status Pembayaran
          SizedBox(
            height: 48,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              children: [
                for (final (value, label) in filters)
                  Padding(
                    padding: const EdgeInsets.only(right: Spacing.sm),
                    child: ChoiceChip(
                      label: Text(label),
                      selected: status == value,
                      onSelected: (_) =>
                          ref.read(_statusProvider.notifier).state = value,
                      showCheckmark: false,
                      selectedColor: AppColors.movementDarkGreen,
                      labelStyle: TextStyle(
                        color: status == value
                            ? AppColors.white
                            : AppColors.textPrimary,
                        fontWeight: FontWeight.w600,
                        fontSize: 12,
                      ),
                      backgroundColor: AppColors.white,
                      side: const BorderSide(color: AppColors.divider),
                    ),
                  ),
              ],
            ),
          ),

          // 3. Kandungan Data Yuran
          Expanded(
            child: feesAsync.when(
              data: (data) => _FeesContent(
                data: data,
                searchQuery: _searchQuery,
              ),
              loading: () => const _FeesSkeleton(),
              error: (error, _) => ErrorRetry(
                message: error is ApiException
                    ? error.message
                    : context.l10n.adminUnexpectedError,
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
  const _FeesContent({
    required this.data,
    required this.searchQuery,
  });

  final FeesData data;
  final String searchQuery;

  @override
  Widget build(BuildContext context) {
    if (data.fees.isEmpty) {
      return EmptyState(
        icon: Icons.receipt_long_outlined,
        message: context.l10n.adminNoFeeRecords,
      );
    }

    final filteredFees = data.fees.where((fee) {
      if (searchQuery.isEmpty) return true;
      final q = searchQuery.toLowerCase();
      return fee.name.toLowerCase().contains(q) ||
          fee.memberNo.toLowerCase().contains(q);
    }).toList();

    final summary = data.summary;
    return ListView(
      padding: const EdgeInsets.only(bottom: Spacing.xl),
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            Spacing.lg,
            Spacing.sm,
            Spacing.lg,
            Spacing.xs,
          ),
          child: Row(
            children: [
              Expanded(
                child: _SummaryCard(
                  label: context.l10n.adminFeePaid,
                  value: '${summary.paidCount}',
                  icon: Icons.check_circle_outline,
                  color: AppColors.success,
                ),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: _SummaryCard(
                  label: context.l10n.adminFeeUnpaid,
                  value: '${summary.pendingCount}',
                  icon: Icons.schedule,
                  color: AppColors.warning,
                ),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: _SummaryCard(
                  label: context.l10n.adminStatRevenue,
                  value: Formatters.currency(summary.revenue),
                  icon: Icons.payments_outlined,
                  color: AppColors.movementGreen,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: Spacing.sm),
        if (filteredFees.isEmpty)
          const Padding(
            padding: EdgeInsets.all(Spacing.xl),
            child: Center(
              child: Text(
                'Tiada rekod yuran sepadan dengan carian.',
                style: TextStyle(color: AppColors.textSecondary),
              ),
            ),
          )
        else
          for (final fee in filteredFees) _FeeCard(fee: fee),
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
      color: AppColors.white,
      shape: RoundedRectangleBorder(
        borderRadius: AppRadius.card,
        side: const BorderSide(color: AppColors.divider),
      ),
      child: Padding(
        padding: const EdgeInsets.all(Spacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CircleAvatar(
              radius: 14,
              backgroundColor: color.withValues(alpha: 0.12),
              child: Icon(icon, color: color, size: 16),
            ),
            const SizedBox(height: Spacing.sm),
            Text(
              value,
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
                fontSize: 11,
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
      title: fee.name.isEmpty ? context.l10n.adminNoName : fee.name,
      subtitle: [
        if (fee.memberNo.isNotEmpty)
          context.l10n.adminMemberNoPrefix(fee.memberNo),
        if (fee.year.isNotEmpty) context.l10n.adminFeeYear(fee.year),
      ].join(' · '),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            Formatters.currency(fee.amount),
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: fee.isPaid ? AppColors.success : AppColors.warning,
                  fontWeight: FontWeight.w700,
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
              fee.isPaid
                  ? context.l10n.adminFeePaid
                  : context.l10n.adminFeeUnpaid,
              style: TextStyle(
                color: fee.isPaid ? AppColors.movementNavy : AppColors.warning,
                fontSize: AppTextTheme.minSize,
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
        SkeletonBox(height: 80),
        SizedBox(height: Spacing.lg),
        SkeletonBox(height: 70),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 70),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 70),
      ],
    );
  }
}
