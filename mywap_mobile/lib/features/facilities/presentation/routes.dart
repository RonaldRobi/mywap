import 'package:go_router/go_router.dart';

import 'facilities_screen.dart';
import 'facility_detail_screen.dart';

/// Routes owned by the facilities feature.
final List<RouteBase> facilitiesRoutes = [
  GoRoute(
    path: '/facilities',
    builder: (_, __) => const FacilitiesScreen(),
  ),
  GoRoute(
    path: '/facilities/:id',
    builder: (_, state) => FacilityDetailScreen(
      facilityId: int.parse(state.pathParameters['id']!),
    ),
  ),
];
