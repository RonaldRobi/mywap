import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/news_providers.dart';
import '../data/models/news.dart';
import 'content_widgets.dart';

class ArticleDetailScreen extends ConsumerWidget {
  const ArticleDetailScreen({super.key, required this.articleId});

  final int articleId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(articleDetailProvider(articleId));

    return Scaffold(
      appBar: AppBar(title: const Text('Artikel')),
      body: async.when(
        data: (detail) => _ArticleDetailBody(
          detail: detail,
          onReaction: (reaction) async {
            await ref
                .read(newsRepositoryProvider)
                .reactArticle(articleId, reaction);
            ref.invalidate(articleDetailProvider(articleId));
            ref.invalidate(articleListProvider);
          },
          onComment: (content) async {
            await ref
                .read(newsRepositoryProvider)
                .commentArticle(articleId, content);
            ref.invalidate(articleDetailProvider(articleId));
          },
        ),
        loading: () => const _DetailSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(articleDetailProvider(articleId)),
        ),
      ),
    );
  }
}

class _ArticleDetailBody extends StatelessWidget {
  const _ArticleDetailBody({
    required this.detail,
    required this.onReaction,
    required this.onComment,
  });

  final ArticleDetail detail;
  final Future<void> Function(String reaction) onReaction;
  final Future<void> Function(String content) onComment;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final article = detail.article;

    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        if (article.coverImagePath != null && article.coverImagePath!.isNotEmpty)
          AppImage(article.coverImagePath, height: 200, borderRadius: BorderRadius.circular(12)),
        const SizedBox(height: Spacing.lg),
        Row(
          children: [
            Expanded(
              child: Text(
                '${article.authorName ?? 'Admin'} • ${article.publishedAt ?? ''}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.movementGreen,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: Spacing.sm),
        Text(article.title ?? '-', style: theme.textTheme.headlineSmall),
        const SizedBox(height: Spacing.lg),
        Text(article.content ?? '', style: theme.textTheme.bodyLarge),
        if (article.gallery.isNotEmpty) ...[
          const SizedBox(height: Spacing.lg),
          SizedBox(
            height: 180,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: article.gallery.length,
              separatorBuilder: (_, __) => const SizedBox(width: Spacing.md),
              itemBuilder: (_, i) => SizedBox(
                width: 260,
                child: AppImage(
                  article.gallery[i].path,
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ),
        ],
        if (article.tags.isNotEmpty) ...[
          const SizedBox(height: Spacing.lg),
          Wrap(
            spacing: 6,
            children: article.tags
                .where((t) => t.name != null)
                .map(
                  (t) => Chip(
                    label: Text('#${t.name}'),
                    labelStyle: const TextStyle(fontSize: 11),
                    visualDensity: VisualDensity.compact,
                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                )
                .toList(growable: false),
          ),
        ],
        const SizedBox(height: Spacing.xl),
        ReactionBar(
          likesCount: article.likesCount,
          dislikesCount: article.dislikesCount,
          myReaction: article.myReaction,
          onLike: () => onReaction('like'),
          onDislike: () => onReaction('dislike'),
        ),
        const Divider(height: Spacing.xl * 2),
        CommentSection(comments: detail.comments, onSubmit: onComment),
      ],
    );
  }
}

class _DetailSkeleton extends StatelessWidget {
  const _DetailSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: const [
        SkeletonBox(height: 200, radius: 12),
        SizedBox(height: Spacing.lg),
        SkeletonBox(height: 28, radius: 8),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 16, width: 200, radius: 6),
        SizedBox(height: Spacing.lg),
        SkeletonBox(height: 16, radius: 6),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 16, radius: 6),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 16, width: 240, radius: 6),
      ],
    );
  }
}
