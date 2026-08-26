import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/cart_notifier.dart';
import '../application/product_providers.dart';
import '../data/models/product.dart';

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

  void _addToCart({required bool buyNow}) {
    final product = _detail?.product;
    if (product == null) return;
    final item = _buildCartItem(product);
    ref.read(cartProvider.notifier).add(item);
    if (!mounted) return;
    if (buyNow) {
      context.push('/checkout');
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Ditambah ke troli.')),
      );
    }
  }

  CartItem _buildCartItem(Product product) {
    final selected = <ProductVariationOption>[];
    for (final variation in product.variations) {
      if (variation.options.isEmpty) continue;
      final optionId =
          _selectedOptions[variation.id] ?? variation.options.first.id!;
      final option = variation.options.firstWhere(
        (o) => o.id == optionId,
        orElse: () => variation.options.first,
      );
      selected.add(option);
    }

    // Mirrors OrderService.createOrder: member pricing replaces the whole
    // unit price (adjustments are only added for non-members).
    final isMember = product.isMember;
    final singleOption = selected.length == 1 ? selected.first : null;
    final unitPrice = isMember
        ? (product.priceForMember ?? (product.price ?? 0))
        : (product.price ?? 0) + (singleOption?.priceAdjustment ?? 0);

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
      key: '${product.id}:${singleOption?.id ?? 0}',
      productId: product.id ?? 0,
      name: product.name ?? 'Produk',
      image: product.displayImage,
      unitPrice: unitPrice,
      variationOptionId: singleOption?.id,
      variationLabel: labels.isEmpty ? null : labels.join(' / '),
      quantity: _quantity,
    );
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(productDetailProvider(widget.productId));

    return Scaffold(
      appBar: AppBar(title: const Text('Butiran Produk')),
      body: detailAsync.when(
        data: (detail) {
          _detail = detail;
          return _DetailContent(
            detail: detail,
            selectedOptions: _selectedOptions,
            onOptionSelected: (variationId, optionId) => setState(
              () => _selectedOptions[variationId] = optionId,
            ),
          );
        },
        loading: () => const _DetailSkeleton(),
        error: (error, _) => ErrorRetry(
          message:
              error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () =>
              ref.invalidate(productDetailProvider(widget.productId)),
        ),
      ),
      bottomNavigationBar: detailAsync.when(
        data: (detail) => detail.product == null
            ? null
            : _BottomBar(
                quantity: _quantity,
                onDecrease: () => setState(() {
                  if (_quantity > 1) _quantity--;
                }),
                onIncrease: () => setState(() {
                  if (_quantity < 99) _quantity++;
                }),
                onAddToCart: () => _addToCart(buyNow: false),
                onBuyNow: () => _addToCart(buyNow: true),
              ),
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
    required this.onOptionSelected,
  });

  final ProductDetail detail;
  final Map<int, int> selectedOptions;
  final void Function(int variationId, int optionId) onOptionSelected;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final product = detail.product;
    if (product == null) {
      return const Center(child: Text('Produk tidak dijumpai.'));
    }

    final showBadge = product.isMember && product.priceForMember != null;
    final price = showBadge ? product.priceForMember! : (product.price ?? 0);

    return ListView(
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
                      child: const Text(
                        'Harga Ahli',
                        style: TextStyle(
                          color: AppColors.white,
                          fontSize: 12,
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
                Text('Penerangan', style: theme.textTheme.titleLarge),
                const SizedBox(height: Spacing.sm),
                Text(product.description!, style: theme.textTheme.bodyLarge),
              ],
              if (product.variations.any((v) => v.options.isNotEmpty)) ...[
                const SizedBox(height: Spacing.xl),
                Text('Pilihan', style: theme.textTheme.titleLarge),
                for (final variation in product.variations)
                  if (variation.options.isNotEmpty) ...[
                    const SizedBox(height: Spacing.md),
                    Text(
                      variation.name ?? 'Pilihan',
                      style: theme.textTheme.titleSmall,
                    ),
                    const SizedBox(height: Spacing.xs),
                    Wrap(
                      spacing: Spacing.sm,
                      runSpacing: Spacing.xs,
                      children: [
                        for (final option in variation.options)
                          ChoiceChip(
                            label: Text(_optionLabel(option)),
                            selected: (selectedOptions[variation.id] ??
                                    variation.options.first.id!) ==
                                option.id,
                            showCheckmark: false,
                            onSelected: (_) => onOptionSelected(
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
          const SectionHeader('Produk Berkaitan'),
          SizedBox(
            height: 220,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              itemCount: detail.relatedProducts.length,
              separatorBuilder: (_, __) => const SizedBox(width: Spacing.md),
              itemBuilder: (context, index) => _RelatedCard(
                product: detail.relatedProducts[index],
              ),
            ),
          ),
          const SizedBox(height: Spacing.xl),
        ],
      ],
    );
  }
}

String _optionLabel(ProductVariationOption option) {
  final adjustment = option.priceAdjustment;
  if (adjustment == null || adjustment <= 0) return option.name ?? '-';
  return '${option.name} (+${Formatters.currency(adjustment)})';
}

class _RelatedCard extends StatelessWidget {
  const _RelatedCard({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SizedBox(
      width: 140,
      child: Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: product.id == null
              ? null
              : () => context.push('/products/${product.id}'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AppImage(
                product.displayImage,
                height: 90,
                width: double.infinity,
                fit: BoxFit.cover,
                borderRadius: BorderRadius.zero,
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.sm),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        product.name ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleSmall,
                      ),
                      const Spacer(),
                      Text(
                        Formatters.currency(product.effectivePrice),
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: AppColors.movementGreen,
                          fontWeight: FontWeight.w700,
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
    required this.onDecrease,
    required this.onIncrease,
    required this.onAddToCart,
    required this.onBuyNow,
  });

  final int quantity;
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
                Text('Kuantiti', style: Theme.of(context).textTheme.titleSmall),
                const Spacer(),
                IconButton.outlined(
                  onPressed: quantity > 1 ? onDecrease : null,
                  icon: const Icon(Icons.remove),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
                  child: Text('$quantity'),
                ),
                IconButton.outlined(
                  onPressed: quantity < 99 ? onIncrease : null,
                  icon: const Icon(Icons.add),
                ),
              ],
            ),
            const SizedBox(height: Spacing.sm),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: onAddToCart,
                    icon: const Icon(Icons.add_shopping_cart),
                    label: const Text('Masukkan ke Troli'),
                  ),
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: FilledButton(
                    onPressed: onBuyNow,
                    child: const Text('Beli Sekarang'),
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
