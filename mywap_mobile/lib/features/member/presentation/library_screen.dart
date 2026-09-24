import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../../core/network/api_exception.dart';
import '../application/member_core_providers.dart';
import '../data/models/library_item.dart';
import 'library_reader_screen.dart';

/// Pustaka — digital library of ebooks. Portrait book covers in a grid, with
/// quick actions (Baca / ❤ kegemaran) on every card. "Baca" opens the in-app
/// book reader directly (no external link / URL is shown to the user).
class LibraryScreen extends ConsumerStatefulWidget {
  const LibraryScreen({super.key});

  @override
  ConsumerState<LibraryScreen> createState() => _LibraryScreenState();
}

enum _LibraryFilter { semua, kegemaran }

class _LibraryScreenState extends ConsumerState<LibraryScreen> {
  _LibraryFilter _filter = _LibraryFilter.semua;

  @override
  Widget build(BuildContext context) {
    final libraryAsync = ref.watch(memberLibraryProvider);
    final favourites = ref.watch(libraryFavouritesProvider).value ?? const <int>{};

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: const Text('Pustaka'),
      ),
      body: libraryAsync.when(
        data: (items) {
          if (items.isEmpty) {
            return const EmptyState(
              icon: Icons.local_library_outlined,
              message: 'Tiada bahan pustaka buat masa ini.',
            );
          }

          final visible = _filter == _LibraryFilter.semua
              ? items
              : items.where((e) => favourites.contains(e.id)).toList();

          if (_filter == _LibraryFilter.kegemaran && visible.isEmpty) {
            return Column(
              children: [
                _FilterChips(
                  filter: _filter,
                  total: items.length,
                  favouritesCount: favourites.length,
                  onChanged: (value) => setState(() => _filter = value),
                ),
                const Expanded(
                  child: EmptyState(
                    icon: Icons.favorite_border,
                    message: 'Tiada buku dalam kegemaran lagi.',
                  ),
                ),
              ],
            );
          }

          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(memberLibraryProvider),
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverToBoxAdapter(
                  child: _FilterChips(
                    filter: _filter,
                    total: items.length,
                    favouritesCount: favourites.length,
                    onChanged: (value) => setState(() => _filter = value),
                  ),
                ),
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(
                    Spacing.lg,
                    0,
                    Spacing.lg,
                    Spacing.lg,
                  ),
                  sliver: SliverGrid(
                    gridDelegate: _gridDelegate(context),
                    delegate: SliverChildBuilderDelegate(
                      (context, index) {
                        final item = visible[index];
                        return _BookCard(
                          item: item,
                          isFavourite: favourites.contains(item.id),
                          onToggleFavourite: () => ref
                              .read(libraryFavouritesProvider.notifier)
                              .toggle(item.id ?? 0),
                          onOpen: () => _openReader(context, item),
                        );
                      },
                      childCount: visible.length,
                    ),
                  ),
                ),
              ],
            ),
          );
        },
        loading: () => GridView.builder(
          padding: const EdgeInsets.all(Spacing.lg),
          gridDelegate: _gridDelegate(context),
          itemCount: 6,
          itemBuilder: (_, __) => const SkeletonBox(radius: 22),
        ),
        error: (error, _) => ErrorRetry(
          message:
              error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(memberLibraryProvider),
        ),
      ),
    );
  }

  SliverGridDelegate _gridDelegate(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final crossAxisExtent = (width - Spacing.lg * 2 - Spacing.md) / 2;
    final coverHeight = crossAxisExtent * 4 / 3;
    final childAspectRatio = crossAxisExtent / (coverHeight + 126);
    return SliverGridDelegateWithFixedCrossAxisCount(
      crossAxisCount: 2,
      mainAxisSpacing: Spacing.lg,
      crossAxisSpacing: Spacing.md,
      childAspectRatio: childAspectRatio,
    );
  }

  void _openReader(BuildContext context, LibraryItem item) {
    final path = item.file_path;
    if (path == null || path.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tiada fail untuk dibuka.')),
      );
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => LibraryReaderScreen(item: item),
      ),
    );
  }
}

class _FilterChips extends StatelessWidget {
  const _FilterChips({
    required this.filter,
    required this.total,
    required this.favouritesCount,
    required this.onChanged,
  });

  final _LibraryFilter filter;
  final int total;
  final int favouritesCount;
  final ValueChanged<_LibraryFilter> onChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.xs,
        Spacing.lg,
        Spacing.md,
      ),
      child: Row(
        children: [
          _chip(context, _LibraryFilter.semua, 'Semua', total),
          const SizedBox(width: Spacing.sm),
          _chip(context, _LibraryFilter.kegemaran, 'Kegemaran', favouritesCount),
        ],
      ),
    );
  }

  Widget _chip(
    BuildContext context,
    _LibraryFilter value,
    String label,
    int count,
  ) {
    final selected = filter == value;
    return Material(
      color: selected ? AppColors.movementGreen : AppColors.surface,
      borderRadius: BorderRadius.circular(99),
      child: InkWell(
        borderRadius: BorderRadius.circular(99),
        onTap: () => onChanged(value),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: Spacing.md, vertical: 7),
          child: Text(
            '$label ($count)',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: selected ? AppColors.white : AppColors.textSecondary,
            ),
          ),
        ),
      ),
    );
  }
}

class _BookCard extends StatelessWidget {
  const _BookCard({
    required this.item,
    required this.isFavourite,
    required this.onToggleFavourite,
    required this.onOpen,
  });

  final LibraryItem item;
  final bool isFavourite;
  final VoidCallback onToggleFavourite;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(borderRadius: AppRadius.card),
      elevation: 0,
      color: AppColors.surface,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: Stack(
              fit: StackFit.expand,
              children: [
                InkWell(
                  onTap: onOpen,
                  child: _BookCover(item: item),
                ),
                Positioned(
                  top: 6,
                  right: 6,
                  child: Material(
                    color: AppColors.white.withValues(alpha: 0.92),
                    shape: const CircleBorder(),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: onToggleFavourite,
                      child: Padding(
                        padding: const EdgeInsets.all(7),
                        child: Icon(
                          isFavourite
                              ? Icons.favorite
                              : Icons.favorite_border,
                          size: 20,
                          color: isFavourite
                              ? AppColors.error
                              : AppColors.textSecondary,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.md,
              Spacing.sm,
              Spacing.md,
              Spacing.md,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                if (item.category != null && item.category!.isNotEmpty) ...[
                  Text(
                    item.category!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: AppColors.movementGreen,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 3),
                ],
                Text(
                  item.title ?? '-',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleSmall?.copyWith(
                    height: 1.2,
                  ),
                ),
                const SizedBox(height: Spacing.sm),
                SizedBox(
                  height: 34,
                  child: FilledButton(
                    onPressed: onOpen,
                    style: FilledButton.styleFrom(
                      padding: const EdgeInsets.symmetric(
                        horizontal: Spacing.md,
                      ),
                      minimumSize: const Size.fromHeight(34),
                      textStyle: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    child: const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.menu_book_outlined, size: 16),
                        SizedBox(width: 6),
                        Text('Baca'),
                      ],
                    ),
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

class _BookCover extends StatelessWidget {
  const _BookCover({required this.item});

  final LibraryItem item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cover = item.cover_image_path;

    if (cover == null || cover.isEmpty) {
      return DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: AppColors.mintGradient,
          ),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.menu_book_outlined,
              size: 42,
              color: AppColors.white,
            ),
            const SizedBox(height: Spacing.sm),
            if (item.category != null && item.category!.isNotEmpty)
              Text(
                item.category!,
                textAlign: TextAlign.center,
                style: theme.textTheme.labelMedium?.copyWith(
                  color: AppColors.white.withValues(alpha: 0.95),
                  fontWeight: FontWeight.w700,
                ),
              ),
          ],
        ),
      );
    }

    return AppImage(
      cover,
      fit: BoxFit.cover,
      borderRadius: BorderRadius.zero,
    );
  }
}
