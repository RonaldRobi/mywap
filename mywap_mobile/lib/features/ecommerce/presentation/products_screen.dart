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
import '../../../shared/widgets/app_back_button.dart';
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
    ref
        .read(productsProvider.notifier)
        .setFilters(search: _searchText, categoryId: _categoryId, sort: _sort);
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
        leading: const AppBackButton(),
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
            padding: const EdgeInsets.fromLTRB(
              Spacing.lg,
              Spacing.md,
              Spacing.lg,
              0,
            ),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: 'Cari produk...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon:
                    _searchText.isEmpty
                        ? null
                        : IconButton(
                          icon: const Icon(Icons.close),
                          onPressed: _clearSearch,
                        ),
              ),
            ),
          ),
          categoriesAsync.when(
            data:
                (categories) => _CategoryChips(
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
                    DropdownMenuItem(value: 'latest', child: Text('Terkini')),
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
              error:
                  (error, _) => ErrorRetry(
                    message:
                        error is ApiException
                            ? error.message
                            : 'Ralat tidak dijangka.',
                    onRetry:
                        () => ref.read(productsProvider.notifier).refresh(),
                  ),
              data:
                  (state) =>
                      state.items.isEmpty
                          ? RefreshIndicator(
                            onRefresh:
                                () async =>
                                    ref
                                        .read(productsProvider.notifier)
                                        .refresh(),
                            child: const SingleChildScrollView(
                              physics: AlwaysScrollableScrollPhysics(),
                              child: EmptyState(
                                icon: Icons.storefront_outlined,
                                message: 'Tiada produk dijumpai.',
                              ),
                            ),
                          )
                          : _ProductsGrid(
                            state: state,
                            scrollController: _scrollController,
                            onRefresh:
                                () async =>
                                    ref
                                        .read(productsProvider.notifier)
                                        .refresh(),
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
            padding: const EdgeInsets.only(
              top: Spacing.sm,
              bottom: Spacing.sm,
              right: Spacing.sm,
            ),
            child: ChoiceChip(
              label: const Text('Semua'),
              selected: selectedId == null,
              showCheckmark: false,
              onSelected: (_) => onSelected(null),
            ),
          ),
          for (final category in categories)
            Padding(
              padding: const EdgeInsets.only(
                top: Spacing.sm,
                bottom: Spacing.sm,
                right: Spacing.sm,
              ),
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
  const _ProductsGrid({
    required this.state,
    required this.scrollController,
    required this.onRefresh,
  });

  final ProductsState state;
  final ScrollController scrollController;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final cellWidth = _cellWidth(MediaQuery.sizeOf(context).width);
    final gridExtent = cellWidth + _cardFooterHeight;

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: CustomScrollView(
        controller: scrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverPadding(
            padding: const EdgeInsets.all(Spacing.md),
            sliver: SliverGrid(
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                mainAxisSpacing: Spacing.md,
                crossAxisSpacing: Spacing.md,
                // Fixed row extent = 1:1 image + footer → no overflow regardless
                // of text metrics, unlike a fragile childAspectRatio.
                mainAxisExtent: gridExtent,
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
      ),
    );
  }
}

/// Reserved footer height (info text + buy button) beneath the square image.
const double _cardFooterHeight = 148;

/// Cell width for a 2-column grid padded `Spacing.md` all round with
/// `Spacing.md` cross spacing.
double _cellWidth(double screenWidth) => (screenWidth - Spacing.md * 3) / 2;

class _ProductCard extends ConsumerWidget {
  const _ProductCard({required this.product});

  final Product product;

  void _buy(BuildContext context, WidgetRef ref) {
    final id = product.id;
    if (id == null) return;

    // Products with variations need an option chosen first → open the detail
    // page where the buyer picks before adding to cart.
    if (product.variationsCount != null && product.variationsCount! > 0) {
      context.push('/products/$id');
      return;
    }

    ref.read(cartProvider.notifier).add(
      CartItem(
        key: '$id:0',
        productId: id,
        name: product.name ?? 'Produk',
        image: product.displayImage,
        unitPrice: product.effectivePrice,
        quantity: 1,
      ),
    );
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(content: Text('Ditambah ke troli.')),
      );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final showMemberPrice = product.isMember && product.priceForMember != null;
    final price =
        showMemberPrice ? product.priceForMember! : (product.price ?? 0);
    final hasDiscount = showMemberPrice && product.price != null;
    final discountPercent =
        hasDiscount && product.price! > 0
            ? (((product.price! - price) / product.price!) * 100).round()
            : 0;

    return Material(
      color: AppColors.white,
      borderRadius: AppRadius.card,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap:
            product.id == null
                ? null
                : () => context.push('/products/${product.id}'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Shopee-style 1:1 thumbnail — the dominant visual element.
            AspectRatio(
              aspectRatio: 1,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  AppImage(
                    product.displayImage,
                    fit: BoxFit.cover,
                    borderRadius: BorderRadius.zero,
                  ),
                  if (hasDiscount && discountPercent > 0)
                    Positioned(
                      top: 0,
                      left: 0,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: Spacing.sm,
                          vertical: 3,
                        ),
                        decoration: const BoxDecoration(
                          color: AppColors.error,
                          borderRadius: BorderRadius.only(
                            bottomRight: Radius.circular(8),
                          ),
                        ),
                        child: Text(
                          '-$discountPercent%',
                          style: const TextStyle(
                            color: AppColors.white,
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(
                  Spacing.sm,
                  Spacing.xs,
                  Spacing.sm,
                  Spacing.sm,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            product.name ?? '-',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodyMedium?.copyWith(
                              fontWeight: FontWeight.w600,
                              height: 1.25,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            Formatters.currency(price),
                            style: theme.textTheme.titleSmall?.copyWith(
                              color: AppColors.error,
                              fontWeight: FontWeight.w800,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 2),
                          SizedBox(
                            height: 16,
                            child: hasDiscount
                                ? Row(
                                    children: [
                                      Text(
                                        Formatters.currency(product.price),
                                        style: theme.textTheme.bodySmall
                                            ?.copyWith(
                                                  color: AppColors.textTertiary,
                                                  decoration: TextDecoration
                                                      .lineThrough,
                                                  fontSize: 13,
                                                ),
                                      ),
                                      const SizedBox(width: 6),
                                      Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 5,
                                          vertical: 1,
                                        ),
                                        decoration: BoxDecoration(
                                          color: AppColors.softGreenSurface,
                                          borderRadius: BorderRadius.circular(
                                            4,
                                          ),
                                        ),
                                        child: Text(
                                          'Ahli',
                                          style: theme.textTheme.bodySmall
                                              ?.copyWith(
                                                    color:
                                                        AppColors.movementGreen,
                                                    fontSize: 13,
                                                    fontWeight:
                                                        FontWeight.w700,
                                                  ),
                                        ),
                                      ),
                                    ],
                                  )
                                : (product.category?.name != null
                                      ? Text(
                                          product.category!.name!,
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: theme.textTheme.bodySmall
                                              ?.copyWith(
                                                    color: AppColors
                                                        .textSecondary,
                                                    fontSize: 13,
                                                  ),
                                        )
                                      : const SizedBox.shrink()),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 6),
                    SizedBox(
                      width: double.infinity,
                      height: 34,
                      child: FilledButton(
                        onPressed:
                            product.id == null ? null : () => _buy(context, ref),
                        style: FilledButton.styleFrom(
                          padding: EdgeInsets.zero,
                          backgroundColor: AppColors.movementGreen,
                          foregroundColor: AppColors.white,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                          textStyle: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        child: const Text('Beli'),
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
    final cellWidth = _cellWidth(MediaQuery.sizeOf(context).width);
    return GridView.builder(
      padding: const EdgeInsets.all(Spacing.md),
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: Spacing.md,
        crossAxisSpacing: Spacing.md,
        mainAxisExtent: cellWidth + _cardFooterHeight,
      ),
      itemCount: 6,
      itemBuilder: (_, __) => const SkeletonBox(radius: 16),
    );
  }
}
