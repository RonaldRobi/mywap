import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/l10n/l10n.dart';
import '../../../shared/widgets/floating_nav_dock.dart';

/// Scaffold utama bagi ruang kerja pentadbir (Admin Workspace Shell)
/// dilengkapi Apple Floating Navigation Dock.
///
/// Menyediakan navigasi bawah khusus untuk pentadbir:
/// - Tab 0: Ringkasan Pentadbir (`/admin`)
/// - Tab 1: Program & Kehadiran (`/admin/attendance`)
/// - Butang Teras Tengah: Imbas Kad Ahli Segera (`/admin/scan`)
/// - Tab 3: Direktori Ahli (`/admin/members`)
/// - Tab 4: Menu Operasi Pentadbir (`/admin/menu`)
class AdminShell extends StatelessWidget {
  const AdminShell({super.key, required this.child});

  final Widget child;

  int _currentIndex(BuildContext context) {
    final location = GoRouterState.of(context).uri.path;
    if (location.startsWith('/admin/attendance')) return 1;
    if (location.startsWith('/admin/members')) return 3;
    if (location.startsWith('/admin/menu') ||
        location.startsWith('/admin/fees') ||
        location.startsWith('/admin/broadcast')) {
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
        centerTooltip: context.l10n.adminScanAttendanceCard,
        items: [
          FloatingNavDockItem(
            icon: Icons.dashboard_outlined,
            selectedIcon: Icons.dashboard_rounded,
            label: context.l10n.adminNavHome,
            selected: selectedIndex == 0,
            onTap: () {
              if (router.state.matchedLocation != '/admin') {
                router.go('/admin');
              }
            },
          ),
          FloatingNavDockItem(
            icon: Icons.event_note_outlined,
            selectedIcon: Icons.event_note_rounded,
            label: context.l10n.navProgram,
            selected: selectedIndex == 1,
            onTap: () {
              if (router.state.matchedLocation != '/admin/attendance') {
                router.go('/admin/attendance');
              }
            },
          ),
          FloatingNavDockItem(
            icon: Icons.people_outline_rounded,
            selectedIcon: Icons.people_rounded,
            label: context.l10n.adminMembersTab,
            selected: selectedIndex == 3,
            onTap: () {
              if (router.state.matchedLocation != '/admin/members') {
                router.go('/admin/members');
              }
            },
          ),
          FloatingNavDockItem(
            icon: Icons.grid_view_outlined,
            selectedIcon: Icons.grid_view_rounded,
            label: context.l10n.adminNavOperations,
            selected: selectedIndex == 4,
            onTap: () {
              if (router.state.matchedLocation != '/admin/menu') {
                router.go('/admin/menu');
              }
            },
          ),
        ],
        onCenterTap: () => context.push('/admin/scan'),
      ),
    );
  }
}


