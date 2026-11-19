import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../../shared/widgets/html_content.dart';
import '../../../shared/widgets/share_item.dart';
import '../../auth/application/auth_controller.dart';
import '../../public/presentation/guest_prompt.dart';
import '../application/news_providers.dart';
import '../data/models/news.dart';
import 'content_widgets.dart';
import '../../../core/utils/formatters.dart';

class NewsDetailScreen extends ConsumerWidget {
  const NewsDetailScreen({super.key, required this.newsId});

  final int newsId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(newsDetailProvider(newsId));
    final post = async.valueOrNull?.post;
    final isAuthenticated = ref.watch(currentUserProvider) != null;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        leading: const AppBackButton(fallback: '/news'),
        title: Text(
          context.l10n.newsTitle,
          style: const TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w700,
            color: AppColors.movementNavy,
          ),
        ),
        actions: [
          if (post != null)
            ShareItemIconButton(
              title: post.title ?? '',
              url: post.shareUrl,
              text: post.excerpt,
            ),
        ],
      ),
      body: async.when(
        data:
            (detail) => _NewsDetailBody(
              detail: detail,
              onReaction: (reaction) async {
                if (!isAuthenticated) {
                  await showLoginPrompt(
                    context,
                    message: context.l10n.newsLoginToReact,
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
                      : context.l10n.unexpectedError,
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
    final post = detail.post;
    final orgName = post.organizationName ?? context.l10n.newsAllOrganizations;
    final categoryName = post.category?.name ?? 'BERITA';

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(
          Spacing.lg,
          Spacing.sm,
          Spacing.lg,
          Spacing.xxl,
        ),
        children: [
          // 1. Tag Kategori & Tarikh Penerbitan
          Row(
            children: [
              Flexible(
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.movementGreen.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    categoryName.toUpperCase(),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppColors.movementGreen,
                      fontWeight: FontWeight.w800,
                      fontSize: 10.5,
                      letterSpacing: 0.5,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    const Icon(
                      Icons.access_time_rounded,
                      size: 13,
                      color: AppColors.textTertiary,
                    ),
                    const SizedBox(width: 4),
                    Flexible(
                      child: Text(
                        Formatters.datetimeFromIso(post.publishedAt),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.end,
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w500,
                          color: AppColors.textTertiary,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: Spacing.md),

          // 2. Tajuk Utama Editorial
          Text(
            post.title ?? '-',
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.5,
              color: AppColors.movementNavy,
              height: 1.25,
            ),
          ),
          const SizedBox(height: Spacing.sm),

          // 3. Sumber & Penulis Berita
          Row(
            children: [
              Container(
                width: 28,
                height: 28,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: Color(0xFFE8F5E9),
                ),
                child: const Icon(
                  Icons.newspaper_rounded,
                  size: 15,
                  color: AppColors.movementGreen,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      orgName,
                      style: const TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: AppColors.movementNavy,
                      ),
                    ),
                    const Text(
                      'Sumber Rasmi myWAP',
                      style: TextStyle(
                        fontSize: 10,
                        color: AppColors.textTertiary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: Spacing.lg),

          // 4. Gambar Poster / Tajuk Berita
          if (post.coverImagePath != null &&
              post.coverImagePath!.isNotEmpty) ...[
            ClipRRect(
              borderRadius: BorderRadius.circular(18),
              child: AspectRatio(
                aspectRatio: 16 / 10,
                child: AppImage(
                  post.coverImagePath,
                  width: double.infinity,
                  fit: BoxFit.cover,
                ),
              ),
            ),
            const SizedBox(height: Spacing.lg),
          ],

          // 5. Kandungan Teks Berita (Apple Editorial Typography)
          Container(
            padding: const EdgeInsets.all(Spacing.lg),
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: const Color(0x10000000), width: 0.8),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x0612241C),
                  blurRadius: 10,
                  offset: Offset(0, 3),
                ),
              ],
            ),
            child: HtmlContent(
              post.content,
              style: const TextStyle(
                fontSize: 15.5,
                height: 1.7,
                color: Color(0xFF2C3E50),
                letterSpacing: -0.1,
              ),
            ),
          ),
          const SizedBox(height: Spacing.xl),

          // 6. Bar Reaksi (Like / Dislike)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0x10000000), width: 0.8),
            ),
            child: ReactionBar(
              likesCount: post.likesCount,
              dislikesCount: post.dislikesCount,
              myReaction: post.myReaction,
              onLike: () => onReaction('like'),
              onDislike: () => onReaction('dislike'),
            ),
          ),
          const SizedBox(height: Spacing.xl),

          // 7. Ruangan Komen Komuniti
          Container(
            padding: const EdgeInsets.all(Spacing.lg),
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: const Color(0x10000000), width: 0.8),
            ),
            child: CommentSection(
              comments: detail.comments,
              commentsCount: post.commentsCount,
              reportableType: 'news_comment',
              onSubmit: onComment,
            ),
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
        SkeletonBox(height: 18, width: 90, radius: 6),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 28, radius: 8),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 24, width: 220, radius: 8),
        SizedBox(height: Spacing.lg),
        AspectRatio(aspectRatio: 16 / 10, child: SkeletonBox(radius: 18)),
        SizedBox(height: Spacing.lg),
        SkeletonBox(height: 120, radius: 18),
      ],
    );
  }
}
