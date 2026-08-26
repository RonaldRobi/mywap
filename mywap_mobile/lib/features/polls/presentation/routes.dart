import 'package:go_router/go_router.dart';

import 'poll_detail_screen.dart';
import 'poll_results_screen.dart';
import 'polls_screen.dart';

/// Routes owned by the polls feature.
final List<RouteBase> pollsRoutes = [
  GoRoute(
    path: '/polls',
    builder: (_, __) => const PollsScreen(),
  ),
  GoRoute(
    path: '/polls/:id',
    builder: (_, state) => PollDetailScreen(
      pollId: int.parse(state.pathParameters['id']!),
    ),
  ),
  GoRoute(
    path: '/polls/:id/results',
    builder: (_, state) => PollResultsScreen(
      pollId: int.parse(state.pathParameters['id']!),
    ),
  ),
];
