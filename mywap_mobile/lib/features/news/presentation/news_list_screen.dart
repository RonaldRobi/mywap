import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/news_providers.dart';
import '../data/models/news.dart';

class NewsListScreen extends ConsumerWidget {
  const NewsListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(newsListProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Info Terkini')),
      body: async.when(
        data: (items) => _NewsList(
          items: items,
          hasMore: ref.read(newsListProvider.notifier).hasMore,
          onLoadMore: () => ref.read(newsListProvider.notifier).loadMore(),
          onRefresh: () async => ref.invalidate(newsListProvider),
        ),
        loading: () => const _NewsSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(newsListProvider),
        ),
      ),
    );
  }
}

class _NewsList extends StatelessWidget {
  const _NewsList({
    required this.items,
    required this.hasMore,
    required this.onLoadMore,
    required this.onRefresh,
  });

  final List<NewsPost> items;
  final bool hasMore;
  final VoidCallback onLoadMore;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const EmptyState(
        icon: Icons.article_outlined,
        message: 'Tiada info terkini buat masa ini.',
      );
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.builder(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(Spacing.lg),
        itemCount: items.length + (hasMore ? 1 : 0),
        itemBuilder: (context, index) {
          if (index >= items.length) {
            return Center(
              child: TextButton(onPressed: onLoadMore, child: const Text('Muat Lagi')),
            );
          }
          return _NewsCard(post: items[index]);
        },
      ),
    );
  }
}

class _NewsCard extends StatelessWidget {
  const _NewsCard({required this.post});

  final NewsPost post;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/news/${post.id}'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (post.coverImagePath != null && post.coverImagePath!.isNotEmpty)
              AppImage(post.coverImagePath, height: 160, width: double.infinity),
            Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      if (post.categoryName != null)
                        _Badge(label: post.categoryName!),
                      if (post.categoryName != null)
                        const SizedBox(width: Spacing.sm),
                      Text(
                        post.publishedAt ?? '',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: Spacing.sm),
                  Text(post.title ?? '-', style: theme.textTheme.titleMedium),
                  if (post.excerpt?.isNotEmpty ?? false) ...[
                    const SizedBox(height: Spacing.sm),
                    Text(
                      post.excerpt!,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                  const SizedBox(height: Spacing.md),
                  Row(
                    children: [
                      const Icon(Icons.thumb_up_outlined, size: 16, color: AppColors.textSecondary),
                      const SizedBox(width: 4),
                      Text('${post.likesCount}', style: theme.textTheme.bodySmall),
                      const SizedBox(width: Spacing.md),
                      const Icon(Icons.comment_outlined, size: 16, color: AppColors.textSecondary),
                      const SizedBox(width: 4),
                      Text('${post.commentsCount}', style: theme.textTheme.bodySmall),
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

class _Badge extends StatelessWidget {
  const _Badge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: AppColors.movementGreen.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: AppColors.movementGreen,
          fontWeight: FontWeight.w600,
          fontSize: 12,
        ),
      ),
    );
  }
}

class _NewsSkeleton extends StatelessWidget {
  const _NewsSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      itemCount: 5,
      itemBuilder: (_, __) => const Padding(
        padding: EdgeInsets.only(bottom: Spacing.lg),
        child: SkeletonBox(height: 220, radius: 16),
      ),
    );
  }
}
