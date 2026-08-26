import 'package:go_router/go_router.dart';

import 'form_screen.dart';

/// Routes owned by the forms feature.
final List<RouteBase> formsRoutes = [
  GoRoute(
    path: '/forms/:token',
    builder: (_, state) => FormScreen(
      token: state.pathParameters['token'] ?? '',
    ),
  ),
];
