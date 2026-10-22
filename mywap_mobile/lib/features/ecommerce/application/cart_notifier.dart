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
    this.stock,
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

  /// Maximum quantity available when the item was added to the cart.
  /// Null means stock is either unknown or not tracked.
  final int? stock;

  double get lineTotal => unitPrice * quantity;

  bool get isOutOfStock => stock != null && stock! <= 0;

  bool get isAtStockLimit => stock != null && quantity >= stock!;

  CartItem copyWith({int? quantity, int? stock}) => CartItem(
        key: key,
        productId: productId,
        name: name,
        image: image,
        unitPrice: unitPrice,
        variationOptionId: variationOptionId,
        variationLabel: variationLabel,
        quantity: quantity ?? this.quantity,
        stock: stock ?? this.stock,
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
  ///
  /// Returns false when the item is already sold out, and clamps the merged
  /// quantity to the stock captured when it was added. This prevents the cart
  /// from silently exceeding what the backend can actually fulfil.
  bool add(CartItem item) {
    if (item.isOutOfStock) return false;

    final index = state.items.indexWhere((i) => i.key == item.key);
    if (index >= 0) {
      final existing = state.items[index];
      final max = item.stock ?? existing.stock;
      var quantity = existing.quantity + item.quantity;
      if (max != null) quantity = quantity.clamp(1, max).toInt();
      final updated = existing.copyWith(quantity: quantity, stock: max);
      state = CartState(items: [...state.items]..[index] = updated);
    } else {
      final max = item.stock;
      final quantity =
          max == null ? item.quantity : item.quantity.clamp(1, max).toInt();
      state = CartState(
        items: [...state.items, item.copyWith(quantity: quantity)],
      );
    }
    return true;
  }

  /// Quantity ≤ 0 removes the line. Quantities above the available stock are
  /// clamped so the UI can never create an unfulfillable cart line.
  void updateQuantity(String key, int quantity) {
    if (quantity <= 0) {
      remove(key);
      return;
    }
    state = CartState(
      items: state.items.map((i) {
        if (i.key != key) return i;
        final max = i.stock;
        return i.copyWith(
          quantity: max == null ? quantity : quantity.clamp(1, max).toInt(),
        );
      }).toList(growable: false),
    );
  }

  /// Replaces the captured stock for a line after a checkout revalidation.
  /// A non-positive stock marks the line as sold out; a lower stock also
  /// clamps the quantity so the buyer sees exactly what can be fulfilled.
  void updateStock(String key, int? stock) {
    state = CartState(
      items: state.items.map((i) {
        if (i.key != key) return i;
        var quantity = i.quantity;
        if (stock != null && stock > 0 && quantity > stock) {
          quantity = stock;
        }
        return CartItem(
          key: i.key,
          productId: i.productId,
          name: i.name,
          image: i.image,
          unitPrice: i.unitPrice,
          variationOptionId: i.variationOptionId,
          variationLabel: i.variationLabel,
          quantity: quantity,
          stock: stock,
        );
      }).toList(growable: false),
    );
  }

  void remove(String key) {
    state = CartState(
      items: state.items.where((i) => i.key != key).toList(growable: false),
    );
  }

  void clear() => state = const CartState();
}
