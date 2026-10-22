import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../application/cart_notifier.dart';
import '../application/product_providers.dart';
import '../data/models/product.dart';
import '../../../shared/theme/app_text_theme.dart';

class ProductDetailScreen extends ConsumerStatefulWidget {
  const ProductDetailScreen({super.key, required this.productId});

  final int productId;

  @override
  ConsumerState<ProductDetailScreen> createState() =>
      _ProductDetailScreenState();
}

class _ProductDetailScreenState extends ConsumerState<ProductDetailScreen> {
  final Map<int, int> _selectedOptions = {};
  int _quantity = 1;
  ProductDetail? _detail;

  ProductVariationOption? _selectedOption(ProductVariation variation) {
    if (variation.options.isEmpty) return null;
    final selectedId = variation.id == null ? null : _selectedOptions[variation.id];
    if (selectedId != null) {
      for (final option in variation.options) {
        if (option.id == selectedId) return option;
      }
    }
    return _defaultOption(variation);
  }

  /// First option the buyer can actually buy, falling back to the first one
  /// when the whole variation is sold out (so the sold-out state is visible).
  ProductVariationOption _defaultOption(ProductVariation variation) {
    for (final option in variation.options) {
      if (!option.isOutOfStock) return option;
    }
    return variation.options.first;
  }

  void _ensureSelections(Product product) {
    for (final variation in product.variations) {
      final variationId = variation.id;
      if (variationId == null || variation.options.isEmpty) continue;

      ProductVariationOption? current;
      final selectedId = _selectedOptions[variationId];
      if (selectedId != null) {
        for (final option in variation.options) {
          if (option.id == selectedId) {
            current = option;
            break;
          }
        }
      }

      // Pick an in-stock option when nothing is chosen yet, or when a refresh
      // revealed that the previously chosen option has sold out.
      if (current == null || current.isOutOfStock) {
        final fallback = _defaultOption(variation);
        if (fallback.id != null) _selectedOptions[variationId] = fallback.id!;
      }
    }
  }

  /// Smallest known stock across the selected options, else the product stock.
  /// A null stock means "not tracked" (unlimited).
  int? _availableStock(Product product) {
    int? available;
    for (final variation in product.variations) {
      if (variation.options.isEmpty) continue;
      final stock = _selectedOption(variation)?.stock;
      if (stock == null) continue;
      available = available == null
          ? stock
          : (stock < available ? stock : available);
    }
    return available ?? product.stock;
  }

  bool _isSoldOut(Product product) {
    final stock = _availableStock(product);
    return stock != null && stock <= 0;
  }

  int _clampedQuantity(Product product) {
    final max = _availableStock(product);
    if (max == null) return _quantity;
    if (max <= 0) return 1;
    return _quantity.clamp(1, max).toInt();
  }

  bool _canBuy(Product product) {
    if (product.id == null || _isSoldOut(product)) return false;
    for (final variation in product.variations) {
      if (variation.options.isEmpty) continue;
      if (variation.required == true && _selectedOption(variation) == null) {
        return false;
      }
    }
    return true;
  }

  void _addToCart({required bool buyNow}) {
    final product = _detail?.product;
    if (product == null) return;

    if (!_canBuy(product)) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(content: Text(context.l10n.ecomOutOfStockBody)),
        );
      return;
    }

    final added = ref.read(cartProvider.notifier).add(_buildCartItem(product));
    if (!mounted) return;
    if (!added) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(content: Text(context.l10n.ecomOutOfStockBody)),
        );
      return;
    }
    if (buyNow) {
      context.push('/checkout');
    } else {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.l10n.ecomAddedToCart)));
    }
  }

  CartItem _buildCartItem(Product product) {
    final selected = <ProductVariationOption>[];
    for (final variation in product.variations) {
      final option = _selectedOption(variation);
      if (option != null) selected.add(option);
    }

    // Mirrors OrderService.createOrder: member pricing replaces the whole
    // unit price (adjustments are only added for non-members). The backend
    // accepts one variation option per line, so send the first one just like
    // the web checkout does.
    final isMember = product.isMember;
    final firstOption = selected.isEmpty ? null : selected.first;
    final unitPrice =
        isMember
            ? (product.priceForMember ?? (product.price ?? 0))
            : (product.price ?? 0) + (firstOption?.priceAdjustment ?? 0);

    final labels = <String>[];
    for (final option in selected) {
      for (final variation in product.variations) {
        if (variation.options.any((o) => o.id == option.id)) {
          labels.add('${variation.name}: ${option.name}');
          break;
        }
      }
    }

    return CartItem(
      key: '${product.id}:${firstOption?.id ?? 0}',
      productId: product.id ?? 0,
      name: product.name ?? context.l10n.ecomProductFallback,
      image: product.displayImage,
      unitPrice: unitPrice,
      variationOptionId: firstOption?.id,
      variationLabel: labels.isEmpty ? null : labels.join(' / '),
      quantity: _clampedQuantity(product),
      stock: _availableStock(product),
    );
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(productDetailProvider(widget.productId));

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(fallback: '/products'),
        title: Text(context.l10n.ecomProductDetailTitle),
      ),
      body: detailAsync.when(
        data: (detail) {
          _detail = detail;
          final product = detail.product;
          if (product != null) _ensureSelections(product);
          return _DetailContent(
            detail: detail,
            selectedOptions: _selectedOptions,
            availableStock: product == null ? null : _availableStock(product),
            onOptionSelected:
                (variationId, optionId) => setState(() {
                  _selectedOptions[variationId] = optionId;
                  final currentProduct = _detail?.product;
                  if (currentProduct == null) return;
                  final max = _availableStock(currentProduct);
                  if (max != null && max > 0 && _quantity > max) {
                    _quantity = max;
                  }
                }),
            onRefresh:
                () async =>
                    ref.invalidate(productDetailProvider(widget.productId)),
          );
        },
        loading: () => const _DetailSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : context.l10n.unexpectedError,
              onRetry:
                  () => ref.invalidate(productDetailProvider(widget.productId)),
            ),
      ),
      bottomNavigationBar: detailAsync.when(
        data: (detail) {
          final product = detail.product;
          if (product == null) return null;
          final max = _availableStock(product);
          final quantity = _clampedQuantity(product);
          final soldOut = _isSoldOut(product);
          return _BottomBar(
            quantity: quantity,
            canDecrease: quantity > 1,
            canIncrease: !soldOut && (max == null || quantity < max),
            canBuy: _canBuy(product),
            soldOut: soldOut,
            onDecrease:
                () => setState(() {
                  final current = _clampedQuantity(product);
                  if (current > 1) _quantity = current - 1;
                }),
            onIncrease:
                () => setState(() {
                  final current = _clampedQuantity(product);
                  final limit = _availableStock(product);
                  if (limit == null || current < limit) _quantity = current + 1;
                }),
            onAddToCart: () => _addToCart(buyNow: false),
            onBuyNow: () => _addToCart(buyNow: true),
          );
        },
        loading: () => null,
        error: (_, __) => null,
      ),
    );
  }
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({
    required this.detail,
    required this.selectedOptions,
    required this.availableStock,
    required this.onOptionSelected,
    required this.onRefresh,
  });

  final ProductDetail detail;
  final Map<int, int> selectedOptions;
  final int? availableStock;
  final void Function(int variationId, int optionId) onOptionSelected;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final product = detail.product;
    if (product == null) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Center(child: Text(l10n.ecomProductNotFound)),
        ),
      );
    }

    final showBadge = product.isMember && product.priceForMember != null;
    final price = showBadge ? product.priceForMember! : (product.price ?? 0);

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.zero,
        children: [
          AppImage(
            product.displayImage,
            height: 280,
            width: double.infinity,
            fit: BoxFit.cover,
            borderRadius: BorderRadius.zero,
          ),
          Padding(
            padding: const EdgeInsets.all(Spacing.xl),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        product.name ?? '-',
                        style: theme.textTheme.headlineSmall,
                      ),
                    ),
                    if (showBadge)
                      Container(
                        margin: const EdgeInsets.only(top: 4),
                        padding: const EdgeInsets.symmetric(
                          horizontal: Spacing.sm,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.movementGreen,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          l10n.ecomMemberPrice,
                          style: const TextStyle(
                            color: AppColors.white,
                            fontSize: AppTextTheme.minSize,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: Spacing.sm),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.baseline,
                  textBaseline: TextBaseline.alphabetic,
                  children: [
                    Text(
                      Formatters.currency(price),
                      style: theme.textTheme.headlineSmall?.copyWith(
                        color: AppColors.movementGreen,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    if (showBadge && product.price != null) ...[
                      const SizedBox(width: Spacing.sm),
                      Text(
                        Formatters.currency(product.price),
                        style: theme.textTheme.titleMedium?.copyWith(
                          color: AppColors.textSecondary,
                          decoration: TextDecoration.lineThrough,
                        ),
                      ),
                    ],
                  ],
                ),
                if (availableStock != null) ...[
                  const SizedBox(height: Spacing.sm),
                  _StockStatus(stock: availableStock!),
                ],
                if (product.organization?.name != null) ...[
                  const SizedBox(height: Spacing.sm),
                  Text(
                    product.organization!.name!,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: AppColors.movementGreen,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
                if (product.description != null &&
                    product.description!.isNotEmpty) ...[
                  const SizedBox(height: Spacing.xl),
                  Text(l10n.ecomDescription, style: theme.textTheme.titleLarge),
                  const SizedBox(height: Spacing.sm),
                  Text(product.description!, style: theme.textTheme.bodyLarge),
                ],
                if (product.variations.any((v) => v.options.isNotEmpty)) ...[
                  const SizedBox(height: Spacing.xl),
                  Text(l10n.ecomOptions, style: theme.textTheme.titleLarge),
                  for (final variation in product.variations)
                    if (variation.options.isNotEmpty) ...[
                      const SizedBox(height: Spacing.md),
                      Text(
                        variation.name ?? l10n.ecomOptions,
                        style: theme.textTheme.titleSmall,
                      ),
                      const SizedBox(height: Spacing.xs),
                      Wrap(
                        spacing: Spacing.sm,
                        runSpacing: Spacing.xs,
                        children: [
                          for (final option in variation.options)
                            ChoiceChip(
                              label: Text(_optionLabel(context, option)),
                              selected:
                                  selectedOptions[variation.id] == option.id,
                              showCheckmark: false,
                              onSelected:
                                  option.isOutOfStock ||
                                          variation.id == null ||
                                          option.id == null
                                      ? null
                                      : (_) => onOptionSelected(
                                        variation.id!,
                                        option.id!,
                                      ),
                            ),
                        ],
                      ),
                    ],
                ],
              ],
            ),
          ),
          if (detail.relatedProducts.isNotEmpty) ...[
            SectionHeader(l10n.ecomRelatedProducts),
            SizedBox(
              height: 280,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
                itemCount: detail.relatedProducts.length,
                separatorBuilder: (_, __) => const SizedBox(width: Spacing.md),
                itemBuilder:
                    (context, index) =>
                        _RelatedCard(product: detail.relatedProducts[index]),
              ),
            ),
            const SizedBox(height: Spacing.xl),
          ],
        ],
      ),
    );
  }
}

String _optionLabel(BuildContext context, ProductVariationOption option) {
  final adjustment = option.priceAdjustment;
  final price =
      adjustment == null || adjustment <= 0
          ? ''
          : ' (+${Formatters.currency(adjustment)})';
  final soldOut =
      option.isOutOfStock ? ' • ${context.l10n.ecomOptionSoldOut}' : '';
  return '${option.name ?? '-'}$price$soldOut';
}

class _StockStatus extends StatelessWidget {
  const _StockStatus({required this.stock});

  final int stock;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final style = Theme.of(context).textTheme.bodyMedium;
    if (stock <= 0) {
      return Text(
        l10n.ecomSoldOut,
        style: style?.copyWith(
          color: AppColors.error,
          fontWeight: FontWeight.w700,
        ),
      );
    }
    if (stock <= 10) {
      return Text(
        l10n.ecomStockRemaining(stock),
        style: style?.copyWith(
          color: AppColors.warning,
          fontWeight: FontWeight.w700,
        ),
      );
    }
    return Text(
      l10n.ecomStockAvailable(stock),
      style: style?.copyWith(color: AppColors.movementGreen),
    );
  }
}

class _RelatedCard extends StatelessWidget {
  const _RelatedCard({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SizedBox(
      width: 168,
      child: Material(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap:
              product.id == null
                  ? null
                  : () => context.push('/products/${product.id}'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Consistent 1:1 thumbnail, same as the catalogue cards.
              AspectRatio(
                aspectRatio: 1,
                child: AppImage(
                  product.displayImage,
                  fit: BoxFit.cover,
                  borderRadius: BorderRadius.zero,
                ),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(
                    Spacing.sm,
                    Spacing.xs,
                    Spacing.sm,
                    Spacing.sm,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        product.name ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                          height: 1.25,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        Formatters.currency(product.effectivePrice),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: AppColors.movementGreen,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BottomBar extends StatelessWidget {
  const _BottomBar({
    required this.quantity,
    required this.canDecrease,
    required this.canIncrease,
    required this.canBuy,
    required this.soldOut,
    required this.onDecrease,
    required this.onIncrease,
    required this.onAddToCart,
    required this.onBuyNow,
  });

  final int quantity;
  final bool canDecrease;
  final bool canIncrease;
  final bool canBuy;
  final bool soldOut;
  final VoidCallback onDecrease;
  final VoidCallback onIncrease;
  final VoidCallback onAddToCart;
  final VoidCallback onBuyNow;

  @override
  Widget build(BuildContext context) {
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
          children: [
            Row(
              children: [
                Text(
                  context.l10n.ecomQuantity,
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                const Spacer(),
                IconButton.outlined(
                  onPressed: canDecrease ? onDecrease : null,
                  icon: const Icon(Icons.remove),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
                  child: Text('$quantity'),
                ),
                IconButton.outlined(
                  onPressed: canIncrease ? onIncrease : null,
                  icon: const Icon(Icons.add),
                ),
              ],
            ),
            if (soldOut) ...[
              const SizedBox(height: Spacing.xs),
              Text(
                context.l10n.ecomOutOfStockBody,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.error,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
            const SizedBox(height: Spacing.sm),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: canBuy ? onAddToCart : null,
                    icon: const Icon(Icons.add_shopping_cart),
                    label: Text(context.l10n.ecomAddToCart),
                  ),
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: FilledButton(
                    onPressed: canBuy ? onBuyNow : null,
                    child: Text(
                      soldOut
                          ? context.l10n.ecomSoldOut
                          : context.l10n.ecomBuyNow,
                    ),
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

class _DetailSkeleton extends StatelessWidget {
  const _DetailSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: const [
        SkeletonBox(height: 280, radius: 0),
        Padding(
          padding: EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SkeletonBox(height: 28, width: 240),
              SizedBox(height: 12),
              SkeletonBox(height: 24, width: 140),
              SizedBox(height: 24),
              SkeletonBox(height: 80),
              SizedBox(height: 24),
              SkeletonBox(height: 32),
              SizedBox(height: 8),
              SkeletonBox(height: 32),
            ],
          ),
        ),
      ],
    );
  }
}
