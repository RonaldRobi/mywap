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
import '../../../shared/widgets/app_back_button.dart';
import '../../member/presentation/widgets/notification_bell.dart';
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
        leading: const AppBackButton(),
        title: const Text('Info Organisasi'),
        actions: const [NotificationBell(), SizedBox(width: Spacing.sm)],
      ),
      body: infoAsync.when(
        data:
            (data) => _OrganizationContent(
              data: data,
              onRefresh: () async => ref.invalidate(organizationInfoProvider),
            ),
        loading: () => const _OrganizationSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : 'Ralat tidak dijangka.',
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
          Padding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.lg,
              Spacing.lg,
              Spacing.lg,
              Spacing.sm,
            ),
            child: _OrgHeaderCard(org: org),
          ),
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

class _OrgHeaderCard extends StatelessWidget {
  const _OrgHeaderCard({required this.org});

  final OrganizationDetail org;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final hasLogo = org.logo_path?.isNotEmpty == true;
    final hasDescription = org.description?.isNotEmpty == true;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(Spacing.xl),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: AppRadius.card,
        boxShadow: AppShadows.subtle,
      ),
      child: Column(
        children: [
          Container(
            width: 120,
            height: 120,
            clipBehavior: Clip.antiAlias,
            decoration: BoxDecoration(
              color: AppColors.softGreenSurface,
              borderRadius: AppRadius.xl,
              border: Border.all(color: AppColors.paleGreen),
            ),
            child: hasLogo
                ? AppImage(
                    org.logo_path,
                    fit: BoxFit.contain,
                    borderRadius: BorderRadius.zero,
                  )
                : const Icon(
                    Icons.account_balance_outlined,
                    color: AppColors.movementGreen,
                    size: 56,
                  ),
          ),
          const SizedBox(height: Spacing.lg),
          Text(
            org.name ?? '-',
            textAlign: TextAlign.center,
            style: theme.textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          if (hasDescription) ...[
            const SizedBox(height: Spacing.md),
            Text(
              org.description!,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: AppColors.textSecondary,
                height: 1.6,
              ),
            ),
          ],
          const SizedBox(height: Spacing.lg),
          _SocialLinksRow(org: org),
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
    final links =
        <(IconData, String?)>[
          (Icons.public, org.website_url),
          (Icons.facebook, org.facebook_url),
          (Icons.alternate_email, org.twitter_url),
          (Icons.camera_alt_outlined, org.instagram_url),
          (Icons.play_circle_outline, org.youtube_url),
          (Icons.music_note, org.tiktok_url),
        ].where((e) => e.$2 != null && e.$2!.isNotEmpty).toList();

    if (links.isEmpty) return const SizedBox.shrink();

    return Wrap(
      alignment: WrapAlignment.center,
      spacing: Spacing.xs,
      children: [
        for (final link in links)
          IconButton.filledTonal(
            onPressed: () => launchUrl(Uri.parse(link.$2!)),
            icon: Icon(link.$1),
          ),
      ],
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
            _MemberAvatar(member: member),
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

class _MemberAvatar extends StatelessWidget {
  const _MemberAvatar({required this.member});

  final OrgChartMember member;

  @override
  Widget build(BuildContext context) {
    final hasImage = member.image_path?.isNotEmpty == true;

    if (hasImage) {
      return ClipRRect(
        borderRadius: AppRadius.sm,
        child: SizedBox(
          width: 52,
          height: 52,
          child: AppImage(
            member.image_path,
            fit: BoxFit.cover,
            borderRadius: BorderRadius.zero,
          ),
        ),
      );
    }

    return Container(
      width: 52,
      height: 52,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: AppColors.paleGreen,
        borderRadius: AppRadius.sm,
      ),
      child: Text(
        _initials(member.name),
        style: const TextStyle(
          color: AppColors.movementGreen,
          fontSize: 18,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

String _initials(String? name) {
  final raw = name?.trim() ?? '';
  if (raw.isEmpty) return '?';
  final parts = raw.split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
  if (parts.isEmpty) return '?';
  if (parts.length == 1) {
    return parts.first.substring(0, 1).toUpperCase();
  }
  return (parts.first.substring(0, 1) + parts.last.substring(0, 1)).toUpperCase();
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
