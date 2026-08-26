import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/member_core_providers.dart';
import '../data/models/announcement.dart';

/// Announcements list with expandable content, like (react) and mark-read.
class AnnouncementsScreen extends ConsumerWidget {
  const AnnouncementsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final announcementsAsync = ref.watch(memberAnnouncementsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Pengumuman')),
      body: announcementsAsync.when(
        data: (items) => items.isEmpty
            ? const EmptyState(
                icon: Icons.campaign_outlined,
                message: 'Tiada pengumuman buat masa ini.',
              )
            : RefreshIndicator(
                onRefresh: () async =>
                    ref.invalidate(memberAnnouncementsProvider),
                child: ListView.builder(
                  padding: const EdgeInsets.only(bottom: Spacing.xl),
                  itemCount: items.length + 1,
                  itemBuilder: (context, index) {
                    if (index == 0) {
                      return SectionHeader(
                        'Pengumuman',
                        trailing: Text(
                          '${items.length}',
                          style: Theme.of(context).textTheme.labelMedium?.copyWith(
                                color: AppColors.textSecondary,
                              ),
                        ),
                      );
                    }
                    return _AnnouncementCard(item: items[index - 1]);
                  },
                ),
              ),
        loading: () => const _AnnouncementsSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(memberAnnouncementsProvider),
        ),
      ),
    );
  }
}

class _AnnouncementCard extends ConsumerStatefulWidget {
  const _AnnouncementCard({required this.item});

  final Announcement item;

  @override
  ConsumerState<_AnnouncementCard> createState() => _AnnouncementCardState();
}

class _AnnouncementCardState extends ConsumerState<_AnnouncementCard> {
  bool _expanded = false;
  bool _reacting = false;

  Announcement get item => widget.item;

  Future<void> _markRead() async {
    final id = item.id;
    if (id == null || item.is_read == true) return;
    try {
      await ref.read(memberCoreRepositoryProvider).markRead(id);
      if (mounted) ref.invalidate(memberAnnouncementsProvider);
    } on ApiException {
      // Non-critical; keep the UI usable.
    }
  }

  Future<void> _toggleReact() async {
    final id = item.id;
    if (id == null || _reacting) return;
    setState(() => _reacting = true);
    try {
      await ref.read(memberCoreRepositoryProvider).react(id);
      if (mounted) ref.invalidate(memberAnnouncementsProvider);
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _reacting = false);
    }
  }

  void _onTap() {
    if (!_expanded) _markRead();
    setState(() => _expanded = !_expanded);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final hasLiked = item.user_reaction == 'like';

    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          InkWell(
            onTap: _onTap,
            child: Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (item.is_pinned == true) ...[
                    const _PinnedBadge(),
                    const SizedBox(height: Spacing.sm),
                  ],
                  Text(item.title ?? '-', style: theme.textTheme.titleMedium),
                  const SizedBox(height: Spacing.sm),
                  Row(
                    children: [
                      const Icon(
                        Icons.schedule,
                        size: 14,
                        color: AppColors.textSecondary,
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          item.published_human ?? item.published_at ?? '',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ),
                    ],
                  ),
                  if (item.author_name != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      'Oleh ${item.author_name}',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                  if (item.cover_image_url != null &&
                      item.cover_image_url!.isNotEmpty) ...[
                    const SizedBox(height: Spacing.md),
                    AppImage(
                      item.cover_image_url,
                      height: 140,
                      width: double.infinity,
                    ),
                  ],
                  const SizedBox(height: Spacing.md),
                  Text(
                    item.content ?? '',
                    style: theme.textTheme.bodyMedium,
                    maxLines: _expanded ? null : 2,
                    overflow: _expanded ? null : TextOverflow.ellipsis,
                  ),
                  if (_expanded && item.images.isNotEmpty) ...[
                    const SizedBox(height: Spacing.md),
                    for (final image in item.images)
                      if (image.url != null && image.url!.isNotEmpty) ...[
                        AppImage(image.url, height: 140, width: double.infinity),
                        if (image.caption != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Text(
                              image.caption!,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ),
                        const SizedBox(height: Spacing.sm),
                      ],
                  ],
                ],
              ),
            ),
          ),
          const Divider(height: 1),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: Spacing.sm),
            child: Row(
              children: [
                TextButton.icon(
                  onPressed: _reacting ? null : _toggleReact,
                  icon: Icon(
                    hasLiked ? Icons.favorite : Icons.favorite_border,
                    color: hasLiked ? AppColors.error : AppColors.textSecondary,
                  ),
                  label: Text('${item.likes_count ?? 0}'),
                ),
                const Spacer(),
                Padding(
                  padding: const EdgeInsets.only(right: Spacing.md),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.visibility_outlined,
                        size: 16,
                        color: AppColors.textSecondary,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '${item.reads_count ?? 0}',
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
        ],
      ),
    );
  }
}

class _PinnedBadge extends StatelessWidget {
  const _PinnedBadge();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: AppColors.movementSoftGreen,
        borderRadius: BorderRadius.circular(10),
      ),
      child: const Text(
        'Disemat',
        style: TextStyle(
          color: AppColors.movementNavy,
          fontWeight: FontWeight.w700,
          fontSize: 12,
        ),
      ),
    );
  }
}

class _AnnouncementsSkeleton extends StatelessWidget {
  const _AnnouncementsSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      itemCount: 5,
      itemBuilder: (_, __) => const Padding(
        padding: EdgeInsets.only(bottom: Spacing.lg),
        child: SkeletonBox(height: 120, radius: 16),
      ),
    );
  }
}
