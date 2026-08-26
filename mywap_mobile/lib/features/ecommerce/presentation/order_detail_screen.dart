import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/payment/payment_webview_screen.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/order_providers.dart';
import '../data/models/order.dart';
import 'order_status.dart';

class OrderDetailScreen extends ConsumerStatefulWidget {
  const OrderDetailScreen({super.key, required this.orderId});

  final int orderId;

  @override
  ConsumerState<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends ConsumerState<OrderDetailScreen> {
  bool _paying = false;

  Future<void> _pay() async {
    setState(() => _paying = true);
    try {
      final result = await ref.read(orderRepositoryProvider).pay(widget.orderId);
      if (!mounted) return;
      ref.invalidate(orderDetailProvider(widget.orderId));
      ref.invalidate(ordersProvider);

      if (result.status == 'redirect' &&
          result.paymentUrl != null &&
          result.paymentUrl!.isNotEmpty) {
        final paid = await Navigator.of(context).push<bool>(
          MaterialPageRoute(
            builder: (_) => PaymentWebviewScreen(paymentUrl: result.paymentUrl!),
          ),
        );
        if (!mounted) return;
        ref.invalidate(orderDetailProvider(widget.orderId));
        ref.invalidate(ordersProvider);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              paid == true
                  ? 'Pembayaran berjaya. Terima kasih!'
                  : 'Pembayaran belum selesai. Sila cuba lagi.',
            ),
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pembayaran berjaya. Terima kasih!')),
        );
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      ref.invalidate(orderDetailProvider(widget.orderId));
      ref.invalidate(ordersProvider);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } finally {
      if (mounted) setState(() => _paying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(orderDetailProvider(widget.orderId));
    final order = detailAsync.valueOrNull?.order;

    return Scaffold(
      appBar: AppBar(title: const Text('Butiran Pesanan')),
      body: detailAsync.when(
        data: (detail) => _DetailBody(order: detail.order),
        loading: () => const _DetailSkeleton(),
        error: (error, _) => ErrorRetry(
          message:
              error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(orderDetailProvider(widget.orderId)),
        ),
      ),
      bottomNavigationBar: order != null && order.status == 'pending'
          ? SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.all(Spacing.lg),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  border: Border(top: BorderSide(color: AppColors.divider)),
                ),
                child: FilledButton.icon(
                  onPressed: _paying ? null : _pay,
                  icon: _paying
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: AppColors.white,
                          ),
                        )
                      : const Icon(Icons.payment),
                  label: const Text('Bayar'),
                ),
              ),
            )
          : null,
    );
  }
}

class _DetailBody extends StatelessWidget {
  const _DetailBody({required this.order});

  final Order? order;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final o = order;
    if (o == null) {
      return const Center(child: Text('Pesanan tidak dijumpai.'));
    }

    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(Spacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text('Pesanan #${o.id ?? '-'}',
                          style: theme.textTheme.titleLarge),
                    ),
                    _StatusChip(status: o.status),
                  ],
                ),
                const SizedBox(height: Spacing.xs),
                Text(
                  formatOrderDate(o.createdAt),
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: Spacing.sm),
                Text(
                  'Jumlah: ${Formatters.currency(o.grandTotal)}',
                  style: theme.textTheme.titleMedium?.copyWith(
                    color: AppColors.movementGreen,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: Spacing.md),
        _SectionCard(
          title: 'Item',
          child: o.items.isEmpty
              ? const Text('Tiada item.')
              : Column(
                  children: [
                    for (final item in o.items)
                      _ItemRow(item: item),
                  ],
                ),
        ),
        const SizedBox(height: Spacing.md),
        _SectionCard(
          title: 'Maklumat Penghantaran',
          child: Column(
            children: [
              _InfoLine(label: 'Nama', value: o.shippingName),
              _InfoLine(label: 'Telefon', value: o.shippingPhone),
              _InfoLine(label: 'Alamat', value: o.shippingAddress),
              _InfoLine(label: 'Poskod', value: o.shippingPostcode),
              _InfoLine(label: 'Kurier', value: o.courier),
              _InfoLine(label: 'No. Penjejakan', value: o.trackingNo),
            ],
          ),
        ),
        const SizedBox(height: Spacing.md),
        _SectionCard(
          title: 'Pembayaran',
          child: o.payments.isEmpty
              ? const Text('Tiada pembayaran.')
              : Column(
                  children: [
                    for (final payment in o.payments)
                      _PaymentRow(payment: payment),
                  ],
                ),
        ),
        const SizedBox(height: Spacing.md),
        _SectionCard(
          title: 'Ringkasan',
          child: Column(
            children: [
              _InfoLine(label: 'Subjumlah', value: Formatters.currency(o.total)),
              _InfoLine(
                label: 'Pos',
                value: Formatters.currency(o.postageCost),
              ),
              const Divider(height: Spacing.lg),
              _InfoLine(
                label: 'Jumlah',
                value: Formatters.currency(o.grandTotal),
                bold: true,
              ),
            ],
          ),
        ),
        const SizedBox(height: Spacing.xl),
      ],
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: Spacing.sm),
            child,
          ],
        ),
      ),
    );
  }
}

class _ItemRow extends StatelessWidget {
  const _ItemRow({required this.item});

  final OrderItem item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: Spacing.xs),
      child: Row(
        children: [
          AppImage(
            item.product?.image,
            width: 48,
            height: 48,
          ),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.product?.name ?? 'Produk',
                  style: theme.textTheme.bodyMedium,
                ),
                if (item.variationSnapshot != null &&
                    item.variationSnapshot!.isNotEmpty)
                  Text(
                    item.variationSnapshot!,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                Text(
                  '${item.quantity} x ${Formatters.currency(item.price)}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          Text(
            Formatters.currency((item.price ?? 0) * (item.quantity ?? 0)),
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _PaymentRow extends StatelessWidget {
  const _PaymentRow({required this.payment});

  final PaymentInfo payment;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: Spacing.xs),
      child: Row(
        children: [
          const Icon(
            Icons.credit_card,
            size: 20,
            color: AppColors.textSecondary,
          ),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  payment.reference ?? 'Pembayaran',
                  style: theme.textTheme.bodyMedium,
                ),
                if (payment.gateway != null)
                  Text(
                    payment.gateway!,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                Formatters.currency(payment.amount),
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              Text(
                paymentStatusLabel(payment.status),
                style: theme.textTheme.bodySmall?.copyWith(
                  color: orderStatusColor(payment.status == 'successful'
                      ? 'paid'
                      : payment.status == 'failed'
                          ? 'cancelled'
                          : 'pending'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  const _InfoLine({required this.label, this.value, this.bold = false});

  final String label;
  final String? value;
  final bool bold;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isEmpty = value == null || value!.trim().isEmpty;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(
              label,
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ),
          Expanded(
            child: Text(
              isEmpty ? '-' : value!,
              style: theme.textTheme.bodyMedium?.copyWith(
                fontWeight: bold ? FontWeight.w700 : null,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});

  final String? status;

  @override
  Widget build(BuildContext context) {
    final color = orderStatusColor(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.sm, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        orderStatusLabel(status),
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: AppColors.textPrimary,
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
      padding: const EdgeInsets.all(Spacing.lg),
      children: const [
        SkeletonBox(height: 120, radius: 16),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 160, radius: 16),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 140, radius: 16),
      ],
    );
  }
}
