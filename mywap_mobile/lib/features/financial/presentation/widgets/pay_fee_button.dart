import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/network/api_exception.dart';
import '../../../member/application/member_core_providers.dart';
import '../../../profile/application/profile_providers.dart';
import '../../application/financial_providers.dart';
import '../../../../shared/payment/payment_webview_screen.dart';
import '../../../../shared/theme/app_colors.dart';
import '../../../../shared/theme/app_theme.dart';

/// "Bayar Yuran Sekarang" — initiates the annual membership-fee payment
/// (`POST /member/pay-fee`), opens the gateway WebView when a redirect is
/// returned, then refreshes every fee-status provider so the UI stays in sync.
class PayFeeButton extends ConsumerStatefulWidget {
  const PayFeeButton({super.key, this.compact = false});

  /// Renders as a full-width padded button block instead of an inline one.
  final bool compact;

  @override
  ConsumerState<PayFeeButton> createState() => _PayFeeButtonState();
}

class _PayFeeButtonState extends ConsumerState<PayFeeButton> {
  bool _busy = false;

  Future<void> _pay() async {
    if (_busy) return;
    setState(() => _busy = true);

    void refreshFees() {
      ref.invalidate(financialOverviewProvider);
      ref.invalidate(memberFeeStatusProvider);
      ref.invalidate(profileProvider);
    }

    try {
      final result = await ref.read(financialRepositoryProvider).payFee();
      if (!mounted) return;

      String message;
      if (result.isRedirect && (result.paymentUrl?.isNotEmpty ?? false)) {
        refreshFees();
        final paid = await Navigator.of(context).push<bool>(
          MaterialPageRoute(
            builder: (_) =>
                PaymentWebviewScreen(paymentUrl: result.paymentUrl!),
          ),
        );
        if (!mounted) return;
        refreshFees();
        message =
            paid == true
                ? 'Pembayaran yuran berjaya. Terima kasih!'
                : 'Pembayaran belum disahkan. Sila semak semula selepas selesai di gateway.';
      } else if (result.isSuccess) {
        refreshFees();
        message = result.message ?? 'Yuran keahlian berjaya dibayar.';
      } else {
        message = result.message ?? 'Pembayaran yuran tidak dapat diproses.';
      }

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Ralat tidak dijangka. Sila cuba lagi.')),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final content = FilledButton.icon(
      onPressed: _busy ? null : _pay,
      style: FilledButton.styleFrom(
        minimumSize: const Size.fromHeight(48),
        backgroundColor: AppColors.movementDarkGreen,
      ),
      icon:
          _busy
              ? const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: AppColors.white,
                ),
              )
              : const Icon(Icons.payments_outlined, size: 20),
      label: Text(_busy ? 'Memproses...' : 'Bayar Yuran Sekarang'),
    );

    if (!widget.compact) return content;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
      child: content,
    );
  }
}
