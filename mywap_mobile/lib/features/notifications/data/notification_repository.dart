import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/app_notification.dart';

class NotificationRepository {
  NotificationRepository(this._api);

  final ApiClient _api;

  Future<List<AppNotification>> list() async {
    final data = await _api.get(ApiPaths.notifications);
    if (data is! Map<String, dynamic>) return const [];
    final items = data['notifications'];
    if (items is! List) return const [];
    return items
        .whereType<Map<String, dynamic>>()
        .map(AppNotification.fromJson)
        .toList(growable: false);
  }

  Future<void> readAll() async {
    await _api.post(ApiPaths.notificationsReadAll);
  }
}
