import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../loading_screen/application/loading_screen_providers.dart';
import '../../member/presentation/widgets/shell_scaffold_key.dart';
import '../application/public_home_providers.dart';
import '../data/public_home_repository.dart';
import 'guest_prompt.dart';

/// Pastikan dialog log masuk hanya muncul sekali setiap sesi app.
bool _loginPromptShown = false;

/// Beranda awam — boleh dilihat tanpa log masuk. Memaparkan carousel banner
/// admin + artikel, video, pustaka, info terkini, program dan (di bawah sekali)
/// kempen infaq.
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
            title: const Text('Selamat Datang ke myWAP'),
            content: const Text(
              'Log masuk untuk akses penuh sebagai ahli, atau terus terokai '
              'kandungan sebagai tetamu.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: const Text('Tutup'),
              ),
              FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                child: const Text('Log Masuk'),
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
        leading: const AppMenuButton(),
        title: const _HomeLogo(),
        actions: [
          TextButton(
            onPressed: () => context.go('/login'),
            child: const Text('Log Masuk'),
          ),
          const SizedBox(width: Spacing.sm),
        ],
      ),
      body: async.when(
        data:
            (data) => RefreshIndicator(
              onRefresh: () async => ref.invalidate(publicHomeProvider),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.only(bottom: Spacing.xxl),
                children: [
                  _TopCarousel(banners: data.banners),
                  _ArticlesSection(items: data.articles),
                  _VideosSection(items: data.videos),
                  _LibrarySection(items: data.library),
                  _NewsSection(items: data.news),
                  _EventsSection(items: data.events),
                  _InfaqSection(items: data.infaqs),
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
              message: 'Ralat memuatkan kandungan. Sila cuba lagi.',
              onRetry: () => ref.invalidate(publicHomeProvider),
            ),
      ),
    );
  }
}

/// Logo sistem (dimuat naik admin) di bar atas Beranda — jatuh balik kepada
/// teks "myWAP" jika logo belum dikonfigurasi.
class _HomeLogo extends ConsumerWidget {
  const _HomeLogo();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final logo = ref.watch(appLogoProvider);
    if (logo == null || logo.isEmpty) {
      return const Text('myWAP');
    }
    return SizedBox(
      height: 32,
      child: AppImage(logo, fit: BoxFit.contain, borderRadius: AppRadius.sm),
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
        Spacing.lg,
        Spacing.lg,
        Spacing.sm,
      ),
      child: Column(
        children: [
          AspectRatio(
            aspectRatio: 21 / 9,
            child: ClipRRect(
              borderRadius: AppRadius.hero,
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
                                banner.title ?? 'myWAP',
                                style: Theme.of(context).textTheme.titleMedium
                                    ?.copyWith(
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

/// Hero jenama (gabungan logo admin + tagline) bila tiada banner admin.
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
        borderRadius: AppRadius.hero,
        boxShadow: AppShadows.card,
      ),
      child: Row(
        children: [
          if (logo != null && logo.isNotEmpty) ...[
            Container(
              width: 64,
              height: 64,
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
                  'myWAP',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    color: AppColors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: Spacing.xs),
                Text(
                  'Berita, artikel, program dan kempen infaq komuniti — '
                  'terbuka untuk semua.',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textOnDark,
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

// ─── Section chrome ───────────────────────────────────────────────────────

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, required this.path});

  final String title;
  final String path;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.lg,
        Spacing.sm,
        Spacing.sm,
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
            ),
          ),
          TextButton(
            onPressed: () => context.go(path),
            child: const Text('Lihat Semua'),
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

// ─── Articles ─────────────────────────────────────────────────────────────

class _ArticlesSection extends StatelessWidget {
  const _ArticlesSection({required this.items});
  final List<PublicArticle> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionHeader(title: 'Artikel', path: '/articles'),
        if (items.isEmpty)
          const _EmptyHint(message: 'Tiada artikel buat masa ini.')
        else
          ...items.take(4).map((a) => _ArticleTile(article: a)),
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
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
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
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${article.authorName ?? 'Admin'} • ${article.publishedAt ?? ''}',
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
    );
  }
}

// ─── Videos ───────────────────────────────────────────────────────────────

class _VideosSection extends StatelessWidget {
  const _VideosSection({required this.items});
  final List<PublicVideo> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionHeader(title: 'Video', path: '/videos'),
        if (items.isEmpty)
          const _EmptyHint(message: 'Tiada video buat masa ini.')
        else
          SizedBox(
            height: 184,
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
      child: Card(
        clipBehavior: Clip.antiAlias,
        margin: EdgeInsets.zero,
        child: InkWell(
          onTap: () => context.push('/videos'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                height: 110,
                width: double.infinity,
                child:
                    (video.thumbnailUrl?.isNotEmpty ?? false)
                        ? AppImage(
                          video.thumbnailUrl,
                          fit: BoxFit.cover,
                          borderRadius: BorderRadius.zero,
                        )
                        : Container(
                          color: AppColors.paleGreen,
                          child: const Icon(
                            Icons.play_circle_outline,
                            color: AppColors.movementGreen,
                            size: 36,
                          ),
                        ),
              ),
              Padding(
                padding: const EdgeInsets.all(Spacing.sm),
                child: Text(
                  video.title ?? '-',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
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

// ─── Library (Pustaka) ────────────────────────────────────────────────────

class _LibrarySection extends StatelessWidget {
  const _LibrarySection({required this.items});
  final List<PublicLibraryItem> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionHeader(title: 'Pustaka', path: '/member/library'),
        if (items.isEmpty)
          const _EmptyHint(message: 'Tiada bahan pustaka buat masa ini.')
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
        borderRadius: AppRadius.md,
        onTap: () => showLoginPrompt(
          context,
          message: 'Log masuk sebagai ahli untuk membaca di Pustaka Digital.',
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 3 / 4,
              child:
                  (item.coverImagePath?.isNotEmpty ?? false)
                      ? AppImage(item.coverImagePath, fit: BoxFit.cover)
                      : Container(
                        decoration: BoxDecoration(
                          color: AppColors.paleGreen,
                          borderRadius: AppRadius.md,
                        ),
                        child: const Icon(
                          Icons.menu_book_outlined,
                          color: AppColors.movementGreen,
                          size: 36,
                        ),
                      ),
            ),
            const SizedBox(height: Spacing.xs),
            Text(
              item.title ?? '-',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── News (Info Terkini) ──────────────────────────────────────────────────

class _NewsSection extends StatelessWidget {
  const _NewsSection({required this.items});
  final List<PublicNews> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionHeader(title: 'Info Terkini', path: '/news'),
        if (items.isEmpty)
          const _EmptyHint(message: 'Tiada info terkini buat masa ini.')
        else
          ...items.take(4).map((n) => _NewsTile(item: n)),
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
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
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
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${item.categoryName ?? 'Umum'} • ${item.publishedAt ?? ''}',
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
    );
  }
}

// ─── Events (Program) ─────────────────────────────────────────────────────

class _EventsSection extends StatelessWidget {
  const _EventsSection({required this.items});
  final List<PublicEvent> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionHeader(title: 'Program Akan Datang', path: '/events'),
        if (items.isEmpty)
          const _EmptyHint(message: 'Tiada program dijadualkan.')
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
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
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
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Infaq (bawah, tidak dihighlight) ─────────────────────────────────────

class _InfaqSection extends StatelessWidget {
  const _InfaqSection({required this.items});
  final List<PublicInfaq> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionHeader(title: 'Infaq', path: '/infaq'),
        if (items.isEmpty)
          const _EmptyHint(message: 'Tiada kempen infaq buat masa ini.')
        else
          SizedBox(
            height: 192,
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
    final theme = Theme.of(context);
    final progress = ((item.progressPercent ?? 0) / 100).clamp(0.0, 1.0);
    return SizedBox(
      width: 200,
      child: Card(
        clipBehavior: Clip.antiAlias,
        margin: EdgeInsets.zero,
        child: InkWell(
          onTap: () => context.push('/infaq/${item.slug}'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                height: 78,
                width: double.infinity,
                child:
                    (item.imagePath?.isNotEmpty ?? false)
                        ? AppImage(
                          item.imagePath,
                          fit: BoxFit.cover,
                          borderRadius: BorderRadius.zero,
                        )
                        : Container(color: AppColors.paleGreen),
              ),
              Padding(
                padding: const EdgeInsets.all(Spacing.sm),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.title ?? '-',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 6),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: progress,
                        minHeight: 5,
                        backgroundColor: AppColors.surfaceMuted,
                        color: AppColors.movementGreen,
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

// ─── Shared bits ──────────────────────────────────────────────────────────

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

class _MemberCta extends StatelessWidget {
  const _MemberCta();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.all(Spacing.lg),
      padding: const EdgeInsets.all(Spacing.xl),
      decoration: BoxDecoration(
        color: AppColors.softGreenSurface,
        borderRadius: AppRadius.hero,
      ),
      child: Column(
        children: [
          Text(
            'Jadi Ahli myWAP',
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: Spacing.sm),
          const Text(
            'Daftar untuk akses kad ahli, status yuran, program, usrah '
            'dan banyak lagi.',
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: Spacing.lg),
          FilledButton(
            onPressed: () => context.go('/register'),
            child: const Text('Daftar Sekarang'),
          ),
        ],
      ),
    );
  }
}
