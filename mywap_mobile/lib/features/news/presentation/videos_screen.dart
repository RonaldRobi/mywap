import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_theme.dart';
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
      appBar: AppBar(title: const Text('Video')),
      body: async.when(
        data: (videos) => _VideoList(
          videos: videos,
          onRefresh: () async => ref.invalidate(videosProvider),
        ),
        loading: () => const _VideoSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(videosProvider),
        ),
      ),
    );
  }
}

class _VideoList extends StatelessWidget {
  const _VideoList({required this.videos, required this.onRefresh});

  final List<Video> videos;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    if (videos.isEmpty) {
      return const EmptyState(
        icon: Icons.video_library_outlined,
        message: 'Tiada video buat masa ini.',
      );
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.builder(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(Spacing.lg),
        itemCount: videos.length,
        itemBuilder: (context, index) => _VideoCard(video: videos[index]),
      ),
    );
  }
}

class _VideoCard extends StatelessWidget {
  const _VideoCard({required this.video});

  final Video video;

  Future<void> _open(BuildContext context) async {
    final url = video.watchUrl;
    if (url.isEmpty) return;
    final ok = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
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
      child: InkWell(
        onTap: () => _open(context),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              alignment: Alignment.center,
              children: [
                AppImage(
                  video.thumbnailUrl,
                  height: 180,
                  width: double.infinity,
                  borderRadius: BorderRadius.zero,
                ),
                Container(
                  width: 52,
                  height: 52,
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.55),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.play_arrow, color: Colors.white, size: 32),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: Text(video.title ?? '-', style: theme.textTheme.titleMedium),
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
    return ListView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      itemCount: 4,
      itemBuilder: (_, __) => const Padding(
        padding: EdgeInsets.only(bottom: Spacing.lg),
        child: SkeletonBox(height: 220, radius: 16),
      ),
    );
  }
}
