import 'package:go_router/go_router.dart';

import 'cart_screen.dart';
import 'checkout_screen.dart';
import 'order_detail_screen.dart';
import 'orders_screen.dart';
import 'product_detail_screen.dart';
import 'products_screen.dart';

/// Routes owned by the ecommerce feature (Pasar / Pesanan).
final List<RouteBase> ecommerceRoutes = [
  GoRoute(
    path: '/products',
    builder: (_, __) => const ProductsScreen(),
  ),
  GoRoute(
    path: '/products/:id',
    builder: (_, state) => ProductDetailScreen(
      productId: int.parse(state.pathParameters['id']!),
    ),
  ),
  GoRoute(
    path: '/cart',
    builder: (_, __) => const CartScreen(),
  ),
  GoRoute(
    path: '/checkout',
    builder: (_, __) => const CheckoutScreen(),
  ),
  GoRoute(
    path: '/orders',
    builder: (_, __) => const OrdersScreen(),
  ),
  GoRoute(
    path: '/orders/:id',
    builder: (_, state) => OrderDetailScreen(
      orderId: int.parse(state.pathParameters['id']!),
    ),
  ),
];
