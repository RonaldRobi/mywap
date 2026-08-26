import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/models/product.dart';
import '../data/product_repository.dart';

final productRepositoryProvider = Provider<ProductRepository>(
  (ref) => ProductRepository(ref.watch(apiClientProvider)),
);

final categoriesProvider = FutureProvider<List<Category>>(
  (ref) => ref.watch(productRepositoryProvider).categories(),
);

final productDetailProvider = FutureProvider.family<ProductDetail, int>(
  (ref, id) => ref.watch(productRepositoryProvider).productDetail(id),
);

/// Paginated, filterable product catalogue. Filters live inside the notifier;
/// the UI drives them via [ProductsNotifier.setFilters] and loads more pages
/// from the scroll listener.
final productsProvider =
    AsyncNotifierProvider<ProductsNotifier, ProductsState>(
  ProductsNotifier.new,
);

class ProductsState {
  const ProductsState({
    this.items = const [],
    this.page = 0,
    this.hasMore = false,
    this.isLoadingMore = false,
    this.search = '',
    this.categoryId,
    this.sort = 'latest',
  });

  final List<Product> items;
  final int page;
  final bool hasMore;
  final bool isLoadingMore;
  final String search;
  final int? categoryId;
  final String sort;

  ProductsState copyWith({
    List<Product>? items,
    int? page,
    bool? hasMore,
    bool? isLoadingMore,
    String? search,
    int? categoryId,
    String? sort,
  }) =>
      ProductsState(
        items: items ?? this.items,
        page: page ?? this.page,
        hasMore: hasMore ?? this.hasMore,
        isLoadingMore: isLoadingMore ?? this.isLoadingMore,
        search: search ?? this.search,
        categoryId: categoryId ?? this.categoryId,
        sort: sort ?? this.sort,
      );
}

class ProductsNotifier extends AsyncNotifier<ProductsState> {
  String _search = '';
  int? _categoryId;
  String _sort = 'latest';

  @override
  Future<ProductsState> build() => _fetch(1);

  Future<ProductsState> _fetch(int page) async {
    final result = await ref.read(productRepositoryProvider).products(
          page: page,
          search: _search.isEmpty ? null : _search,
          categoryId: _categoryId,
          sort: _sort,
        );
    return ProductsState(
      items: result.items,
      page: page,
      hasMore: result.hasMore,
      search: _search,
      categoryId: _categoryId,
      sort: _sort,
    );
  }

  Future<void> setFilters({String? search, int? categoryId, String? sort}) async {
    _search = search ?? _search;
    _categoryId = categoryId;
    _sort = sort ?? _sort;
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => _fetch(1));
  }

  Future<void> refresh() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => _fetch(1));
  }

  Future<void> loadMore() async {
    final current = state.valueOrNull;
    if (current == null || !current.hasMore || current.isLoadingMore) return;
    state = AsyncData(current.copyWith(isLoadingMore: true));
    try {
      final next = await _fetch(current.page + 1);
      state = AsyncData(
        current.copyWith(
          items: [...current.items, ...next.items],
          page: next.page,
          hasMore: next.hasMore,
          isLoadingMore: false,
        ),
      );
    } catch (_) {
      state = AsyncData(current.copyWith(isLoadingMore: false));
    }
  }
}
