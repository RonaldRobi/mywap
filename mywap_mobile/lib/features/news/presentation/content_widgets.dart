import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../auth/application/auth_controller.dart';
import '../../moderation/application/moderation_providers.dart';
import '../../public/presentation/guest_prompt.dart';
import '../data/models/news.dart';

/// Like / dislike reaction bar shared by news + article detail screens.
class ReactionBar extends StatelessWidget {
  const ReactionBar({
    super.key,
    required this.likesCount,
    required this.dislikesCount,
    required this.myReaction,
    required this.onLike,
    required this.onDislike,
  });

  final int likesCount;
  final int dislikesCount;
  final String? myReaction;
  final VoidCallback onLike;
  final VoidCallback onDislike;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        _ReactionChip(
          icon: Icons.thumb_up_outlined,
          selectedIcon: Icons.thumb_up,
          label: '$likesCount',
          selected: myReaction == 'like',
          onTap: onLike,
        ),
        const SizedBox(width: Spacing.md),
        _ReactionChip(
          icon: Icons.thumb_down_outlined,
          selectedIcon: Icons.thumb_down,
          label: '$dislikesCount',
          selected: myReaction == 'dislike',
          onTap: onDislike,
        ),
      ],
    );
  }
}

class _ReactionChip extends StatelessWidget {
  const _ReactionChip({
    required this.icon,
    required this.selectedIcon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final IconData selectedIcon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.movementGreen : AppColors.textSecondary;
    return Material(
      color: selected ? AppColors.paleGreen : AppColors.surface,
      borderRadius: BorderRadius.circular(24),
      child: InkWell(
        borderRadius: BorderRadius.circular(24),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: Spacing.md,
            vertical: Spacing.sm,
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(selected ? selectedIcon : icon, size: 20, color: color),
              const SizedBox(width: 6),
              Text(
                label,
                style: TextStyle(color: color, fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Comment list + composer shared by news + article detail screens.
///
/// Komen ialah ciri ahli: tetamu hanya melihat kiraan komen dan gesaan log
/// masuk. Setiap komen ahli boleh dilaporkan atau penggunanya disekat
/// (keperluan App Review — report/block UGC).
class CommentSection extends ConsumerStatefulWidget {
  const CommentSection({
    super.key,
    required this.comments,
    required this.commentsCount,
    required this.reportableType,
    required this.onSubmit,
  });

  final List<Comment> comments;
  final int commentsCount;

  /// `article_comment` atau `news_comment`.
  final String reportableType;
  final Future<void> Function(String content) onSubmit;

  @override
  ConsumerState<CommentSection> createState() => _CommentSectionState();
}

class _CommentSectionState extends ConsumerState<CommentSection> {
  final _controller = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final content = _controller.text.trim();
    if (content.isEmpty || _submitting) return;
    setState(() => _submitting = true);
    try {
      await widget.onSubmit(content);
      _controller.clear();
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(context.l10n.newsCommentSent)));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(context.l10n.newsCommentFailed)));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _report(Comment comment) async {
    final reasons = <String, String>{
      'spam': context.l10n.newsReportSpam,
      'harassment': context.l10n.newsReportHarassment,
      'hate': context.l10n.newsReportHate,
      'sexual': context.l10n.newsReportSexual,
      'violence': context.l10n.newsReportViolence,
      'misinformation': context.l10n.newsReportMisinformation,
      'other': context.l10n.newsReportOther,
    };
    final reason = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder:
          (sheetContext) => SafeArea(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Padding(
                  padding: const EdgeInsets.all(Spacing.lg),
                  child: Text(
                    context.l10n.newsReportCommentTitle,
                    style: Theme.of(sheetContext).textTheme.titleMedium,
                  ),
                ),
                for (final entry in reasons.entries)
                  ListTile(
                    title: Text(entry.value),
                    onTap: () => Navigator.of(sheetContext).pop(entry.key),
                  ),
              ],
            ),
          ),
    );
    if (reason == null || comment.id == null || !mounted) return;

    try {
      await ref
          .read(moderationRepositoryProvider)
          .reportComment(
            reportableType: widget.reportableType,
            reportableId: comment.id!,
            reason: reason,
          );
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(context.l10n.newsReportSent)));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(context.l10n.newsReportFailed)));
      }
    }
  }

  Future<void> _block(Comment comment) async {
    final userId = comment.userId;
    if (userId == null) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder:
          (dialogContext) => AlertDialog(
            title: Text(context.l10n.newsBlockUserTitle),
            content: Text(
              context.l10n.newsBlockConfirmBody(
                comment.userName ?? context.l10n.newsThisUser,
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: Text(context.l10n.cancel),
              ),
              FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                child: Text(context.l10n.newsBlock),
              ),
            ],
          ),
    );
    if (confirmed != true || !mounted) return;

    try {
      await ref.read(moderationRepositoryProvider).blockUser(userId);
      ref.invalidate(blockedUserIdsProvider);
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(context.l10n.newsUserBlocked)));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(context.l10n.newsBlockFailed)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isAuthenticated = ref.watch(currentUserProvider) != null;

    if (!isAuthenticated) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.newsCommentsCount('${widget.commentsCount}'),
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: Spacing.md),
          GuestLoginBanner(message: context.l10n.newsLoginToComment),
        ],
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.l10n.newsCommentsCount('${widget.commentsCount}'),
          style: theme.textTheme.titleMedium,
        ),
        const SizedBox(height: Spacing.md),
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: TextField(
                controller: _controller,
                minLines: 1,
                maxLines: 3,
                decoration: InputDecoration(
                  hintText: context.l10n.newsCommentHint,
                  isDense: true,
                  border: const OutlineInputBorder(),
                ),
                textInputAction: TextInputAction.send,
                onSubmitted: (_) => _submit(),
              ),
            ),
            const SizedBox(width: Spacing.sm),
            IconButton.filled(
              onPressed: _submitting ? null : _submit,
              icon: const Icon(Icons.send),
              color: AppColors.movementGreen,
            ),
          ],
        ),
        const SizedBox(height: Spacing.lg),
        if (widget.comments.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: Spacing.lg),
            child: Center(child: Text(context.l10n.newsNoComments)),
          )
        else
          ...widget.comments.map(
            (c) => _CommentTile(
              comment: c,
              onReport: () => _report(c),
              onBlock: () => _block(c),
            ),
          ),
      ],
    );
  }
}

class _CommentTile extends ConsumerWidget {
  const _CommentTile({
    required this.comment,
    required this.onReport,
    required this.onBlock,
  });

  final Comment comment;
  final VoidCallback onReport;
  final VoidCallback onBlock;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final currentUserId = ref.watch(currentUserProvider)?.id;
    final isOwnComment =
        comment.userId != null && comment.userId == currentUserId;

    return Padding(
      padding: const EdgeInsets.only(bottom: Spacing.md),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            backgroundColor: AppColors.movementSoftGreen,
            child: Text(
              (comment.userName?.isNotEmpty ?? false)
                  ? comment.userName![0].toUpperCase()
                  : 'A',
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        comment.userName ?? context.l10n.memberFallback,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    Text(
                      comment.createdAt ?? '',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                    if (!isOwnComment && comment.userId != null)
                      SizedBox(
                        width: 28,
                        height: 28,
                        child: PopupMenuButton<String>(
                          padding: EdgeInsets.zero,
                          iconSize: 18,
                          tooltip: context.l10n.newsOptionsTooltip,
                          onSelected: (value) {
                            if (value == 'report') onReport();
                            if (value == 'block') onBlock();
                          },
                          itemBuilder:
                              (_) => [
                                PopupMenuItem(
                                  value: 'report',
                                  child: Text(context.l10n.newsReportComment),
                                ),
                                PopupMenuItem(
                                  value: 'block',
                                  child: Text(context.l10n.newsBlockUser),
                                ),
                              ],
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 2),
                Text(comment.content ?? '', style: theme.textTheme.bodyMedium),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
