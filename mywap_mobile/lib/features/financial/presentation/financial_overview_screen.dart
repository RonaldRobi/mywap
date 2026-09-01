import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../member/presentation/widgets/notification_bell.dart';
import '../../member/presentation/widgets/shell_scaffold_key.dart';
import '../application/financial_providers.dart';
import '../data/models/financial_overview.dart';

/// Yuran & Kewangan — sepadan dengan web `/member/financial/overview`
/// (FinancialController::memberOverview): status yuran, kempen infaq aktif,
/// sejarah bayaran.
class FinancialOverviewScreen extends ConsumerWidget {
  const FinancialOverviewScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dataAsync = ref.watch(financialOverviewProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const AppMenuButton(),
        title: const Text('Yuran & Kewangan'),
        actions: const [NotificationBell(), SizedBox(width: Spacing.sm)],
      ),
      body: dataAsync.when(
        data: (data) => _FinancialContent(
          data: data,
          onRefresh: () async => ref.invalidate(financialOverviewProvider),
        ),
        loading: () => const _FinancialSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(financialOverviewProvider),
        ),
      ),
    );
  }
}

class _FinancialContent extends StatelessWidget {
  const _FinancialContent({required this.data, required this.onRefresh});
  final FinancialOverviewData data;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final fee = data.fee_status;
    final campaigns = data.campaigns;
    final history = data.payment_history;

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        _FeeStatusCard(fee: fee),
        const SizedBox(height: Spacing.xl),
        Text(
          'Kempen Infaq Aktif',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: Spacing.md),
        if (campaigns.isEmpty)
          const EmptyState(
            icon: Icons.volunteer_activism_outlined,
            message: 'Tiada kempen infaq aktif buat masa ini.',
          )
        else
          ...campaigns.map(
            (c) => _CampaignCard(
              campaign: c,
              onTap: () => context.push('/infaq/${c.slug}'),
            ),
          ),
        const SizedBox(height: Spacing.xl),
        Text(
          'Sejarah Pembayaran',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: Spacing.md),
        if (history.isEmpty)
          const EmptyState(
            icon: Icons.receipt_long_outlined,
            message: 'Tiada sejarah pembayaran lagi.',
          )
        else
          ...history.map((p) => _PaymentTile(payment: p)),
      ],
      ),
    );
  }
}

class _FeeStatusCard extends StatelessWidget {
  const _FeeStatusCard({required this.fee});
  final FeeStatusSummary? fee;

  @override
  Widget build(BuildContext context) {
    final active = fee?.isActive ?? false;
    final accent = active ? AppColors.success : AppColors.warning;

    return Container(
      padding: const EdgeInsets.all(Spacing.xl),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: .08),
        borderRadius: AppRadius.hero,
        border: Border.all(color: accent.withValues(alpha: .3)),
      ),
      child: Row(
        children: [
          Icon(
            active ? Icons.verified_outlined : Icons.error_outline,
            color: accent,
            size: 32,
          ),
          const SizedBox(width: Spacing.lg),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  active ? 'Yuran Ahli Aktif' : 'Yuran Belum Dibayar',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.movementNavy,
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const SizedBox(height: 4),
                if (active)
                  Text(
                    'Bayaran terakhir: ${fee?.last_paid_at ?? '-'}',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                  )
                else
                  Text(
                    'Amaun tertunggak: ${Formatters.currency(fee?.amount_due ?? 0)}',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CampaignCard extends StatelessWidget {
  const _CampaignCard({required this.campaign, required this.onTap});
  final FinancialCampaign campaign;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final progress = ((campaign.progress_percent ?? 0) / 100).clamp(0, 1).toDouble();
    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: AppRadius.card,
        child: Padding(
          padding: const EdgeInsets.all(Spacing.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                campaign.title ?? '-',
                style: Theme.of(context).textTheme.titleSmall,
              ),
              const SizedBox(height: Spacing.sm),
              ClipRRect(
                borderRadius: AppRadius.sm,
                child: LinearProgressIndicator(
                  value: progress,
                  minHeight: 8,
                  backgroundColor: AppColors.divider,
                  color: AppColors.movementGreen,
                ),
              ),
              const SizedBox(height: Spacing.sm),
              Text(
                '${Formatters.currency(campaign.current_amount ?? 0)} / ${Formatters.currency(campaign.target_amount ?? 0)} (${campaign.progress_percent ?? 0}%)',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PaymentTile extends StatelessWidget {
  const _PaymentTile({required this.payment});
  final PaymentHistoryItem payment;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: Icon(
          payment.isSuccessful ? Icons.check_circle_outline : Icons.pending_outlined,
          color: payment.isSuccessful ? AppColors.success : AppColors.warning,
        ),
        title: Text(_typeLabel(payment.payable_type)),
        subtitle: Text(payment.created_at ?? '-'),
        trailing: Text(
          Formatters.currency(payment.amount ?? 0),
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
    );
  }

  String _typeLabel(String? type) {
    switch (type) {
      case 'membership_fee':
        return 'Yuran Keahlian';
      case 'infaq_donation':
        return 'Infaq';
      default:
        return type ?? 'Bayaran';
    }
  }
}

class _FinancialSkeleton extends StatelessWidget {
  const _FinancialSkeleton();

  @override
  Widget build(BuildContext context) => ListView(
        padding: const EdgeInsets.all(Spacing.lg),
        children: const [
          SkeletonBox(height: 110, radius: 28),
          SizedBox(height: Spacing.xl),
          SkeletonBox(height: 90),
          SizedBox(height: Spacing.md),
          SkeletonBox(height: 90),
        ],
      );
}
