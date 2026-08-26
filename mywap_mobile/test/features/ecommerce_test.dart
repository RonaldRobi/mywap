import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:mywap_mobile/core/network/api_exception.dart';
import 'package:mywap_mobile/features/ecommerce/application/cart_notifier.dart';
import 'package:mywap_mobile/features/ecommerce/application/order_providers.dart';
import 'package:mywap_mobile/features/ecommerce/application/product_providers.dart';
import 'package:mywap_mobile/features/ecommerce/data/models/order.dart';
import 'package:mywap_mobile/features/ecommerce/data/models/product.dart';
import 'package:mywap_mobile/features/ecommerce/data/order_repository.dart';
import 'package:mywap_mobile/features/ecommerce/data/paged_result.dart';
import 'package:mywap_mobile/features/ecommerce/data/product_repository.dart';
import 'package:mywap_mobile/features/ecommerce/presentation/cart_screen.dart';
import 'package:mywap_mobile/features/ecommerce/presentation/checkout_screen.dart';
import 'package:mywap_mobile/features/ecommerce/presentation/order_detail_screen.dart';
import 'package:mywap_mobile/features/ecommerce/presentation/orders_screen.dart';
import 'package:mywap_mobile/features/ecommerce/presentation/product_detail_screen.dart';
import 'package:mywap_mobile/features/ecommerce/presentation/products_screen.dart';
import 'package:mywap_mobile/features/ecommerce/presentation/routes.dart';
import 'package:mywap_mobile/shared/theme/app_theme.dart';

class _FakeProductRepository implements ProductRepository {
  _FakeProductRepository({
    this.productList = const [],
    this.categoryList = const [],
    this.detail,
    this.throwOnList = false,
  });

  final List<Product> productList;
  final List<Category> categoryList;
  final ProductDetail? detail;
  final bool throwOnList;

  @override
  Future<PagedResult<Product>> products({
    int page = 1,
    String? search,
    int? categoryId,
    String sort = 'latest',
  }) async {
    if (throwOnList) throw const ApiException('Ralat rangkaian.');
    var list = productList;
    if (search != null && search.trim().isNotEmpty) {
      list = list
          .where((p) =>
              (p.name ?? '').toLowerCase().contains(search.toLowerCase()))
          .toList();
    }
    if (categoryId != null) {
      list = list.where((p) => p.categoryId == categoryId).toList();
    }
    return PagedResult(items: list, hasMore: false);
  }

  @override
  Future<ProductDetail> productDetail(int id) async {
    return detail ?? const ProductDetail();
  }

  @override
  Future<List<Category>> categories() async => categoryList;
}

class _FakeOrderRepository implements OrderRepository {
  _FakeOrderRepository({
    this.orderList = const [],
    this.detail,
    this.checkoutResult,
    this.payResult = const PayResult(),
    this.throwOnCheckout = false,
  });

  final List<Order> orderList;
  final OrderDetail? detail;
  final CheckoutResult? checkoutResult;
  final PayResult payResult;
  final bool throwOnCheckout;

  @override
  Future<PagedResult<Order>> orders({int page = 1}) async =>
      PagedResult(items: orderList, hasMore: false);

  @override
  Future<OrderDetail> orderDetail(int id) async =>
      detail ??
      OrderDetail(order: orderList.isNotEmpty ? orderList.first : null);

  @override
  Future<CheckoutResult> checkout({
    required List<Map<String, dynamic>> products,
    String? shippingName,
    String? shippingPhone,
    String? shippingAddress,
    String? shippingPostcode,
  }) async {
    if (throwOnCheckout) throw const ApiException('Stok tidak mencukupi.');
    return checkoutResult ?? const CheckoutResult();
  }

  @override
  Future<PayResult> pay(int orderId) async => payResult;
}

Product _product({
  int id = 1,
  String name = 'Produk',
  bool member = false,
  Category? category,
}) =>
    Product(
      id: id,
      name: name,
      price: 50,
      priceForMember: member ? 45 : null,
      isMember: member,
      category: category,
    );

void main() {
  test('cart notifier adds, merges, updates and removes', () {
    final container = ProviderContainer();
    addTearDown(container.dispose);
    final notifier = container.read(cartProvider.notifier);

    const item = CartItem(key: '1:0', productId: 1, name: 'Baju', unitPrice: 10);
    notifier.add(item);
    notifier.add(item);
    expect(container.read(cartProvider).totalCount, 2);

    notifier.updateQuantity('1:0', 5);
    expect(container.read(cartProvider).items.single.quantity, 5);
    expect(container.read(cartProvider).subtotal, 50);

    notifier.updateQuantity('1:0', 0);
    expect(container.read(cartProvider).isEmpty, isTrue);
  });

  testWidgets('ProductsScreen renders product cards with member badge',
      (tester) async {
    tester.view.physicalSize = const Size(800, 1500);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          productRepositoryProvider.overrideWithValue(
            _FakeProductRepository(
              categoryList: const [Category(id: 1, name: 'Pakaian')],
              productList: [
                _product(
                  id: 1,
                  name: 'Baju Melayu',
                  member: true,
                  category: const Category(id: 1, name: 'Pakaian'),
                ),
              ],
            ),
          ),
        ],
        child: MaterialApp(home: const ProductsScreen(), theme: AppTheme.light),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Baju Melayu'), findsOneWidget);
    expect(find.text('Harga Ahli'), findsOneWidget);
    expect(find.text('RM45'), findsOneWidget);
  });

  testWidgets('ProductsScreen search filters the list', (tester) async {
    tester.view.physicalSize = const Size(800, 1500);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          productRepositoryProvider.overrideWithValue(
            _FakeProductRepository(productList: [
              _product(id: 1, name: 'Baju Melayu'),
              _product(id: 2, name: 'Jubah'),
            ]),
          ),
        ],
        child: MaterialApp(home: const ProductsScreen(), theme: AppTheme.light),
      ),
    );
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'Baju');
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pumpAndSettle();

    expect(find.text('Baju Melayu'), findsOneWidget);
    expect(find.text('Jubah'), findsNothing);
  });

  testWidgets('ProductsScreen shows error state with retry', (tester) async {
    tester.view.physicalSize = const Size(800, 1500);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          productRepositoryProvider.overrideWithValue(
            _FakeProductRepository(throwOnList: true),
          ),
        ],
        child: MaterialApp(home: const ProductsScreen(), theme: AppTheme.light),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Cuba Semula'), findsOneWidget);
    expect(find.text('Ralat rangkaian.'), findsOneWidget);
  });

  testWidgets('ProductDetailScreen renders variations and actions',
      (tester) async {
    tester.view.physicalSize = const Size(800, 1600);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final detail = ProductDetail(
      product: const Product(
        id: 1,
        name: 'Baju Melayu',
        price: 100,
        memberPrice: 90,
        priceForMember: 90,
        isMember: true,
        description: 'Baju yang selesa.',
        variations: [
          ProductVariation(
            id: 1,
            name: 'Saiz',
            options: [
              ProductVariationOption(id: 1, name: 'M'),
              ProductVariationOption(id: 2, name: 'L'),
            ],
          ),
        ],
      ),
      relatedProducts: [_product(id: 2, name: 'Jubah')],
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          productRepositoryProvider.overrideWithValue(
            _FakeProductRepository(detail: detail),
          ),
        ],
        child: MaterialApp(
          home: const ProductDetailScreen(productId: 1),
          theme: AppTheme.light,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Baju Melayu'), findsOneWidget);
    expect(find.text('Harga Ahli'), findsOneWidget);
    expect(find.text('Saiz'), findsOneWidget);
    expect(find.text('M'), findsOneWidget);
    expect(find.text('L'), findsOneWidget);
    expect(find.text('Masukkan ke Troli'), findsOneWidget);
    expect(find.text('Beli Sekarang'), findsOneWidget);
  });

  testWidgets('CartScreen shows items and total', (tester) async {
    final container = ProviderContainer(
      overrides: [cartProvider.overrideWith(CartNotifier.new)],
    );
    addTearDown(container.dispose);
    container.read(cartProvider.notifier).add(
          const CartItem(
            key: '1:0',
            productId: 1,
            name: 'Baju Melayu',
            unitPrice: 50,
            quantity: 2,
          ),
        );

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp(home: const CartScreen(), theme: AppTheme.light),
      ),
    );

    expect(find.text('Baju Melayu'), findsOneWidget);
    expect(find.text('RM100'), findsNWidgets(2));
    expect(find.text('Teruskan ke Checkout'), findsOneWidget);
  });

  testWidgets('CheckoutScreen submits order, clears cart and navigates',
      (tester) async {
    tester.view.physicalSize = const Size(800, 1800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final container = ProviderContainer(
      overrides: [
        productRepositoryProvider.overrideWithValue(_FakeProductRepository()),
        orderRepositoryProvider.overrideWithValue(
          _FakeOrderRepository(
            checkoutResult: const CheckoutResult(paymentUrl: null),
          ),
        ),
        cartProvider.overrideWith(CartNotifier.new),
      ],
    );
    addTearDown(container.dispose);
    container.read(cartProvider.notifier).add(
          const CartItem(
            key: '1:0',
            productId: 1,
            name: 'Baju Melayu',
            unitPrice: 50,
            quantity: 2,
          ),
        );

    final router = GoRouter(routes: ecommerceRoutes);

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp.router(routerConfig: router, theme: AppTheme.light),
      ),
    );
    router.go('/checkout');
    await tester.pumpAndSettle();

    expect(find.text('Baju Melayu'), findsOneWidget);
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Nama'),
      'Ali',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Telefon'),
      '0123456789',
    );
    await tester.tap(find.text('Hantar Pesanan'));
    await tester.pumpAndSettle();

    expect(container.read(cartProvider).isEmpty, isTrue);
    expect(find.text('Pesanan berjaya dibuat!'), findsOneWidget);
  });

  testWidgets('CheckoutScreen shows backend error on submit', (tester) async {
    tester.view.physicalSize = const Size(800, 1800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final container = ProviderContainer(
      overrides: [
        orderRepositoryProvider.overrideWithValue(
          _FakeOrderRepository(throwOnCheckout: true),
        ),
        cartProvider.overrideWith(CartNotifier.new),
      ],
    );
    addTearDown(container.dispose);
    container.read(cartProvider.notifier).add(
          const CartItem(
            key: '1:0',
            productId: 1,
            name: 'Baju Melayu',
            unitPrice: 50,
          ),
        );

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp(home: const CheckoutScreen(), theme: AppTheme.light),
      ),
    );

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Nama'),
      'Ali',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Telefon'),
      '0123456789',
    );
    await tester.tap(find.text('Hantar Pesanan'));
    await tester.pumpAndSettle();

    expect(find.text('Stok tidak mencukupi.'), findsOneWidget);
  });

  testWidgets('OrdersScreen renders order cards with status and total',
      (tester) async {
    final container = ProviderContainer(
      overrides: [
        orderRepositoryProvider.overrideWithValue(
          _FakeOrderRepository(
            orderList: const [
              Order(
                id: 1,
                status: 'pending',
                total: 100,
                postageCost: 5,
                createdAt: '2026-08-25T10:00:00Z',
                items: [OrderItem(productId: 1, quantity: 2, price: 50)],
              ),
            ],
          ),
        ),
      ],
    );
    addTearDown(container.dispose);

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp(home: const OrdersScreen(), theme: AppTheme.light),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Pesanan #1'), findsOneWidget);
    expect(find.text('Menunggu Pembayaran'), findsOneWidget);
    expect(find.text('Jumlah: RM105'), findsOneWidget);
  });

  testWidgets('OrderDetailScreen shows items and pays pending order',
      (tester) async {
    final order = const Order(
      id: 7,
      status: 'pending',
      total: 200,
      postageCost: 10,
      shippingName: 'Ali',
      shippingPhone: '0123456789',
      items: [
        OrderItem(
          productId: 1,
          product: OrderItemProduct(id: 1, name: 'Baju Melayu', price: 100),
          quantity: 2,
          price: 100,
        ),
      ],
    );

    final container = ProviderContainer(
      overrides: [
        orderRepositoryProvider.overrideWithValue(
          _FakeOrderRepository(
            detail: OrderDetail(order: order),
            payResult: const PayResult(status: 'success'),
          ),
        ),
      ],
    );
    addTearDown(container.dispose);

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp(
          home: const OrderDetailScreen(orderId: 7),
          theme: AppTheme.light,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Baju Melayu'), findsOneWidget);
    expect(find.text('Bayar'), findsOneWidget);

    await tester.tap(find.text('Bayar'));
    await tester.pumpAndSettle();

    expect(find.text('Pembayaran berjaya. Terima kasih!'), findsOneWidget);
  });
}
