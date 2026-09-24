import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../../shared/widgets/html_content.dart';
import '../../auth/application/auth_controller.dart';
import '../../public/presentation/guest_prompt.dart';
import '../application/news_providers.dart';
import '../data/models/news.dart';
import 'content_widgets.dart';
import '../../../shared/theme/app_text_theme.dart';

class NewsDetailScreen extends ConsumerWidget {
  const NewsDetailScreen({super.key, required this.newsId});

  final int newsId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(newsDetailProvider(newsId));
    final isAuthenticated = ref.watch(currentUserProvider) != null;

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(fallback: '/news'),
        title: const Text('Info Terkini'),
      ),
      body: async.when(
        data:
            (detail) => _NewsDetailBody(
              detail: detail,
              onReaction: (reaction) async {
                if (!isAuthenticated) {
                  await showLoginPrompt(
                    context,
                    message: 'Log masuk sebagai ahli untuk memberi reaksi.',
                  );
                  return;
                }
                await ref
                    .read(newsRepositoryProvider)
                    .reactNews(newsId, reaction);
                ref.invalidate(newsDetailProvider(newsId));
                ref.invalidate(newsListProvider);
              },
              onComment: (content) async {
                await ref
                    .read(newsRepositoryProvider)
                    .commentNews(newsId, content);
                ref.invalidate(newsDetailProvider(newsId));
              },
              onRefresh: () async => ref.invalidate(newsDetailProvider(newsId)),
            ),
        loading: () => const _DetailSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : 'Ralat tidak dijangka.',
              onRetry: () => ref.invalidate(newsDetailProvider(newsId)),
            ),
      ),
    );
  }
}

class _NewsDetailBody extends StatelessWidget {
  const _NewsDetailBody({
    required this.detail,
    required this.onReaction,
    required this.onComment,
    required this.onRefresh,
  });

  final NewsDetail detail;
  final Future<void> Function(String reaction) onReaction;
  final Future<void> Function(String content) onComment;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final post = detail.post;

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(Spacing.lg),
        children: [
          if (post.coverImagePath != null && post.coverImagePath!.isNotEmpty)
            AspectRatio(
              aspectRatio: 4 / 5,
              child: AppImage(
                post.coverImagePath,
                width: double.infinity,
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          const SizedBox(height: Spacing.lg),
          Row(
            children: [
              if (post.category?.name != null)
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 2,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.movementGreen.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    post.category!.name!,
                    style: const TextStyle(
                      color: AppColors.movementGreen,
                      fontWeight: FontWeight.w600,
                      fontSize: AppTextTheme.minSize,
                    ),
                  ),
                ),
              const SizedBox(width: Spacing.sm),
              Expanded(
                child: Text(
                  post.publishedAt ?? '',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                  textAlign: TextAlign.end,
                ),
              ),
            ],
          ),
          const SizedBox(height: Spacing.sm),
          Text(post.title ?? '-', style: theme.textTheme.headlineSmall),
          const SizedBox(height: 4),
          Text(
            post.organizationName ?? 'Semua Organisasi',
            style: theme.textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: Spacing.lg),
          HtmlContent(post.content, style: theme.textTheme.bodyLarge),
          const SizedBox(height: Spacing.xl),
          ReactionBar(
            likesCount: post.likesCount,
            dislikesCount: post.dislikesCount,
            myReaction: post.myReaction,
            onLike: () => onReaction('like'),
            onDislike: () => onReaction('dislike'),
          ),
          const SizedBox(height: Spacing.xl),
          const Divider(),
          const SizedBox(height: Spacing.lg),
          CommentSection(
            comments: detail.comments,
            commentsCount: post.commentsCount,
            reportableType: 'news_comment',
            onSubmit: onComment,
          ),
        ],
      ),
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
        AspectRatio(aspectRatio: 4 / 5, child: SkeletonBox(radius: 12)),
        SizedBox(height: Spacing.lg),
        SkeletonBox(height: 28, radius: 8),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 16, width: 140, radius: 6),
        SizedBox(height: Spacing.lg),
        SkeletonBox(height: 16, radius: 6),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 16, radius: 6),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 16, width: 260, radius: 6),
      ],
    );
  }
}
