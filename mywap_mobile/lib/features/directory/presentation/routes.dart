import 'package:go_router/go_router.dart';

import '../../notifications/presentation/notifications_screen.dart';

/// Routes untuk notifikasi. (Ahli-directory & chat screen digantung dari
/// mobile apps — bakal diputuskan kemudian.)
final List<RouteBase> directoryRoutes = [
  GoRoute(
    path: '/notifications',
    builder: (_, __) => const NotificationsScreen(),
  ),
];
