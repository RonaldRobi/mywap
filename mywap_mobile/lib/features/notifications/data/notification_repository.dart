import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/app_notification.dart';

/// Satu muatan senarai notifikasi bersama jumlah sebenar belum dibaca.
///
/// `unreadCount` diambil dari `unread_count` backend (bukan dikira dari
/// senarai 20 item) supaya badge ikon app kekal tepat walaupun notifikasi
/// melebihi 20.
class NotificationsPage {
  const NotificationsPage({
    required this.notifications,
    required this.unreadCount,
  });

  final List<AppNotification> notifications;
  final int unreadCount;
}

class NotificationRepository {
  NotificationRepository(this._api);

  final ApiClient _api;

  Future<NotificationsPage> list() async {
    final data = await _api.get(ApiPaths.notifications);
    if (data is! Map<String, dynamic>) {
      return const NotificationsPage(notifications: [], unreadCount: 0);
    }

    final items = data['notifications'];
    final notifications =
        items is List
            ? items
                .whereType<Map<String, dynamic>>()
                .map(AppNotification.fromJson)
                .toList(growable: false)
            : const <AppNotification>[];

    final rawUnread = data['unread_count'];
    final unreadCount =
        rawUnread is int
            ? rawUnread
            : notifications.where((n) => !n.isRead).length;

    return NotificationsPage(
      notifications: notifications,
      unreadCount: unreadCount,
    );
  }

  Future<void> read(String id) async {
    await _api.post(ApiPaths.notificationRead(id));
  }

  Future<void> readAll() async {
    await _api.post(ApiPaths.notificationsReadAll);
  }
}
