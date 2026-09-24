import 'package:flutter/material.dart';

import '../../../../shared/theme/app_colors.dart';
import '../../../../shared/theme/app_theme.dart';

/// Label Melayu untuk status kelulusan tempahan.
String bookingStatusLabel(String? status) => switch (status) {
  'approved' => 'Diluluskan',
  'rejected' => 'Ditolak',
  'pending' => 'Menunggu',
  _ => status ?? '-',
};

/// Label Melayu untuk status bayaran tempahan.
String paymentStatusLabel(String? status) => switch (status) {
  'paid' => 'Dibayar',
  'unpaid' => 'Belum Dibayar',
  _ => status ?? '-',
};

class BookingStatusChip extends StatelessWidget {
  const BookingStatusChip({super.key, this.status});

  final String? status;

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'approved' => AppColors.success,
      'rejected' => AppColors.error,
      'pending' => AppColors.warning,
      _ => AppColors.textSecondary,
    };
    return _StatusChip(label: bookingStatusLabel(status), color: color);
  }
}

class PaymentStatusChip extends StatelessWidget {
  const PaymentStatusChip({super.key, this.status});

  final String? status;

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'paid' => AppColors.success,
      'unpaid' => AppColors.warning,
      _ => AppColors.textSecondary,
    };
    return _StatusChip(label: paymentStatusLabel(status), color: color);
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.sm, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w600,
          color: color,
        ),
      ),
    );
  }
}
