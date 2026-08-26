import 'package:flutter/material.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
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
      color: selected ? const Color(0xFFE1F0E4) : AppColors.surface,
      borderRadius: BorderRadius.circular(24),
      child: InkWell(
        borderRadius: BorderRadius.circular(24),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: Spacing.md, vertical: Spacing.sm),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(selected ? selectedIcon : icon, size: 20, color: color),
              const SizedBox(width: 6),
              Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w600)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Comment list + composer shared by news + article detail screens.
class CommentSection extends StatefulWidget {
  const CommentSection({super.key, required this.comments, required this.onSubmit});

  final List<Comment> comments;
  final Future<void> Function(String content) onSubmit;

  @override
  State<CommentSection> createState() => _CommentSectionState();
}

class _CommentSectionState extends State<CommentSection> {
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
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Komen berjaya dihantar.')),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Komen gagal dihantar. Sila cuba lagi.')),
        );
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Komen (${widget.comments.length})', style: theme.textTheme.titleMedium),
        const SizedBox(height: Spacing.md),
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: TextField(
                controller: _controller,
                minLines: 1,
                maxLines: 3,
                decoration: const InputDecoration(
                  hintText: 'Tulis komen...',
                  isDense: true,
                  border: OutlineInputBorder(),
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
          const Padding(
            padding: EdgeInsets.symmetric(vertical: Spacing.lg),
            child: Center(child: Text('Tiada komen lagi.')),
          )
        else
          ...widget.comments.map((c) => _CommentTile(comment: c)),
      ],
    );
  }
}

class _CommentTile extends StatelessWidget {
  const _CommentTile({required this.comment});

  final Comment comment;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
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
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
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
                        comment.userName ?? 'Ahli',
                        style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                      ),
                    ),
                    Text(
                      comment.createdAt ?? '',
                      style: theme.textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
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
