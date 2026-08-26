import 'package:go_router/go_router.dart';

import 'admin_attendance_detail_screen.dart';
import 'admin_attendance_screen.dart';
import 'admin_broadcast_screen.dart';
import 'admin_dashboard_screen.dart';
import 'admin_fees_screen.dart';
import 'admin_members_screen.dart';

/// Routes owned by the admin feature.
///
/// `/admin` is the Admin bottom-nav tab (also registered by the central shell
/// route — this registration is the source of truth for the sub-routes below).
final List<RouteBase> adminRoutes = [
  GoRoute(path: '/admin', builder: (_, __) => const AdminDashboardScreen()),
  GoRoute(path: '/admin/members', builder: (_, __) => const AdminMembersScreen()),
  GoRoute(path: '/admin/fees', builder: (_, __) => const AdminFeesScreen()),
  GoRoute(path: '/admin/attendance', builder: (_, __) => const AdminAttendanceScreen()),
  GoRoute(
    path: '/admin/attendance/:eventId',
    builder: (_, state) => AdminAttendanceDetailScreen(
      eventId: int.parse(state.pathParameters['eventId']!),
    ),
  ),
  GoRoute(path: '/admin/broadcast', builder: (_, __) => const AdminBroadcastScreen()),
];
