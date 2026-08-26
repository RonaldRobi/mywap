import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/member_core_providers.dart';
import '../data/models/member_card_data.dart';

/// Digital member card with profile details and a scanable QR code.
class MemberCardScreen extends ConsumerWidget {
  const MemberCardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cardAsync = ref.watch(memberCardProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Kad Ahli')),
      body: cardAsync.when(
        data: (data) => _CardContent(data: data),
        loading: () => const _CardSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(memberCardProvider),
        ),
      ),
    );
  }
}

class _CardContent extends StatelessWidget {
  const _CardContent({required this.data});

  final MemberCardData data;

  @override
  Widget build(BuildContext context) {
    final card = data.card;
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        _MemberCard(card: card),
        const SizedBox(height: Spacing.xl),
        const Center(
          child: Text(
            'Imbas kod QR untuk pengesahan keahlian',
            style: TextStyle(color: AppColors.textSecondary),
          ),
        ),
        const SizedBox(height: Spacing.md),
        Card(
          margin: EdgeInsets.zero,
          child: Padding(
            padding: const EdgeInsets.all(Spacing.xl),
            child: Center(child: _buildQr(card?.qrValue)),
          ),
        ),
      ],
    );
  }

  Widget _buildQr(String? value) {
    if (value == null || value.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(24),
        child: Icon(Icons.qr_code_2, size: 120, color: AppColors.divider),
      );
    }
    return QrImageView(
      data: value,
      version: QrVersions.auto,
      size: 220,
      padding: const EdgeInsets.all(8),
      backgroundColor: AppColors.white,
      gapless: true,
    );
  }
}

class _MemberCard extends StatelessWidget {
  const _MemberCard({required this.card});

  final MemberCardInfo? card;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final org = card?.organization;

    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [AppColors.movementDarkGreen, AppColors.movementGreen],
          ),
        ),
        padding: const EdgeInsets.all(Spacing.xl),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (org?.logo_path != null || card?.system_logo_path != null) ...[
              Row(
                children: [
                  ClipOval(
                    child: AppImage(
                      org?.logo_path ?? card?.system_logo_path,
                      width: 44,
                      height: 44,
                    ),
                  ),
                  if (org?.name != null) ...[
                    const SizedBox(width: Spacing.md),
                    Expanded(
                      child: Text(
                        org!.name!,
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: AppColors.white,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: Spacing.xl),
            ],
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        card?.name ?? '-',
                        style: theme.textTheme.headlineSmall?.copyWith(
                          color: AppColors.white,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: Spacing.xs),
                      Text(
                        'No. Ahli: ${card?.member_no ?? '-'}',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: AppColors.movementSoftGreen,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      if (card?.member_since != null) ...[
                        const SizedBox(height: Spacing.xs),
                        Text(
                          'Ahli sejak ${card!.member_since}',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.textOnDark,
                          ),
                        ),
                      ],
                      if (card?.branch_name != null) ...[
                        const SizedBox(height: Spacing.xs),
                        Text(
                          card!.branch_name!,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.textOnDark,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: Spacing.lg),
                ClipOval(
                  child: AppImage(card?.photo_url, width: 72, height: 72),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CardSkeleton extends StatelessWidget {
  const _CardSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: const [
        SkeletonBox(height: 240, radius: 16),
        SizedBox(height: Spacing.xl),
        Center(child: SkeletonBox(height: 20, width: 220)),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 280, radius: 16),
      ],
    );
  }
}
