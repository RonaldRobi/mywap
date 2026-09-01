import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../member/presentation/widgets/notification_bell.dart';
import '../../member/presentation/widgets/shell_scaffold_key.dart';
import '../application/referral_providers.dart';
import '../data/models/referral_data.dart';

/// Jemput Ahli (Referral) — sepadan dengan web `/member/referral`
/// (MemberDashboardController::referral). Server sudah keluarkan QR sebagai
/// SVG mentah, tapi kami jana semula client-side dgn qr_flutter (dari
/// `referral_link` yang sama) supaya tidak perlu bungkus parser SVG khas.
class ReferralScreen extends ConsumerWidget {
  const ReferralScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dataAsync = ref.watch(referralDataProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const AppMenuButton(),
        title: const Text('Jemput Ahli'),
        actions: const [NotificationBell(), SizedBox(width: Spacing.sm)],
      ),
      body: dataAsync.when(
        data: (data) => _ReferralContent(
          data: data,
          onRefresh: () async => ref.invalidate(referralDataProvider),
        ),
        loading: () => const _ReferralSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(referralDataProvider),
        ),
      ),
    );
  }
}

class _ReferralContent extends StatelessWidget {
  const _ReferralContent({required this.data, required this.onRefresh});
  final ReferralData data;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final link = data.referral_link ?? '';
    final members = data.referred_members;

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        Container(
          padding: const EdgeInsets.all(Spacing.xl),
          decoration: BoxDecoration(
            gradient: const LinearGradient(colors: AppColors.heroGradient),
            borderRadius: AppRadius.hero,
            boxShadow: AppShadows.card,
          ),
          child: Column(
            children: [
              Text(
                'Jemput rakan menyertai myWAP',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: AppColors.white,
                      fontWeight: FontWeight.w700,
                    ),
              ),
              const SizedBox(height: Spacing.lg),
              if (link.isNotEmpty)
                Container(
                  padding: const EdgeInsets.all(Spacing.md),
                  decoration: BoxDecoration(
                    color: AppColors.white,
                    borderRadius: AppRadius.lg,
                  ),
                  child: QrImageView(
                    data: link,
                    size: 180,
                    backgroundColor: AppColors.white,
                  ),
                ),
              const SizedBox(height: Spacing.lg),
              Text(
                'No. Ahli: ${data.member_no ?? '-'}',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.textOnDark,
                    ),
              ),
              const SizedBox(height: Spacing.md),
              OutlinedButton.icon(
                onPressed: link.isEmpty
                    ? null
                    : () => Clipboard.setData(ClipboardData(text: link)),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.white,
                  side: const BorderSide(color: AppColors.white),
                ),
                icon: const Icon(Icons.copy_outlined),
                label: const Text('Salin Pautan Rujukan'),
              ),
            ],
          ),
        ),
        const SizedBox(height: Spacing.xl),
        Row(
          children: [
            Expanded(
              child: _StatCard(label: 'Jumlah', value: '${data.stats.total}'),
            ),
            const SizedBox(width: Spacing.md),
            Expanded(
              child: _StatCard(label: 'Aktif', value: '${data.stats.active}'),
            ),
            const SizedBox(width: Spacing.md),
            Expanded(
              child: _StatCard(
                label: 'Menunggu',
                value: '${data.stats.pending}',
              ),
            ),
          ],
        ),
        const SizedBox(height: Spacing.xl),
        Text(
          'Ahli Dijemput',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: Spacing.md),
        if (members.isEmpty)
          const EmptyState(
            icon: Icons.person_add_alt_outlined,
            message: 'Belum ada ahli yang dijemput menggunakan pautan anda.',
          )
        else
          ...members.map((m) => _ReferredMemberTile(member: m)),
      ],
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(Spacing.lg),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: AppRadius.card,
          boxShadow: AppShadows.subtle,
        ),
        child: Column(
          children: [
            Text(
              value,
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: AppColors.movementGreen,
                  ),
            ),
            const SizedBox(height: 4),
            Text(
              label,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            ),
          ],
        ),
      );
}

class _ReferredMemberTile extends StatelessWidget {
  const _ReferredMemberTile({required this.member});
  final ReferredMember member;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: member.isActive
              ? AppColors.movementSoftGreen
              : AppColors.divider,
          child: Icon(
            member.isActive ? Icons.check : Icons.hourglass_empty,
            color: AppColors.movementNavy,
            size: 18,
          ),
        ),
        title: Text(member.name ?? '-'),
        subtitle: Text(member.member_no ?? '-'),
        trailing: Text(
          member.isActive ? 'Aktif' : 'Menunggu',
          style: TextStyle(
            color: member.isActive ? AppColors.success : AppColors.warning,
            fontWeight: FontWeight.w600,
            fontSize: 12,
          ),
        ),
      ),
    );
  }
}

class _ReferralSkeleton extends StatelessWidget {
  const _ReferralSkeleton();

  @override
  Widget build(BuildContext context) => ListView(
        padding: const EdgeInsets.all(Spacing.lg),
        children: const [
          SkeletonBox(height: 320, radius: 28),
          SizedBox(height: Spacing.xl),
          SkeletonBox(height: 80),
        ],
      );
}
