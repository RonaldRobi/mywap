import 'package:go_router/go_router.dart';

import '../../chat/presentation/chat_screen.dart';
import '../../notifications/presentation/notifications_screen.dart';
import 'directory_screen.dart';

/// Routes owned by the directory feature (directory, chat & notifications).
final List<RouteBase> directoryRoutes = [
  GoRoute(
    path: '/directory',
    builder: (_, __) => const DirectoryScreen(),
  ),
  GoRoute(
    path: '/chat',
    builder: (_, __) => const ChatScreen(),
  ),
  GoRoute(
    path: '/notifications',
    builder: (_, __) => const NotificationsScreen(),
  ),
];
