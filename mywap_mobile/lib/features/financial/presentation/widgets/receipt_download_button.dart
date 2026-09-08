import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/network/api_exception.dart';
import '../../../../shared/theme/app_colors.dart';
import '../../application/financial_providers.dart';

/// Muat turun resit PDF bagi satu pembayaran yang berjaya.
///
/// Meminta URL yang ditandatangani daripada backend
/// (`GET /member/payments/{id}/receipt`), kemudian membukanya dalam pelayar
/// luaran supaya ahli boleh lihat/simpan PDF — sama corak surat ahli.
class ReceiptDownloadButton extends ConsumerWidget {
  const ReceiptDownloadButton({super.key, required this.paymentId, this.color});

  final int? paymentId;
  final Color? color;

  Future<void> _download(BuildContext context, WidgetRef ref) async {
    final id = paymentId;
    if (id == null) return;

    final messenger = ScaffoldMessenger.of(context);
    final repo = ref.read(financialRepositoryProvider);

    try {
      final url = await repo.receiptUrl(id);
      if (!context.mounted) return;

      if (url == null || url.isEmpty) {
        messenger.showSnackBar(
          const SnackBar(content: Text('Resit tidak tersedia.')),
        );
        return;
      }

      final launched = await launchUrl(
        Uri.parse(url),
        mode: LaunchMode.externalApplication,
      );
      if (!launched && context.mounted) {
        messenger.showSnackBar(
          const SnackBar(content: Text('Gagal membuka resit. Sila cuba lagi.')),
        );
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        messenger.showSnackBar(SnackBar(content: Text(e.message)));
      }
    } catch (_) {
      if (context.mounted) {
        messenger.showSnackBar(
          const SnackBar(content: Text('Gagal memuat turun resit.')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (paymentId == null) {
      return const SizedBox.shrink();
    }

    return IconButton(
      onPressed: () => _download(context, ref),
      tooltip: 'Muat Turun Resit',
      icon: Icon(
        Icons.download_outlined,
        size: 20,
        color: color ?? AppColors.movementGreen,
      ),
      padding: EdgeInsets.zero,
      constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
      iconSize: 20,
    );
  }
}
