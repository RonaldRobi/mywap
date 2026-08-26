import 'package:flutter/material.dart';

import '../../../shared/theme/app_colors.dart';

/// Malay label for an order lifecycle status.
String orderStatusLabel(String? status) => switch (status) {
      'pending' => 'Menunggu Pembayaran',
      'paid' => 'Dibayar',
      'processing' => 'Sedang Diproses',
      'shipped' => 'Dihantar',
      'completed' => 'Selesai',
      'cancelled' => 'Dibatalkan',
      _ => status ?? '-',
    };

Color orderStatusColor(String? status) => switch (status) {
      'pending' => AppColors.warning,
      'paid' => AppColors.success,
      'processing' => AppColors.movementGreen,
      'shipped' => AppColors.movementSoftGreen,
      'completed' => AppColors.success,
      'cancelled' => AppColors.error,
      _ => AppColors.textSecondary,
    };

/// Malay label for a payment status.
String paymentStatusLabel(String? status) => switch (status) {
      'successful' => 'Berjaya',
      'pending' => 'Menunggu',
      'failed' => 'Gagal',
      _ => status ?? '-',
    };

/// Formats an ISO-8601 datetime as `d MMM yyyy` (Malay months) without
/// depending on intl locale data initialization.
String formatOrderDate(String? iso) {
  if (iso == null || iso.isEmpty) return '-';
  final dt = DateTime.tryParse(iso);
  if (dt == null) return iso;
  const months = [
    'Jan',
    'Feb',
    'Mac',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Ogo',
    'Sep',
    'Okt',
    'Nov',
    'Dis',
  ];
  return '${dt.day} ${months[dt.month - 1]} ${dt.year}';
}
