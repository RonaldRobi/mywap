import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/payment/payment_webview_screen.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/loading_overlay.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../auth/application/auth_controller.dart';
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
  final _emailController = TextEditingController();
  final _addressController = TextEditingController();
  final _postcodeController = TextEditingController();
  bool _submitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    // Pra-isi maklumat ahli jika sudah log masuk; tetamu isi sendiri.
    final user = ref.read(currentUserProvider);
    if (user != null) {
      _nameController.text = user.name ?? '';
      _phoneController.text = user.phone ?? '';
      _emailController.text = user.email ?? '';
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
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
    if (ref.read(currentUserProvider)?.isStaff ?? false) {
      setState(() => _error = context.l10n.ecomAdminCannotBuy);
      return;
    }
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
            shippingEmail: _emailController.text,
            shippingAddress: _addressController.text,
            shippingPostcode: _postcodeController.text,
          );
      if (!mounted) return;

      final isGuest = ref.read(currentUserProvider) == null;

      ref.read(cartProvider.notifier).clear();
      ref.invalidate(ordersProvider);
      // Stock was decremented on the server; drop cached catalogues so the
      // product list/detail immediately show the updated sold-out state.
      ref.invalidate(productsProvider);
      ref.invalidate(productDetailProvider);

      final paymentUrl = result.paymentUrl;
      final receiptUrl = result.order?.receiptUrl;
      final l10n = context.l10n;
      final receiptAction =
          (isGuest && receiptUrl != null && receiptUrl.isNotEmpty)
              ? SnackBarAction(
                label: 'Lihat Resit',
                onPressed:
                    () => launchUrl(
                      Uri.parse(receiptUrl),
                      mode: LaunchMode.externalApplication,
                    ),
              )
              : null;

      if (paymentUrl != null && paymentUrl.isNotEmpty) {
        final paid = await Navigator.of(context).push<bool>(
          MaterialPageRoute(
            builder: (_) => PaymentWebviewScreen(paymentUrl: paymentUrl),
          ),
        );
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              paid == true
                  ? l10n.ecomOrderPaidSuccess
                  : l10n.ecomOrderCreatedAwaitPayment,
            ),
            action: receiptAction,
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(l10n.ecomOrderCreatedSuccess),
            action: receiptAction,
          ),
        );
      }
      context.go(isGuest ? '/home' : '/orders');
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
    final isStaff = ref.watch(currentUserProvider)?.isStaff ?? false;

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(fallback: '/cart'),
        title: Text(context.l10n.ecomCheckoutTitle),
      ),
      body: Stack(
        children: [
          cart.isEmpty
              ? const _EmptyCheckout()
              : _buildCheckoutForm(cart, isStaff),
          if (_submitting)
            LoadingOverlay(message: context.l10n.ecomProcessingOrder),
        ],
      ),
    );
  }

  Widget _buildCheckoutForm(CartState cart, bool isStaff) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        if (isStaff) ...[
          Container(
            padding: const EdgeInsets.all(Spacing.md),
            decoration: BoxDecoration(
              color: AppColors.accentTint,
              borderRadius: AppRadius.md,
              border: Border.all(color: AppColors.divider),
            ),
            child: Text(
              l10n.ecomAdminCannotBuy,
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.warning,
                fontWeight: FontWeight.w600,
                height: 1.4,
              ),
            ),
          ),
          const SizedBox(height: Spacing.md),
        ],
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
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    decoration: InputDecoration(
                      labelText: l10n.emailLabel,
                      hintText: l10n.emailHint,
                      prefixIcon: const Icon(Icons.mail_outline),
                    ),
                    validator: (value) {
                      final email = (value ?? '').trim();
                      if (email.isEmpty) return l10n.emailRequired;
                      if (!RegExp(
                        r'^[^@\s]+@[^@\s]+\.[^@\s]+$',
                      ).hasMatch(email)) {
                        return l10n.emailInvalid;
                      }
                      return null;
                    },
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
                _AmountRow(label: l10n.ecomSubtotal, amount: cart.subtotal),
                const SizedBox(height: Spacing.xs),
                _AmountRow(label: l10n.ecomPostage, amount: cart.postage),
                Padding(
                  padding: const EdgeInsets.only(top: Spacing.xs),
                  child: Text(
                    l10n.ecomPostageOnceNote,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.textTertiary,
                    ),
                  ),
                ),
                const Divider(height: Spacing.lg),
                _AmountRow(
                  label: l10n.ecomTotal,
                  amount: cart.grandTotal,
                  emphasized: true,
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
          onPressed: _submitting || isStaff ? null : _submit,
          icon: const Icon(Icons.receipt_long_outlined),
          label: Text(l10n.ecomSubmitOrder),
        ),
        const SizedBox(height: Spacing.xl),
      ],
    );
  }
}

class _AmountRow extends StatelessWidget {
  const _AmountRow({
    required this.label,
    required this.amount,
    this.emphasized = false,
  });

  final String label;
  final double amount;
  final bool emphasized;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      children: [
        Text(
          label,
          style:
              emphasized
                  ? theme.textTheme.titleMedium
                  : theme.textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                  ),
        ),
        const Spacer(),
        Text(
          Formatters.currency(amount),
          style:
              emphasized
                  ? theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                  )
                  : theme.textTheme.bodyMedium,
        ),
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
