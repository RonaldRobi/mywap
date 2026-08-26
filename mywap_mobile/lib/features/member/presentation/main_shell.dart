import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/app_colors.dart';
import '../../auth/application/auth_controller.dart';

/// Scaffold with a bottom navigation bar wrapping the main member routes.
class MainShell extends StatelessWidget {
  const MainShell({super.key, required this.child});

  final Widget child;

  int _currentIndex(BuildContext context) {
    final location = GoRouterState.of(context).uri.path;
    if (location.startsWith('/events')) return 1;
    if (location.startsWith('/infaq')) return 2;
    if (location.startsWith('/menu')) return 3;
    if (location.startsWith('/admin')) return 4;
    return 0;
  }

  bool _isAdmin(String? role) =>
      role == 'Admin' || role == 'org-admin' || role == 'Superadmin';

  @override
  Widget build(BuildContext context) {
    return Consumer(builder: (context, ref, _) {
      final authState = ref.watch(authControllerProvider);
      final user = authState is AuthAuthenticated ? authState.user : null;
      final isAdmin =
          user != null && (user.roles ?? const []).any(_isAdmin);

      final destinations = <NavigationDestination>[
        const NavigationDestination(
          icon: Icon(Icons.home_outlined),
          selectedIcon: Icon(Icons.home),
          label: 'Utama',
        ),
        const NavigationDestination(
          icon: Icon(Icons.event_outlined),
          selectedIcon: Icon(Icons.event),
          label: 'Acara',
        ),
        const NavigationDestination(
          icon: Icon(Icons.volunteer_activism_outlined),
          selectedIcon: Icon(Icons.volunteer_activism),
          label: 'Infaq',
        ),
        const NavigationDestination(
          icon: Icon(Icons.grid_view_outlined),
          selectedIcon: Icon(Icons.grid_view),
          label: 'Menu',
        ),
        if (isAdmin)
          const NavigationDestination(
            icon: Icon(Icons.admin_panel_settings_outlined),
            selectedIcon: Icon(Icons.admin_panel_settings),
            label: 'Admin',
          ),
      ];

      final selectedIndex = _currentIndex(context);
      final effectiveIndex = !isAdmin && selectedIndex == 4 ? 3 : selectedIndex;

      return Scaffold(
        body: child,
        bottomNavigationBar: NavigationBar(
          selectedIndex: effectiveIndex,
          onDestinationSelected: (index) {
            final router = GoRouter.of(context);
            final paths = ['/dashboard', '/events', '/infaq', '/menu', '/admin'];
            final path = paths[index];
            if (router.state.matchedLocation != path) {
              router.go(path);
            }
          },
          backgroundColor: AppColors.white,
          indicatorColor: AppColors.movementSoftGreen,
          labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
          destinations: destinations,
        ),
      );
    });
  }
}

/// App bar action used across member screens to log out.
class LogoutIconButton extends ConsumerWidget {
  const LogoutIconButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return IconButton(
      tooltip: 'Log Keluar',
      onPressed: () => ref.read(authControllerProvider.notifier).logout(),
      icon: const Icon(Icons.logout, color: AppColors.movementGreen),
    );
  }
}
