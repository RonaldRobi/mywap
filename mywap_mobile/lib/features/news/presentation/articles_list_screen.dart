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

class ArticlesListScreen extends ConsumerWidget {
  const ArticlesListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(articleListProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Artikel')),
      body: async.when(
        data: (items) => _ArticleList(
          items: items,
          hasMore: ref.read(articleListProvider.notifier).hasMore,
          onLoadMore: () => ref.read(articleListProvider.notifier).loadMore(),
          onRefresh: () async => ref.invalidate(articleListProvider),
        ),
        loading: () => const _ArticleSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(articleListProvider),
        ),
      ),
    );
  }
}

class _ArticleList extends StatelessWidget {
  const _ArticleList({
    required this.items,
    required this.hasMore,
    required this.onLoadMore,
    required this.onRefresh,
  });

  final List<Article> items;
  final bool hasMore;
  final VoidCallback onLoadMore;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const EmptyState(
        icon: Icons.menu_book_outlined,
        message: 'Tiada artikel buat masa ini.',
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
          return _ArticleCard(article: items[index]);
        },
      ),
    );
  }
}

class _ArticleCard extends StatelessWidget {
  const _ArticleCard({required this.article});

  final Article article;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/articles/${article.id}'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (article.coverImage != null && article.coverImage!.isNotEmpty)
              AppImage(article.coverImage, height: 150, width: double.infinity),
            Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          article.authorName ?? 'Admin',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.movementGreen,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      Text(
                        article.publishedDate ?? '',
                        style: theme.textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
                      ),
                    ],
                  ),
                  const SizedBox(height: Spacing.sm),
                  Text(article.title ?? '-', style: theme.textTheme.titleMedium),
                  if (article.excerpt?.isNotEmpty ?? false) ...[
                    const SizedBox(height: Spacing.sm),
                    Text(
                      article.excerpt!,
                      style: theme.textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                  if (article.categories.isNotEmpty) ...[
                    const SizedBox(height: Spacing.sm),
                    Wrap(
                      spacing: 6,
                      children: article.categories
                          .where((c) => c.name != null)
                          .map(
                            (c) => Chip(
                              label: Text(c.name!),
                              labelStyle: const TextStyle(fontSize: 11),
                              visualDensity: VisualDensity.compact,
                              materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                            ),
                          )
                          .toList(growable: false),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ArticleSkeleton extends StatelessWidget {
  const _ArticleSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      itemCount: 5,
      itemBuilder: (_, __) => const Padding(
        padding: EdgeInsets.only(bottom: Spacing.lg),
        child: SkeletonBox(height: 200, radius: 16),
      ),
    );
  }
}
