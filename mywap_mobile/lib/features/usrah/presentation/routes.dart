import 'package:go_router/go_router.dart';

import 'usrah_screen.dart';

/// Routes owned by the usrah feature.
final List<RouteBase> usrahRoutes = [
  GoRoute(
    path: '/usrah',
    builder: (_, __) => const UsrahScreen(),
  ),
];
