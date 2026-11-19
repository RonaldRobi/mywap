import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/organic_canvas_background.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../auth/application/auth_controller.dart';
import '../../ecommerce/application/cart_notifier.dart';
import '../../ecommerce/application/product_providers.dart';
import '../../ecommerce/data/models/product.dart';
import '../../events/data/models/event.dart';
import '../application/member_core_providers.dart';
import '../application/member_providers.dart';
import '../data/models/dashboard_data.dart';
import '../data/models/library_item.dart';
import 'library_reader_screen.dart';
import 'widgets/notification_bell.dart';
import '../../prayer/presentation/prayer_banner.dart';
import '../../media_statements/application/media_statement_providers.dart';
import '../../media_statements/data/media_statement_repository.dart';
import '../../news/application/news_providers.dart';
import '../../news/data/models/news.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../shared/theme/app_text_theme.dart';

class MemberDashboardScreen extends ConsumerWidget {
  const MemberDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    final dashboardAsync = ref.watch(memberDashboardProvider);

    return Scaffold(
      body: Stack(
        fit: StackFit.expand,
        children: [
          // Lapisan hiasan paling bawah — kekal di belakang kandungan untuk
          // semua keadaan (data/loading/error) tanpa menjejaskan SafeArea
          // atau scroll.
          const OrganicCanvasBackground(),
          dashboardAsync.when(
            data: (data) => _DashboardContent(userName: user?.name, data: data),
            loading: () => const _DashboardSkeleton(),
            error:
                (error, _) => ErrorRetry(
                  message:
                      error is ApiException
                          ? error.message
                          : context.l10n.unexpectedError,
                  onRetry: () => ref.invalidate(memberDashboardProvider),
                ),
          ),
        ],
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
    final memberName = member?.name ?? userName ?? context.l10n.memberFallback;
    final banners = data.banners ?? const [];
    final events = data.upcoming_events ?? const [];
    final infaqItems = data.infaq_items ?? const [];
    final news = data.latest_news ?? const [];
    final articles = data.latest_articles ?? const [];
    final polls = data.active_polls ?? const [];
    final mediaStatements =
        ref.watch(mediaStatementsProvider).valueOrNull ??
        const <MediaStatement>[];
    final videos = ref.watch(videosProvider).valueOrNull ?? const <Video>[];
    final mallProducts =
        ref.watch(productsProvider).valueOrNull?.items ?? const <Product>[];

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(memberDashboardProvider);
        ref.invalidate(memberLibraryProvider);
        ref.invalidate(videosProvider);
      },
      child: CustomScrollView(
        slivers: [
          SliverAppBar(
            pinned: true,
            backgroundColor: AppColors.pageBackground.withValues(alpha: 0.94),
            surfaceTintColor: Colors.transparent,
            elevation: 0,
            toolbarHeight: 64,
            titleSpacing: Spacing.lg,
            title: _PolishedGreeting(member: member, name: memberName),
            actions: const [NotificationBell(), SizedBox(width: Spacing.lg)],
          ),
          const SliverToBoxAdapter(child: PrayerBanner()),
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
                  title: context.l10n.menuPrograms,
                  subtitle: context.l10n.sectionProgramSubtitle,
                  action: () => context.go('/events'),
                ),
                const SizedBox(height: Spacing.md),
                if (events.isEmpty)
                  _EmptySection(
                    icon: Icons.event_busy_outlined,
                    message: context.l10n.noUpcomingEventsShort,
                  )
                else
                  SizedBox(
                    // Kad Pas Program (Event Pass) dengan Poster Potret 4:5 Sebenar & Butang CTA Penuh
                    height: 406,
                    child: ListView.separated(
                      clipBehavior: Clip.none,
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
                    title: context.l10n.infaqTitle,
                    subtitle: context.l10n.sectionInfaqSubtitle,
                    action: () => context.go('/infaq'),
                  ),
                  const SizedBox(height: Spacing.md),
                  SizedBox(
                    height: 255,
                    child: ListView.separated(
                      clipBehavior: Clip.none,
                      scrollDirection: Axis.horizontal,
                      itemCount: infaqItems.length,
                      separatorBuilder:
                          (_, __) => const SizedBox(width: Spacing.md),
                      itemBuilder:
                          (_, index) => _InfaqCard(item: infaqItems[index]),
                    ),
                  ),
                ],
                if (mediaStatements.isNotEmpty) ...[
                  const SizedBox(height: Spacing.xl),
                  _SectionLabel(
                    title: context.l10n.mediaStatementsTitle,
                    subtitle: 'Siaran rasmi & pendirian terkini pertubuhan',
                    action: () => context.push('/media-statements'),
                  ),
                  const SizedBox(height: Spacing.md),
                  SizedBox(
                    // Kad Media Statement 4:5 Potret Padan dengan Kad Program
                    height: 406,
                    child: ListView.separated(
                      clipBehavior: Clip.none,
                      scrollDirection: Axis.horizontal,
                      itemCount: mediaStatements.length,
                      separatorBuilder:
                          (_, __) => const SizedBox(width: Spacing.md),
                      itemBuilder:
                          (_, index) => _MediaStatementPreview(
                            item: mediaStatements[index],
                          ),
                    ),
                  ),
                ],
                if (polls.isNotEmpty) ...[
                  const SizedBox(height: Spacing.xl),
                  _SectionLabel(
                    title: context.l10n.sectionPolls,
                    subtitle: context.l10n.sectionPollsSubtitle,
                    action: () => context.push('/polls'),
                  ),
                  const SizedBox(height: Spacing.md),
                  ...polls.take(2).map((p) => _PollPreviewCard(poll: p)),
                ],
                if (news.isNotEmpty) ...[
                  const SizedBox(height: Spacing.xl),
                  _SectionLabel(
                    title: context.l10n.newsTitle,
                    subtitle: context.l10n.sectionNewsSubtitle,
                    action: () => context.push('/news'),
                  ),
                  const SizedBox(height: Spacing.md),
                  SizedBox(
                    height: 220,
                    child: ListView.separated(
                      clipBehavior: Clip.none,
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
                    title: context.l10n.articlesTitle,
                    subtitle: context.l10n.sectionArticlesSubtitle,
                    action: () => context.push('/articles'),
                  ),
                  const SizedBox(height: Spacing.md),
                  SizedBox(
                    height: 220,
                    child: ListView.separated(
                      clipBehavior: Clip.none,
                      scrollDirection: Axis.horizontal,
                      itemCount: articles.length.clamp(0, 6),
                      separatorBuilder:
                          (_, __) => const SizedBox(width: Spacing.md),
                      itemBuilder:
                          (_, index) => _ArticleCard(item: articles[index]),
                    ),
                  ),
                ],
                if (videos.isNotEmpty) ...[
                  const SizedBox(height: Spacing.xl),
                  _SectionLabel(
                    title: context.l10n.videoTitle,
                    subtitle: 'Kuliah pilihan, tazkirah & rakaman terkini',
                    action: () => context.push('/videos'),
                  ),
                  const SizedBox(height: Spacing.md),
                  _VideoSection(videos: videos),
                ],
                if (mallProducts.isNotEmpty) ...[
                  const SizedBox(height: Spacing.xl),
                  _SectionLabel(
                    title: 'MyWAP Mall',
                    subtitle: 'Pilihan ahli',
                    action: () => context.push('/products'),
                  ),
                  const SizedBox(height: Spacing.md),
                  _MallSection(products: mallProducts),
                ],
                const _LibrarySection(),
              ]),
            ),
          ),
        ],
      ),
    );
  }
}

class _PolishedGreeting extends StatelessWidget {
  const _PolishedGreeting({this.member, required this.name});
  final DashboardMember? member;
  final String name;

  /// Nama pertama sahaja — nama penuh memenuhi bar tajuk dan terpotong
  String get _firstName {
    final parts = name.trim().split(RegExp(r'\s+'));
    return parts.isEmpty ? name : parts.first;
  }

  String get _initial {
    final trimmed = _firstName.trim();
    if (trimmed.isEmpty) return 'M';
    return trimmed.substring(0, 1).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final hour = DateTime.now().hour;
    final (timeGreeting, timeIcon) =
        hour < 12
            ? ('SELAMAT PAGI', Icons.wb_sunny_rounded)
            : hour < 15
            ? ('SELAMAT TENGAH HARI', Icons.wb_sunny_rounded)
            : hour < 19
            ? ('SELAMAT PETANG', Icons.wb_twilight_rounded)
            : ('SELAMAT MALAM', Icons.nights_stay_rounded);

    final photoUrl = member?.photo_url;

    return Row(
      children: [
        // 1. VIP Avatar dengan Dwi-Lapisan Ring Apple
        GestureDetector(
          onTap: () => context.push('/profile'),
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              Container(
                width: 44,
                height: 44,
                padding: const EdgeInsets.all(2),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      AppColors.movementGreen.withValues(alpha: 0.45),
                      AppColors.movementDarkGreen.withValues(alpha: 0.15),
                    ],
                  ),
                  boxShadow: const [
                    BoxShadow(
                      color: Color(0x10000000),
                      blurRadius: 8,
                      offset: Offset(0, 2),
                    ),
                  ],
                ),
                child: Container(
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.white,
                  ),
                  child: ClipOval(
                    child:
                        photoUrl != null && photoUrl.isNotEmpty
                            ? AppImage(photoUrl, fit: BoxFit.cover)
                            : Container(
                              decoration: const BoxDecoration(
                                gradient: LinearGradient(
                                  begin: Alignment.topLeft,
                                  end: Alignment.bottomRight,
                                  colors: [
                                    Color(0xFFE8F5E9),
                                    Color(0xFFC8E6C9),
                                  ],
                                ),
                              ),
                              child: Center(
                                child: Text(
                                  _initial,
                                  style: const TextStyle(
                                    color: AppColors.movementDarkGreen,
                                    fontWeight: FontWeight.w800,
                                    fontSize: 17,
                                    letterSpacing: -0.5,
                                  ),
                                ),
                              ),
                            ),
                  ),
                ),
              ),
              // Titik Ahli Aktif (Active Status Ring)
              Positioned(
                right: 0,
                bottom: 0,
                child: Container(
                  width: 12,
                  height: 12,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: const Color(0xFF34C759), // Apple Active Green
                    border: Border.all(color: AppColors.white, width: 2),
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: Spacing.md),

        // 2. Hirarki Tipografi Sapaan Anggun
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(timeIcon, size: 11, color: AppColors.movementGreen),
                  const SizedBox(width: 4),
                  Text(
                    timeGreeting,
                    style: const TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 0.8,
                      color: AppColors.movementGreen,
                      height: 1.1,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 2),
              Text(
                context.l10n.greetingSalam(_firstName),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.5,
                  color: AppColors.movementNavy,
                  height: 1.2,
                ),
              ),
            ],
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
        borderRadius: AppRadius.banner,
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
                    banner?.title ?? context.l10n.welcomeDefault,
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
                ),
              ),
          ],
        ),
      ),
      if (action != null)
        TextButton(onPressed: action, child: Text(context.l10n.viewAll)),
    ],
  );
}

/// Compact 4-column shortcut grid for high-frequency member modules.
///
/// Lapan item sahaja (Pustaka & Undian dibuang — ia ada dalam drawer dan
/// bukan tindakan pantas). Dua baris 4-lajur supaya seimbang.
class _ShortcutsGrid extends ConsumerWidget {
  const _ShortcutsGrid();

  static List<_ShortcutItem> _items(
    AppLocalizations l10n, {
    required bool includeFee,
    required bool isAdmin,
  }) => [
    if (includeFee)
      _ShortcutItem(
        icon: Icons.receipt_long_rounded,
        label: l10n.quickFees,
        color: AppColors.shortcutFee,
        path: '/member/fee-status',
      ),
    _ShortcutItem(
      icon: Icons.apartment_rounded,
      label: l10n.quickBook,
      color: AppColors.shortcutBooking,
      path: '/facilities',
    ),
    _ShortcutItem(
      icon: Icons.newspaper_rounded,
      label: l10n.quickNews,
      color: AppColors.shortcutNews,
      path: '/news',
    ),
    _ShortcutItem(
      icon: Icons.volunteer_activism_rounded,
      label: l10n.menuInfaq,
      color: AppColors.shortcutInfaq,
      path: '/infaq',
    ),
    if (!isAdmin)
      _ShortcutItem(
        icon: Icons.badge_rounded,
        label: l10n.quickCard,
        color: AppColors.shortcutCard,
        path: '/card',
      ),
    if (!isAdmin)
      _ShortcutItem(
        icon: Icons.diversity_3_rounded,
        label: l10n.menuUsrah,
        color: AppColors.shortcutUsrah,
        path: '/usrah',
      ),
    _ShortcutItem(
      icon: Icons.storefront_rounded,
      label: l10n.quickMall,
      color: AppColors.shortcutShop,
      path: '/products',
    ),
    if (!isAdmin)
      _ShortcutItem(
        icon: Icons.person_add_alt_1_rounded,
        label: l10n.quickInvite,
        color: AppColors.shortcutReferral,
        path: '/member/referral',
      ),
  ];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isAdmin = ref.watch(currentUserProvider)?.isAdmin ?? false;
    final items = _items(context.l10n, includeFee: !isAdmin, isAdmin: isAdmin);
    final rows = <Widget>[];
    for (var i = 0; i < items.length; i += 4) {
      if (i > 0) rows.add(const SizedBox(height: Spacing.md));
      rows.add(
        Row(
          children: [
            for (final item in items.skip(i).take(4))
              Expanded(child: _ShortcutTile(item: item)),
          ],
        ),
      );
    }
    return Column(children: rows);
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
        borderRadius: BorderRadius.circular(16),
        onTap: () => context.push(item.path),
        child: Padding(
          padding: const EdgeInsets.symmetric(
            vertical: Spacing.xs,
            horizontal: 2,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: item.color,
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [
                    BoxShadow(
                      color: item.color.withValues(alpha: 0.28),
                      blurRadius: 8,
                      offset: const Offset(0, 3),
                    ),
                  ],
                ),
                child: Icon(item.icon, color: AppColors.white, size: 22),
              ),
              const SizedBox(height: 6),
              Text(
                item.label,
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  letterSpacing: -0.2,
                  color: AppColors.textPrimary,
                  height: 1.15,
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
    final orgName = member?.organization?.name?.trim();
    final photo = member?.photo_url;
    final name = member?.name ?? context.l10n.memberFallback;

    return Container(
      constraints: const BoxConstraints(minHeight: 248),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: AppColors.heroGradient,
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
                            context.l10n
                                .memberCardTitle(
                                  orgName?.isNotEmpty == true
                                      ? orgName!
                                      : context.l10n.orgFallbackUpper,
                                )
                                .toUpperCase(),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: AppColors.textOnDark,
                              fontSize: AppTextTheme.minSize,
                              fontWeight: FontWeight.w600,
                              letterSpacing: 1.6,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            name.toUpperCase(),
                            maxLines: 3,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(
                              context,
                            ).textTheme.titleLarge?.copyWith(
                              color: AppColors.white,
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                              height: 1.15,
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
                              label: context.l10n.memberNoLabel,
                              value: member?.member_no ?? '-',
                            ),
                          ),
                          Container(
                            width: 1,
                            height: 40,
                            color: AppColors.white.withValues(alpha: .20),
                          ),
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.only(left: Spacing.md),
                              child: _FieldValue(
                                label: context.l10n.sinceLabel,
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
        border: Border.all(
          color: AppColors.white.withValues(alpha: .35),
          width: 2,
        ),
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
                  color: AppColors.movementDarkGreen,
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
      color: AppColors.white.withValues(alpha: .15),
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
        border: Border.all(color: AppColors.white.withValues(alpha: .35)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.circle, size: 8, color: AppColors.movementSoftGreen),
          const SizedBox(width: 6),
          Text(
            context.l10n.activeMember,
            style: TextStyle(
              color: AppColors.white,
              fontSize: AppTextTheme.minSize,
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
          style: const TextStyle(
            color: AppColors.textOnDark,
            fontSize: AppTextTheme.minSize,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: AppColors.white,
            fontSize: 16,
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
                    (label?.isNotEmpty == true
                            ? label!
                            : context.l10n.quickCard)
                        .toUpperCase(),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: AppColors.movementGreen,
                      fontSize: AppTextTheme.minSize,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.2,
                    ),
                  ),
                  const SizedBox(height: Spacing.lg),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: AppColors.white,
                      border: Border.all(color: AppColors.divider),
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
                  Text(
                    context.l10n.scanQrHint,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: AppTextTheme.minSize,
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
                      child: Text(context.l10n.close),
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
          color: AppColors.movementDarkGreen,
          borderRadius: BorderRadius.circular(99),
          border: Border.all(color: AppColors.white.withValues(alpha: .25)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              context.l10n.viewFullCard,
              style: TextStyle(
                color: AppColors.white,
                fontSize: AppTextTheme.minSize,
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
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ),
    );
  }
}

class _EventDateParts {
  const _EventDateParts({
    required this.day,
    required this.month,
    required this.weekday,
  });

  final String day;
  final String month;
  final String weekday;
}

_EventDateParts _parseCalendarDate(Event event) {
  if (event.start_time?.isNotEmpty == true) {
    final dt = DateTime.tryParse(event.start_time!);
    if (dt != null) {
      final local = dt.toLocal();
      final day = local.day.toString().padLeft(2, '0');
      final month = Formatters.months[local.month - 1].toUpperCase();
      final weekday =
          Formatters.weekdays[local.weekday - 1].substring(0, 3).toUpperCase();
      return _EventDateParts(day: day, month: month, weekday: weekday);
    }
  }

  final raw = event.start_formatted?.trim();
  if (raw != null && raw.contains('/')) {
    final datePart = raw.split(' ').first;
    final parts = datePart.split('/');
    if (parts.length == 3) {
      final d = int.tryParse(parts[0]);
      final m = int.tryParse(parts[1]);
      final y = int.tryParse(parts[2]);
      if (d != null && m != null && y != null && m >= 1 && m <= 12) {
        final dt = DateTime(y, m, d);
        final day = d.toString().padLeft(2, '0');
        final month = Formatters.months[m - 1].toUpperCase();
        final weekday =
            Formatters.weekdays[dt.weekday - 1].substring(0, 3).toUpperCase();
        return _EventDateParts(day: day, month: month, weekday: weekday);
      }
    }
  }

  if (raw != null && raw.isNotEmpty) {
    final clean = raw.contains('•') ? raw.split('•').first.trim() : raw;
    final commaParts = clean.split(',');
    String weekday = 'ACARA';
    String day = '--';
    String month = 'ACARA';

    if (commaParts.length == 2) {
      final w = commaParts.first.trim();
      weekday =
          w.length >= 3 ? w.substring(0, 3).toUpperCase() : w.toUpperCase();
      final dateTokens = commaParts[1].trim().split(RegExp(r'\s+'));
      if (dateTokens.isNotEmpty) day = dateTokens[0].padLeft(2, '0');
      if (dateTokens.length > 1) month = dateTokens[1].toUpperCase();
    }
    return _EventDateParts(day: day, month: month, weekday: weekday);
  }

  return const _EventDateParts(day: '--', month: 'ACARA', weekday: 'ACARA');
}

/// Stempel Kalendar Timbul (Floating 3D-feel Calendar Stamp)
class _FloatingCalendarStamp extends StatelessWidget {
  const _FloatingCalendarStamp({required this.dateParts});

  final _EventDateParts dateParts;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 48,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(
          color: AppColors.divider.withValues(alpha: 0.8),
          width: 0.8,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x35000000),
            blurRadius: 8,
            offset: Offset(0, 3),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Header Bulan Hijau Jenama
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 2.5),
            color: AppColors.movementGreen,
            child: Text(
              dateParts.month,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: AppColors.white,
                fontSize: 9,
                fontWeight: FontWeight.w800,
                letterSpacing: 0.6,
              ),
            ),
          ),
          // Nombor Hari Besar & Jelas
          Padding(
            padding: const EdgeInsets.fromLTRB(2, 3, 2, 1),
            child: Text(
              dateParts.day,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: AppColors.movementNavy,
                fontSize: 17,
                fontWeight: FontWeight.w900,
                height: 1.05,
              ),
            ),
          ),
          // Singkatan Hari (cth: SAB)
          Padding(
            padding: const EdgeInsets.only(bottom: 3),
            child: Text(
              dateParts.weekday,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 8,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.4,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Kad Acara Konsep Pas Masuk / Tiket (Event Pass Card)
class _EventCard extends StatelessWidget {
  const _EventCard({required this.event});
  final Event event;

  @override
  Widget build(BuildContext context) {
    final isOnline = event.type == 'online';
    final locationText =
        isOnline
            ? (event.location_or_link?.isNotEmpty == true
                ? event.location_or_link!
                : context.l10n.eventOnline)
            : (event.location_or_link?.isNotEmpty == true
                ? event.location_or_link!
                : context.l10n.eventPhysical);
    final dateParts = _parseCalendarDate(event);
    final isMalay = Localizations.localeOf(context).languageCode == 'ms';

    return Container(
      width: 210,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: AppColors.divider.withValues(alpha: 0.6),
          width: 0.8,
        ),
        boxShadow: AppShadows.subtle,
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => context.push('/events/${event.id}'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              // Bahagian Atas: Poster Potret 4:5 Sebenar dengan Stempel Kalendar Timbul & Lencana Status
              AspectRatio(
                aspectRatio: 4 / 5,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    if (event.featured_image_url?.isNotEmpty == true)
                      AppImage(
                        event.featured_image_url,
                        width: double.infinity,
                        height: double.infinity,
                        fit: BoxFit.cover,
                      )
                    else
                      Container(
                        color: AppColors.movementNavy,
                        child: Center(
                          child: Icon(
                            Icons.event_outlined,
                            size: 48,
                            color: AppColors.movementSoftGreen.withValues(
                              alpha: 0.4,
                            ),
                          ),
                        ),
                      ),
                    // Gradien bayang atas halus untuk kontras lencana
                    const Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      height: 50,
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [Color(0x40000000), Colors.transparent],
                          ),
                        ),
                      ),
                    ),
                    // Stempel Kalendar Terapung di Bucu Kiri Atas
                    Positioned(
                      left: 10,
                      top: 10,
                      child: _FloatingCalendarStamp(dateParts: dateParts),
                    ),
                    // Lencana Status (Online / Fizikal) di Bucu Kanan Atas
                    Positioned(
                      right: 10,
                      top: 10,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.movementNavy.withValues(alpha: 0.72),
                          borderRadius: BorderRadius.circular(99),
                          border: Border.all(
                            color: AppColors.white.withValues(alpha: 0.25),
                            width: 0.75,
                          ),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 6,
                              height: 6,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color:
                                    isOnline
                                        ? const Color(0xFF34D399)
                                        : const Color(0xFFFBBF24),
                                boxShadow: [
                                  BoxShadow(
                                    color: (isOnline
                                            ? const Color(0xFF34D399)
                                            : const Color(0xFFFBBF24))
                                        .withValues(alpha: 0.6),
                                    blurRadius: 4,
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 5),
                            Text(
                              isOnline
                                  ? context.l10n.eventOnline.toUpperCase()
                                  : context.l10n.eventPhysical.toUpperCase(),
                              style: const TextStyle(
                                color: AppColors.white,
                                fontSize: 9.5,
                                fontWeight: FontWeight.w700,
                                letterSpacing: 0.6,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              // Bahagian Tengah: Pembahagi Tiket / Kupon Bertakuk (Ticket Notch)
              Row(
                children: [
                  Container(
                    width: 8,
                    height: 14,
                    decoration: const BoxDecoration(
                      color: AppColors.pageBackground,
                      borderRadius: BorderRadius.horizontal(
                        right: Radius.circular(7),
                      ),
                    ),
                  ),
                  Expanded(
                    child: LayoutBuilder(
                      builder: (context, constraints) {
                        final dashCount =
                            (constraints.constrainWidth() / 8).floor();
                        return Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: List.generate(
                            dashCount,
                            (_) => Container(
                              width: 4,
                              height: 1,
                              color: AppColors.divider.withValues(alpha: 0.7),
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                  Container(
                    width: 8,
                    height: 14,
                    decoration: const BoxDecoration(
                      color: AppColors.pageBackground,
                      borderRadius: BorderRadius.horizontal(
                        left: Radius.circular(7),
                      ),
                    ),
                  ),
                ],
              ),

              // Bahagian Bawah: Maklumat Teks & Butang CTA Penuh
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 6, 12, 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    SizedBox(
                      height: 38,
                      child: Text(
                        event.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppColors.movementNavy,
                          height: 1.25,
                        ),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Icon(
                          isOnline
                              ? Icons.link_rounded
                              : Icons.location_on_outlined,
                          size: 14,
                          color: AppColors.movementGreen,
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            locationText,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w500,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    // Butang Penuh (Full-Width Button) Gradient Hijau
                    Container(
                      width: double.infinity,
                      height: 34,
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [
                            AppColors.movementGreen,
                            AppColors.movementDarkGreen,
                          ],
                        ),
                        borderRadius: BorderRadius.circular(9),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.movementGreen.withValues(
                              alpha: 0.28,
                            ),
                            blurRadius: 4,
                            offset: const Offset(0, 1.5),
                          ),
                        ],
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            isMalay ? 'Lihat Program' : 'View Event',
                            style: const TextStyle(
                              color: AppColors.white,
                              fontSize: 11.5,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 0.2,
                            ),
                          ),
                          const SizedBox(width: 5),
                          const Icon(
                            Icons.arrow_forward_rounded,
                            size: 13,
                            color: AppColors.white,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
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
                context.l10n.nextEvent,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: AppColors.movementGreen,
                  fontSize: AppTextTheme.minSize,
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
    final percentInt = (item.progress_percent ?? 0).toInt();

    String formatMoney(dynamic value) {
      if (value == null) return 'RM 0';
      if (value is num) return Formatters.currency(value);
      final parsed = double.tryParse(value.toString());
      return parsed != null ? Formatters.currency(parsed) : value.toString();
    }

    final collectedStr =
        item.collected_amount != null
            ? formatMoney(item.collected_amount)
            : null;
    final targetStr =
        item.target_amount != null ? formatMoney(item.target_amount) : null;

    return Container(
      width: 240,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: AppColors.divider.withValues(alpha: 0.6),
          width: 0.8,
        ),
        boxShadow: AppShadows.subtle,
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => context.go('/infaq'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              // Gambar Kempen dengan Tag Status
              SizedBox(
                height: 120,
                width: double.infinity,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    if (item.image_path?.isNotEmpty == true)
                      AppImage(
                        item.image_path,
                        width: double.infinity,
                        height: 120,
                        fit: BoxFit.cover,
                      )
                    else
                      Container(
                        color: AppColors.softGreenSurface,
                        child: const Center(
                          child: Icon(
                            Icons.volunteer_activism_rounded,
                            size: 40,
                            color: AppColors.movementGreen,
                          ),
                        ),
                      ),
                    // Tag Status di Bucu Kanan Atas
                    Positioned(
                      right: 10,
                      top: 10,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.movementNavy.withValues(alpha: 0.72),
                          borderRadius: BorderRadius.circular(99),
                          border: Border.all(
                            color: AppColors.white.withValues(alpha: 0.25),
                            width: 0.75,
                          ),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 6,
                              height: 6,
                              decoration: const BoxDecoration(
                                shape: BoxShape.circle,
                                color: Color(0xFF34D399),
                              ),
                            ),
                            const SizedBox(width: 4),
                            Text(
                              (item.type?.isNotEmpty == true
                                      ? item.type!
                                      : 'KEMPEN AKTIF')
                                  .toUpperCase(),
                              style: const TextStyle(
                                color: AppColors.white,
                                fontSize: 9.5,
                                fontWeight: FontWeight.w700,
                                letterSpacing: 0.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              // Bahagian Maklumat Kempen & Sasaran
              Padding(
                padding: const EdgeInsets.all(Spacing.md),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      height: 36,
                      child: Text(
                        item.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppColors.movementNavy,
                          height: 1.25,
                        ),
                      ),
                    ),
                    const SizedBox(height: Spacing.sm),
                    // Bar Kemajuan Tebal & Berwarna
                    ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: progress,
                        minHeight: 7,
                        backgroundColor: AppColors.divider.withValues(
                          alpha: 0.5,
                        ),
                        color: AppColors.movementGreen,
                      ),
                    ),
                    const SizedBox(height: 6),
                    // Angka Kutipan & Sasaran
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          collectedStr ?? '$percentInt% Terkumpul',
                          style: const TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w800,
                            color: AppColors.movementGreen,
                          ),
                        ),
                        Text(
                          targetStr != null
                              ? 'Sasaran: $targetStr'
                              : '$percentInt%',
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    // Baris Butang Infaq Pantas & Social Proof
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.favorite_rounded,
                              size: 13,
                              color: Color(0xFFEF4444),
                            ),
                            SizedBox(width: 4),
                            Text(
                              'Infaq Jemaah',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ],
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 9,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.greenTint,
                            borderRadius: BorderRadius.circular(99),
                            border: Border.all(
                              color: AppColors.movementGreen.withValues(
                                alpha: 0.25,
                              ),
                              width: 0.75,
                            ),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                'SUMBANG',
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.movementGreen,
                                  letterSpacing: 0.5,
                                ),
                              ),
                              SizedBox(width: 2),
                              Icon(
                                Icons.chevron_right_rounded,
                                size: 13,
                                color: AppColors.movementGreen,
                              ),
                            ],
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
      ),
    );
  }
}

class _MediaStatementPreview extends StatelessWidget {
  const _MediaStatementPreview({required this.item});

  final MediaStatement item;

  @override
  Widget build(BuildContext context) {
    final isMalay = Localizations.localeOf(context).languageCode == 'ms';

    return Container(
      width: 210,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: AppColors.divider.withValues(alpha: 0.6),
          width: 0.8,
        ),
        boxShadow: AppShadows.subtle,
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => context.push('/media-statements/${item.id}'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              // Bahagian Atas: Poster Potret 4:5 Sebenar dengan Lencana Rasmi
              AspectRatio(
                aspectRatio: 4 / 5,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    if (item.featuredImage?.isNotEmpty == true)
                      AppImage(
                        item.featuredImage,
                        width: double.infinity,
                        height: double.infinity,
                        fit: BoxFit.cover,
                      )
                    else
                      Container(
                        decoration: const BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: [AppColors.movementNavy, Color(0xFF1E3A2B)],
                          ),
                        ),
                        child: Center(
                          child: Icon(
                            Icons.campaign_outlined,
                            size: 48,
                            color: AppColors.movementSoftGreen.withValues(
                              alpha: 0.5,
                            ),
                          ),
                        ),
                      ),
                    // Gradien bayang atas halus untuk kontras badge
                    const Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      height: 50,
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [Color(0x55000000), Colors.transparent],
                          ),
                        ),
                      ),
                    ),
                    // Lencana Rasmi di Bucu Atas Gambar
                    Positioned(
                      left: 10,
                      top: 10,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.movementNavy.withValues(alpha: 0.78),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(
                            color: AppColors.white.withValues(alpha: 0.25),
                            width: 0.75,
                          ),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 6,
                              height: 6,
                              decoration: const BoxDecoration(
                                shape: BoxShape.circle,
                                color: Color(0xFF34D399),
                              ),
                            ),
                            const SizedBox(width: 5),
                            Text(
                              isMalay ? 'SIARAN MEDIA' : 'PRESS RELEASE',
                              style: const TextStyle(
                                color: AppColors.white,
                                fontSize: 9,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.6,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              // Bahagian Tengah: Pembahagi Takuk Tiket
              Row(
                children: [
                  Container(
                    width: 8,
                    height: 14,
                    decoration: const BoxDecoration(
                      color: AppColors.pageBackground,
                      borderRadius: BorderRadius.horizontal(
                        right: Radius.circular(7),
                      ),
                    ),
                  ),
                  Expanded(
                    child: LayoutBuilder(
                      builder: (context, constraints) {
                        final dashCount =
                            (constraints.constrainWidth() / 8).floor();
                        return Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: List.generate(
                            dashCount,
                            (_) => Container(
                              width: 4,
                              height: 1,
                              color: AppColors.divider.withValues(alpha: 0.7),
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                  Container(
                    width: 8,
                    height: 14,
                    decoration: const BoxDecoration(
                      color: AppColors.pageBackground,
                      borderRadius: BorderRadius.horizontal(
                        left: Radius.circular(7),
                      ),
                    ),
                  ),
                ],
              ),

              // Bahagian Bawah: Maklumat Teks Kenyataan Media & Butang Tindakan Penuh
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 6, 12, 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            (item.organizationName ?? 'Organisasi')
                                .toUpperCase(),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                              color: AppColors.movementGreen,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ),
                        if (item.publishedDate?.isNotEmpty == true)
                          Text(
                            item.publishedDate!,
                            style: const TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w500,
                              color: AppColors.textTertiary,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    SizedBox(
                      height: 38,
                      child: Text(
                        item.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppColors.movementNavy,
                          height: 1.25,
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    // Butang Penuh (Full-Width Button) untuk Membaca Kenyataan
                    Container(
                      width: double.infinity,
                      height: 34,
                      decoration: BoxDecoration(
                        color: AppColors.greenTint,
                        borderRadius: BorderRadius.circular(9),
                        border: Border.all(
                          color: AppColors.movementGreen.withValues(
                            alpha: 0.35,
                          ),
                          width: 0.8,
                        ),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            isMalay ? 'Baca Kenyataan' : 'Read Statement',
                            style: const TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w700,
                              color: AppColors.movementGreen,
                              letterSpacing: 0.2,
                            ),
                          ),
                          const SizedBox(width: 5),
                          const Icon(
                            Icons.arrow_forward_rounded,
                            size: 13,
                            color: AppColors.movementGreen,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
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
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color:
                responded
                    ? AppColors.movementGreen.withValues(alpha: 0.35)
                    : AppColors.divider.withValues(alpha: 0.6),
            width: 0.8,
          ),
          boxShadow: AppShadows.subtle,
        ),
        clipBehavior: Clip.antiAlias,
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: () => context.push('/polls/${poll.id}'),
            child: Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color:
                              responded
                                  ? const Color(0xFFE6F4EA)
                                  : AppColors.accentTint,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              responded
                                  ? Icons.check_circle_rounded
                                  : Icons.how_to_vote_rounded,
                              size: 13,
                              color:
                                  responded
                                      ? AppColors.movementGreen
                                      : AppColors.warning,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              responded ? 'UNDIAN SELESAI' : 'SUARA AHLI AKTIF',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                                color:
                                    responded
                                        ? AppColors.movementGreen
                                        : AppColors.warning,
                                letterSpacing: 0.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const Spacer(),
                      if (poll.response_count != null)
                        Text(
                          '${poll.response_count} undian',
                          style: const TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                            color: AppColors.textTertiary,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: Spacing.md),
                  Text(
                    poll.title ?? '-',
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: AppColors.movementNavy,
                      height: 1.3,
                    ),
                  ),
                  const SizedBox(height: Spacing.md),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        responded
                            ? context.l10n.pollAnswered
                            : (poll.ends_at_formatted?.isNotEmpty == true
                                ? 'Tamat: ${poll.ends_at_formatted}'
                                : 'Undian dibuka'),
                        style: TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w500,
                          color:
                              responded
                                  ? AppColors.movementGreen
                                  : AppColors.textSecondary,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color:
                              responded
                                  ? AppColors.pageBackground
                                  : AppColors.greenTint,
                          borderRadius: BorderRadius.circular(99),
                          border: Border.all(
                            color: AppColors.movementGreen.withValues(
                              alpha: 0.25,
                            ),
                            width: 0.75,
                          ),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              responded ? 'LIHAT HASIL' : 'UNDI SEKARANG',
                              style: const TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                                color: AppColors.movementGreen,
                                letterSpacing: 0.5,
                              ),
                            ),
                            const SizedBox(width: 3),
                            const Icon(
                              Icons.arrow_forward_rounded,
                              size: 11,
                              color: AppColors.movementGreen,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
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
    return Container(
      width: 250,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: AppColors.divider.withValues(alpha: 0.6),
          width: 0.8,
        ),
        boxShadow: AppShadows.subtle,
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => context.push('/news/${item.id}'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              // Gambar Landskap 16:9 Bersih
              SizedBox(
                height: 122,
                width: double.infinity,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    if (item.cover_image_path?.isNotEmpty == true)
                      AppImage(
                        item.cover_image_path,
                        width: double.infinity,
                        height: 122,
                        fit: BoxFit.cover,
                      )
                    else
                      Container(
                        color: AppColors.greenTint,
                        child: const Center(
                          child: Icon(
                            Icons.newspaper_rounded,
                            size: 36,
                            color: AppColors.movementGreen,
                          ),
                        ),
                      ),
                    const Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      height: 40,
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [Color(0x45000000), Colors.transparent],
                          ),
                        ),
                      ),
                    ),
                    Positioned(
                      left: 10,
                      top: 10,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.movementNavy.withValues(alpha: 0.78),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(
                            color: AppColors.white.withValues(alpha: 0.2),
                            width: 0.7,
                          ),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 5,
                              height: 5,
                              decoration: const BoxDecoration(
                                shape: BoxShape.circle,
                                color: Color(0xFFEF4444),
                              ),
                            ),
                            const SizedBox(width: 4),
                            Text(
                              (item.category_name?.isNotEmpty == true
                                      ? item.category_name!
                                      : 'BERITA')
                                  .toUpperCase(),
                              style: const TextStyle(
                                color: AppColors.white,
                                fontSize: 9,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              // Kandungan Teks Berita
              Padding(
                padding: const EdgeInsets.all(Spacing.md),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      height: 38,
                      child: Text(
                        item.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppColors.movementNavy,
                          height: 1.25,
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        const Icon(
                          Icons.schedule_rounded,
                          size: 12,
                          color: AppColors.textTertiary,
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            item.organization_name ?? 'Berita Semasa',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w500,
                              color: AppColors.textTertiary,
                            ),
                          ),
                        ),
                        const Icon(
                          Icons.arrow_forward_rounded,
                          size: 12,
                          color: AppColors.movementGreen,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
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
    final author =
        item.author_name?.isNotEmpty == true
            ? item.author_name!
            : (item.organization_name ?? 'Wacana Minda');

    return Container(
      width: 250,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: AppColors.divider.withValues(alpha: 0.6),
          width: 0.8,
        ),
        boxShadow: AppShadows.subtle,
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => context.push('/articles/${item.id}'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              // Gambar Landskap 16:9 dengan Badge Artikel Editorial
              SizedBox(
                height: 122,
                width: double.infinity,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    if (item.cover_image_path?.isNotEmpty == true)
                      AppImage(
                        item.cover_image_path,
                        width: double.infinity,
                        height: 122,
                        fit: BoxFit.cover,
                      )
                    else
                      Container(
                        color: const Color(0xFFFAF6F0),
                        child: const Center(
                          child: Icon(
                            Icons.auto_stories_rounded,
                            size: 36,
                            color: Color(0xFF8B6B43),
                          ),
                        ),
                      ),
                    const Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      height: 40,
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [Color(0x45000000), Colors.transparent],
                          ),
                        ),
                      ),
                    ),
                    Positioned(
                      left: 10,
                      top: 10,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.movementNavy.withValues(alpha: 0.78),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(
                            color: AppColors.white.withValues(alpha: 0.2),
                            width: 0.7,
                          ),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.menu_book_rounded,
                              size: 10,
                              color: Color(0xFFFBBF24),
                            ),
                            SizedBox(width: 4),
                            Text(
                              'WACANA & FIKRAH',
                              style: TextStyle(
                                color: AppColors.white,
                                fontSize: 9,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              // Kandungan Teks Artikel Editorial
              Padding(
                padding: const EdgeInsets.all(Spacing.md),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      height: 38,
                      child: Text(
                        item.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppColors.movementNavy,
                          height: 1.25,
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        const Icon(
                          Icons.edit_note_rounded,
                          size: 14,
                          color: AppColors.movementGreen,
                        ),
                        const SizedBox(width: 3),
                        Expanded(
                          child: Text(
                            author,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w600,
                              color: AppColors.movementGreen,
                            ),
                          ),
                        ),
                        const Text(
                          '•  5 min bacaan',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w500,
                            color: AppColors.textTertiary,
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
      ),
    );
  }
}

class _LibrarySection extends ConsumerWidget {
  const _LibrarySection();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final items =
        ref.watch(memberLibraryProvider).value ?? const <LibraryItem>[];
    if (items.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: Spacing.xl),
        _SectionLabel(
          title: context.l10n.sectionLibrary,
          subtitle: context.l10n.sectionLibrarySubtitle,
          action: () => context.push('/member/library'),
        ),
        const SizedBox(height: Spacing.md),
        SizedBox(
          height: 208,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: items.length.clamp(0, 6),
            separatorBuilder: (_, __) => const SizedBox(width: Spacing.md),
            itemBuilder: (_, index) => _LibraryBookCard(item: items[index]),
          ),
        ),
      ],
    );
  }
}

class _LibraryBookCard extends StatelessWidget {
  const _LibraryBookCard({required this.item});
  final LibraryItem item;

  void _openReader(BuildContext context) {
    final path = item.file_path;
    if (path == null || path.isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.l10n.noFileToOpen)));
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => LibraryReaderScreen(item: item)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cover = item.cover_image_path;

    return SizedBox(
      width: 120,
      child: Material(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        child: InkWell(
          borderRadius: AppRadius.card,
          onTap: () => _openReader(context),
          child: ClipRRect(
            borderRadius: AppRadius.card,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AspectRatio(
                  aspectRatio: 3 / 4,
                  child:
                      cover?.isNotEmpty == true
                          ? AppImage(cover, fit: BoxFit.cover)
                          : Container(
                            color: AppColors.paleGreen,
                            alignment: Alignment.center,
                            child: const Icon(
                              Icons.menu_book_outlined,
                              color: AppColors.movementGreen,
                            ),
                          ),
                ),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.all(Spacing.sm),
                    child: Text(
                      item.title ?? '-',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.labelMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                        height: 1.2,
                      ),
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

class _VideoSection extends StatelessWidget {
  const _VideoSection({required this.videos});

  final List<Video> videos;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 236,
      child: ListView.separated(
        clipBehavior: Clip.none,
        scrollDirection: Axis.horizontal,
        itemCount: videos.length.clamp(0, 6),
        separatorBuilder: (_, __) => const SizedBox(width: Spacing.md),
        itemBuilder: (_, index) => _VideoCard(video: videos[index]),
      ),
    );
  }
}

class _VideoCard extends StatelessWidget {
  const _VideoCard({required this.video});

  final Video video;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 260,
      child: Material(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
        elevation: 0,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () {
            final url = video.watchUrl;
            if (url.isNotEmpty) {
              launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
            }
          },
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0x12000000), width: 0.8),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x0A12241C),
                  blurRadius: 10,
                  offset: Offset(0, 3),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(16),
                  ),
                  child: AspectRatio(
                    aspectRatio: 16 / 9,
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        if (video.thumbnailUrl != null &&
                            video.thumbnailUrl!.isNotEmpty)
                          AppImage(video.thumbnailUrl, fit: BoxFit.cover)
                        else
                          Container(
                            color: const Color(0xFF1B2A22),
                            child: const Center(
                              child: Icon(
                                Icons.videocam_rounded,
                                color: Colors.white54,
                                size: 36,
                              ),
                            ),
                          ),
                        const DecoratedBox(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [Colors.transparent, Color(0x75000000)],
                            ),
                          ),
                        ),
                        Center(
                          child: Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: Colors.white.withValues(alpha: 0.88),
                              boxShadow: const [
                                BoxShadow(
                                  color: Color(0x40000000),
                                  blurRadius: 12,
                                  offset: Offset(0, 3),
                                ),
                              ],
                            ),
                            child: const Icon(
                              Icons.play_arrow_rounded,
                              size: 28,
                              color: AppColors.movementNavy,
                            ),
                          ),
                        ),
                        if (video.isLive)
                          Positioned(
                            left: 10,
                            top: 10,
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 7,
                                vertical: 3,
                              ),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFF3B30),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: const Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(
                                    Icons.fiber_manual_record,
                                    size: 8,
                                    color: Colors.white,
                                  ),
                                  SizedBox(width: 4),
                                  Text(
                                    'LIVE',
                                    style: TextStyle(
                                      color: Colors.white,
                                      fontSize: 9.5,
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: 0.5,
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
                Padding(
                  padding: const EdgeInsets.all(Spacing.md),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        video.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppColors.movementNavy,
                          height: 1.25,
                        ),
                      ),
                      const SizedBox(height: 4),
                      const Row(
                        children: [
                          Icon(
                            Icons.ondemand_video_rounded,
                            size: 13,
                            color: AppColors.movementGreen,
                          ),
                          SizedBox(width: 4),
                          Text(
                            'Tonton di YouTube',
                            style: TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w600,
                              color: AppColors.movementGreen,
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
        ),
      ),
    );
  }
}

class _MallSection extends ConsumerWidget {
  const _MallSection({required this.products});

  final List<Product> products;

  void _addToCart(BuildContext context, WidgetRef ref, Product product) {
    final id = product.id;
    if (id == null || product.isOutOfStock) return;
    if ((product.variationsCount ?? 0) > 0) {
      context.push('/products/$id');
      return;
    }

    final added = ref
        .read(cartProvider.notifier)
        .add(
          CartItem(
            key: '$id:0',
            productId: id,
            name: product.name ?? context.l10n.ecomProductFallback,
            image: product.displayImage,
            unitPrice: product.effectivePrice,
            quantity: 1,
            stock: product.stock,
            postageCost: product.postageCost,
          ),
        );
    if (!added) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(context.l10n.ecomAddedToCart)));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return SizedBox(
      height: 310,
      child: ListView.separated(
        clipBehavior: Clip.none,
        scrollDirection: Axis.horizontal,
        itemCount: products.length.clamp(0, 6),
        separatorBuilder: (_, __) => const SizedBox(width: Spacing.md),
        itemBuilder: (_, index) {
          final item = products[index];
          return SizedBox(
            width: 175,
            child: Material(
              color: AppColors.white,
              borderRadius: AppRadius.card,
              clipBehavior: Clip.antiAlias,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Material(
                    color: Colors.transparent,
                    child: InkWell(
                      onTap:
                          item.id == null
                              ? null
                              : () => context.push('/products/${item.id}'),
                      child: AspectRatio(
                        aspectRatio: 4 / 3,
                        child: AppImage(
                          item.displayImage,
                          fit: BoxFit.cover,
                          borderRadius: BorderRadius.zero,
                        ),
                      ),
                    ),
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.all(Spacing.sm),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (item.category?.name?.isNotEmpty ?? false) ...[
                            Text(
                              item.category!.name!,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 9.5,
                                fontWeight: FontWeight.w600,
                                color: AppColors.textTertiary,
                              ),
                            ),
                            const SizedBox(height: Spacing.xs),
                          ],
                          Text(
                            item.name ?? context.l10n.ecomProductFallback,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: AppColors.movementNavy,
                              height: 1.2,
                            ),
                          ),
                          const Spacer(),
                          Text(
                            Formatters.currency(item.effectivePrice),
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                              color: AppColors.movementGreen,
                            ),
                          ),
                          const SizedBox(height: Spacing.sm),
                          SizedBox(
                            width: double.infinity,
                            height: 48,
                            child: FilledButton.icon(
                              onPressed:
                                  item.isOutOfStock
                                      ? null
                                      : () => _addToCart(context, ref, item),
                              style: FilledButton.styleFrom(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: Spacing.sm,
                                ),
                                backgroundColor: AppColors.movementGreen,
                                foregroundColor: AppColors.white,
                                disabledBackgroundColor: AppColors.surfaceMuted,
                                disabledForegroundColor: AppColors.textTertiary,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(8),
                                ),
                              ),
                              icon: const Icon(
                                Icons.add_shopping_cart_rounded,
                                size: 16,
                              ),
                              label: Text(
                                item.isOutOfStock
                                    ? context.l10n.ecomSoldOut
                                    : (item.variationsCount ?? 0) > 0
                                    ? context.l10n.ecomChooseOptions
                                    : context.l10n.ecomAddToCart,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
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
