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

/// Best-effort deep-link into a courier's tracking page for a known courier
/// name, interpolating `{no}` with the tracking number where the courier
/// supports query-based tracking. Returns `null` for unrecognised couriers —
/// the caller then falls back to copying the tracking number to the clipboard.
String? courierTrackingUrl(String? courier, String? trackingNo) {
  final name = courier?.trim().toLowerCase() ?? '';
  final number = trackingNo?.trim() ?? '';
  if (name.isEmpty || number.isEmpty) return null;

  String? template;
  if (name.contains('pos laju') ||
      name.contains('poslaju') ||
      name.contains('pos malaysia')) {
    template = 'https://www.pos.com.my/track-trace';
  } else if (name.contains('j&t') ||
      name.contains('j and t') ||
      name == 'jt' ||
      name.contains('jt express')) {
    template = 'https://www.jtexpress.my/tracking?trackingNo={no}';
  } else if (name.contains('city-link') || name.contains('citylink')) {
    template = 'https://www.citylinkexpress.com/track-trace';
  } else if (name.contains('dhl')) {
    template = 'https://www.dhl.com/my-en/home/tracking.html?tracking-id={no}';
  } else if (name.contains('gdex') || name.contains('gdexpress')) {
    template = 'https://www.gdexpress.com/track/';
  } else if (name.contains('ninja')) {
    template = 'https://www.ninjavan.co/en-my/tracking?id={no}';
  } else if (name.contains('fedex')) {
    template = 'https://www.fedex.com/fedextrack/?trknbr={no}';
  } else if (name.contains('ups')) {
    template = 'https://www.ups.com/track?tracknum={no}';
  } else if (name.contains('flash')) {
    template = 'https://www.flashexpress.my/track/';
  } else if (name.contains('pgeon')) {
    template = 'https://www.pgeon.my/';
  } else if (name.contains('abx')) {
    template = 'https://abxexpress.com.my/';
  } else if (name.contains('skynet')) {
    template = 'https://www.skynet.com.my/';
  }

  if (template == null) return null;
  return template.replaceFirst('{no}', Uri.encodeComponent(number));
}
