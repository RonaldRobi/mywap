import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../member/presentation/widgets/notification_bell.dart';
import '../../member/presentation/widgets/shell_scaffold_key.dart';
import '../application/organization_providers.dart';
import '../data/models/organization_info.dart';

/// Info Organisasi — maklumat + carta organisasi. Sepadan dengan web
/// `/info-organisasi` (OrganizationInfoController::show).
class OrganizationInfoScreen extends ConsumerWidget {
  const OrganizationInfoScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final infoAsync = ref.watch(organizationInfoProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const AppMenuButton(),
        title: const Text('Info Organisasi'),
        actions: const [NotificationBell(), SizedBox(width: Spacing.sm)],
      ),
      body: infoAsync.when(
        data: (data) => _OrganizationContent(
          data: data,
          onRefresh: () async => ref.invalidate(organizationInfoProvider),
        ),
        loading: () => const _OrganizationSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(organizationInfoProvider),
        ),
      ),
    );
  }
}

class _OrganizationContent extends StatelessWidget {
  const _OrganizationContent({required this.data, required this.onRefresh});

  final OrganizationInfoData data;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final org = data.organization;
    if (org == null) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: const SingleChildScrollView(
          physics: AlwaysScrollableScrollPhysics(),
          child: EmptyState(
            icon: Icons.info_outline,
            message: 'Tiada maklumat organisasi untuk dipaparkan.',
          ),
        ),
      );
    }

    final chart = data.chartMembers;

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.only(bottom: Spacing.xl),
      children: [
        Container(
          margin: const EdgeInsets.all(Spacing.lg),
          padding: const EdgeInsets.all(Spacing.xl),
          decoration: BoxDecoration(
            gradient: const LinearGradient(colors: AppColors.heroGradient),
            borderRadius: AppRadius.hero,
            boxShadow: AppShadows.card,
          ),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: AppRadius.lg,
                child: Container(
                  width: 64,
                  height: 64,
                  color: AppColors.white.withValues(alpha: .14),
                  child: org.logo_path?.isNotEmpty == true
                      ? AppImage(org.logo_path, fit: BoxFit.contain)
                      : const Icon(
                          Icons.account_balance_outlined,
                          color: AppColors.white,
                          size: 32,
                        ),
                ),
              ),
              const SizedBox(width: Spacing.lg),
              Expanded(
                child: Text(
                  org.name ?? '-',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        color: AppColors.white,
                        fontWeight: FontWeight.w800,
                      ),
                ),
              ),
            ],
          ),
        ),
        if (org.description?.isNotEmpty == true) ...[
          const SectionHeader('Maklumat'),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
            child: Text(
              org.description!,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.6),
            ),
          ),
          const SizedBox(height: Spacing.md),
        ],
        _SocialLinksRow(org: org),
        const SectionHeader('Carta Organisasi'),
        if (chart.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: Spacing.lg),
            child: EmptyState(
              icon: Icons.groups_outlined,
              message: 'Carta organisasi belum tersedia.',
            ),
          )
        else
          ...chart.map((member) => _ChartMemberTile(member: member)),
      ],
      ),
    );
  }
}

class _SocialLinksRow extends StatelessWidget {
  const _SocialLinksRow({required this.org});
  final OrganizationDetail org;

  @override
  Widget build(BuildContext context) {
    final links = <(IconData, String?)>[
      (Icons.public, org.website_url),
      (Icons.facebook, org.facebook_url),
      (Icons.camera_alt_outlined, org.instagram_url),
      (Icons.play_circle_outline, org.youtube_url),
    ].where((e) => e.$2 != null && e.$2!.isNotEmpty).toList();

    if (links.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
      child: Wrap(
        spacing: Spacing.sm,
        children: [
          for (final link in links)
            IconButton.filledTonal(
              onPressed: () => launchUrl(Uri.parse(link.$2!)),
              icon: Icon(link.$1),
            ),
        ],
      ),
    );
  }
}

class _ChartMemberTile extends StatelessWidget {
  const _ChartMemberTile({required this.member});
  final OrgChartMember member;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.md),
        child: Row(
          children: [
            ClipOval(
              child: AppImage(
                member.image_path,
                width: 52,
                height: 52,
                fit: BoxFit.cover,
              ),
            ),
            const SizedBox(width: Spacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    member.name ?? '-',
                    style: Theme.of(context).textTheme.titleSmall,
                  ),
                  Text(
                    member.position ?? '-',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
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

class _OrganizationSkeleton extends StatelessWidget {
  const _OrganizationSkeleton();

  @override
  Widget build(BuildContext context) => ListView(
        padding: const EdgeInsets.all(Spacing.lg),
        children: const [
          SkeletonBox(height: 140, radius: 28),
          SizedBox(height: Spacing.xl),
          SkeletonBox(height: 20, width: 160),
          SizedBox(height: Spacing.md),
          SkeletonBox(height: 80),
          SizedBox(height: Spacing.md),
          SkeletonBox(height: 80),
        ],
      );
}
