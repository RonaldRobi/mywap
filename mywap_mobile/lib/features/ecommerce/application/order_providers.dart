import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/models/order.dart';
import '../data/order_repository.dart';

final orderRepositoryProvider = Provider<OrderRepository>(
  (ref) => OrderRepository(ref.watch(apiClientProvider)),
);

/// Paginated order list (infinite scroll).
final ordersProvider = AsyncNotifierProvider<OrdersNotifier, OrdersState>(
  OrdersNotifier.new,
);

final orderDetailProvider = FutureProvider.family<OrderDetail, int>(
  (ref, id) => ref.watch(orderRepositoryProvider).orderDetail(id),
);

class OrdersState {
  const OrdersState({
    this.items = const [],
    this.page = 0,
    this.hasMore = false,
    this.isLoadingMore = false,
  });

  final List<Order> items;
  final int page;
  final bool hasMore;
  final bool isLoadingMore;

  OrdersState copyWith({
    List<Order>? items,
    int? page,
    bool? hasMore,
    bool? isLoadingMore,
  }) =>
      OrdersState(
        items: items ?? this.items,
        page: page ?? this.page,
        hasMore: hasMore ?? this.hasMore,
        isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      );
}

class OrdersNotifier extends AsyncNotifier<OrdersState> {
  @override
  Future<OrdersState> build() => _fetch(1);

  Future<OrdersState> _fetch(int page) async {
    final result = await ref.read(orderRepositoryProvider).orders(page: page);
    return OrdersState(items: result.items, page: page, hasMore: result.hasMore);
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
