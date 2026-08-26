import 'package:flutter_riverpod/flutter_riverpod.dart';

/// A single cart line. `key` uniquely identifies the product + variation.
class CartItem {
  const CartItem({
    required this.key,
    required this.productId,
    required this.name,
    this.image,
    required this.unitPrice,
    this.variationOptionId,
    this.variationLabel,
    this.quantity = 1,
  });

  final String key;
  final int productId;
  final String name;
  final String? image;

  /// Effective unit price (member price when the user is eligible).
  final double unitPrice;
  final int? variationOptionId;
  final String? variationLabel;
  final int quantity;

  double get lineTotal => unitPrice * quantity;

  CartItem copyWith({int? quantity}) => CartItem(
        key: key,
        productId: productId,
        name: name,
        image: image,
        unitPrice: unitPrice,
        variationOptionId: variationOptionId,
        variationLabel: variationLabel,
        quantity: quantity ?? this.quantity,
      );
}

class CartState {
  const CartState({this.items = const []});

  final List<CartItem> items;

  double get subtotal => items.fold(0, (sum, item) => sum + item.lineTotal);
  int get totalCount => items.fold(0, (sum, item) => sum + item.quantity);
  bool get isEmpty => items.isEmpty;

  CartState copyWith({List<CartItem>? items}) =>
      CartState(items: items ?? this.items);
}

final cartProvider = NotifierProvider<CartNotifier, CartState>(
  CartNotifier.new,
);

class CartNotifier extends Notifier<CartState> {
  @override
  CartState build() => const CartState();

  /// Adds an item, merging with the existing line for the same product+variation.
  void add(CartItem item) {
    final index = state.items.indexWhere((i) => i.key == item.key);
    if (index >= 0) {
      final existing = state.items[index];
      final updated = existing.copyWith(
        quantity: existing.quantity + item.quantity,
      );
      state = CartState(items: [...state.items]..[index] = updated);
    } else {
      state = CartState(items: [...state.items, item]);
    }
  }

  /// Quantity ≤ 0 removes the line.
  void updateQuantity(String key, int quantity) {
    if (quantity <= 0) {
      remove(key);
      return;
    }
    state = CartState(
      items: state.items
          .map((i) => i.key == key ? i.copyWith(quantity: quantity) : i)
          .toList(growable: false),
    );
  }

  void remove(String key) {
    state = CartState(
      items: state.items.where((i) => i.key != key).toList(growable: false),
    );
  }

  void clear() => state = const CartState();
}
