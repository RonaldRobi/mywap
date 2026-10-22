import 'package:flutter/material.dart';

import '../../../shared/l10n/l10n.dart';
import '../data/models/app_notification.dart';

/// Tajuk notifikasi daripada payload Laravel (`data.title`).
String notificationTitle(AppNotification notification) {
  return (notification.data['title'] as String?)?.trim() ?? '';
}

/// Kandungan penuh notifikasi (`data.content` atau `data.message`).
String notificationBody(AppNotification notification) {
  final content = (notification.data['content'] as String?)?.trim();
  if (content != null && content.isNotEmpty) return content;
  return (notification.data['message'] as String?)?.trim() ?? '';
}

/// Label jenis notifikasi untuk dipaparkan kepada pengguna.
String notificationTypeLabel(
  BuildContext context,
  AppNotification notification,
) {
  final type = _typeOf(notification);
  if (type.contains('announcement')) {
    return context.l10n.menuAnnouncements;
  }
  if (type.contains('infaq')) return context.l10n.menuInfaq;
  if (type.contains('event')) return context.l10n.notifTypeEvent;
  if (type.contains('fee')) return context.l10n.notifTypeFee;
  if (type.contains('form')) return context.l10n.notifTypeForm;
  if (type.contains('facility') || type.contains('booking')) {
    return context.l10n.notifTypeFacilityBooking;
  }
  if (type.contains('transition') ||
      type.contains('member') ||
      type.contains('registration')) {
    return context.l10n.notifTypeMembership;
  }
  if (type.contains('broadcast')) return context.l10n.notifTypeBroadcast;
  return context.l10n.notifTypeNotification;
}

IconData notificationIconFor(AppNotification notification) {
  final type = _typeOf(notification);
  if (type.contains('announcement')) return Icons.campaign_outlined;
  if (type.contains('infaq')) return Icons.volunteer_activism_outlined;
  if (type.contains('event')) return Icons.event_outlined;
  if (type.contains('fee')) return Icons.receipt_long_outlined;
  if (type.contains('form')) return Icons.assignment_outlined;
  if (type.contains('facility') || type.contains('booking')) {
    return Icons.meeting_room_outlined;
  }
  if (type.contains('transition') ||
      type.contains('member') ||
      type.contains('registration')) {
    return Icons.group_add_outlined;
  }
  if (type.contains('broadcast')) return Icons.campaign_outlined;
  return Icons.notifications_outlined;
}

/// Pautan tindakan pilihan (`data.action_url`) jika sah dan http(s).
Uri? notificationActionUri(AppNotification notification) {
  final raw = (notification.data['action_url'] as String?)?.trim();
  if (raw == null || raw.isEmpty) return null;
  final uri = Uri.tryParse(raw);
  if (uri == null || !uri.hasScheme) return null;
  if (uri.scheme != 'http' && uri.scheme != 'https') return null;
  return uri;
}

String _typeOf(AppNotification notification) {
  return '${notification.type ?? ''} ${notification.data['type'] ?? ''}'
      .toLowerCase();
}

String notificationTimeAgo(BuildContext context, DateTime? time) {
  if (time == null) return '';
  final diff = DateTime.now().difference(time);
  if (diff.inMinutes < 1) return context.l10n.notifJustNow;
  if (diff.inMinutes < 60) {
    return context.l10n.notifMinutesAgo('${diff.inMinutes}');
  }
  if (diff.inHours < 24) {
    return context.l10n.notifHoursAgo('${diff.inHours}');
  }
  if (diff.inDays < 7) {
    return context.l10n.notifDaysAgo('${diff.inDays}');
  }
  final d = time.day.toString().padLeft(2, '0');
  final m = time.month.toString().padLeft(2, '0');
  return '$d/$m/${time.year}';
}
