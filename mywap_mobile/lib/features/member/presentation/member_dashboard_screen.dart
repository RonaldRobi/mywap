import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../auth/application/auth_controller.dart';
import '../../events/data/models/event.dart';
import '../application/member_providers.dart';
import '../data/models/dashboard_data.dart';
import 'main_shell.dart';

class MemberDashboardScreen extends ConsumerWidget {
  const MemberDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    final dashboardAsync = ref.watch(memberDashboardProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Utama'),
        actions: const [LogoutIconButton()],
      ),
      body: dashboardAsync.when(
        data: (data) => _DashboardContent(userName: user?.name, data: data),
        loading: () => const _DashboardSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(memberDashboardProvider),
        ),
      ),
    );
  }
}

class _DashboardContent extends ConsumerWidget {
  const _DashboardContent({required this.userName, required this.data});

  final String? userName;
  final DashboardData data;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final member = data.member;
    final events = data.upcoming_events ?? const [];

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(memberDashboardProvider),
      child: ListView(
        padding: const EdgeInsets.only(bottom: Spacing.xl),
        children: [
          Container(
            padding: const EdgeInsets.all(Spacing.xl),
            decoration: const BoxDecoration(color: AppColors.movementDarkGreen),
            child: Row(
              children: [
                ClipOval(
                  child: AppImage(
                    member?.photo_url,
                    width: 64,
                    height: 64,
                    fit: BoxFit.cover,
                  ),
                ),
                const SizedBox(width: Spacing.lg),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Assalamualaikum,',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: AppColors.movementSoftGreen,
                        ),
                      ),
                      Text(
                        member?.name ?? userName ?? 'Ahli',
                        style: theme.textTheme.headlineSmall?.copyWith(
                          color: AppColors.white,
                        ),
                      ),
                      if (member?.branch_name != null)
                        Text(
                          member!.branch_name!,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: AppColors.textOnDark,
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: Spacing.md),
          if (data.next_event != null) ..._buildNextEvent(theme),
          _SectionTitle(title: 'Acara Akan Datang', count: events.length),
          if (events.isEmpty)
            const Padding(
              padding: EdgeInsets.all(Spacing.xl),
              child: Center(
                child: Text('Tiada acara akan datang buat masa ini.'),
              ),
            )
          else
            for (final event in events) _EventCard(event: event),
          if ((data.banners ?? []).isNotEmpty) ...[
            _SectionTitle(title: 'Pengumuman', count: data.banners!.length),
            for (final banner in data.banners!) _BannerCard(banner: banner),
          ],
        ],
      ),
    );
  }

  List<Widget> _buildNextEvent(ThemeData theme) {
    final next = data.next_event!;
    return [
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(Spacing.lg),
            child: Row(
              children: [
                const Icon(Icons.star, color: AppColors.movementGreen, size: 32),
                const SizedBox(width: Spacing.lg),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Acara Seterusnya',
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        next['title']?.toString() ?? '-',
                        style: theme.textTheme.titleMedium,
                      ),
                      Text(
                        next['start_formatted']?.toString() ?? '',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ];
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, this.count});

  final String title;
  final int? count;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(Spacing.lg, Spacing.xl, Spacing.lg, Spacing.sm),
      child: Row(
        children: [
          Text(title, style: Theme.of(context).textTheme.titleLarge),
          if (count != null && count! > 0) ...[
            const SizedBox(width: Spacing.sm),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(
                color: AppColors.movementSoftGreen,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                '$count',
                style: const TextStyle(
                  color: AppColors.movementNavy,
                  fontWeight: FontWeight.w700,
                  fontSize: 14,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event});

  final Event event;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final imageUrl = event.featured_image_url;

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/events/${event.id}'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (imageUrl != null && imageUrl.isNotEmpty)
              AppImage(imageUrl, height: 160, width: double.infinity)
            else
              const SizedBox(height: 12),
            Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(event.title ?? '-', style: theme.textTheme.titleMedium),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(
                        Icons.calendar_today_outlined,
                        size: 18,
                        color: AppColors.textSecondary,
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          event.start_formatted ?? '',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      const Icon(
                        Icons.location_on_outlined,
                        size: 18,
                        color: AppColors.textSecondary,
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          event.location_or_link ?? '',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: AppColors.textSecondary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
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

class _BannerCard extends StatelessWidget {
  const _BannerCard({required this.banner});

  final DashboardBanner banner;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.lg, vertical: Spacing.sm),
      child: AppImage(
        banner.image_path,
        height: 140,
        width: double.infinity,
        borderRadius: BorderRadius.circular(16),
      ),
    );
  }
}

class _DashboardSkeleton extends StatelessWidget {
  const _DashboardSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: const [
        SkeletonBox(height: 120, radius: 0),
        SizedBox(height: 24),
        Padding(
          padding: EdgeInsets.symmetric(horizontal: 24),
          child: SkeletonBox(height: 20, width: 200),
        ),
        SizedBox(height: 12),
        Padding(
          padding: EdgeInsets.symmetric(horizontal: 24),
          child: SkeletonBox(height: 140),
        ),
        SizedBox(height: 12),
        Padding(
          padding: EdgeInsets.symmetric(horizontal: 24),
          child: SkeletonBox(height: 140),
        ),
      ],
    );
  }
}
