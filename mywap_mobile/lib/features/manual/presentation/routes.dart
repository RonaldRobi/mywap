import 'package:go_router/go_router.dart';

import 'manual_screen.dart';

/// Routes owned by the manual feature (Manual Pengguna).
final List<RouteBase> manualRoutes = [
  GoRoute(path: '/manual', builder: (_, __) => const ManualScreen()),
];
