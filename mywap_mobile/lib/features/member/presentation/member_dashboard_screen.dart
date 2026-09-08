import 'dart:math' as math;

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
                const SizedBox(height: Spacing.sm),
                const _ShortcutsGrid(),
                const SizedBox(height: Spacing.sm),
                _MembershipCard(member: member),
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
                    // 4:5 image + title/progress footer. Keeping this height
                    // explicit prevents the portrait card footer overflowing.
                    height: 338,
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
                    height: 338,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: news.length.clamp(0, 6),
                      separatorBuilder:
                          (_, __) => const SizedBox(width: Spacing.md),
                      itemBuilder: (_, index) => _NewsCard(item: news[index]),
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
                    height: 338,
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

/// Compact 5-column grid for high-frequency member modules.
class _ShortcutsGrid extends StatelessWidget {
  const _ShortcutsGrid();

  static const List<_ShortcutItem> _items = [
    _ShortcutItem(
      icon: Icons.receipt_long_rounded,
      label: 'Yuran',
      color: Color(0xFF059669),
      path: '/member/fee-status',
    ),
    _ShortcutItem(
      icon: Icons.calendar_today_rounded,
      label: 'Tempah',
      color: Color(0xFFD97706),
      path: '/facilities',
    ),
    _ShortcutItem(
      icon: Icons.article_rounded,
      label: 'Berita',
      color: Color(0xFF4F46E5),
      path: '/news',
    ),
    _ShortcutItem(
      icon: Icons.favorite_rounded,
      label: 'Infaq',
      color: Color(0xFFE11D48),
      path: '/infaq',
    ),
    _ShortcutItem(
      icon: Icons.contact_page_rounded,
      label: 'Kad Ahli',
      color: Color(0xFF2563EB),
      path: '/card',
    ),
    _ShortcutItem(
      icon: Icons.groups_2_rounded,
      label: 'Usrah',
      color: Color(0xFF7C3AED),
      path: '/usrah',
    ),
    _ShortcutItem(
      icon: Icons.shopping_bag_rounded,
      label: 'Mall',
      color: Color(0xFFEA580C),
      path: '/products',
    ),
    _ShortcutItem(
      icon: Icons.share_rounded,
      label: 'Jemput',
      color: Color(0xFF0D9488),
      path: '/member/referral',
    ),
    _ShortcutItem(
      icon: Icons.menu_book_rounded,
      label: 'Pustaka',
      color: Color(0xFF0F766E),
      path: '/member/library',
    ),
    _ShortcutItem(
      icon: Icons.poll_rounded,
      label: 'Undian',
      color: Color(0xFF9D174D),
      path: '/polls',
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      primary: false,
      padding: EdgeInsets.zero,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: _items.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 5,
        mainAxisSpacing: Spacing.sm,
        crossAxisSpacing: Spacing.xs,
        mainAxisExtent: 70,
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
    return SizedBox(
      height: 70,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: AppRadius.lg,
          onTap: () => context.push(item.path),
          child: Column(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: item.color.withValues(alpha: .12),
                  borderRadius: AppRadius.lg,
                  border: Border.all(color: item.color.withValues(alpha: .08)),
                ),
                child: Icon(item.icon, color: item.color, size: 20),
              ),
              const SizedBox(height: Spacing.xs),
              Text(
                item.label,
                textAlign: TextAlign.center,
                maxLines: 2,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  fontSize: 9,
                  fontWeight: FontWeight.w600,
                  height: 1.1,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MembershipCard extends StatelessWidget {
  const _MembershipCard({required this.member});
  final DashboardMember? member;

  String? get _logoUrl {
    final orgLogo = member?.organization?.logo_path;
    if (orgLogo?.isNotEmpty == true) return orgLogo;
    final systemLogo = member?.system_logo_path;
    return (systemLogo?.isNotEmpty == true) ? systemLogo : null;
  }

  @override
  Widget build(BuildContext context) {
    final org = member?.organization;
    final orgName = org?.name;
    final since = member?.member_since;
    final sinceSuffix = since?.isNotEmpty == true ? since! : '-';
    final sinceText =
        (orgName?.isNotEmpty == true)
            ? 'Ahli $orgName sejak $sinceSuffix'
            : 'Ahli sejak $sinceSuffix';
    final branch = member?.branch_name;

    return Container(
      clipBehavior: Clip.antiAlias,
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
      child: Stack(
        children: [
          const Positioned.fill(
            child: IgnorePointer(
              child: CustomPaint(painter: _IslamicPatternPainter()),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(Spacing.xl),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _OrgLogoBadge(url: _logoUrl),
                const SizedBox(height: Spacing.lg),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ClipRRect(
                      borderRadius: AppRadius.md,
                      child: AppImage(
                        member?.photo_url,
                        width: 76,
                        height: 76,
                        fit: BoxFit.cover,
                      ),
                    ),
                    const SizedBox(width: Spacing.lg),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            member?.name ?? 'Ahli',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(
                              context,
                            ).textTheme.titleMedium?.copyWith(
                              color: AppColors.white,
                              fontSize: 17,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: Spacing.xs),
                          Text(
                            sinceText,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(
                              context,
                            ).textTheme.labelMedium?.copyWith(
                              color: Colors.white.withValues(alpha: .72),
                              fontSize: 11,
                              height: 1.3,
                            ),
                          ),
                          if (branch?.isNotEmpty == true) ...[
                            const SizedBox(height: 2),
                            Text(
                              'Cawangan $branch',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(
                                context,
                              ).textTheme.labelMedium?.copyWith(
                                color: Colors.white.withValues(alpha: .72),
                                fontSize: 11,
                              ),
                            ),
                          ],
                          const SizedBox(height: Spacing.sm),
                          Text(
                            member?.member_no ?? '-',
                            style: const TextStyle(
                              color: AppColors.movementSoftGreen,
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: Spacing.xs),
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton.icon(
                    onPressed: () => context.push('/card'),
                    icon: const Icon(Icons.chevron_right_rounded, size: 14),
                    label: const Text('Lihat Kad Penuh'),
                    style: TextButton.styleFrom(
                      foregroundColor: Colors.white.withValues(alpha: .78),
                      minimumSize: Size.zero,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 6,
                        vertical: 2,
                      ),
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      textStyle: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
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

class _OrgLogoBadge extends StatelessWidget {
  const _OrgLogoBadge({required this.url});
  final String? url;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 44,
      height: 44,
      padding: const EdgeInsets.all(6),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(12),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child:
            url?.isNotEmpty == true
                ? AppImage(url, fit: BoxFit.contain)
                : const Icon(
                  Icons.groups_2_rounded,
                  color: AppColors.movementGreen,
                  size: 22,
                ),
      ),
    );
  }
}

/// Subtle seamless islamic geometric lattice painted behind card content.
class _IslamicPatternPainter extends CustomPainter {
  const _IslamicPatternPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final paint =
        Paint()
          ..style = PaintingStyle.stroke
          ..strokeWidth = 0.6
          ..color = Colors.white.withValues(alpha: 0.055);

    const spacing = 92.0;
    final side = spacing / math.sqrt2;

    for (double x = -spacing; x < size.width + spacing; x += spacing) {
      for (double y = -spacing; y < size.height + spacing; y += spacing) {
        _square(canvas, Offset(x, y), side, 0, paint);
        _square(canvas, Offset(x, y), side, math.pi / 4, paint);
      }
    }
  }

  void _square(
    Canvas canvas,
    Offset center,
    double side,
    double angle,
    Paint paint,
  ) {
    canvas.save();
    canvas.translate(center.dx, center.dy);
    canvas.rotate(angle);
    canvas.drawRect(
      Rect.fromCenter(center: Offset.zero, width: side, height: side),
      paint,
    );
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _IslamicPatternPainter oldDelegate) => false;
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
    final progress =
        ((item.progress_percent ?? 0) / 100).clamp(0, 1).toDouble();
    return SizedBox(
      width: 172,
      child: Material(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        child: InkWell(
          borderRadius: AppRadius.card,
          onTap: () => context.go('/infaq'),
          child: ClipRRect(
            borderRadius: AppRadius.card,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AspectRatio(
                  aspectRatio: 4 / 5,
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      AppImage(item.image_path, fit: BoxFit.cover),
                      const DecoratedBox(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.bottomCenter,
                            end: Alignment.topCenter,
                            colors: [Color(0x55071525), Colors.transparent],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.all(Spacing.sm),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.title ?? '-',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(
                            context,
                          ).textTheme.labelMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                            height: 1.2,
                          ),
                        ),
                        const Spacer(),
                        ClipRRect(
                          borderRadius: AppRadius.xs,
                          child: LinearProgressIndicator(
                            value: progress,
                            minHeight: 5,
                            backgroundColor: AppColors.divider,
                            color: AppColors.movementGreen,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          '${item.progress_percent ?? 0}% terkumpul',
                          style: Theme.of(
                            context,
                          ).textTheme.bodySmall?.copyWith(
                            color: AppColors.textSecondary,
                            fontSize: 10,
                          ),
                        ),
                      ],
                    ),
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
                  color:
                      responded ? AppColors.success : AppColors.movementGreen,
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
      width: 172,
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
                  aspectRatio: 4 / 5,
                  child:
                      item.cover_image_path?.isNotEmpty == true
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
                        style: Theme.of(context).textTheme.labelMedium
                            ?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        item.category_name ?? '',
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

class _ArticleCard extends StatelessWidget {
  const _ArticleCard({required this.item});
  final ArticleItem item;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 172,
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
                  aspectRatio: 4 / 5,
                  child:
                      item.cover_image_path?.isNotEmpty == true
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
                        style: Theme.of(context).textTheme.labelMedium
                            ?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        item.author_name ?? '',
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
