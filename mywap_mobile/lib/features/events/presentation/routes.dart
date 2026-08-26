import 'package:go_router/go_router.dart';

import 'my_registrations_screen.dart';

/// Routes owned by the events feature (detail + secondary screens).
/// The `/events` tab and `/events/:id` detail live in the central router.
final List<RouteBase> eventsRoutes = [
  GoRoute(
    path: '/events/my-registrations',
    builder: (_, __) => const MyRegistrationsScreen(),
  ),
];
