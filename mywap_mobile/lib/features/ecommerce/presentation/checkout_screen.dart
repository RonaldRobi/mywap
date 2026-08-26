import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/payment/payment_webview_screen.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/loading_overlay.dart';
import '../application/cart_notifier.dart';
import '../application/order_providers.dart';

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
        .map((item) => {
              'id': item.productId,
              'quantity': item.quantity,
              if (item.variationOptionId != null)
                'variation_option_id': item.variationOptionId,
              if (item.variationLabel != null && item.variationLabel!.isNotEmpty)
                'variation_snapshot': item.variationLabel,
            })
        .toList(growable: false);
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final cart = ref.read(cartProvider);
    if (cart.isEmpty) return;

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final result = await ref.read(orderRepositoryProvider).checkout(
            products: _productsPayload,
            shippingName: _nameController.text,
            shippingPhone: _phoneController.text,
            shippingAddress: _addressController.text,
            shippingPostcode: _postcodeController.text,
          );
      if (!mounted) return;

      ref.read(cartProvider.notifier).clear();
      ref.invalidate(ordersProvider);

      final paymentUrl = result.paymentUrl;
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
                  ? 'Pesanan berjaya dibuat dan dibayar.'
                  : 'Pesanan dibuat. Sila selesaikan pembayaran anda.',
            ),
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pesanan berjaya dibuat!')),
        );
      }
      context.go('/orders');
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _submitting = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'Ralat tidak dijangka. Sila cuba lagi.';
        _submitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = ref.watch(cartProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: Stack(
        children: [
          cart.isEmpty
              ? const _EmptyCheckout()
              : _buildCheckoutForm(cart),
          if (_submitting) const LoadingOverlay(message: 'Memproses pesanan...'),
        ],
      ),
    );
  }

  Widget _buildCheckoutForm(CartState cart) {
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
                  Text('Maklumat Penghantaran',
                      style: theme.textTheme.titleMedium),
                  const SizedBox(height: Spacing.md),
                  TextFormField(
                    controller: _nameController,
                    textCapitalization: TextCapitalization.words,
                    decoration: const InputDecoration(
                      labelText: 'Nama',
                      hintText: 'Nama penuh',
                      prefixIcon: Icon(Icons.person_outline),
                    ),
                    validator: (value) => (value == null || value.trim().isEmpty)
                        ? 'Nama diperlukan'
                        : null,
                  ),
                  const SizedBox(height: Spacing.md),
                  TextFormField(
                    controller: _phoneController,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'Telefon',
                      hintText: '012-3456789',
                      prefixIcon: Icon(Icons.phone_outlined),
                    ),
                    validator: (value) =>
                        (value == null || value.trim().isEmpty)
                            ? 'Nombor telefon diperlukan'
                            : null,
                  ),
                  const SizedBox(height: Spacing.md),
                  TextFormField(
                    controller: _addressController,
                    textCapitalization: TextCapitalization.sentences,
                    maxLines: 2,
                    decoration: const InputDecoration(
                      labelText: 'Alamat (pilihan)',
                      prefixIcon: Icon(Icons.home_outlined),
                    ),
                  ),
                  const SizedBox(height: Spacing.md),
                  TextFormField(
                    controller: _postcodeController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Poskod (pilihan)',
                      prefixIcon: Icon(Icons.pin_drop_outlined),
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
                Text('Ringkasan Pesanan', style: theme.textTheme.titleMedium),
                const SizedBox(height: Spacing.sm),
                for (final item in cart.items)
                  _SummaryRow(item: item),
                const Divider(height: Spacing.xl),
                Row(
                  children: [
                    Text('Jumlah', style: theme.textTheme.titleMedium),
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
          label: const Text('Hantar Pesanan'),
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
    return const Center(child: Text('Troli anda kosong.'));
  }
}
