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
import 'widgets/notification_bell.dart';
import 'widgets/shell_scaffold_key.dart';

class MemberDashboardScreen extends ConsumerWidget {
  const MemberDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    final dashboardAsync = ref.watch(memberDashboardProvider);

    return Scaffold(
      body: dashboardAsync.when(
        data: (data) => _DashboardContent(userName: user?.name, data: data),
        loading: () => const _DashboardSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : 'Ralat tidak dijangka.',
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
    final member = data.member;
    final memberName = member?.name ?? userName ?? 'Ahli';
    final banners = data.banners ?? const [];
    final events = data.upcoming_events ?? const [];
    final infaqItems = data.infaq_items ?? const [];
    final news = data.latest_news ?? const [];
    final articles = data.latest_articles ?? const [];
    final polls = data.active_polls ?? const [];

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(memberDashboardProvider),
      child: CustomScrollView(
        slivers: [
          SliverAppBar(
            pinned: true,
            backgroundColor: AppColors.pageBackground.withValues(alpha: 0.94),
            surfaceTintColor: Colors.transparent,
            elevation: 0,
            titleSpacing: 0,
            leading: const AppMenuButton(),
            title: _Greeting(name: memberName),
            actions: const [NotificationBell(), SizedBox(width: Spacing.sm)],
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.lg,
              Spacing.lg,
              Spacing.lg,
              Spacing.xxl,
            ),
            sliver: SliverList(
              delegate: SliverChildListDelegate([
                _Banner(banner: banners.isEmpty ? null : banners.first),
                const SizedBox(height: Spacing.xl),
                const _SectionLabel(title: 'Pintasan'),
                const SizedBox(height: Spacing.sm),
                const _ShortcutsGrid(),
                const SizedBox(height: Spacing.xl),
                _MembershipCard(member: member, feeStatus: data.fee_status),
                if (data.next_event != null) ...[
                  const SizedBox(height: Spacing.xl),
                  _NextEvent(event: data.next_event!),
                ],
                const SizedBox(height: Spacing.xl),
                _SectionLabel(
                  title: 'Program',
                  subtitle: 'Acara dan aktiviti akan datang',
                  action: () => context.go('/events'),
                ),
                const SizedBox(height: Spacing.md),
                if (events.isEmpty)
                  const _EmptySection(
                    icon: Icons.event_busy_outlined,
                    message: 'Tiada acara akan datang buat masa ini.',
                  )
                else
                  SizedBox(
                    height: 230,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: events.length,
                      separatorBuilder:
                          (_, __) => const SizedBox(width: Spacing.md),
                      itemBuilder:
                          (_, index) => _EventCard(event: events[index]),
                    ),
                  ),
                if (infaqItems.isNotEmpty) ...[
                  const SizedBox(height: Spacing.xl),
                  _SectionLabel(
                    title: 'Infaq',
                    subtitle: 'Kempen sumbangan aktif',
                    action: () => context.go('/infaq'),
                  ),
                  const SizedBox(height: Spacing.md),
                  SizedBox(
                    height: 150,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: infaqItems.length,
                      separatorBuilder:
                          (_, __) => const SizedBox(width: Spacing.md),
                      itemBuilder:
                          (_, index) => _InfaqCard(item: infaqItems[index]),
                    ),
                  ),
                ],
                if (polls.isNotEmpty) ...[
                  const SizedBox(height: Spacing.xl),
                  _SectionLabel(
                    title: 'Undian',
                    subtitle: 'Sertai undian & tinjauan aktif',
                    action: () => context.push('/polls'),
                  ),
                  const SizedBox(height: Spacing.md),
                  ...polls.take(2).map((p) => _PollPreviewCard(poll: p)),
                ],
                if (news.isNotEmpty) ...[
                  const SizedBox(height: Spacing.xl),
                  _SectionLabel(
                    title: 'Info Terkini',
                    subtitle: 'Berita dan pengumuman terbaharu',
                    action: () => context.push('/news'),
                  ),
                  const SizedBox(height: Spacing.md),
                  SizedBox(
                    height: 190,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: news.length.clamp(0, 6),
                      separatorBuilder:
                          (_, __) => const SizedBox(width: Spacing.md),
                      itemBuilder:
                          (_, index) => _NewsCard(item: news[index]),
                    ),
                  ),
                ],
                if (articles.isNotEmpty) ...[
                  const SizedBox(height: Spacing.xl),
                  _SectionLabel(
                    title: 'Artikel',
                    subtitle: 'Penulisan & rencana pilihan',
                    action: () => context.push('/articles'),
                  ),
                  const SizedBox(height: Spacing.md),
                  SizedBox(
                    height: 190,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: articles.length.clamp(0, 6),
                      separatorBuilder:
                          (_, __) => const SizedBox(width: Spacing.md),
                      itemBuilder:
                          (_, index) => _ArticleCard(item: articles[index]),
                    ),
                  ),
                ],
              ]),
            ),
          ),
        ],
      ),
    );
  }
}

class _Greeting extends StatelessWidget {
  const _Greeting({required this.name});
  final String name;

  @override
  Widget build(BuildContext context) {
    final hour = DateTime.now().hour;
    final greeting =
        hour < 12
            ? 'Selamat Pagi'
            : hour < 18
            ? 'Selamat Petang'
            : 'Selamat Malam';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Assalamualaikum, $name',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(
            context,
          ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
        ),
        Text(
          greeting,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
            color: AppColors.textSecondary,
            fontSize: 10,
          ),
        ),
      ],
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({this.banner});
  final DashboardBanner? banner;

  @override
  Widget build(BuildContext context) {
    return AspectRatio(
      aspectRatio: 21 / 9,
      child: ClipRRect(
        borderRadius: AppRadius.hero,
        child:
            banner?.image_path?.isNotEmpty == true
                ? AppImage(
                  banner!.image_path,
                  width: double.infinity,
                  fit: BoxFit.cover,
                )
                : Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      colors: [
                        AppColors.movementSoftGreen,
                        AppColors.movementGreen,
                      ],
                    ),
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    banner?.title ?? 'Selamat datang ke myWAP',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: AppColors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel({required this.title, this.subtitle, this.action});
  final String title;
  final String? subtitle;
  final VoidCallback? action;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: Theme.of(
                context,
              ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
            ),
            if (subtitle != null)
              Text(
                subtitle!,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: AppColors.textSecondary,
                  fontSize: 11,
                ),
              ),
          ],
        ),
      ),
      if (action != null)
        TextButton(onPressed: action, child: const Text('Lihat Semua')),
    ],
  );
}

/// Grid of shortcuts to every high-frequency member module — surfaces more
/// than the old 4-item row without feeling crowded, since each tile is
/// compact and grouped 4-per-row with generous spacing.
class _ShortcutsGrid extends StatelessWidget {
  const _ShortcutsGrid();

  static const List<_ShortcutItem> _items = [
    _ShortcutItem(
      icon: Icons.credit_card_rounded,
      label: 'Yuran Saya',
      color: Color(0xFF059669),
      path: '/member/fee-status',
    ),
    _ShortcutItem(
      icon: Icons.calendar_month_rounded,
      label: 'Tempah',
      color: Color(0xFFD97706),
      path: '/facilities',
    ),
    _ShortcutItem(
      icon: Icons.newspaper_rounded,
      label: 'Info',
      color: Color(0xFF4F46E5),
      path: '/news',
    ),
    _ShortcutItem(
      icon: Icons.volunteer_activism_rounded,
      label: 'Infaq',
      color: Color(0xFFE11D48),
      path: '/infaq',
    ),
    _ShortcutItem(
      icon: Icons.badge_rounded,
      label: 'Kad Ahli',
      color: Color(0xFF2563EB),
      path: '/card',
    ),
    _ShortcutItem(
      icon: Icons.groups_rounded,
      label: 'Usrah',
      color: Color(0xFF7C3AED),
      path: '/usrah',
    ),
    _ShortcutItem(
      icon: Icons.storefront_rounded,
      label: 'Mall',
      color: Color(0xFFEA580C),
      path: '/products',
    ),
    _ShortcutItem(
      icon: Icons.person_add_alt_1_rounded,
      label: 'Jemput Ahli',
      color: Color(0xFF0D9488),
      path: '/member/referral',
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: _items.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 4,
        mainAxisSpacing: Spacing.sm,
        crossAxisSpacing: Spacing.xs,
        mainAxisExtent: 78,
      ),
      itemBuilder: (context, index) => _ShortcutTile(item: _items[index]),
    );
  }
}

class _ShortcutItem {
  const _ShortcutItem({
    required this.icon,
    required this.label,
    required this.color,
    required this.path,
  });
  final IconData icon;
  final String label;
  final Color color;
  final String path;
}

class _ShortcutTile extends StatelessWidget {
  const _ShortcutTile({required this.item});
  final _ShortcutItem item;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: AppRadius.lg,
        onTap: () => context.push(item.path),
        child: Column(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: item.color.withValues(alpha: .12),
                borderRadius: AppRadius.lg,
              ),
              child: Icon(item.icon, color: item.color, size: 22),
            ),
            const SizedBox(height: 6),
            Text(
              item.label,
              textAlign: TextAlign.center,
              maxLines: 2,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                fontSize: 10,
                fontWeight: FontWeight.w600,
                height: 1.1,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MembershipCard extends StatelessWidget {
  const _MembershipCard({required this.member, required this.feeStatus});
  final DashboardMember? member;
  final Map<String, dynamic>? feeStatus;
  @override
  Widget build(BuildContext context) {
    final active = feeStatus?['status']?.toString() == 'active';
    return Container(
      padding: const EdgeInsets.all(Spacing.xl),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.movementDarkGreen,
            AppColors.movementGreen,
            AppColors.movementDarkGreen,
          ],
        ),
        borderRadius: AppRadius.hero,
        boxShadow: AppShadows.floating,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.workspace_premium_outlined,
                color: AppColors.white,
                size: 20,
              ),
              const SizedBox(width: Spacing.sm),
              Text(
                'KAD AHLI · myWAP',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: AppColors.textOnDark,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1,
                ),
              ),
              const Spacer(),
              Icon(
                Icons.circle,
                size: 12,
                color: active ? AppColors.movementSoftGreen : AppColors.warning,
              ),
            ],
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: Spacing.md),
            child: Divider(color: Color(0x33FFFFFF)),
          ),
          Row(
            children: [
              ClipRRect(
                borderRadius: AppRadius.md,
                child: AppImage(
                  member?.photo_url,
                  width: 72,
                  height: 72,
                  fit: BoxFit.cover,
                ),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      member?.name ?? 'Ahli myWAP',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.white,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: Spacing.xs),
                    Text(
                      'Ahli sejak ${member?.member_since ?? '-'}',
                      style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: AppColors.textOnDark,
                        fontSize: 11,
                      ),
                    ),
                    const SizedBox(height: Spacing.xs),
                    Text(
                      member?.member_no ?? 'No. ahli belum tersedia',
                      style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: AppColors.movementSoftGreen,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: Spacing.lg),
          Row(
            children: [
              Expanded(
                child: _CardStat(
                  label: 'CAWANGAN AHLI',
                  value: member?.branch_name ?? '-',
                ),
              ),
              const SizedBox(width: Spacing.sm),
              Expanded(
                child: _CardStat(
                  label: 'STATUS',
                  value: active ? 'AKTIF' : 'SEMAK YURAN',
                ),
              ),
            ],
          ),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton(
              onPressed: () => context.push('/card'),
              child: const Text(
                'Lihat Kad Penuh',
                style: TextStyle(color: AppColors.white),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CardStat extends StatelessWidget {
  const _CardStat({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(Spacing.sm),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: .1),
      borderRadius: AppRadius.md,
      border: Border.all(color: Colors.white.withValues(alpha: .12)),
    ),
    child: Column(
      children: [
        Text(
          label,
          style: const TextStyle(
            color: Color(0x99FFFFFF),
            fontSize: 9,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: AppColors.white,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event});
  final Event event;
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 175,
      child: Material(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        child: InkWell(
          borderRadius: AppRadius.card,
          onTap: () => context.push('/events/${event.id}'),
          child: ClipRRect(
            borderRadius: AppRadius.card,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      if (event.featured_image_url?.isNotEmpty == true)
                        AppImage(event.featured_image_url, fit: BoxFit.cover)
                      else
                        Container(color: AppColors.paleGreen),
                      const DecoratedBox(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.bottomCenter,
                            end: Alignment.topCenter,
                            colors: [Color(0x66000000), Colors.transparent],
                          ),
                        ),
                      ),
                      Positioned(
                        left: Spacing.sm,
                        top: Spacing.sm,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 6,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.white,
                            borderRadius: BorderRadius.circular(99),
                          ),
                          child: Text(
                            event.type == 'physical' ? 'FIZIKAL' : 'ONLINE',
                            style: const TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(Spacing.md),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        event.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.labelMedium
                            ?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        event.start_formatted ?? '',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(
                          context,
                        ).textTheme.labelMedium?.copyWith(
                          fontSize: 11,
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
    );
  }
}

class _NextEvent extends StatelessWidget {
  const _NextEvent({required this.event});
  final Map<String, dynamic> event;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(Spacing.lg),
    decoration: BoxDecoration(
      color: AppColors.softGreenSurface,
      borderRadius: AppRadius.card,
      border: Border.all(color: AppColors.movementSoftGreen),
    ),
    child: Row(
      children: [
        const Icon(Icons.star_outline, color: AppColors.movementGreen),
        const SizedBox(width: Spacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'ACARA SETERUSNYA',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: AppColors.movementGreen,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1,
                ),
              ),
              Text(
                event['title']?.toString() ?? '-',
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
              ),
              Text(
                event['start_formatted']?.toString() ?? '',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _EmptySection extends StatelessWidget {
  const _EmptySection({required this.icon, required this.message});
  final IconData icon;
  final String message;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(Spacing.xxl),
    decoration: BoxDecoration(
      color: AppColors.white,
      borderRadius: AppRadius.card,
      border: Border.all(color: AppColors.divider),
    ),
    child: Column(
      children: [
        Icon(icon, color: AppColors.textSecondary, size: 32),
        const SizedBox(height: Spacing.sm),
        Text(message, textAlign: TextAlign.center),
      ],
    ),
  );
}

class _InfaqCard extends StatelessWidget {
  const _InfaqCard({required this.item});
  final InfaqItem item;

  @override
  Widget build(BuildContext context) {
    final progress = ((item.progress_percent ?? 0) / 100).clamp(0, 1).toDouble();
    return SizedBox(
      width: 220,
      child: Material(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        child: InkWell(
          borderRadius: AppRadius.card,
          onTap: () => context.go('/infaq'),
          child: Padding(
            padding: const EdgeInsets.all(Spacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title ?? '-',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                const SizedBox(height: Spacing.sm),
                ClipRRect(
                  borderRadius: AppRadius.sm,
                  child: LinearProgressIndicator(
                    value: progress,
                    minHeight: 6,
                    backgroundColor: AppColors.divider,
                    color: AppColors.movementGreen,
                  ),
                ),
                const SizedBox(height: Spacing.xs),
                Text(
                  '${item.progress_percent ?? 0}% terkumpul',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _PollPreviewCard extends StatelessWidget {
  const _PollPreviewCard({required this.poll});
  final PollPreviewItem poll;

  @override
  Widget build(BuildContext context) {
    final responded = poll.has_responded ?? false;
    return Padding(
      padding: const EdgeInsets.only(bottom: Spacing.md),
      child: Material(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        child: InkWell(
          borderRadius: AppRadius.card,
          onTap: () => context.push('/polls/${poll.id}'),
          child: Padding(
            padding: const EdgeInsets.all(Spacing.lg),
            child: Row(
              children: [
                Icon(
                  responded
                      ? Icons.check_circle_outline
                      : Icons.how_to_vote_outlined,
                  color: responded ? AppColors.success : AppColors.movementGreen,
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        poll.title ?? '-',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleSmall,
                      ),
                      Text(
                        responded
                            ? 'Anda telah menjawab'
                            : 'Belum dijawab · ${poll.response_count ?? 0} respons',
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
        ),
      ),
    );
  }
}

class _NewsCard extends StatelessWidget {
  const _NewsCard({required this.item});
  final NewsItem item;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 175,
      child: Material(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        child: InkWell(
          borderRadius: AppRadius.card,
          onTap: () => context.push('/news/${item.id}'),
          child: ClipRRect(
            borderRadius: AppRadius.card,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AspectRatio(
                  aspectRatio: 16 / 9,
                  child: item.cover_image_path?.isNotEmpty == true
                      ? AppImage(item.cover_image_path, fit: BoxFit.cover)
                      : Container(color: AppColors.paleGreen),
                ),
                Padding(
                  padding: const EdgeInsets.all(Spacing.md),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        item.category_name ?? '',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          fontSize: 11,
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
    );
  }
}

class _ArticleCard extends StatelessWidget {
  const _ArticleCard({required this.item});
  final ArticleItem item;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 175,
      child: Material(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        child: InkWell(
          borderRadius: AppRadius.card,
          onTap: () => context.push('/articles/${item.id}'),
          child: ClipRRect(
            borderRadius: AppRadius.card,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AspectRatio(
                  aspectRatio: 16 / 9,
                  child: item.cover_image_path?.isNotEmpty == true
                      ? AppImage(item.cover_image_path, fit: BoxFit.cover)
                      : Container(color: AppColors.paleGreen),
                ),
                Padding(
                  padding: const EdgeInsets.all(Spacing.md),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        item.author_name ?? '',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          fontSize: 11,
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
    );
  }
}

class _DashboardSkeleton extends StatelessWidget {
  const _DashboardSkeleton();
  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(Spacing.lg),
    children: const [
      SkeletonBox(height: 42, radius: 0),
      SizedBox(height: Spacing.lg),
      SkeletonBox(height: 160, radius: 28),
      SizedBox(height: Spacing.xl),
      SkeletonBox(height: 112, radius: 20),
      SizedBox(height: Spacing.xl),
      SkeletonBox(height: 270, radius: 28),
    ],
  );
}
