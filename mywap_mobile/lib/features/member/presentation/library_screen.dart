import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/member_core_providers.dart';
import '../data/models/library_item.dart';

/// Library grid of reference materials (documents, books, guides).
class LibraryScreen extends ConsumerWidget {
  const LibraryScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final libraryAsync = ref.watch(memberLibraryProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Pustaka')),
      body: libraryAsync.when(
        data: (items) => items.isEmpty
            ? const EmptyState(
                icon: Icons.local_library_outlined,
                message: 'Tiada bahan pustaka buat masa ini.',
              )
            : RefreshIndicator(
                onRefresh: () async => ref.invalidate(memberLibraryProvider),
                child: GridView.builder(
                  padding: const EdgeInsets.all(Spacing.lg),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: Spacing.md,
                    crossAxisSpacing: Spacing.md,
                    childAspectRatio: 0.72,
                  ),
                  itemCount: items.length,
                  itemBuilder: (context, index) =>
                      _LibraryCard(item: items[index]),
                ),
              ),
        loading: () => const _LibrarySkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(memberLibraryProvider),
        ),
      ),
    );
  }
}

class _LibraryCard extends StatelessWidget {
  const _LibraryCard({required this.item});

  final LibraryItem item;

  void _showDetails(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) {
        final theme = Theme.of(context);
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(Spacing.lg),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.title ?? '-', style: theme.textTheme.titleMedium),
                if (item.category != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    item.category!,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.movementGreen,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
                if (item.description != null &&
                    item.description!.isNotEmpty) ...[
                  const SizedBox(height: Spacing.md),
                  Text(item.description!, style: theme.textTheme.bodyMedium),
                ],
                if (item.file_path != null &&
                    item.file_path!.isNotEmpty) ...[
                  const SizedBox(height: Spacing.md),
                  Row(
                    children: [
                      const Icon(
                        Icons.insert_drive_file_outlined,
                        size: 18,
                        color: AppColors.textSecondary,
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          item.file_path!,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.textSecondary,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ],
                const SizedBox(height: Spacing.xl),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: () => Navigator.of(context).pop(),
                    child: const Text('Tutup'),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _showDetails(context),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AppImage(item.cover_image_path, height: 110),
            Padding(
              padding: const EdgeInsets.all(Spacing.md),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (item.category != null &&
                      item.category!.isNotEmpty) ...[
                    Text(
                      item.category!,
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: AppColors.movementGreen,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 4),
                  ],
                  Text(
                    item.title ?? '-',
                    style: theme.textTheme.titleSmall,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
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

class _LibrarySkeleton extends StatelessWidget {
  const _LibrarySkeleton();

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: Spacing.md,
        crossAxisSpacing: Spacing.md,
        childAspectRatio: 0.72,
      ),
      itemCount: 6,
      itemBuilder: (_, __) => const SkeletonBox(radius: 16),
    );
  }
}
