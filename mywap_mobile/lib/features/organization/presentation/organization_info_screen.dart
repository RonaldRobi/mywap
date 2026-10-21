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

/// Info Organisasi — ahli boleh memilih PKPIM, ABIM atau WADAH untuk melihat
/// maklumat dan carta organisasi. Sepadan dengan web `/info-organisasi`.
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
        skipLoadingOnRefresh: true,
        data:
            (data) => _OrganizationContent(
              data: data,
              onRefresh: () async {
                ref.invalidate(organizationInfoProvider);
                await ref.read(organizationInfoProvider.future);
              },
              onSelectOrganization: (slug) {
                ref.read(organizationSelectionProvider.notifier).state = slug;
              },
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
  const _OrganizationContent({
    required this.data,
    required this.onRefresh,
    required this.onSelectOrganization,
  });

  final OrganizationInfoData data;
  final Future<void> Function() onRefresh;
  final ValueChanged<String> onSelectOrganization;

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

    final accent = _accentFor(org);
    final chart = data.chartMembers;
    final featured = chart.isNotEmpty ? chart.first : null;
    final rest = chart.length > 1 ? chart.sublist(1) : const <OrgChartMember>[];

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.only(bottom: Spacing.xxl + Spacing.lg),
        children: [
          if (data.organizations.isNotEmpty) ...[
            const Padding(
              padding: EdgeInsets.fromLTRB(
                Spacing.lg,
                Spacing.lg,
                Spacing.lg,
                Spacing.sm,
              ),
              child: Text(
                'Pilih Organisasi',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.1,
                  color: AppColors.textSecondary,
                ),
              ),
            ),
            _OrganizationSelector(
              organizations: data.organizations,
              selectedSlug: org.slug,
              onSelect: onSelectOrganization,
            ),
          ],
          Padding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.lg,
              Spacing.lg,
              Spacing.lg,
              Spacing.sm,
            ),
            child: _OrgHeroCard(
              org: org,
              accent: accent,
              chartCount: chart.length,
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
            child: _StatsRow(
              org: org,
              accent: accent,
              chartCount: chart.length,
            ),
          ),
          if (org.description?.isNotEmpty == true) ...[
            const SectionHeader('Tentang Organisasi'),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(Spacing.lg),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: AppRadius.card,
                  boxShadow: AppShadows.subtle,
                ),
                child: Text(
                  org.description!,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                    height: 1.65,
                  ),
                ),
              ),
            ),
          ],
          if (_socialLinks(org).isNotEmpty) ...[
            const SectionHeader('Hubungi & Media Sosial'),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              child: _SocialLinks(org: org, accent: accent),
            ),
          ],
          const SectionHeader('Carta Organisasi'),
          if (chart.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: Spacing.lg),
              child: EmptyState(
                icon: Icons.groups_outlined,
                message: 'Carta organisasi belum tersedia.',
              ),
            )
          else ...[
            if (featured != null)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
                child: _FeaturedLeader(
                  member: featured,
                  accent: accent,
                  onTap: () => _showMemberDialog(context, featured, accent),
                ),
              ),
            if (rest.isNotEmpty)
              Padding(
                padding: const EdgeInsets.fromLTRB(
                  Spacing.lg,
                  Spacing.md,
                  Spacing.lg,
                  0,
                ),
                child: GridView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: rest.length,
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    crossAxisSpacing: Spacing.md,
                    mainAxisSpacing: Spacing.md,
                    childAspectRatio: 0.56,
                  ),
                  itemBuilder:
                      (_, index) => _ChartMemberCard(
                        member: rest[index],
                        accent: accent,
                        onTap:
                            () =>
                                _showMemberDialog(context, rest[index], accent),
                      ),
                ),
              ),
          ],
        ],
      ),
    );
  }
}

// ─── Organization selector ──────────────────────────────────────────────────

class _OrganizationSelector extends StatelessWidget {
  const _OrganizationSelector({
    required this.organizations,
    required this.selectedSlug,
    required this.onSelect,
  });

  final List<OrganizationDetail> organizations;
  final String? selectedSlug;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 96,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
        itemCount: organizations.length,
        separatorBuilder: (_, __) => const SizedBox(width: Spacing.sm),
        itemBuilder: (context, index) {
          final item = organizations[index];
          final accent = _accentFor(item);
          final selected = item.slug == selectedSlug;

          return Material(
            color: selected ? _tint(accent, .10) : AppColors.surface,
            borderRadius: AppRadius.lg,
            child: InkWell(
              borderRadius: AppRadius.lg,
              onTap: () {
                if (item.slug != null) onSelect(item.slug!);
              },
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                width: 150,
                padding: const EdgeInsets.all(Spacing.md),
                decoration: BoxDecoration(
                  borderRadius: AppRadius.lg,
                  border: Border.all(
                    color: selected ? accent : AppColors.divider,
                    width: selected ? 1.8 : 1,
                  ),
                  boxShadow: selected ? AppShadows.subtle : null,
                ),
                child: Row(
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: _tint(accent, .14),
                        borderRadius: AppRadius.sm,
                      ),
                      clipBehavior: Clip.antiAlias,
                      child:
                          item.logo_path?.isNotEmpty == true
                              ? AppImage(
                                item.logo_path,
                                fit: BoxFit.contain,
                                borderRadius: BorderRadius.zero,
                              )
                              : Text(
                                _initials(item.name),
                                style: TextStyle(
                                  color: accent,
                                  fontWeight: FontWeight.w800,
                                  fontSize: 16,
                                ),
                              ),
                    ),
                    const SizedBox(width: Spacing.sm),
                    Expanded(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Flexible(
                                child: Text(
                                  item.name ?? '-',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 14,
                                    color: AppColors.textPrimary,
                                  ),
                                ),
                              ),
                              if (item.is_current) ...[
                                const SizedBox(width: 4),
                                Container(
                                  width: 6,
                                  height: 6,
                                  decoration: BoxDecoration(
                                    color: accent,
                                    shape: BoxShape.circle,
                                  ),
                                ),
                              ],
                            ],
                          ),
                          const SizedBox(height: 2),
                          Text(
                            item.ageRangeLabel,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 11,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (selected)
                      Icon(Icons.check_circle_rounded, size: 18, color: accent),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

// ─── Hero ───────────────────────────────────────────────────────────────────

class _OrgHeroCard extends StatelessWidget {
  const _OrgHeroCard({
    required this.org,
    required this.accent,
    required this.chartCount,
  });

  final OrganizationDetail org;
  final Color accent;
  final int chartCount;

  @override
  Widget build(BuildContext context) {
    final deep = Color.lerp(accent, AppColors.movementNavy, .62)!;

    return Container(
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: AppRadius.hero,
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.movementNavy, deep, accent],
          stops: const [0, .55, 1.35],
        ),
        boxShadow: AppShadows.floating,
      ),
      child: Stack(
        children: [
          Positioned(
            right: -40,
            top: -50,
            child: Container(
              width: 170,
              height: 170,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white.withValues(alpha: .10)),
              ),
            ),
          ),
          Positioned(
            right: 24,
            bottom: -50,
            child: Container(
              width: 130,
              height: 130,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: .05),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(Spacing.xl),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 74,
                      height: 74,
                      padding: const EdgeInsets.all(Spacing.sm),
                      clipBehavior: Clip.antiAlias,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: AppRadius.lg,
                      ),
                      child:
                          org.logo_path?.isNotEmpty == true
                              ? AppImage(
                                org.logo_path,
                                fit: BoxFit.contain,
                                borderRadius: BorderRadius.zero,
                              )
                              : Center(
                                child: Text(
                                  _initials(org.name),
                                  style: TextStyle(
                                    color: accent,
                                    fontSize: 26,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                    ),
                    const SizedBox(width: Spacing.lg),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            org.name ?? '-',
                            style: const TextStyle(
                              color: AppColors.textOnDark,
                              fontSize: 26,
                              fontWeight: FontWeight.w900,
                              letterSpacing: -.6,
                            ),
                          ),
                          const SizedBox(height: Spacing.xs),
                          Wrap(
                            spacing: Spacing.xs,
                            runSpacing: Spacing.xs,
                            children: [
                              _GlassChip(
                                icon: Icons.cake_outlined,
                                label: org.ageRangeLabel,
                              ),
                              if (org.is_current)
                                const _GlassChip(
                                  icon: Icons.verified_outlined,
                                  label: 'Organisasi Anda',
                                ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                if (org.description?.isNotEmpty == true) ...[
                  const SizedBox(height: Spacing.lg),
                  Text(
                    org.description!,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: .78),
                      fontSize: 13.5,
                      height: 1.55,
                    ),
                  ),
                ],
                const SizedBox(height: Spacing.lg),
                Row(
                  children: [
                    Expanded(
                      child: _HeroStat(
                        label: 'Jumlah Ahli',
                        value: '${org.member_count ?? 0}',
                      ),
                    ),
                    const SizedBox(width: Spacing.sm),
                    Expanded(
                      child: _HeroStat(
                        label: 'Kepimpinan',
                        value: '$chartCount',
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _GlassChip extends StatelessWidget {
  const _GlassChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.sm, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: .14),
        borderRadius: AppRadius.pill,
        border: Border.all(color: Colors.white.withValues(alpha: .14)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: Colors.white.withValues(alpha: .86)),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(
              color: Colors.white.withValues(alpha: .9),
              fontSize: 10.5,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroStat extends StatelessWidget {
  const _HeroStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Spacing.md,
        vertical: Spacing.sm,
      ),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: .10),
        borderRadius: AppRadius.md,
        border: Border.all(color: Colors.white.withValues(alpha: .10)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: TextStyle(
              color: Colors.white.withValues(alpha: .58),
              fontSize: 9.5,
              fontWeight: FontWeight.w800,
              letterSpacing: .8,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            value,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 19,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Stats & social ─────────────────────────────────────────────────────────

class _StatsRow extends StatelessWidget {
  const _StatsRow({
    required this.org,
    required this.accent,
    required this.chartCount,
  });

  final OrganizationDetail org;
  final Color accent;
  final int chartCount;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _InfoTile(
            icon: Icons.groups_2_outlined,
            label: 'Ahli',
            value: '${org.member_count ?? 0}',
            accent: accent,
          ),
        ),
        const SizedBox(width: Spacing.sm),
        Expanded(
          child: _InfoTile(
            icon: Icons.badge_outlined,
            label: 'Kepimpinan',
            value: '$chartCount',
            accent: accent,
          ),
        ),
        const SizedBox(width: Spacing.sm),
        Expanded(
          child: _InfoTile(
            icon: Icons.timelapse_rounded,
            label: 'Had Umur',
            value: org.ageRangeLabel,
            accent: accent,
          ),
        ),
      ],
    );
  }
}

class _InfoTile extends StatelessWidget {
  const _InfoTile({
    required this.icon,
    required this.label,
    required this.value,
    required this.accent,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Spacing.sm,
        vertical: Spacing.md,
      ),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: AppRadius.md,
        border: Border.all(color: AppColors.divider),
      ),
      child: Column(
        children: [
          Icon(icon, size: 19, color: accent),
          const SizedBox(height: Spacing.xs),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 13.5,
              fontWeight: FontWeight.w800,
              color: AppColors.textPrimary,
            ),
          ),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 10.5,
              color: AppColors.textSecondary,
            ),
          ),
        ],
      ),
    );
  }
}

List<(IconData, String, String?)> _socialLinks(OrganizationDetail org) =>
    [
      (Icons.public, 'Laman Web', org.website_url),
      (Icons.facebook, 'Facebook', org.facebook_url),
      (Icons.alternate_email, 'X (Twitter)', org.twitter_url),
      (Icons.camera_alt_outlined, 'Instagram', org.instagram_url),
      (Icons.play_circle_outline, 'YouTube', org.youtube_url),
      (Icons.music_note, 'TikTok', org.tiktok_url),
    ].where((e) => e.$3 != null && e.$3!.isNotEmpty).toList();

class _SocialLinks extends StatelessWidget {
  const _SocialLinks({required this.org, required this.accent});

  final OrganizationDetail org;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    final links = _socialLinks(org);

    return Wrap(
      spacing: Spacing.sm,
      runSpacing: Spacing.sm,
      children: [
        for (final link in links)
          Material(
            color: AppColors.surface,
            borderRadius: AppRadius.md,
            child: InkWell(
              borderRadius: AppRadius.md,
              onTap: () => launchUrl(Uri.parse(link.$3!)),
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: Spacing.md,
                  vertical: Spacing.sm,
                ),
                decoration: BoxDecoration(
                  borderRadius: AppRadius.md,
                  border: Border.all(color: AppColors.divider),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(link.$1, size: 17, color: accent),
                    const SizedBox(width: Spacing.xs),
                    Text(
                      link.$2,
                      style: const TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w600,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}

// ─── Chart ──────────────────────────────────────────────────────────────────

class _FeaturedLeader extends StatelessWidget {
  const _FeaturedLeader({
    required this.member,
    required this.accent,
    required this.onTap,
  });

  final OrgChartMember member;
  final Color accent;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 200,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: AppRadius.card,
        boxShadow: AppShadows.card,
        border: Border.all(color: AppColors.divider),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 150,
            child: GestureDetector(
              onTap: onTap,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  _MemberPhoto(member: member, accent: accent, fontSize: 42),
                  Positioned(
                    left: Spacing.sm,
                    top: Spacing.sm,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: Spacing.sm,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.movementNavy.withValues(alpha: .86),
                        borderRadius: AppRadius.pill,
                      ),
                      child: const Text(
                        'PIMPINAN UTAMA',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 8,
                          fontWeight: FontWeight.w800,
                          letterSpacing: .7,
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    right: Spacing.sm,
                    bottom: Spacing.sm,
                    child: Container(
                      width: 28,
                      height: 28,
                      decoration: BoxDecoration(
                        color: AppColors.white.withValues(alpha: .92),
                        shape: BoxShape.circle,
                        boxShadow: AppShadows.subtle,
                      ),
                      child: const Icon(
                        Icons.open_in_full_rounded,
                        size: 14,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    member.position ?? '-',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: accent,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w800,
                      letterSpacing: .9,
                    ),
                  ),
                  const SizedBox(height: Spacing.xs),
                  Text(
                    member.name ?? '-',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppColors.textPrimary,
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                      height: 1.2,
                    ),
                  ),
                  if (member.email?.isNotEmpty == true) ...[
                    const SizedBox(height: Spacing.md),
                    FilledButton.icon(
                      onPressed:
                          () => launchUrl(Uri.parse('mailto:${member.email}')),
                      icon: const Icon(Icons.mail_outline, size: 17),
                      label: const Text('Hubungi'),
                      style: FilledButton.styleFrom(
                        backgroundColor: accent,
                        minimumSize: const Size(0, 40),
                        padding: const EdgeInsets.symmetric(
                          horizontal: Spacing.lg,
                        ),
                        textStyle: const TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ChartMemberCard extends StatelessWidget {
  const _ChartMemberCard({
    required this.member,
    required this.accent,
    required this.onTap,
  });

  final OrgChartMember member;
  final Color accent;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: AppRadius.lg,
          border: Border.all(color: AppColors.divider),
          boxShadow: AppShadows.subtle,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: Stack(
                fit: StackFit.expand,
                children: [
                  _MemberPhoto(member: member, accent: accent, fontSize: 34),
                  Positioned(
                    right: Spacing.sm,
                    bottom: Spacing.sm,
                    child: Container(
                      width: 24,
                      height: 24,
                      decoration: BoxDecoration(
                        color: AppColors.white.withValues(alpha: .92),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.open_in_full_rounded,
                        size: 12,
                        color: accent,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(
                Spacing.sm,
                Spacing.sm,
                Spacing.sm,
                Spacing.md,
              ),
              child: Column(
                children: [
                  Text(
                    member.name ?? '-',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 1),
                  Text(
                    member.position ?? '-',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppColors.textSecondary,
                    ),
                  ),
                  if (member.email?.isNotEmpty == true) ...[
                    const SizedBox(height: Spacing.sm),
                    SizedBox(
                      height: 28,
                      child: TextButton.icon(
                        onPressed:
                            () =>
                                launchUrl(Uri.parse('mailto:${member.email}')),
                        icon: Icon(Icons.mail_outline, size: 13, color: accent),
                        label: Text(
                          'Hubungi',
                          style: TextStyle(
                            color: accent,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        style: TextButton.styleFrom(
                          padding: const EdgeInsets.symmetric(
                            horizontal: Spacing.sm,
                          ),
                          minimumSize: Size.zero,
                          backgroundColor: _tint(accent, .10),
                          shape: RoundedRectangleBorder(
                            borderRadius: AppRadius.sm,
                          ),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MemberPhoto extends StatelessWidget {
  const _MemberPhoto({
    required this.member,
    required this.accent,
    required this.fontSize,
  });

  final OrgChartMember member;
  final Color accent;
  final double fontSize;

  @override
  Widget build(BuildContext context) {
    if (member.image_path?.isNotEmpty == true) {
      return Container(
        color: _tint(accent, .10),
        padding: const EdgeInsets.all(Spacing.xs),
        child: AppImage(
          member.image_path,
          fit: BoxFit.contain,
          borderRadius: BorderRadius.zero,
        ),
      );
    }

    return Container(
      alignment: Alignment.center,
      color: _tint(accent, .12),
      child: Text(
        _initials(member.name),
        style: TextStyle(
          color: accent,
          fontSize: fontSize,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

// ─── Skeleton ───────────────────────────────────────────────────────────────

class _OrganizationSkeleton extends StatelessWidget {
  const _OrganizationSkeleton();

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(Spacing.lg),
    children: const [
      Row(
        children: [
          Expanded(child: SkeletonBox(height: 96, radius: 18)),
          SizedBox(width: Spacing.sm),
          Expanded(child: SkeletonBox(height: 96, radius: 18)),
        ],
      ),
      SizedBox(height: Spacing.md),
      SkeletonBox(height: 220, radius: 32),
      SizedBox(height: Spacing.md),
      SkeletonBox(height: 78, radius: 14),
      SizedBox(height: Spacing.xl),
      SkeletonBox(height: 20, width: 160),
      SizedBox(height: Spacing.md),
      SkeletonBox(height: 96, radius: 22),
    ],
  );
}

// ─── Member preview dialog ──────────────────────────────────────────────────

void _showMemberDialog(
  BuildContext context,
  OrgChartMember member,
  Color accent,
) {
  final screen = MediaQuery.sizeOf(context);

  showDialog<void>(
    context: context,
    barrierColor: AppColors.movementNavy.withValues(alpha: .72),
    builder: (dialogContext) {
      return Dialog(
        insetPadding: const EdgeInsets.symmetric(
          horizontal: Spacing.xl,
          vertical: Spacing.xl,
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxWidth: 420,
            maxHeight: screen.height * .88,
          ),
          child: Container(
            clipBehavior: Clip.antiAlias,
            decoration: const BoxDecoration(
              color: AppColors.white,
              borderRadius: AppRadius.hero,
            ),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Stack(
                    children: [
                      AspectRatio(
                        aspectRatio: 1080 / 1350,
                        child: _MemberPhoto(
                          member: member,
                          accent: accent,
                          fontSize: 72,
                        ),
                      ),
                      Positioned(
                        right: Spacing.md,
                        top: Spacing.md,
                        child: Material(
                          color: AppColors.white.withValues(alpha: .94),
                          shape: const CircleBorder(),
                          child: InkWell(
                            customBorder: const CircleBorder(),
                            onTap: () => Navigator.of(dialogContext).pop(),
                            child: const SizedBox(
                              width: 38,
                              height: 38,
                              child: Icon(
                                Icons.close_rounded,
                                size: 20,
                                color: AppColors.textPrimary,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(
                      Spacing.xl,
                      Spacing.lg,
                      Spacing.xl,
                      Spacing.xl,
                    ),
                    child: Column(
                      children: [
                        Text(
                          (member.position ?? '-').toUpperCase(),
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: accent,
                            fontSize: 10.5,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 1.1,
                          ),
                        ),
                        const SizedBox(height: Spacing.xs),
                        Text(
                          member.name ?? '-',
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: AppColors.textPrimary,
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                            height: 1.2,
                          ),
                        ),
                        if (member.email?.isNotEmpty == true) ...[
                          const SizedBox(height: Spacing.lg),
                          FilledButton.icon(
                            onPressed:
                                () => launchUrl(
                                  Uri.parse('mailto:${member.email}'),
                                ),
                            icon: const Icon(Icons.mail_outline, size: 18),
                            label: const Text('Hubungi'),
                            style: FilledButton.styleFrom(
                              backgroundColor: accent,
                              minimumSize: const Size(0, 46),
                              padding: const EdgeInsets.symmetric(
                                horizontal: Spacing.xl,
                              ),
                              textStyle: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
    },
  );
}

// ─── Helpers ────────────────────────────────────────────────────────────────

Color _accentFor(OrganizationDetail? org) {
  final fallback = switch (org?.slug) {
    'pkpim' => AppColors.movementSoftGreen,
    'wadah' => AppColors.accent,
    _ => AppColors.movementGreen,
  };

  final raw = org?.color_theme?.replaceAll('#', '').trim();
  if (raw == null || raw.length != 6) return fallback;

  final value = int.tryParse(raw, radix: 16);
  if (value == null) return fallback;

  return Color(0xFF000000 | value);
}

Color _tint(Color color, double alpha) => color.withValues(alpha: alpha);

String _initials(String? name) {
  final raw = name?.trim() ?? '';
  if (raw.isEmpty) return '?';
  final parts = raw.split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
  if (parts.isEmpty) return '?';
  if (parts.length == 1) {
    return parts.first.substring(0, 1).toUpperCase();
  }
  return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
      .toUpperCase();
}
