import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/widgets/floating_nav_dock.dart';
import '../../auth/application/auth_controller.dart';

/// Scaffold wrapping the main member tabs with Apple Floating Navigation Dock.
class MainShell extends StatelessWidget {
  const MainShell({super.key, required this.child});

  final Widget child;

  int _currentIndex(BuildContext context) {
    final location = GoRouterState.of(context).uri.path;
    if (location.startsWith('/events')) return 1;
    if (location.startsWith('/infaq')) return 3;
    if (location.startsWith('/menu') ||
        location.startsWith('/profile')) {
      return 4;
    }
    return 0;
  }

  @override
  Widget build(BuildContext context) {
    final selectedIndex = _currentIndex(context);
    final router = GoRouter.of(context);

    return Scaffold(
      body: child,
      bottomNavigationBar: FloatingNavDock(
        items: [
          FloatingNavDockItem(
            icon: Icons.home_outlined,
            selectedIcon: Icons.home_rounded,
            label: context.l10n.navHomeMember,
            selected: selectedIndex == 0,
            onTap: () {
              if (router.state.matchedLocation != '/dashboard') {
                router.go('/dashboard');
              }
            },
          ),
          FloatingNavDockItem(
            icon: Icons.event_outlined,
            selectedIcon: Icons.event_rounded,
            label: context.l10n.navProgram,
            selected: selectedIndex == 1,
            onTap: () {
              if (router.state.matchedLocation != '/events') {
                router.go('/events');
              }
            },
          ),
          FloatingNavDockItem(
            icon: Icons.volunteer_activism_outlined,
            selectedIcon: Icons.volunteer_activism_rounded,
            label: context.l10n.navInfaq,
            selected: selectedIndex == 3,
            onTap: () {
              if (router.state.matchedLocation != '/infaq') {
                router.go('/infaq');
              }
            },
          ),
          FloatingNavDockItem(
            icon: Icons.grid_view_outlined,
            selectedIcon: Icons.grid_view_rounded,
            label: context.l10n.navMenu,
            selected: selectedIndex == 4,
            onTap: () {
              if (router.state.matchedLocation != '/menu') {
                router.go('/menu');
              }
            },
          ),
        ],
        onCenterTap: () => context.push('/scan'),
      ),
    );
  }
}

/// App bar action used across member screens to log out.
class LogoutIconButton extends ConsumerWidget {
  const LogoutIconButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return IconButton(
      tooltip: context.l10n.logout,
      onPressed: () => ref.read(authControllerProvider.notifier).logout(),
      icon: const Icon(Icons.logout, color: AppColors.movementGreen),
    );
  }
}

