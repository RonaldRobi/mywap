import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/l10n/l10n.dart';
import '../../../shared/widgets/floating_nav_dock.dart';
import '../../admin/presentation/admin_shell.dart';
import '../../auth/application/auth_controller.dart';
import '../../member/presentation/main_shell.dart';

/// Chooses the correct shell for the current auth state:
/// - logged in as admin in admin mode → [AdminShell] (admin tabs + card scan FAB)
/// - logged in as member (or admin in member view) → [MainShell] (member tabs + QR scan FAB)
/// - logged out → [PublicShell] (public tabs + Log Masuk)
///
/// All shells wrap the same routed `child`, so screens can be reused for
/// members, admins and guests without duplication.
class AdaptiveShell extends ConsumerWidget {
  const AdaptiveShell({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    if (user == null) {
      return PublicShell(child: child);
    }

    if (user.isAdmin) {
      return AdminShell(child: child);
    }

    return MainShell(child: child);
  }
}

/// Scaffold wrapping the public (guest) tabs with Apple Floating Navigation Dock.
class PublicShell extends ConsumerWidget {
  const PublicShell({super.key, required this.child});

  final Widget child;

  int _currentIndex(BuildContext context) {
    final location = GoRouterState.of(context).uri.path;
    if (location.startsWith('/events')) return 1;
    if (location.startsWith('/infaq')) return 3;
    if (location.startsWith('/menu')) return 4;
    return 0;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final selectedIndex = _currentIndex(context);
    final router = GoRouter.of(context);

    return Scaffold(
      body: child,
      bottomNavigationBar: FloatingNavDock(
        items: [
          FloatingNavDockItem(
            icon: Icons.home_outlined,
            selectedIcon: Icons.home_rounded,
            label: context.l10n.navHomeGuest,
            selected: selectedIndex == 0,
            onTap: () {
              if (router.state.matchedLocation != '/home') {
                router.go('/home');
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

