import 'package:go_router/go_router.dart';

import 'settings_screen.dart';

/// Routes owned by the settings feature.
final List<RouteBase> settingsRoutes = [
  GoRoute(path: '/settings', builder: (_, __) => const SettingsScreen()),
];
