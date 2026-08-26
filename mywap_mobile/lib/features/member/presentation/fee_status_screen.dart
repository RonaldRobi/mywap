import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/member_core_providers.dart';
import '../data/models/fee_status.dart';

/// Membership fee status (due / active) with amounts.
class FeeStatusScreen extends ConsumerWidget {
  const FeeStatusScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final feeAsync = ref.watch(memberFeeStatusProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Status Yuran')),
      body: feeAsync.when(
        data: (fee) => _FeeStatusContent(fee: fee),
        loading: () => const Padding(
          padding: EdgeInsets.all(Spacing.lg),
          child: SkeletonBox(height: 260, radius: 16),
        ),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(memberFeeStatusProvider),
        ),
      ),
    );
  }
}

class _FeeStatusContent extends StatelessWidget {
  const _FeeStatusContent({required this.fee});

  final FeeStatus fee;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDue = fee.isDue;
    final amountDue = fee.amount_due ?? 0;

    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        Card(
          margin: EdgeInsets.zero,
          child: Padding(
            padding: const EdgeInsets.all(Spacing.xl),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text('Status Yuran', style: theme.textTheme.titleMedium),
                    ),
                    _StatusBadge(active: !isDue),
                  ],
                ),
                const SizedBox(height: Spacing.xl),
                _InfoRow(
                  label: 'Amaun Yuran Tahunan',
                  value: _formatMoney(fee.fee_amount),
                ),
                if (isDue) ...[
                  const SizedBox(height: Spacing.md),
                  _InfoRow(
                    label: 'Amaun Belum Dibayar',
                    value: _formatMoney(amountDue),
                    emphasize: true,
                  ),
                ] else if (fee.last_paid_at != null) ...[
                  const SizedBox(height: Spacing.md),
                  _InfoRow(
                    label: 'Tarikh Dibayar',
                    value: _formatDate(fee.last_paid_at),
                  ),
                ],
              ],
            ),
          ),
        ),
        const SizedBox(height: Spacing.lg),
        Text(
          isDue
              ? 'Sila jelaskan yuran keahlian anda untuk mengekalkan status ahli yang aktif.'
              : 'Yuran keahlian anda adalah LUNAS. Terima kasih atas sokongan anda.',
          style: theme.textTheme.bodyMedium?.copyWith(
            color: AppColors.textSecondary,
          ),
        ),
      ],
    );
  }

  String _formatMoney(num? value) {
    return 'RM ${(value ?? 0).toStringAsFixed(2)}';
  }

  String _formatDate(String? iso) {
    if (iso == null || iso.isEmpty) return '-';
    final datePart = iso.split('T').first;
    final parts = datePart.split('-');
    if (parts.length == 3) {
      final day = int.tryParse(parts[2]);
      final month = int.tryParse(parts[1]);
      final year = int.tryParse(parts[0]);
      if (day != null && month != null && year != null) {
        return '$day/$month/$year';
      }
    }
    return iso;
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.md, vertical: 6),
      decoration: BoxDecoration(
        color: active ? AppColors.success : AppColors.warning,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        active ? 'AKTIF' : 'BELUM BAYAR',
        style: const TextStyle(
          color: AppColors.white,
          fontWeight: FontWeight.w700,
          fontSize: 12,
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.label,
    required this.value,
    this.emphasize = false,
  });

  final String label;
  final String value;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            label,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
        ),
        const SizedBox(width: Spacing.md),
        Text(
          value,
          style: emphasize
              ? theme.textTheme.titleMedium?.copyWith(
                  color: AppColors.warning,
                  fontWeight: FontWeight.w700,
                )
              : theme.textTheme.titleMedium,
        ),
      ],
    );
  }
}
