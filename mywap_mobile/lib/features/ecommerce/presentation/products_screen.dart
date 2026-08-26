import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/cart_notifier.dart';
import '../application/product_providers.dart';
import '../data/models/product.dart';

class ProductsScreen extends ConsumerStatefulWidget {
  const ProductsScreen({super.key});

  @override
  ConsumerState<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends ConsumerState<ProductsScreen> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();
  Timer? _debounce;
  String _searchText = '';
  int? _categoryId;
  String _sort = 'latest';

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 300) {
      ref.read(productsProvider.notifier).loadMore();
    }
  }

  void _applyFilters() {
    ref.read(productsProvider.notifier).setFilters(
          search: _searchText,
          categoryId: _categoryId,
          sort: _sort,
        );
  }

  void _onSearchChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      if (!mounted) return;
      setState(() => _searchText = value);
      _applyFilters();
    });
  }

  void _clearSearch() {
    _searchController.clear();
    setState(() => _searchText = '');
    _applyFilters();
  }

  @override
  Widget build(BuildContext context) {
    final productsAsync = ref.watch(productsProvider);
    final categoriesAsync = ref.watch(categoriesProvider);
    final cartCount = ref.watch(cartProvider.select((c) => c.totalCount));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pasar'),
        actions: [
          IconButton(
            tooltip: 'Troli',
            onPressed: () => context.push('/cart'),
            icon: Badge(
              label: Text('$cartCount'),
              isLabelVisible: cartCount > 0,
              child: const Icon(Icons.shopping_cart_outlined),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(Spacing.lg, Spacing.md, Spacing.lg, 0),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: 'Cari produk...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchText.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: _clearSearch,
                      ),
              ),
            ),
          ),
          categoriesAsync.when(
            data: (categories) => _CategoryChips(
              categories: categories,
              selectedId: _categoryId,
              onSelected: (id) {
                setState(() => _categoryId = id);
                _applyFilters();
              },
            ),
            loading: () => const SizedBox(height: 52),
            error: (_, __) => const SizedBox(height: 52),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
            child: Row(
              children: [
                const Icon(
                  Icons.sort,
                  size: 20,
                  color: AppColors.textSecondary,
                ),
                const SizedBox(width: Spacing.sm),
                const Text('Susun:'),
                const SizedBox(width: Spacing.sm),
                DropdownButton<String>(
                  value: _sort,
                  underline: const SizedBox.shrink(),
                  borderRadius: AppRadius.md,
                  items: const [
                    DropdownMenuItem(
                      value: 'latest',
                      child: Text('Terkini'),
                    ),
                    DropdownMenuItem(
                      value: 'price_low',
                      child: Text('Harga Rendah'),
                    ),
                    DropdownMenuItem(
                      value: 'price_high',
                      child: Text('Harga Tinggi'),
                    ),
                  ],
                  onChanged: (value) {
                    if (value == null) return;
                    setState(() => _sort = value);
                    _applyFilters();
                  },
                ),
              ],
            ),
          ),
          const SizedBox(height: Spacing.sm),
          Expanded(
            child: productsAsync.when(
              loading: () => const _ProductsSkeleton(),
              error: (error, _) => ErrorRetry(
                message:
                    error is ApiException ? error.message : 'Ralat tidak dijangka.',
                onRetry: () => ref.read(productsProvider.notifier).refresh(),
              ),
              data: (state) => state.items.isEmpty
                  ? const EmptyState(
                      icon: Icons.storefront_outlined,
                      message: 'Tiada produk dijumpai.',
                    )
                  : _ProductsGrid(
                      state: state,
                      scrollController: _scrollController,
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CategoryChips extends StatelessWidget {
  const _CategoryChips({
    required this.categories,
    required this.selectedId,
    required this.onSelected,
  });

  final List<Category> categories;
  final int? selectedId;
  final ValueChanged<int?> onSelected;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 52,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
        children: [
          Padding(
            padding: const EdgeInsets.only(top: Spacing.sm, bottom: Spacing.sm, right: Spacing.sm),
            child: ChoiceChip(
              label: const Text('Semua'),
              selected: selectedId == null,
              showCheckmark: false,
              onSelected: (_) => onSelected(null),
            ),
          ),
          for (final category in categories)
            Padding(
              padding: const EdgeInsets.only(top: Spacing.sm, bottom: Spacing.sm, right: Spacing.sm),
              child: ChoiceChip(
                label: Text(category.name ?? '-'),
                selected: selectedId == category.id,
                showCheckmark: false,
                onSelected: (_) => onSelected(category.id),
              ),
            ),
        ],
      ),
    );
  }
}

class _ProductsGrid extends StatelessWidget {
  const _ProductsGrid({required this.state, required this.scrollController});

  final ProductsState state;
  final ScrollController scrollController;

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      controller: scrollController,
      slivers: [
        SliverPadding(
          padding: const EdgeInsets.all(Spacing.lg),
          sliver: SliverGrid(
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: Spacing.md,
              crossAxisSpacing: Spacing.md,
              mainAxisExtent: 264,
            ),
            delegate: SliverChildBuilderDelegate(
              (context, index) => _ProductCard(product: state.items[index]),
              childCount: state.items.length,
            ),
          ),
        ),
        if (state.isLoadingMore)
          const SliverToBoxAdapter(
            child: Padding(
              padding: EdgeInsets.only(bottom: Spacing.xl),
              child: Center(
                child: SizedBox(
                  width: 24,
                  height: 24,
                  child: CircularProgressIndicator(strokeWidth: 2.5),
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final showBadge = product.isMember && product.priceForMember != null;
    final price = showBadge ? product.priceForMember! : (product.price ?? 0);

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: product.id == null
            ? null
            : () => context.push('/products/${product.id}'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AppImage(
              product.displayImage,
              height: 150,
              width: double.infinity,
              fit: BoxFit.cover,
              borderRadius: BorderRadius.zero,
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(Spacing.sm),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      product.name ?? '-',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleSmall,
                    ),
                    const Spacer(),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            Formatters.currency(price),
                            style: theme.textTheme.titleSmall?.copyWith(
                              color: AppColors.movementGreen,
                              fontWeight: FontWeight.w700,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        if (showBadge)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 6,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.movementGreen,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text(
                              'Harga Ahli',
                              style: TextStyle(
                                color: AppColors.white,
                                fontSize: 9,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                      ],
                    ),
                    if (showBadge && product.price != null)
                      Text(
                        Formatters.currency(product.price),
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                          decoration: TextDecoration.lineThrough,
                        ),
                      )
                    else if (product.category?.name != null)
                      Text(
                        product.category!.name!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProductsSkeleton extends StatelessWidget {
  const _ProductsSkeleton();

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: Spacing.md,
        crossAxisSpacing: Spacing.md,
        mainAxisExtent: 264,
      ),
      itemCount: 6,
      itemBuilder: (_, __) => const SkeletonBox(radius: 16),
    );
  }
}
