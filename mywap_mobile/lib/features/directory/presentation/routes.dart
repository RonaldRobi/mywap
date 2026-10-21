import 'package:go_router/go_router.dart';

import '../../notifications/data/models/app_notification.dart';
import '../../notifications/presentation/notification_detail_screen.dart';
import '../../notifications/presentation/notifications_screen.dart';

/// Routes untuk notifikasi. (Ahli-directory & chat screen digantung dari
/// mobile apps — bakal diputuskan kemudian.)
final List<RouteBase> directoryRoutes = [
  GoRoute(
    path: '/notifications',
    builder: (_, __) => const NotificationsScreen(),
  ),
  GoRoute(
    path: '/notifications/detail',
    builder:
        (_, state) => NotificationDetailScreen(
          notification: state.extra is AppNotification
              ? state.extra as AppNotification
              : null,
        ),
  ),
];
