import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:qr_flutter/qr_flutter.dart';

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

  static const Color _gradientTop = Color(0xFF0F5F3E);
  static const Color _gradientBottom = Color(0xFF1B8F5A);

  String? get _logoUrl {
    final orgLogo = member?.organization?.logo_path;
    if (orgLogo?.isNotEmpty == true) return orgLogo;
    final systemLogo = member?.system_logo_path;
    return (systemLogo?.isNotEmpty == true) ? systemLogo : null;
  }

  @override
  Widget build(BuildContext context) {
    final orgName = member?.organization?.name?.trim();
    final photo = member?.photo_url;
    final name = member?.name ?? 'Ahli';

    return Container(
      constraints: const BoxConstraints(minHeight: 248),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [_gradientTop, _gradientBottom],
          stops: [0.15, 1.0],
        ),
        borderRadius: const BorderRadius.all(Radius.circular(28)),
        boxShadow: AppShadows.floating,
      ),
      child: Stack(
        children: [
          // Large subtle ABIM / org logo watermark.
          Positioned.fill(
            child: IgnorePointer(
              child: _Watermark(url: _logoUrl, name: orgName),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.md,
              Spacing.md,
              Spacing.md,
              Spacing.xxl,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _MemberAvatar(photoUrl: photo, name: name),
                    const SizedBox(width: Spacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'KAD KEAHLIAN · '
                                    '${orgName?.isNotEmpty == true ? orgName : 'ORGANISASI'}'
                                .toUpperCase(),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Color(0xCCFFFFFF),
                              fontSize: 10,
                              fontWeight: FontWeight.w600,
                              letterSpacing: 1.6,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            name.toUpperCase(),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(
                              context,
                            ).textTheme.titleLarge?.copyWith(
                              color: AppColors.white,
                              fontSize: 19,
                              fontWeight: FontWeight.w800,
                              height: 1.05,
                              letterSpacing: .2,
                            ),
                          ),
                          const SizedBox(height: 6),
                          const _ActiveBadge(),
                        ],
                      ),
                    ),
                    const SizedBox(width: Spacing.sm),
                    _OrgLogo(url: _logoUrl),
                  ],
                ),
                const SizedBox(height: Spacing.md),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    Expanded(
                      child: Row(
                        children: [
                          Expanded(
                            child: _FieldValue(
                              label: 'No Ahli',
                              value: member?.member_no ?? '-',
                            ),
                          ),
                          Container(
                            width: 1,
                            height: 40,
                            color: const Color(0x33FFFFFF),
                          ),
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.only(left: Spacing.md),
                              child: _FieldValue(
                                label: 'Sejak',
                                value: member?.member_since ?? '-',
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: Spacing.md),
                    _QrBadge(
                      value: member?.qr_value,
                      label: member?.member_no ?? name,
                    ),
                  ],
                ),
              ],
            ),
          ),
          // "Lihat Kad Penuh" button pinned to the bottom-center of the card.
          Positioned(
            left: 0,
            right: 0,
            bottom: 3,
            child: Center(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: _SeeFullCardButton(),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MemberAvatar extends StatelessWidget {
  const _MemberAvatar({required this.photoUrl, required this.name});
  final String? photoUrl;
  final String name;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 98,
      height: 98,
      padding: const EdgeInsets.all(2),
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: const Color(0x59FFFFFF), width: 2),
      ),
      child: ClipOval(
        child:
            photoUrl?.isNotEmpty == true
                ? AppImage(photoUrl, fit: BoxFit.cover, borderRadius: null)
                : _AvatarFallback(name: name),
      ),
    );
  }
}

class _OrgLogo extends StatelessWidget {
  const _OrgLogo({required this.url});
  final String? url;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 52,
      height: 52,
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
                  color: _MembershipCard._gradientTop,
                  size: 24,
                ),
      ),
    );
  }
}

class _AvatarFallback extends StatelessWidget {
  const _AvatarFallback({required this.name});
  final String name;

  String get _initials {
    final parts = name
        .replaceAll(RegExp(r'[^A-Za-z ]'), '')
        .trim()
        .split(RegExp(r'\s+'));
    if (parts.isEmpty) return '?';
    final first = parts.first.isNotEmpty ? parts.first[0] : '';
    final second = parts.length > 1 && parts[1].isNotEmpty ? parts[1][0] : '';
    return '$first$second'.toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      color: const Color(0x26FFFFFF),
      alignment: Alignment.center,
      child: Text(
        _initials,
        style: const TextStyle(
          color: AppColors.white,
          fontSize: 32,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _ActiveBadge extends StatelessWidget {
  const _ActiveBadge();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
      decoration: BoxDecoration(
        color: const Color(0xFF2E8B57).withValues(alpha: .35),
        borderRadius: BorderRadius.circular(99),
        border: Border.all(color: const Color(0x59FFFFFF)),
      ),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.circle, size: 8, color: Color(0xFFA8E6BB)),
          SizedBox(width: 6),
          Text(
            'Ahli Aktif',
            style: TextStyle(
              color: AppColors.white,
              fontSize: 11,
              fontWeight: FontWeight.w700,
              letterSpacing: .3,
            ),
          ),
        ],
      ),
    );
  }
}

class _FieldValue extends StatelessWidget {
  const _FieldValue({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(color: Color(0x99FFFFFF), fontSize: 11),
        ),
        const SizedBox(height: 3),
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: AppColors.white,
            fontSize: 19,
            fontWeight: FontWeight.w700,
            letterSpacing: .4,
          ),
        ),
      ],
    );
  }
}

class _QrBadge extends StatelessWidget {
  const _QrBadge({this.value, this.label});
  final String? value;
  final String? label;

  @override
  Widget build(BuildContext context) {
    final data = value;
    return GestureDetector(
      onTap: () => _showQrDialog(context, data),
      child: Container(
        padding: const EdgeInsets.all(6),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(8),
        ),
        child:
            data?.isNotEmpty == true
                ? QrImageView(
                  data: data!,
                  version: QrVersions.auto,
                  size: 56,
                  padding: EdgeInsets.zero,
                  backgroundColor: AppColors.white,
                  gapless: true,
                )
                : const Icon(
                  Icons.qr_code_2,
                  size: 56,
                  color: AppColors.movementGreen,
                ),
      ),
    );
  }

  void _showQrDialog(BuildContext context, String? data) {
    showDialog<void>(
      context: context,
      builder:
          (dialogContext) => Dialog(
            backgroundColor: Colors.transparent,
            insetPadding: const EdgeInsets.all(Spacing.xl),
            child: Container(
              padding: const EdgeInsets.all(Spacing.xl),
              decoration: BoxDecoration(
                color: AppColors.white,
                borderRadius: BorderRadius.circular(24),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    (label?.isNotEmpty == true ? label! : 'Kad Ahli')
                        .toUpperCase(),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: AppColors.movementGreen,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.2,
                    ),
                  ),
                  const SizedBox(height: Spacing.lg),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: AppColors.white,
                      border: Border.all(color: const Color(0xFFE7EAEE)),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child:
                        data?.isNotEmpty == true
                            ? QrImageView(
                              data: data!,
                              version: QrVersions.auto,
                              size: 230,
                              padding: EdgeInsets.zero,
                              backgroundColor: AppColors.white,
                              gapless: true,
                            )
                            : const SizedBox(
                              width: 230,
                              height: 230,
                              child: Icon(
                                Icons.qr_code_2,
                                size: 140,
                                color: AppColors.movementGreen,
                              ),
                            ),
                  ),
                  const SizedBox(height: Spacing.lg),
                  const Text(
                    'Imbas QR ini untuk pengesahan keahlian.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: Spacing.md),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: () => Navigator.of(dialogContext).pop(),
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.movementGreen,
                        foregroundColor: AppColors.white,
                        minimumSize: const Size.fromHeight(46),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      child: const Text('Tutup'),
                    ),
                  ),
                ],
              ),
            ),
          ),
    );
  }
}

class _SeeFullCardButton extends StatelessWidget {
  const _SeeFullCardButton();

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => context.push('/card'),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: const Color(0xFF123D2A),
          borderRadius: BorderRadius.circular(99),
          border: Border.all(color: const Color(0x40FFFFFF)),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Lihat Kad Penuh',
              style: TextStyle(
                color: AppColors.white,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
            SizedBox(width: 2),
            Icon(Icons.chevron_right_rounded, color: AppColors.white, size: 16),
          ],
        ),
      ),
    );
  }
}

/// Large low-opacity org logo used as a card watermark.
class _Watermark extends StatelessWidget {
  const _Watermark({required this.url, required this.name});
  final String? url;
  final String? name;

  @override
  Widget build(BuildContext context) {
    final logoUrl = url;
    if (logoUrl?.isNotEmpty == true) {
      return Align(
        alignment: Alignment.centerRight,
        child: Padding(
          padding: const EdgeInsets.only(right: 28),
          child: Opacity(
            opacity: 0.08,
            child: SizedBox(
              width: 170,
              height: 170,
              child: AppImage(logoUrl, fit: BoxFit.contain, borderRadius: null),
            ),
          ),
        ),
      );
    }
    return Opacity(
      opacity: 0.06,
      child: Align(
        alignment: Alignment.centerRight,
        child: Padding(
          padding: const EdgeInsets.only(right: 20),
          child: Text(
            (name?.isNotEmpty == true ? name : 'ABIM')!.toUpperCase(),
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 72,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      ),
    );
  }
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
