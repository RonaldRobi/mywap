import 'package:go_router/go_router.dart';

import 'complete_profile_screen.dart';
import 'edit_profile_screen.dart';
import 'journey_screen.dart';
import 'profile_screen.dart';

/// Routes owned by the profile feature.
final List<RouteBase> profileRoutes = [
  GoRoute(path: '/profile', builder: (_, __) => const ProfileScreen()),
  GoRoute(path: '/profile/edit', builder: (_, __) => const EditProfileScreen()),
  GoRoute(
    path: '/profile/complete',
    builder: (_, __) => const CompleteProfileScreen(),
  ),
  GoRoute(path: '/profile/journey', builder: (_, __) => const JourneyScreen()),
];
