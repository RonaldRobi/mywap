import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/utils/formatters.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/confirm_dialog.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../auth/application/auth_controller.dart';
import '../application/cart_notifier.dart';

class CartScreen extends ConsumerWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final cart = ref.watch(cartProvider);
    final isStaff = ref.watch(currentUserProvider)?.isStaff ?? false;

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(fallback: '/products'),
        title: Text(l10n.ecomCartTitle),
      ),
      body:
          cart.isEmpty
              ? EmptyState(
                icon: Icons.shopping_cart_outlined,
                message: l10n.ecomCartEmpty,
                actionLabel: l10n.ecomExploreMarket,
                onAction: () => context.go('/products'),
              )
              : Column(
                children: [
                  Expanded(
                    child: ListView.builder(
                      padding: const EdgeInsets.symmetric(vertical: Spacing.sm),
                      itemCount: cart.items.length,
                      itemBuilder:
                          (context, index) =>
                              _CartItemCard(item: cart.items[index]),
                    ),
                  ),
                  _CartSummary(cart: cart, isStaff: isStaff),
                ],
              ),
    );
  }
}

class _CartItemCard extends ConsumerWidget {
  const _CartItemCard({required this.item});

  final CartItem item;

  Future<void> _confirmRemove(BuildContext context, WidgetRef ref) async {
    final l10n = context.l10n;
    final ok = await showConfirmDialog(
      context,
      title: l10n.ecomRemoveItemTitle,
      message: l10n.ecomRemoveItemMessage(item.name),
      confirmLabel: l10n.ecomRemove,
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
                  if (item.stock != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      context.l10n.ecomStockAvailable(item.stock!),
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                  const SizedBox(height: Spacing.xs),
                  Row(
                    children: [
                      IconButton.outlined(
                        visualDensity: VisualDensity.compact,
                        onPressed:
                            item.quantity > 1
                                ? () => ref
                                    .read(cartProvider.notifier)
                                    .updateQuantity(item.key, item.quantity - 1)
                                : null,
                        icon: const Icon(Icons.remove),
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: Spacing.sm,
                        ),
                        child: Text(
                          '${item.quantity}',
                          style: theme.textTheme.titleSmall,
                        ),
                      ),
                      IconButton.outlined(
                        visualDensity: VisualDensity.compact,
                        onPressed:
                            !item.isOutOfStock &&
                                    (item.stock == null ||
                                        item.quantity < item.stock!)
                                ? () => ref
                                    .read(cartProvider.notifier)
                                    .updateQuantity(item.key, item.quantity + 1)
                                : null,
                        icon: const Icon(Icons.add),
                      ),
                    ],
                  ),
                  if (item.isOutOfStock)
                    Text(
                      context.l10n.ecomOutOfStockBody,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.error,
                        fontWeight: FontWeight.w700,
                      ),
                    )
                  else if (item.isAtStockLimit)
                    Text(
                      context.l10n.ecomMaxStockReached,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.warning,
                        fontWeight: FontWeight.w600,
                      ),
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
                  tooltip: context.l10n.ecomRemove,
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
  const _CartSummary({required this.cart, required this.isStaff});

  final CartState cart;
  final bool isStaff;

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
                Text(
                  context.l10n.ecomSubtotal,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
                const Spacer(),
                Text(
                  Formatters.currency(cart.subtotal),
                  style: theme.textTheme.bodyMedium,
                ),
              ],
            ),
            const SizedBox(height: Spacing.xs),
            Row(
              children: [
                Text(
                  context.l10n.ecomPostage,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
                const Spacer(),
                Text(
                  Formatters.currency(cart.postage),
                  style: theme.textTheme.bodyMedium,
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.only(top: Spacing.xs),
              child: Text(
                context.l10n.ecomPostageOnceNote,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.textTertiary,
                ),
              ),
            ),
            const Divider(height: Spacing.lg),
            Row(
              children: [
                Text(
                  context.l10n.ecomTotal,
                  style: theme.textTheme.titleMedium,
                ),
                const Spacer(),
                Text(
                  Formatters.currency(cart.grandTotal),
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
            if (cart.items.any((item) => item.isOutOfStock)) ...[
              const SizedBox(height: Spacing.sm),
              Text(
                context.l10n.ecomOutOfStockBody,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.error,
                  fontWeight: FontWeight.w700,
                ),
                textAlign: TextAlign.center,
              ),
            ],
            if (isStaff) ...[
              const SizedBox(height: Spacing.sm),
              Text(
                context.l10n.ecomAdminCannotBuy,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.warning,
                  fontWeight: FontWeight.w600,
                ),
                textAlign: TextAlign.center,
              ),
            ],
            const SizedBox(height: Spacing.md),
            FilledButton.icon(
              onPressed:
                  isStaff || cart.items.any((item) => item.isOutOfStock)
                      ? null
                      : () => context.push('/checkout'),
              icon: const Icon(Icons.chevron_right),
              label: Text(context.l10n.ecomContinueCheckout),
            ),
          ],
        ),
      ),
    );
  }
}
