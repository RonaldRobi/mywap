import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/confirm_dialog.dart';
import '../../../shared/widgets/empty_state.dart';
import '../application/cart_notifier.dart';

class CartScreen extends ConsumerWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cart = ref.watch(cartProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Troli')),
      body: cart.isEmpty
          ? EmptyState(
              icon: Icons.shopping_cart_outlined,
              message: 'Troli anda kosong.',
              actionLabel: 'Terokai Pasar',
              onAction: () => context.go('/products'),
            )
          : Column(
              children: [
                Expanded(
                  child: ListView.builder(
                    padding: const EdgeInsets.symmetric(vertical: Spacing.sm),
                    itemCount: cart.items.length,
                    itemBuilder: (context, index) =>
                        _CartItemCard(item: cart.items[index]),
                  ),
                ),
                _CartSummary(cart: cart),
              ],
            ),
    );
  }
}

class _CartItemCard extends ConsumerWidget {
  const _CartItemCard({required this.item});

  final CartItem item;

  Future<void> _confirmRemove(BuildContext context, WidgetRef ref) async {
    final ok = await showConfirmDialog(
      context,
      title: 'Buang Item?',
      message: 'Buang "${item.name}" daripada troli?',
      confirmLabel: 'Buang',
    );
    if (ok) ref.read(cartProvider.notifier).remove(item.key);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.md),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AppImage(item.image, width: 72, height: 72),
            const SizedBox(width: Spacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(item.name, style: theme.textTheme.titleMedium),
                  if (item.variationLabel != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      item.variationLabel!,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                  const SizedBox(height: Spacing.xs),
                  Text(
                    Formatters.currency(item.unitPrice),
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: AppColors.movementGreen,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: Spacing.xs),
                  Row(
                    children: [
                      IconButton.outlined(
                        visualDensity: VisualDensity.compact,
                        onPressed: item.quantity > 1
                            ? () => ref
                                .read(cartProvider.notifier)
                                .updateQuantity(item.key, item.quantity - 1)
                            : null,
                        icon: const Icon(Icons.remove),
                      ),
                      Padding(
                        padding:
                            const EdgeInsets.symmetric(horizontal: Spacing.sm),
                        child: Text('${item.quantity}',
                            style: theme.textTheme.titleSmall),
                      ),
                      IconButton.outlined(
                        visualDensity: VisualDensity.compact,
                        onPressed: item.quantity < 99
                            ? () => ref
                                .read(cartProvider.notifier)
                                .updateQuantity(item.key, item.quantity + 1)
                            : null,
                        icon: const Icon(Icons.add),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  Formatters.currency(item.lineTotal),
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: Spacing.sm),
                IconButton(
                  tooltip: 'Buang',
                  onPressed: () => _confirmRemove(context, ref),
                  icon: const Icon(
                    Icons.delete_outline,
                    color: AppColors.error,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CartSummary extends StatelessWidget {
  const _CartSummary({required this.cart});

  final CartState cart;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.all(Spacing.lg),
        decoration: BoxDecoration(
          color: AppColors.surface,
          border: Border(top: BorderSide(color: AppColors.divider)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
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
            const SizedBox(height: Spacing.md),
            FilledButton.icon(
              onPressed: () => context.push('/checkout'),
              icon: const Icon(Icons.chevron_right),
              label: const Text('Teruskan ke Checkout'),
            ),
          ],
        ),
      ),
    );
  }
}
