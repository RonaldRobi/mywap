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
      list =
          list
              .where(
                (p) =>
                    (p.name ?? '').toLowerCase().contains(search.toLowerCase()),
              )
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
    this.checkoutError,
  });

  final List<Order> orderList;
  final OrderDetail? detail;
  final CheckoutResult? checkoutResult;
  final PayResult payResult;
  final bool throwOnCheckout;
  final ApiException? checkoutError;

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
    if (checkoutError != null) throw checkoutError!;
    if (throwOnCheckout) throw const ApiException('Stok tidak mencukupi.');
    return checkoutResult ?? const CheckoutResult();
  }

  @override
  Future<PayResult> pay(int orderId) async => payResult;

  @override
  Future<Order> receive(int orderId) async {
    final current =
        detail?.order ?? (orderList.isNotEmpty ? orderList.first : null);
    if (current == null) return const Order();
    return Order(
      id: current.id,
      userId: current.userId,
      organisasiId: current.organisasiId,
      total: current.total,
      postageCost: current.postageCost,
      status: 'completed',
      trackingNo: current.trackingNo,
      shippingName: current.shippingName,
      shippingAddress: current.shippingAddress,
      shippingPostcode: current.shippingPostcode,
      shippingPhone: current.shippingPhone,
      courier: current.courier,
      createdAt: current.createdAt,
      updatedAt: current.updatedAt,
      items: current.items,
      payments: current.payments,
    );
  }
}

Product _product({
  int id = 1,
  String name = 'Produk',
  bool member = false,
  Category? category,
  int? stock,
  int variationsCount = 0,
}) => Product(
  id: id,
  name: name,
  price: 50,
  priceForMember: member ? 45 : null,
  isMember: member,
  category: category,
  stock: stock,
  variationsCount: variationsCount,
);

void main() {
  test('cart notifier adds, merges, updates and removes', () {
    final container = ProviderContainer();
    addTearDown(container.dispose);
    final notifier = container.read(cartProvider.notifier);

    const item = CartItem(
      key: '1:0',
      productId: 1,
      name: 'Baju',
      unitPrice: 10,
    );
    notifier.add(item);
    notifier.add(item);
    expect(container.read(cartProvider).totalCount, 2);

    notifier.updateQuantity('1:0', 5);
    expect(container.read(cartProvider).items.single.quantity, 5);
    expect(container.read(cartProvider).subtotal, 50);

    notifier.updateQuantity('1:0', 0);
    expect(container.read(cartProvider).isEmpty, isTrue);
  });

  test('cart notifier clamps quantities to available stock', () {
    final container = ProviderContainer();
    addTearDown(container.dispose);
    final notifier = container.read(cartProvider.notifier);

    const item = CartItem(
      key: '1:0',
      productId: 1,
      name: 'Baju',
      unitPrice: 10,
      quantity: 5,
      stock: 2,
    );
    expect(notifier.add(item), isTrue);
    expect(container.read(cartProvider).items.single.quantity, 2);

    notifier.updateQuantity('1:0', 10);
    expect(container.read(cartProvider).items.single.quantity, 2);

    const soldOut = CartItem(
      key: '2:0',
      productId: 2,
      name: 'Habis',
      unitPrice: 10,
      stock: 0,
    );
    expect(notifier.add(soldOut), isFalse);
    expect(container.read(cartProvider).items.length, 1);
  });

  testWidgets('ProductsScreen marks sold-out products and disables buy', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(800, 1500);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          productRepositoryProvider.overrideWithValue(
            _FakeProductRepository(
              productList: [_product(id: 3, name: 'Baju Habis', stock: 0)],
            ),
          ),
        ],
        child: MaterialApp(home: const ProductsScreen(), theme: AppTheme.light),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Baju Habis'), findsOneWidget);
    expect(find.text('Habis Dijual'), findsNWidgets(2));
    final button = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Habis Dijual'),
    );
    expect(button.onPressed, isNull);
  });

  testWidgets('ProductDetailScreen disables buying a sold-out product', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(800, 1600);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final detail = ProductDetail(
      product: const Product(id: 9, name: 'Produk Habis', price: 100, stock: 0),
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          productRepositoryProvider.overrideWithValue(
            _FakeProductRepository(detail: detail),
          ),
        ],
        child: MaterialApp(
          home: const ProductDetailScreen(productId: 9),
          theme: AppTheme.light,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Produk ini tiada stok.'), findsOneWidget);
    expect(find.text('Habis Dijual'), findsNWidgets(2));
    final buyButton = tester.widget<FilledButton>(
      find.byWidgetPredicate((widget) => widget is FilledButton),
    );
    expect(buyButton.onPressed, isNull);
    final addButton = tester.widget<OutlinedButton>(
      find.byWidgetPredicate((widget) => widget is OutlinedButton),
    );
    expect(addButton.onPressed, isNull);
  });

  testWidgets('ProductDetailScreen disables sold-out variation options', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(800, 1600);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final detail = ProductDetail(
      product: const Product(
        id: 10,
        name: 'Baju Variasi',
        price: 100,
        stock: 10,
        variations: [
          ProductVariation(
            id: 1,
            name: 'Saiz',
            required: true,
            options: [
              ProductVariationOption(id: 1, name: 'M', stock: 0),
              ProductVariationOption(id: 2, name: 'L', stock: 4),
            ],
          ),
        ],
      ),
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          productRepositoryProvider.overrideWithValue(
            _FakeProductRepository(detail: detail),
          ),
        ],
        child: MaterialApp(
          home: const ProductDetailScreen(productId: 10),
          theme: AppTheme.light,
        ),
      ),
    );
    await tester.pumpAndSettle();

    final soldOutChip = tester.widget<ChoiceChip>(
      find.widgetWithText(ChoiceChip, 'M • Habis'),
    );
    expect(soldOutChip.onSelected, isNull);

    final availableChip = tester.widget<ChoiceChip>(
      find.widgetWithText(ChoiceChip, 'L'),
    );
    expect(availableChip.selected, isTrue);
    expect(find.text('Baki 4'), findsOneWidget);
  });

  testWidgets('CartScreen caps quantity at available stock', (tester) async {
    final container = ProviderContainer(
      overrides: [cartProvider.overrideWith(CartNotifier.new)],
    );
    addTearDown(container.dispose);
    container
        .read(cartProvider.notifier)
        .add(
          const CartItem(
            key: '1:0',
            productId: 1,
            name: 'Baju Melayu',
            unitPrice: 50,
            quantity: 2,
            stock: 2,
          ),
        );

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp(home: const CartScreen(), theme: AppTheme.light),
      ),
    );

    expect(find.text('Stok maksimum dicapai'), findsOneWidget);
    final plusButton = tester.widget<IconButton>(
      find.widgetWithIcon(IconButton, Icons.add),
    );
    expect(plusButton.onPressed, isNull);
  });

  testWidgets('ProductsScreen renders product cards with member badge', (
    tester,
  ) async {
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
    expect(find.text('Ahli'), findsOneWidget);
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
            _FakeProductRepository(
              productList: [
                _product(id: 1, name: 'Baju Melayu'),
                _product(id: 2, name: 'Jubah'),
              ],
            ),
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

  testWidgets('ProductDetailScreen renders variations and actions', (
    tester,
  ) async {
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
    container
        .read(cartProvider.notifier)
        .add(
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

  testWidgets('CheckoutScreen submits order, clears cart and navigates', (
    tester,
  ) async {
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
    container
        .read(cartProvider.notifier)
        .add(
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
    await tester.enterText(find.widgetWithText(TextFormField, 'Nama'), 'Ali');
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
    container
        .read(cartProvider.notifier)
        .add(
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

    await tester.enterText(find.widgetWithText(TextFormField, 'Nama'), 'Ali');
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Telefon'),
      '0123456789',
    );
    await tester.tap(find.text('Hantar Pesanan'));
    await tester.pumpAndSettle();

    expect(find.text('Stok tidak mencukupi.'), findsOneWidget);
  });

  testWidgets('CheckoutScreen refreshes cart stock after a 422', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(800, 1800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final container = ProviderContainer(
      overrides: [
        productRepositoryProvider.overrideWithValue(
          _FakeProductRepository(
            detail: const ProductDetail(
              product: Product(id: 1, name: 'Baju Melayu', price: 50, stock: 1),
            ),
          ),
        ),
        orderRepositoryProvider.overrideWithValue(
          _FakeOrderRepository(
            checkoutError: const ApiException(
              'Stok tidak mencukupi untuk Baju Melayu (tinggal 1).',
              statusCode: 422,
            ),
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
            stock: 5,
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

    expect(
      find.text('Stok tidak mencukupi untuk Baju Melayu (tinggal 1).'),
      findsOneWidget,
    );
    expect(container.read(cartProvider).items.single.quantity, 1);
    expect(container.read(cartProvider).items.single.stock, 1);
  });

  testWidgets('OrdersScreen renders order cards with status and total', (
    tester,
  ) async {
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

  testWidgets('OrderDetailScreen shows items and pays pending order', (
    tester,
  ) async {
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
