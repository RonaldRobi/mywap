import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/utils/formatters.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_text_theme.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/organic_canvas_background.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../ecommerce/application/cart_notifier.dart';
import '../../ecommerce/application/product_providers.dart';
import '../../ecommerce/data/models/product.dart';
import '../../loading_screen/application/loading_screen_providers.dart';
import '../application/public_home_providers.dart';
import '../data/public_home_repository.dart';
import 'guest_prompt.dart';

@visibleForTesting
void resetLoginPromptShownForTesting() => _loginPromptShown = false;

bool _loginPromptShown = false;

/// Beranda awam dengan reka bentuk teratur, kemas dan bernafas tinggi
/// (gaya Apple, Spotify & Tesla UI/UX). Membolehkan orang awam menerokai
/// kandungan, program, infaq, serta membeli-belah di myWAP Mall tanpa login.
class PublicHomeScreen extends ConsumerStatefulWidget {
  const PublicHomeScreen({super.key});

  @override
  ConsumerState<PublicHomeScreen> createState() => _PublicHomeScreenState();
}

class _PublicHomeScreenState extends ConsumerState<PublicHomeScreen> {
  @override
  void initState() {
    super.initState();
    if (!_loginPromptShown) {
      _loginPromptShown = true;
      WidgetsBinding.instance.addPostFrameCallback((_) => _maybePromptLogin());
    }
  }

  Future<void> _maybePromptLogin() async {
    if (!mounted) return;
    final goLogin = await showDialog<bool>(
      context: context,
      builder:
          (dialogContext) => AlertDialog(
            title: Text(context.l10n.publicWelcomeTitle),
            content: Text(context.l10n.publicWelcomeBody),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: Text(context.l10n.close),
              ),
              FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                child: Text(context.l10n.login),
              ),
            ],
          ),
    );
    if (goLogin == true && mounted) context.go('/login');
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(publicHomeProvider);

    return Scaffold(
      appBar: AppBar(
        elevation: 0,
        backgroundColor: Colors.transparent,
        titleSpacing: Spacing.lg,
        title: const _HomeLogo(),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 4),
            child: FilledButton.tonal(
              style: FilledButton.styleFrom(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                minimumSize: const Size(0, 36),
                shape: const StadiumBorder(),
                backgroundColor: AppColors.movementGreen.withValues(
                  alpha: 0.12,
                ),
                foregroundColor: AppColors.movementDarkGreen,
              ),
              onPressed: () => context.go('/login'),
              child: Text(
                context.l10n.login,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
          Consumer(
            builder: (context, ref, _) {
              final count = ref.watch(cartProvider).totalCount;
              return Padding(
                padding: const EdgeInsets.only(right: Spacing.sm),
                child: IconButton(
                  icon: Badge(
                    isLabelVisible: count > 0,
                    label: Text('$count', style: const TextStyle(fontSize: 10)),
                    child: const Icon(
                      Icons.shopping_bag_outlined,
                      size: 22,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  tooltip: 'Troli Beli-Belah',
                  onPressed: () => context.push('/cart'),
                ),
              );
            },
          ),
        ],
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          const OrganicCanvasBackground(),
          async.when(
            data:
                (data) => RefreshIndicator(
                  onRefresh: () async => ref.invalidate(publicHomeProvider),
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.only(bottom: Spacing.xxl),
                    children: [
                      _TopCarousel(banners: data.banners),
                      const SizedBox(height: Spacing.sm),
                      const _QuickPillsBar(),
                      const _GuestAccessCard(),
                      _MallSection(items: data.products),
                      _EventsSection(items: data.events),
                      _NewsSection(items: data.news),
                      _ArticlesSection(items: data.articles),
                      _VideosSection(items: data.videos),
                      _LibrarySection(items: data.library),
                      _InfaqSection(items: data.infaqs),
                      _MediaStatementsSection(items: data.mediaStatements),
                      const _MemberCta(),
                    ],
                  ),
                ),
            loading:
                () => ListView(
                  padding: const EdgeInsets.all(Spacing.lg),
                  children: const [
                    SkeletonBox(height: 180, radius: 24),
                    SizedBox(height: Spacing.xl),
                    SkeletonBox(height: 140, radius: 16),
                    SizedBox(height: Spacing.lg),
                    SkeletonBox(height: 140, radius: 16),
                  ],
                ),
            error:
                (error, _) => ErrorRetry(
                  message: context.l10n.loadContentError,
                  onRetry: () => ref.invalidate(publicHomeProvider),
                ),
          ),
        ],
      ),
    );
  }
}

// ─── Logo sistem di bar atas ───────────────────────────────────────────────

class _HomeLogo extends ConsumerWidget {
  const _HomeLogo();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final logo = ref.watch(appLogoProvider);
    if (logo == null || logo.isEmpty) {
      return const Text(
        'MyWAP',
        style: TextStyle(
          fontWeight: FontWeight.w900,
          letterSpacing: -0.5,
          fontSize: 20,
        ),
      );
    }
    return SizedBox(
      height: 32,
      child: AppImage(
        logo,
        fit: BoxFit.contain,
        borderRadius: BorderRadius.circular(4),
      ),
    );
  }
}

// ─── Top carousel (banner admin) ──────────────────────────────────────────

class _TopCarousel extends ConsumerStatefulWidget {
  const _TopCarousel({required this.banners});
  final List<PublicBanner> banners;

  @override
  ConsumerState<_TopCarousel> createState() => _TopCarouselState();
}

class _TopCarouselState extends ConsumerState<_TopCarousel> {
  final _controller = PageController();
  Timer? _timer;
  int _page = 0;

  @override
  void initState() {
    super.initState();
    _startAuto();
  }

  void _startAuto() {
    _timer?.cancel();
    if (widget.banners.length < 2) return;
    _timer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!mounted || !_controller.hasClients) return;
      final next = (_page + 1) % widget.banners.length;
      _controller.animateToPage(
        next,
        duration: const Duration(milliseconds: 400),
        curve: Curves.easeOutCubic,
      );
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  Future<void> _open(PublicBanner banner) async {
    final url = banner.linkUrl;
    if (url == null || url.isEmpty) return;
    await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) {
    if (widget.banners.isEmpty) return const _BrandHero();

    return Padding(
      padding: const EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.sm,
        Spacing.lg,
        Spacing.sm,
      ),
      child: Column(
        children: [
          AspectRatio(
            aspectRatio: 21 / 9,
            child: ClipRRect(
              borderRadius: BorderRadius.circular(20),
              child: PageView.builder(
                controller: _controller,
                itemCount: widget.banners.length,
                onPageChanged: (p) => setState(() => _page = p),
                itemBuilder: (_, index) {
                  final banner = widget.banners[index];
                  return GestureDetector(
                    onTap: () => _open(banner),
                    child:
                        (banner.imagePath?.isNotEmpty ?? false)
                            ? AppImage(
                              banner.imagePath,
                              width: double.infinity,
                              fit: BoxFit.cover,
                            )
                            : Container(
                              decoration: const BoxDecoration(
                                gradient: LinearGradient(
                                  colors: AppColors.heroGradient,
                                ),
                              ),
                              alignment: Alignment.center,
                              child: Text(
                                banner.title ?? 'MyWAP',
                                style: Theme.of(
                                  context,
                                ).textTheme.titleMedium?.copyWith(
                                  color: AppColors.white,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                  );
                },
              ),
            ),
          ),
          if (widget.banners.length > 1) ...[
            const SizedBox(height: Spacing.sm),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(
                widget.banners.length,
                (i) => AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  width: i == _page ? 20 : 7,
                  height: 7,
                  margin: const EdgeInsets.symmetric(horizontal: 3),
                  decoration: BoxDecoration(
                    color:
                        i == _page
                            ? AppColors.movementGreen
                            : AppColors.divider,
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Hero jenama bila tiada banner admin.
class _BrandHero extends ConsumerWidget {
  const _BrandHero();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final logo = ref.watch(appLogoProvider);
    return Container(
      margin: const EdgeInsets.all(Spacing.lg),
      padding: const EdgeInsets.all(Spacing.xl),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: AppColors.heroGradient,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: AppShadows.card,
      ),
      child: Row(
        children: [
          if (logo != null && logo.isNotEmpty) ...[
            Container(
              width: 56,
              height: 56,
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: AppColors.white,
                borderRadius: AppRadius.md,
              ),
              child: AppImage(logo, fit: BoxFit.contain),
            ),
            const SizedBox(width: Spacing.lg),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'MyWAP',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    color: AppColors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: Spacing.xs),
                Text(
                  context.l10n.publicHeroSubtitle,
                  style: Theme.of(
                    context,
                  ).textTheme.bodySmall?.copyWith(color: AppColors.textOnDark),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Bar Pil Penerokaan Pantas (Spotify / Apple Style) ─────────────────────

class _QuickPillsBar extends StatelessWidget {
  const _QuickPillsBar();

  @override
  Widget build(BuildContext context) {
    final pills = [
      (
        Icons.storefront_rounded,
        context.l10n.menuSectionMall,
        '/products',
        const Color(0xFFFF9F0A),
      ),
      (
        Icons.event_rounded,
        context.l10n.menuPrograms,
        '/events',
        const Color(0xFF007AFF),
      ),
      (
        Icons.volunteer_activism_rounded,
        context.l10n.menuInfaq,
        '/infaq',
        const Color(0xFFFF2D55),
      ),
      (
        Icons.newspaper_rounded,
        context.l10n.menuNews,
        '/news',
        const Color(0xFF007AFF),
      ),
      (
        Icons.article_rounded,
        context.l10n.menuArticles,
        '/articles',
        const Color(0xFF30B0C7),
      ),
      (
        Icons.play_circle_rounded,
        context.l10n.menuVideos,
        '/videos',
        const Color(0xFFFF3B30),
      ),
      (
        Icons.local_library_rounded,
        context.l10n.menuLibrary,
        '/member/library',
        const Color(0xFF5856D6),
      ),
      (
        Icons.corporate_fare_rounded,
        context.l10n.menuOrgInfo,
        '/organization/info',
        const Color(0xFF1D3557),
      ),
    ];

    return SizedBox(
      height: 44,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
        itemCount: pills.length,
        separatorBuilder: (_, __) => const SizedBox(width: Spacing.xs),
        itemBuilder: (context, index) {
          final p = pills[index];
          return Material(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(12),
            child: InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: () => context.push(p.$3),
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: AppColors.divider.withValues(alpha: 0.7),
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(p.$1, size: 16, color: p.$4),
                    const SizedBox(width: 6),
                    Text(
                      p.$2,
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
          );
        },
      ),
    );
  }
}

// ─── Kad Akses Tetamu & Log Masuk (Tesla / Apple Industrial Style) ─────────

class _GuestAccessCard extends StatelessWidget {
  const _GuestAccessCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.md,
        Spacing.lg,
        Spacing.md,
      ),
      padding: const EdgeInsets.all(Spacing.xl),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: AppColors.heroGradient,
        ),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.10),
          width: 1,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x28000000),
            blurRadius: 18,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  context.l10n.homeAccessLabel.toUpperCase(),
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1.0,
                    color: Color(0xFF7FC99A),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: Spacing.sm),
          Text(
            context.l10n.homeGuestTitle,
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.5,
              color: AppColors.white,
              height: 1.2,
            ),
          ),
          const SizedBox(height: Spacing.xs),
          Text(
            context.l10n.homeGuestBody,
            style: TextStyle(
              fontSize: 13.5,
              color: Colors.white.withValues(alpha: 0.8),
              height: 1.5,
            ),
          ),
          const SizedBox(height: Spacing.lg),
          // Visual button stays compact while retaining the platform tap target.
          Row(
            children: [
              Expanded(
                child: SizedBox(
                  height: 44,
                  child: FilledButton(
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.white,
                      foregroundColor: AppColors.movementDarkGreen,
                      elevation: 0,
                      minimumSize: const Size(0, 44),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                      textStyle: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    onPressed: () => context.go('/login'),
                    child: Text(
                      context.l10n.login,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: SizedBox(
                  height: 44,
                  child: OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.white,
                      side: BorderSide(
                        color: Colors.white.withValues(alpha: 0.35),
                        width: 1.5,
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                      textStyle: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                      ),
                      minimumSize: const Size(0, 44),
                    ),
                    onPressed: () => context.push('/register'),
                    child: Text(
                      context.l10n.homeRegisterShort,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: Spacing.md),
          LayoutBuilder(
            builder: (context, constraints) {
              final stackButtons = constraints.maxWidth < 340;
              final activate = _GuestUtilityButton(
                icon: Icons.key_rounded,
                label: context.l10n.homeActivateMember,
                onTap: () => context.push('/first-login'),
              );
              final faq = _GuestUtilityButton(
                icon: Icons.help_outline_rounded,
                label: context.l10n.faqTitle,
                onTap: () => context.push('/faq'),
              );

              if (stackButtons) {
                return Column(
                  children: [activate, const SizedBox(height: Spacing.sm), faq],
                );
              }

              return Row(
                children: [
                  Expanded(child: activate),
                  const SizedBox(width: Spacing.sm),
                  Expanded(child: faq),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

class _GuestUtilityButton extends StatelessWidget {
  const _GuestUtilityButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white.withValues(alpha: 0.1),
      borderRadius: AppRadius.sm,
      child: InkWell(
        borderRadius: AppRadius.sm,
        onTap: onTap,
        child: SizedBox(
          height: 44,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: Spacing.md),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, size: 16, color: AppColors.textOnDark),
                const SizedBox(width: Spacing.sm),
                Flexible(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textOnDark,
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

// ─── Section Header (Apple Style) ─────────────────────────────────────────

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({
    required this.title,
    required this.path,
    this.subtitle,
  });

  final String title;
  final String path;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.xl,
        Spacing.lg,
        Spacing.sm,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.4,
                  ),
                ),
                if (subtitle != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    subtitle!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: Spacing.sm),
          Material(
            color: AppColors.movementGreen.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () => context.push(path),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      context.l10n.viewAll,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: AppColors.movementGreen,
                      ),
                    ),
                    const SizedBox(width: 4),
                    const Icon(
                      Icons.arrow_forward_ios_rounded,
                      size: 11,
                      color: AppColors.movementGreen,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyHint extends StatelessWidget {
  const _EmptyHint({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(
      horizontal: Spacing.lg,
      vertical: Spacing.md,
    ),
    child: Text(
      message,
      style: Theme.of(
        context,
      ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
    ),
  );
}

// ─── myWAP Mall (E-Dagang Awam) ───────────────────────────────────────────

class _MallSection extends ConsumerWidget {
  const _MallSection({required this.items});
  final List<Product> items;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final displayItems =
        items.isNotEmpty
            ? items
            : (ref.watch(productsProvider).valueOrNull?.items ??
                const <Product>[]);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionHeader(
          title: 'MyWAP Mall',
          subtitle:
              'Beli buku, pakaian & cenderahati rasmi tanpa perlu log masuk',
          path: '/products',
        ),
        if (displayItems.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.all(Spacing.lg),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: AppRadius.card,
                border: Border.all(color: AppColors.divider),
              ),
              child: Row(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFF9F0A).withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.storefront_rounded,
                      color: Color(0xFFFF9F0A),
                      size: 26,
                    ),
                  ),
                  const SizedBox(width: Spacing.md),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Koleksi Rasmi MyWAP',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'Terokai barangan dan terbitan rasmi di gedung digital kami.',
                          style: TextStyle(
                            fontSize: 12,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: Spacing.sm),
                  FilledButton.tonal(
                    style: FilledButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 14),
                      minimumSize: const Size(0, 36),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                    onPressed: () => context.push('/products'),
                    child: const Text(
                      'Jelajah',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          )
        else
          SizedBox(
            height: 294,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              itemCount: displayItems.length,
              separatorBuilder: (_, __) => const SizedBox(width: Spacing.md),
              itemBuilder: (_, i) => _ProductCard(product: displayItems[i]),
            ),
          ),
      ],
    );
  }
}

class _ProductCard extends ConsumerWidget {
  const _ProductCard({required this.product});
  final Product product;

  void _addToCart(BuildContext context, WidgetRef ref) {
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
    final priceStr =
        product.price != null ? Formatters.currency(product.price) : 'RM0';
    final categoryName = product.category?.name ?? 'MyWAP Mall';
    final actionLabel =
        product.isOutOfStock
            ? context.l10n.ecomSoldOut
            : (product.variationsCount ?? 0) > 0
            ? context.l10n.ecomChooseOptions
            : context.l10n.ecomAddToCart;

    return SizedBox(
      width: 172,
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: AppRadius.card,
          border: Border.all(color: AppColors.divider.withValues(alpha: 0.6)),
          boxShadow: AppShadows.subtle,
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap:
                      product.id == null
                          ? null
                          : () => context.push('/products/${product.id}'),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ClipRRect(
                        borderRadius: const BorderRadius.only(
                          topLeft: Radius.circular(16),
                          topRight: Radius.circular(16),
                        ),
                        child: AspectRatio(
                          aspectRatio: 1.15,
                          child: Stack(
                            fit: StackFit.expand,
                            children: [
                              if (product.displayImage?.isNotEmpty == true)
                                AppImage(
                                  product.displayImage,
                                  fit: BoxFit.cover,
                                )
                              else
                                Container(
                                  color: const Color(0xFFF3F4F6),
                                  child: const Icon(
                                    Icons.shopping_bag_outlined,
                                    size: 38,
                                    color: AppColors.textTertiary,
                                  ),
                                ),
                              Positioned(
                                top: 8,
                                left: 8,
                                child: Container(
                                  constraints: const BoxConstraints(
                                    maxWidth: 140,
                                  ),
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 6,
                                    vertical: 2,
                                  ),
                                  decoration: BoxDecoration(
                                    color: AppColors.movementNavy.withValues(
                                      alpha: 0.85,
                                    ),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    categoryName,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      fontSize: 9.5,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.white,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(10, 8, 10, 4),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                product.name ?? '-',
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                  height: 1.25,
                                  color: AppColors.textPrimary,
                                ),
                              ),
                              const Spacer(),
                              Text(
                                priceStr,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.movementGreen,
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
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 4, 10, 8),
              child: SizedBox(
                width: double.infinity,
                height: 44,
                child: FilledButton.icon(
                  onPressed:
                      product.isOutOfStock
                          ? null
                          : () => _addToCart(context, ref),
                  style: FilledButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 8),
                    backgroundColor: AppColors.movementGreen,
                    foregroundColor: AppColors.white,
                    disabledBackgroundColor: AppColors.surfaceMuted,
                    disabledForegroundColor: AppColors.textTertiary,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  icon: const Icon(Icons.add_shopping_cart_rounded, size: 15),
                  label: Text(
                    actionLabel,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Program & Acara ──────────────────────────────────────────────────────

class _EventsSection extends StatelessWidget {
  const _EventsSection({required this.items});
  final List<PublicEvent> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionHeader(
          title: context.l10n.upcomingEventsTitle,
          path: '/events',
        ),
        if (items.isEmpty)
          _EmptyHint(message: context.l10n.noUpcomingEvents)
        else
          ...items.take(3).map((e) => _EventTile(event: e)),
      ],
    );
  }
}

class _EventTile extends StatelessWidget {
  const _EventTile({required this.event});
  final PublicEvent event;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.lg, vertical: 4),
      child: Card(
        clipBehavior: Clip.antiAlias,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: AppColors.divider.withValues(alpha: 0.7)),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => context.push('/events/${event.id}'),
          child: Padding(
            padding: const EdgeInsets.all(Spacing.md),
            child: Row(
              children: [
                _Thumb(path: event.featuredImageUrl),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        event.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                          fontSize: 14.5,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        event.startFormatted ?? '',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(
                  Icons.chevron_right_rounded,
                  color: AppColors.textTertiary,
                  size: 20,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ─── Berita (Info Terkini) ────────────────────────────────────────────────

class _NewsSection extends StatelessWidget {
  const _NewsSection({required this.items});
  final List<PublicNews> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionHeader(title: context.l10n.newsTitle, path: '/news'),
        if (items.isEmpty)
          _EmptyHint(message: context.l10n.noNews)
        else
          ...items.take(3).map((n) => _NewsTile(item: n)),
      ],
    );
  }
}

class _NewsTile extends StatelessWidget {
  const _NewsTile({required this.item});
  final PublicNews item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.lg, vertical: 4),
      child: Card(
        clipBehavior: Clip.antiAlias,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: AppColors.divider.withValues(alpha: 0.7)),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => context.push('/news/${item.id}'),
          child: Padding(
            padding: const EdgeInsets.all(Spacing.md),
            child: Row(
              children: [
                _Thumb(path: item.coverImagePath),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                          fontSize: 14.5,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${item.categoryName ?? context.l10n.generalCategory} • ${Formatters.dateFromIso(item.publishedAt)}',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(
                  Icons.chevron_right_rounded,
                  color: AppColors.textTertiary,
                  size: 20,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ─── Artikel ──────────────────────────────────────────────────────────────

class _ArticlesSection extends StatelessWidget {
  const _ArticlesSection({required this.items});
  final List<PublicArticle> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionHeader(title: context.l10n.articlesTitle, path: '/articles'),
        if (items.isEmpty)
          _EmptyHint(message: context.l10n.noArticles)
        else
          ...items.take(3).map((a) => _ArticleTile(article: a)),
      ],
    );
  }
}

class _ArticleTile extends StatelessWidget {
  const _ArticleTile({required this.article});
  final PublicArticle article;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.lg, vertical: 4),
      child: Card(
        clipBehavior: Clip.antiAlias,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: AppColors.divider.withValues(alpha: 0.7)),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => context.push('/articles/${article.id}'),
          child: Padding(
            padding: const EdgeInsets.all(Spacing.md),
            child: Row(
              children: [
                _Thumb(path: article.coverImagePath),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        article.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                          fontSize: 14.5,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${article.authorName ?? context.l10n.adminFallback} • ${Formatters.dateFromIso(article.publishedAt)}',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(
                  Icons.chevron_right_rounded,
                  color: AppColors.textTertiary,
                  size: 20,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ─── Video & Rakaman (Media Gallery) ──────────────────────────────────────

class _VideosSection extends StatelessWidget {
  const _VideosSection({required this.items});
  final List<PublicVideo> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionHeader(title: context.l10n.videoTitle, path: '/videos'),
        if (items.isEmpty)
          _EmptyHint(message: context.l10n.noVideos)
        else
          SizedBox(
            height: 190,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(width: Spacing.md),
              itemBuilder: (_, i) => _VideoCard(video: items[i]),
            ),
          ),
      ],
    );
  }
}

class _VideoCard extends StatelessWidget {
  const _VideoCard({required this.video});
  final PublicVideo video;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 220,
      child: Material(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => context.push('/videos'),
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: AppColors.divider.withValues(alpha: 0.6),
              ),
              boxShadow: AppShadows.subtle,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(16),
                    topRight: Radius.circular(16),
                  ),
                  child: SizedBox(
                    height: 114,
                    width: double.infinity,
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        if (video.thumbnailUrl?.isNotEmpty ?? false)
                          AppImage(video.thumbnailUrl, fit: BoxFit.cover)
                        else
                          Container(color: AppColors.paleGreen),
                        Center(
                          child: Container(
                            width: 38,
                            height: 38,
                            decoration: BoxDecoration(
                              color: Colors.black.withValues(alpha: 0.55),
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.play_arrow_rounded,
                              color: AppColors.white,
                              size: 24,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(Spacing.sm),
                  child: Text(
                    video.title ?? '-',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
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

// ─── Pustaka Digital (Apple Books Style) ───────────────────────────────────

class _LibrarySection extends StatelessWidget {
  const _LibrarySection({required this.items});
  final List<PublicLibraryItem> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionHeader(
          title: context.l10n.libraryTitle,
          path: '/member/library',
        ),
        if (items.isEmpty)
          _EmptyHint(message: context.l10n.noLibrary)
        else
          SizedBox(
            height: 228,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(width: Spacing.md),
              itemBuilder: (_, i) => _LibraryCard(item: items[i]),
            ),
          ),
      ],
    );
  }
}

class _LibraryCard extends StatelessWidget {
  const _LibraryCard({required this.item});
  final PublicLibraryItem item;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 130,
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap:
            () => showLoginPrompt(
              context,
              message: context.l10n.libraryMemberPrompt,
            ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 3 / 4,
              child: Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: AppShadows.subtle,
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child:
                      (item.coverImagePath?.isNotEmpty ?? false)
                          ? AppImage(item.coverImagePath, fit: BoxFit.cover)
                          : Container(
                            color: AppColors.paleGreen,
                            child: const Icon(
                              Icons.menu_book_outlined,
                              color: AppColors.movementGreen,
                              size: 36,
                            ),
                          ),
                ),
              ),
            ),
            const SizedBox(height: Spacing.xs),
            Text(
              item.title ?? '-',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Infaq & Sumbangan ────────────────────────────────────────────────────

class _InfaqSection extends StatelessWidget {
  const _InfaqSection({required this.items});
  final List<PublicInfaq> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionHeader(title: context.l10n.infaqTitle, path: '/infaq'),
        if (items.isEmpty)
          _EmptyHint(message: context.l10n.noInfaqCampaigns)
        else
          SizedBox(
            height: 172,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(width: Spacing.md),
              itemBuilder: (_, i) => _InfaqCard(item: items[i]),
            ),
          ),
      ],
    );
  }
}

class _InfaqCard extends StatelessWidget {
  const _InfaqCard({required this.item});
  final PublicInfaq item;

  @override
  Widget build(BuildContext context) {
    final progress = ((item.progressPercent ?? 0) / 100).clamp(0.0, 1.0);
    return SizedBox(
      width: 204,
      child: Material(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => context.push('/infaq/${item.slug}'),
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: AppColors.divider.withValues(alpha: 0.6),
              ),
              boxShadow: AppShadows.subtle,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(16),
                    topRight: Radius.circular(16),
                  ),
                  child: SizedBox(
                    height: 86,
                    width: double.infinity,
                    child:
                        (item.imagePath?.isNotEmpty ?? false)
                            ? AppImage(item.imagePath, fit: BoxFit.cover)
                            : Container(color: AppColors.paleGreen),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(
                    Spacing.sm,
                    Spacing.xs,
                    Spacing.sm,
                    Spacing.sm,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.title ?? '-',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const SizedBox(height: 6),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(4),
                        child: LinearProgressIndicator(
                          value: progress,
                          minHeight: 6,
                          backgroundColor: AppColors.surfaceMuted,
                          color: AppColors.movementGreen,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${(progress * 100).toInt()}% tercapai',
                        style: const TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w600,
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

// ─── Kenyataan Media ──────────────────────────────────────────────────────

class _MediaStatementsSection extends StatelessWidget {
  const _MediaStatementsSection({required this.items});
  final List<PublicMediaStatement> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionHeader(
          title: context.l10n.mediaStatementsTitle,
          path: '/media-statements',
        ),
        if (items.isEmpty)
          _EmptyHint(message: context.l10n.mediaStatementsEmpty)
        else
          SizedBox(
            height: 290,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(width: Spacing.md),
              itemBuilder:
                  (_, index) => _MediaStatementTile(item: items[index]),
            ),
          ),
      ],
    );
  }
}

class _MediaStatementTile extends StatelessWidget {
  const _MediaStatementTile({required this.item});
  final PublicMediaStatement item;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 175,
      child: Material(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => context.push('/media-statements/${item.id}'),
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: AppColors.divider.withValues(alpha: 0.6),
              ),
              boxShadow: AppShadows.subtle,
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(16),
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
                          right: Spacing.sm,
                          bottom: Spacing.sm,
                          child: Text(
                            item.organizationName ??
                                context.l10n.newsAllOrganizations,
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
                  Padding(
                    padding: const EdgeInsets.all(Spacing.md),
                    child: Text(
                      item.title ?? '-',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w600,
                        color: AppColors.textPrimary,
                      ),
                    ),
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

// ─── Thumbnail Pembantu ───────────────────────────────────────────────────

class _Thumb extends StatelessWidget {
  const _Thumb({required this.path});
  final String? path;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 72,
      height: 72,
      child:
          (path != null && path!.isNotEmpty)
              ? AppImage(path, borderRadius: BorderRadius.circular(12))
              : Container(
                decoration: BoxDecoration(
                  color: AppColors.paleGreen,
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
    );
  }
}

// ─── CTA Keahlian (Tesla / SpaceX Prestigious Banner) ─────────────────────

class _MemberCta extends StatelessWidget {
  const _MemberCta();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.lg,
        Spacing.lg,
        Spacing.xl,
      ),
      padding: const EdgeInsets.all(Spacing.xl),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.divider),
        boxShadow: AppShadows.subtle,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: AppColors.movementGreen.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.stars_rounded,
              size: 28,
              color: AppColors.movementGreen,
            ),
          ),
          const SizedBox(height: Spacing.md),
          Text(
            context.l10n.becomeMemberTitle,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 19,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.3,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: Spacing.xs),
          Text(
            context.l10n.becomeMemberBody,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 13.5,
              height: 1.5,
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: Spacing.lg),
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SizedBox(
                height: 44,
                child: FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.movementGreen,
                    foregroundColor: AppColors.white,
                    minimumSize: const Size(0, 44),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    textStyle: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  onPressed: () => context.go('/register'),
                  child: Text(
                    context.l10n.registerNow,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ),
              const SizedBox(height: Spacing.sm),
              SizedBox(
                height: 44,
                child: OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.movementDarkGreen,
                    minimumSize: const Size(0, 44),
                    side: const BorderSide(
                      color: AppColors.divider,
                      width: 1.2,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    padding: const EdgeInsets.symmetric(horizontal: Spacing.sm),
                  ),
                  onPressed: () => context.push('/organization/info'),
                  child: Text(
                    context.l10n.moreInfoSection,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 14,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
