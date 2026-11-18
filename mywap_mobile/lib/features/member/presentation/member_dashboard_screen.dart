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

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(memberDashboardProvider);
        ref.invalidate(memberLibraryProvider);
      },
      child: CustomScrollView(
        slivers: [
          SliverAppBar(
            pinned: true,
            backgroundColor: AppColors.pageBackground.withValues(alpha: 0.94),
            surfaceTintColor: Colors.transparent,
            elevation: 0,
            titleSpacing: 0,
            title: _Greeting(name: memberName),
            actions: const [NotificationBell(), SizedBox(width: Spacing.sm)],
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
                    // Kad poster penuh 4:5 (200 × 250) mengikut standard poster moden.
                    height: 250,
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
                    // Imej anjal + footer teks. Tinggi ini menentukan saiz
                    // imej; teks sentiasa rapat ke bawah tanpa jurang.
                    height: 300,
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
                if (mediaStatements.isNotEmpty) ...[
                  const SizedBox(height: Spacing.xl),
                  _SectionLabel(
                    title: context.l10n.mediaStatementsTitle,
                    subtitle: context.l10n.mediaStatementsSubtitle,
                    action: () => context.push('/media-statements'),
                  ),
                  const SizedBox(height: Spacing.md),
                  SizedBox(
                    height: 320,
                    child: ListView.separated(
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
                    height: 300,
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
                    title: context.l10n.articlesTitle,
                    subtitle: context.l10n.sectionArticlesSubtitle,
                    action: () => context.push('/articles'),
                  ),
                  const SizedBox(height: Spacing.md),
                  SizedBox(
                    height: 300,
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
                const _LibrarySection(),
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

  /// Nama pertama sahaja — nama penuh memenuhi bar tajuk dan terpotong
  /// ("Assalamualaikum, MUHAMAD H...") pada saiz teks mesra warga emas.
  String get _firstName {
    final parts = name.trim().split(RegExp(r'\s+'));
    return parts.isEmpty ? name : parts.first;
  }

  @override
  Widget build(BuildContext context) {
    final hour = DateTime.now().hour;
    final greeting =
        hour < 12
            ? context.l10n.greetingMorning
            : hour < 18
            ? context.l10n.greetingAfternoon
            : context.l10n.greetingEvening;
    // `SliverAppBar` menetapkan `titleSpacing: 0`, jadi tanpa padding ini
    // teks mula tepat pada tepi skrin — tak selari dengan padding
    // `Spacing.lg` yang dipakai kandungan di bawahnya dan skrin lain.
    return Padding(
      padding: const EdgeInsets.only(left: Spacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            context.l10n.greetingSalam(_firstName),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(
              context,
            ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
          ),
          Text(
            greeting,
            style: Theme.of(
              context,
            ).textTheme.labelMedium?.copyWith(color: AppColors.textSecondary),
          ),
        ],
      ),
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
      icon: Icons.calendar_today_rounded,
      label: l10n.quickBook,
      color: AppColors.shortcutBooking,
      path: '/facilities',
    ),
    _ShortcutItem(
      icon: Icons.article_rounded,
      label: l10n.quickNews,
      color: AppColors.shortcutNews,
      path: '/news',
    ),
    _ShortcutItem(
      icon: Icons.favorite_rounded,
      label: l10n.menuInfaq,
      color: AppColors.shortcutInfaq,
      path: '/infaq',
    ),
    if (!isAdmin)
      _ShortcutItem(
        icon: Icons.contact_page_rounded,
        label: l10n.quickCard,
        color: AppColors.shortcutCard,
        path: '/card',
      ),
    if (!isAdmin)
      _ShortcutItem(
        icon: Icons.groups_2_rounded,
        label: l10n.menuUsrah,
        color: AppColors.shortcutUsrah,
        path: '/usrah',
      ),
    _ShortcutItem(
      icon: Icons.shopping_bag_rounded,
      label: l10n.quickMall,
      color: AppColors.shortcutShop,
      path: '/products',
    ),
    if (!isAdmin)
      _ShortcutItem(
        icon: Icons.share_rounded,
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
      if (i > 0) rows.add(const SizedBox(height: Spacing.sm));
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
        borderRadius: AppRadius.sm,
        onTap: () => context.push(item.path),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: Spacing.xs),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: item.color.withValues(alpha: .12),
                  borderRadius: AppRadius.sm,
                  border: Border.all(color: item.color.withValues(alpha: .08)),
                ),
                child: Icon(item.icon, color: item.color, size: 20),
              ),
              const SizedBox(height: Spacing.xs),
              Text(
                item.label,
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(
                  context,
                ).textTheme.labelSmall?.copyWith(fontSize: 11, height: 1.15),
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

String _formatEventDate(Event event) {
  if (event.start_time?.isNotEmpty == true) {
    final dt = DateTime.tryParse(event.start_time!);
    if (dt != null) {
      return Formatters.shortDate(dt.toLocal());
    }
  }
  final raw = event.start_formatted?.trim();
  if (raw == null || raw.isEmpty) return '';

  if (raw.contains('/')) {
    final datePart = raw.split(' ').first;
    final parts = datePart.split('/');
    if (parts.length == 3) {
      final d = int.tryParse(parts[0]);
      final m = int.tryParse(parts[1]);
      final y = int.tryParse(parts[2]);
      if (d != null && m != null && y != null && m >= 1 && m <= 12) {
        return '$d ${Formatters.months[m - 1]} $y';
      }
    }
  }

  if (raw.contains('•')) {
    final datePart = raw.split('•').first.trim();
    if (datePart.contains(',')) {
      return datePart.split(',').last.trim();
    }
    return datePart;
  }

  return raw;
}

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event});
  final Event event;

  @override
  Widget build(BuildContext context) {
    final isOnline = event.type == 'online';
    final locationText = isOnline
        ? (event.location_or_link?.isNotEmpty == true
            ? event.location_or_link!
            : context.l10n.eventOnline)
        : (event.location_or_link?.isNotEmpty == true
            ? event.location_or_link!
            : context.l10n.eventPhysical);
    final dateText = _formatEventDate(event);

    return Container(
      width: 200,
      decoration: BoxDecoration(
        color: AppColors.movementNavy,
        borderRadius: AppRadius.card,
        border: Border.all(
          color: AppColors.divider.withValues(alpha: 0.35),
          width: 0.8,
        ),
        boxShadow: AppShadows.card,
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => context.push('/events/${event.id}'),
          child: Stack(
            fit: StackFit.expand,
            children: [
              // Poster imej latar penuh (4:5)
              if (event.featured_image_url?.isNotEmpty == true)
                AppImage(event.featured_image_url, fit: BoxFit.cover)
              else
                Container(
                  color: AppColors.movementNavy,
                  child: Center(
                    child: Icon(
                      Icons.event_outlined,
                      size: 44,
                      color: AppColors.movementSoftGreen.withValues(alpha: 0.4),
                    ),
                  ),
                ),
              // Lapisan gradien halus dari tengah ke bawah untuk kontras teks
              const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Colors.transparent,
                      Color(0x2012241C),
                      Color(0xCC12241C),
                      Color(0xF512241C),
                    ],
                    stops: [0.35, 0.55, 0.80, 1.0],
                  ),
                ),
              ),
              // Lencana status Online / Fizikal di bucu atas (rekaan kemas berpetunjuk titik bercahaya)
              Positioned(
                left: Spacing.sm + 2,
                top: Spacing.sm + 2,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.movementNavy.withValues(alpha: 0.72),
                    borderRadius: BorderRadius.circular(99),
                    border: Border.all(
                      color: AppColors.white.withValues(alpha: 0.22),
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
              // Blok maklumat di bahagian bawah (ketinggian seragam)
              Positioned(
                left: Spacing.md,
                right: Spacing.md,
                bottom: Spacing.md,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (dateText.isNotEmpty) ...[
                      Text(
                        dateText,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF6EE7B7),
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.3,
                        ),
                      ),
                      const SizedBox(height: 4),
                    ],
                    SizedBox(
                      height: 36,
                      child: Text(
                        event.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: AppColors.white,
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
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
                          size: 13,
                          color: AppColors.white.withValues(alpha: 0.85),
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            locationText,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: AppColors.white.withValues(alpha: 0.85),
                              fontSize: 11,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Container(
                          width: 22,
                          height: 22,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: AppColors.white.withValues(alpha: 0.18),
                            border: Border.all(
                              color: AppColors.white.withValues(alpha: 0.25),
                              width: 0.75,
                            ),
                          ),
                          child: const Icon(
                            Icons.arrow_forward_rounded,
                            size: 12,
                            color: AppColors.white,
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
    return Container(
      width: 176,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        border: Border.all(
          color: AppColors.divider.withValues(alpha: 0.5),
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
            children: [
              Expanded(
                child: AppImage(item.image_path, fit: BoxFit.cover),
              ),
              Padding(
                padding: const EdgeInsets.all(Spacing.md),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.title ?? '-',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(
                        context,
                      ).textTheme.labelMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                        height: 1.25,
                      ),
                    ),
                    const SizedBox(height: Spacing.sm),
                    ClipRRect(
                      borderRadius: AppRadius.xs,
                      child: LinearProgressIndicator(
                        value: progress,
                        minHeight: 5,
                        backgroundColor: AppColors.divider,
                        color: AppColors.movementGreen,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      context.l10n.collectedPercent(
                        (item.progress_percent ?? 0).toInt(),
                      ),
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
    );
  }
}

class _MediaStatementPreview extends StatelessWidget {
  const _MediaStatementPreview({required this.item});

  final MediaStatement item;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 175,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        border: Border.all(
          color: AppColors.divider.withValues(alpha: 0.5),
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
            children: [
              AspectRatio(
                aspectRatio: 4 / 5,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    if (item.featuredImage?.isNotEmpty == true)
                      AppImage(item.featuredImage, fit: BoxFit.cover)
                    else
                      Container(color: AppColors.paleGreen),
                    const DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.bottomCenter,
                          end: Alignment.topCenter,
                          colors: [AppColors.scrim, Colors.transparent],
                        ),
                      ),
                    ),
                    Positioned(
                      left: Spacing.sm,
                      bottom: Spacing.sm,
                      right: Spacing.sm,
                      child: Text(
                        item.organizationName ?? context.l10n.newsAllOrganizations,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: AppTextTheme.minSize,
                          fontWeight: FontWeight.w700,
                          color: AppColors.white,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Flexible(
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.md),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        item.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.labelMedium
                            ?.copyWith(fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
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
                            ? context.l10n.pollAnswered
                            : context.l10n.pollNotAnswered(
                              poll.response_count ?? 0,
                            ),
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
    return Container(
      width: 176,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        border: Border.all(
          color: AppColors.divider.withValues(alpha: 0.5),
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
            children: [
              Expanded(
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
                          ?.copyWith(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      item.category_name ?? '',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
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
    );
  }
}

class _ArticleCard extends StatelessWidget {
  const _ArticleCard({required this.item});
  final ArticleItem item;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 176,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        border: Border.all(
          color: AppColors.divider.withValues(alpha: 0.5),
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
            children: [
              Expanded(
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
                          ?.copyWith(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      item.author_name ?? '',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
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
