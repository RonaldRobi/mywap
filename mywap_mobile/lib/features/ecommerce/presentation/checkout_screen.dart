import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/payment/payment_webview_screen.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/loading_overlay.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../application/cart_notifier.dart';
import '../application/order_providers.dart';
import '../application/product_providers.dart';

class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _addressController = TextEditingController();
  final _postcodeController = TextEditingController();
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _addressController.dispose();
    _postcodeController.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> get _productsPayload {
    final cart = ref.read(cartProvider);
    return cart.items
        .map(
          (item) => {
            'id': item.productId,
            'quantity': item.quantity,
            if (item.variationOptionId != null)
              'variation_option_id': item.variationOptionId,
            if (item.variationLabel != null && item.variationLabel!.isNotEmpty)
              'variation_snapshot': item.variationLabel,
          },
        )
        .toList(growable: false);
  }

  /// Re-reads each cart line's current stock after the server rejects an
  /// order. This makes the checkout UI show the real sold-out/remaining state
  /// instead of keeping a stale quantity the buyer cannot actually order.
  Future<void> _refreshCartStock() async {
    final items = List.of(ref.read(cartProvider).items);
    final updates = await Future.wait<MapEntry<String, int?>?>(
      items.map((item) async {
        try {
          final detail = await ref
              .read(productRepositoryProvider)
              .productDetail(item.productId);
          final product = detail.product;
          if (product == null) return MapEntry(item.key, 0);

          int? stock = product.stock;
          if (item.variationOptionId != null) {
            for (final variation in product.variations) {
              for (final option in variation.options) {
                if (option.id == item.variationOptionId) {
                  stock = option.stock ?? product.stock;
                }
              }
            }
          }
          return MapEntry(item.key, stock);
        } catch (_) {
          return null;
        }
      }),
    );

    if (!mounted) return;
    for (final update in updates) {
      if (update != null) {
        ref.read(cartProvider.notifier).updateStock(update.key, update.value);
      }
    }
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final cart = ref.read(cartProvider);
    if (cart.isEmpty) return;

    if (cart.items.any((item) => item.isOutOfStock)) {
      setState(() => _error = context.l10n.ecomOutOfStockBody);
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final result = await ref
          .read(orderRepositoryProvider)
          .checkout(
            products: _productsPayload,
            shippingName: _nameController.text,
            shippingPhone: _phoneController.text,
            shippingAddress: _addressController.text,
            shippingPostcode: _postcodeController.text,
          );
      if (!mounted) return;

      ref.read(cartProvider.notifier).clear();
      ref.invalidate(ordersProvider);
      // Stock was decremented on the server; drop cached catalogues so the
      // product list/detail immediately show the updated sold-out state.
      ref.invalidate(productsProvider);
      ref.invalidate(productDetailProvider);

      final paymentUrl = result.paymentUrl;
      if (paymentUrl != null && paymentUrl.isNotEmpty) {
        final paid = await Navigator.of(context).push<bool>(
          MaterialPageRoute(
            builder: (_) => PaymentWebviewScreen(paymentUrl: paymentUrl),
          ),
        );
        if (!mounted) return;
        final l10n = context.l10n;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              paid == true
                  ? l10n.ecomOrderPaidSuccess
                  : l10n.ecomOrderCreatedAwaitPayment,
            ),
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.l10n.ecomOrderCreatedSuccess)),
        );
      }
      context.go('/orders');
    } on ApiException catch (e) {
      if (!mounted) return;
      // A 422 here usually means stock changed while the buyer was checking
      // out. Refresh the catalogue so the cart/detail screens reflect reality.
      if (e.statusCode == 422) {
        await _refreshCartStock();
        if (!mounted) return;
        ref.invalidate(productsProvider);
        ref.invalidate(productDetailProvider);
      }
      setState(() {
        _error = e.message;
        _submitting = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = context.l10n.unexpectedErrorRetry;
        _submitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = ref.watch(cartProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(fallback: '/cart'),
        title: Text(context.l10n.ecomCheckoutTitle),
      ),
      body: Stack(
        children: [
          cart.isEmpty ? const _EmptyCheckout() : _buildCheckoutForm(cart),
          if (_submitting)
            LoadingOverlay(message: context.l10n.ecomProcessingOrder),
        ],
      ),
    );
  }

  Widget _buildCheckoutForm(CartState cart) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(Spacing.lg),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    l10n.ecomShippingInfo,
                    style: theme.textTheme.titleMedium,
                  ),
                  const SizedBox(height: Spacing.md),
                  TextFormField(
                    controller: _nameController,
                    textCapitalization: TextCapitalization.words,
                    decoration: InputDecoration(
                      labelText: l10n.nameLabel,
                      hintText: l10n.ecomFullNameHint,
                      prefixIcon: const Icon(Icons.person_outline),
                    ),
                    validator:
                        (value) =>
                            (value == null || value.trim().isEmpty)
                                ? l10n.ecomNameRequired
                                : null,
                  ),
                  const SizedBox(height: Spacing.md),
                  TextFormField(
                    controller: _phoneController,
                    keyboardType: TextInputType.phone,
                    decoration: InputDecoration(
                      labelText: l10n.phoneLabel,
                      hintText: '012-3456789',
                      prefixIcon: const Icon(Icons.phone_outlined),
                    ),
                    validator:
                        (value) =>
                            (value == null || value.trim().isEmpty)
                                ? l10n.ecomPhoneRequired
                                : null,
                  ),
                  const SizedBox(height: Spacing.md),
                  TextFormField(
                    controller: _addressController,
                    textCapitalization: TextCapitalization.sentences,
                    maxLines: 2,
                    decoration: InputDecoration(
                      labelText: l10n.ecomAddressOptional,
                      prefixIcon: const Icon(Icons.home_outlined),
                    ),
                  ),
                  const SizedBox(height: Spacing.md),
                  TextFormField(
                    controller: _postcodeController,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: l10n.ecomPostcodeOptional,
                      prefixIcon: const Icon(Icons.pin_drop_outlined),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: Spacing.md),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(Spacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l10n.ecomOrderSummary, style: theme.textTheme.titleMedium),
                const SizedBox(height: Spacing.sm),
                for (final item in cart.items) _SummaryRow(item: item),
                const Divider(height: Spacing.xl),
                Row(
                  children: [
                    Text(l10n.ecomTotal, style: theme.textTheme.titleMedium),
                    const Spacer(),
                    Text(
                      Formatters.currency(cart.subtotal),
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        if (_error != null) ...[
          const SizedBox(height: Spacing.md),
          Text(
            _error!,
            style: const TextStyle(color: AppColors.error),
            textAlign: TextAlign.center,
          ),
        ],
        const SizedBox(height: Spacing.lg),
        FilledButton.icon(
          onPressed: _submitting ? null : _submit,
          icon: const Icon(Icons.receipt_long_outlined),
          label: Text(l10n.ecomSubmitOrder),
        ),
        const SizedBox(height: Spacing.xl),
      ],
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({required this.item});

  final CartItem item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: Spacing.xs),
      child: Row(
        children: [
          AppImage(item.image, width: 44, height: 44),
          const SizedBox(width: Spacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium,
                ),
                if (item.variationLabel != null)
                  Text(
                    item.variationLabel!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
              ],
            ),
          ),
          Text(
            '${item.quantity} x ${Formatters.currency(item.unitPrice)}',
            style: theme.textTheme.bodySmall,
          ),
          const SizedBox(width: Spacing.sm),
          Text(
            Formatters.currency(item.lineTotal),
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyCheckout extends StatelessWidget {
  const _EmptyCheckout();

  @override
  Widget build(BuildContext context) {
    return Center(child: Text(context.l10n.ecomCartEmpty));
  }
}
