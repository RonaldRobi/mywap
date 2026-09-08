import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/news_providers.dart';
import '../data/models/news.dart';

class VideosScreen extends ConsumerWidget {
  const VideosScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(videosProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: const Text('Video'),
      ),
      body: async.when(
        data:
            (videos) => _VideoExplorer(
              videos: videos,
              onRefresh: () async => ref.invalidate(videosProvider),
            ),
        loading: () => const _VideoSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : 'Ralat tidak dijangka.',
              onRetry: () => ref.invalidate(videosProvider),
            ),
      ),
    );
  }
}

enum _VideoSort { latest, titleAZ, titleZA }

extension on _VideoSort {
  String get label => switch (this) {
        _VideoSort.latest => 'Terkini',
        _VideoSort.titleAZ => 'A → Z',
        _VideoSort.titleZA => 'Z → A',
      };
}

class _VideoExplorer extends StatefulWidget {
  const _VideoExplorer({required this.videos, required this.onRefresh});

  final List<Video> videos;
  final Future<void> Function() onRefresh;

  @override
  State<_VideoExplorer> createState() => _VideoExplorerState();
}

class _VideoExplorerState extends State<_VideoExplorer> {
  final TextEditingController _searchController = TextEditingController();
  String _query = '';
  _VideoSort _sort = _VideoSort.latest;
  bool _liveOnly = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  List<Video> get _filtered {
    final q = _query.trim().toLowerCase();
    final list = widget.videos.where((video) {
      if (_liveOnly && !video.isLive) return false;
      if (q.isNotEmpty &&
          !((video.title ?? '').toLowerCase().contains(q))) {
        return false;
      }
      return true;
    }).toList();

    switch (_sort) {
      case _VideoSort.latest:
        break;
      case _VideoSort.titleAZ:
        list.sort(
          (a, b) => (a.title ?? '').toLowerCase().compareTo(
            (b.title ?? '').toLowerCase(),
          ),
        );
      case _VideoSort.titleZA:
        list.sort(
          (a, b) => (b.title ?? '').toLowerCase().compareTo(
            (a.title ?? '').toLowerCase(),
          ),
        );
    }
    return list;
  }

  void _clearSearch() {
    _searchController.clear();
    setState(() => _query = '');
  }

  @override
  Widget build(BuildContext context) {
    final videos = _filtered;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            Spacing.lg,
            Spacing.lg,
            Spacing.lg,
            Spacing.sm,
          ),
          child: TextField(
            controller: _searchController,
            onChanged: (value) => setState(() => _query = value),
            textInputAction: TextInputAction.search,
            decoration: InputDecoration(
              hintText: 'Cari video…',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: _query.isNotEmpty
                  ? IconButton(
                      icon: const Icon(Icons.clear),
                      onPressed: _clearSearch,
                    )
                  : null,
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(Spacing.lg, 0, Spacing.lg, Spacing.sm),
          child: Row(
            children: [
              FilterChip(
                label: const Text('Live'),
                selected: _liveOnly,
                onSelected: (value) => setState(() => _liveOnly = value),
                showCheckmark: false,
                avatar: const Icon(
                  Icons.circle,
                  size: 10,
                  color: AppColors.error,
                ),
              ),
              const Spacer(),
              _SortMenuButton(
                sort: _sort,
                onChanged: (value) => setState(() => _sort = value),
              ),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: widget.onRefresh,
            child: videos.isEmpty
                ? ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: [
                      SizedBox(
                        height: 220,
                        child: EmptyState(
                          icon: widget.videos.isEmpty
                              ? Icons.video_library_outlined
                              : Icons.search_off,
                          message: widget.videos.isEmpty
                              ? 'Tiada video buat masa ini.'
                              : 'Tiada video sepadan dengan carian.',
                        ),
                      ),
                    ],
                  )
                : LayoutBuilder(
                    builder: (context, constraints) {
                      final width = constraints.maxWidth;
                      final columns = width >= 600 ? 3 : 2;
                      final cellWidth =
                          (width -
                              2 * Spacing.lg -
                              (columns - 1) * Spacing.md) /
                          columns;
                      final cellHeight = cellWidth * 9 / 16 + 58;
                      return GridView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(
                          Spacing.lg,
                          Spacing.xs,
                          Spacing.lg,
                          Spacing.xl,
                        ),
                        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: columns,
                          mainAxisSpacing: Spacing.md,
                          crossAxisSpacing: Spacing.md,
                          childAspectRatio: cellWidth / cellHeight,
                        ),
                        itemCount: videos.length,
                        itemBuilder: (context, index) =>
                            _VideoGridTile(video: videos[index]),
                      );
                    },
                  ),
          ),
        ),
      ],
    );
  }
}

class _SortMenuButton extends StatelessWidget {
  const _SortMenuButton({required this.sort, required this.onChanged});

  final _VideoSort sort;
  final ValueChanged<_VideoSort> onChanged;

  @override
  Widget build(BuildContext context) {
    return PopupMenuButton<_VideoSort>(
      onSelected: onChanged,
      offset: const Offset(0, 48),
      shape: RoundedRectangleBorder(borderRadius: AppRadius.md),
      itemBuilder: (context) => [
        for (final value in _VideoSort.values)
          PopupMenuItem(
            value: value,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (value == sort)
                  const Icon(
                    Icons.check,
                    size: 18,
                    color: AppColors.movementGreen,
                  )
                else
                  const SizedBox(width: 18),
                const SizedBox(width: Spacing.sm),
                Text(value.label),
              ],
            ),
          ),
      ],
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: Spacing.sm + 4,
          vertical: 6,
        ),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: AppColors.divider),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              sort.label,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: AppColors.movementGreen,
              ),
            ),
            const SizedBox(width: 2),
            const Icon(
              Icons.unfold_more,
              size: 16,
              color: AppColors.textSecondary,
            ),
          ],
        ),
      ),
    );
  }
}

class _VideoGridTile extends StatelessWidget {
  const _VideoGridTile({required this.video});

  final Video video;

  Future<void> _open(BuildContext context) async {
    final url = video.watchUrl;
    if (url.isEmpty) return;
    final ok = await launchUrl(
      Uri.parse(url),
      mode: LaunchMode.externalApplication,
    );
    if (!ok && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Video tidak dapat dibuka.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      clipBehavior: Clip.antiAlias,
      margin: EdgeInsets.zero,
      child: InkWell(
        onTap: () => _open(context),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 16 / 9,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  AppImage(
                    video.thumbnailUrl,
                    width: double.infinity,
                    height: double.infinity,
                    fit: BoxFit.cover,
                    borderRadius: BorderRadius.zero,
                  ),
                  Center(
                    child: Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.55),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.play_arrow,
                        color: Colors.white,
                        size: 26,
                      ),
                    ),
                  ),
                  if (video.isLive)
                    Positioned(
                      top: 8,
                      left: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 6,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.error,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: const Text(
                          'LIVE',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.5,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
              child: Text(
                video.title ?? '-',
                style: theme.textTheme.titleSmall?.copyWith(height: 1.3),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _VideoSkeleton extends StatelessWidget {
  const _VideoSkeleton();

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth;
        final columns = width >= 600 ? 3 : 2;
        final cellWidth =
            (width - 2 * Spacing.lg - (columns - 1) * Spacing.md) / columns;
        final cellHeight = cellWidth * 9 / 16 + 58;
        return SingleChildScrollView(
          physics: const NeverScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(
            Spacing.lg,
            Spacing.xs,
            Spacing.lg,
            Spacing.xl,
          ),
          child: Column(
            children: [
              for (var row = 0; row < 3; row++)
                Padding(
                  padding: EdgeInsets.only(
                    bottom: row < 2 ? Spacing.md : 0,
                  ),
                  child: Row(
                    children: [
                      for (var col = 0; col < columns; col++)
                        Expanded(
                          child: Padding(
                            padding: EdgeInsets.only(
                              right: col < columns - 1 ? Spacing.md : 0,
                            ),
                            child: SkeletonBox(
                              height: cellHeight,
                              radius: 22,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}
