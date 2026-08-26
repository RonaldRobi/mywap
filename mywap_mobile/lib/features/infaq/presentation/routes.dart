import 'package:go_router/go_router.dart';

import 'infaq_detail_screen.dart';

/// Routes owned by the infaq feature. `/infaq` (the tab) lives in the central
/// router; only the detail route is declared here.
final List<RouteBase> infaqRoutes = [
  GoRoute(
    path: '/infaq/:slug',
    builder: (_, state) => InfaqDetailScreen(
      slug: state.pathParameters['slug']!,
    ),
  ),
];
