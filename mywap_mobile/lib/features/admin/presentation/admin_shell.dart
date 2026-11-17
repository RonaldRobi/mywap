import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_text_theme.dart';
import '../../../shared/theme/app_theme.dart';

/// Scaffold utama bagi ruang kerja pentadbir (Admin Workspace Shell).
///
/// Menyediakan navigasi bawah khusus untuk pentadbir:
/// - Tab 0: Ringkasan Pentadbir (`/admin`)
/// - Tab 1: Program & Kehadiran (`/admin/attendance`)
/// - FAB Tengah: Imbas Kad Ahli Segera (`/admin/scan`)
/// - Tab 3: Direktori Ahli (`/admin/members`)
/// - Tab 4: Menu Operasi Pentadbir (`/admin/menu`)
class AdminShell extends StatelessWidget {
  const AdminShell({super.key, required this.child});

  final Widget child;

  static const List<String?> _tabPaths = [
    '/admin',
    '/admin/attendance',
    null,
    '/admin/members',
    '/admin/menu',
  ];

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
    return Scaffold(
      body: child,
      floatingActionButtonLocation: FloatingActionButtonLocation.centerDocked,
      floatingActionButton: _AdminScanFab(
        onTap: () => context.push('/admin/scan'),
      ),
      bottomNavigationBar: _AdminBottomNavBar(
        selectedIndex: _currentIndex(context),
        onSelect: (index) {
          final router = GoRouter.of(context);
          final path = _tabPaths[index];
          if (path != null && router.state.matchedLocation != path) {
            router.go(path);
          }
        },
      ),
    );
  }
}

/// Butang tengah "Imbas Kad Ahli" bagi pentadbir acara.
class _AdminScanFab extends StatelessWidget {
  const _AdminScanFab({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 56,
      height: 56,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.movementGreen,
            AppColors.movementNavy,
          ],
        ),
        boxShadow: AppShadows.floating,
        border: Border.all(color: AppColors.white, width: 3),
      ),
      child: Material(
        color: Colors.transparent,
        shape: const CircleBorder(),
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: onTap,
          child: const Tooltip(
            message: 'Imbas Kad Kehadiran',
            child: Icon(
              Icons.qr_code_scanner_rounded,
              color: AppColors.white,
              size: 26,
            ),
          ),
        ),
      ),
    );
  }
}

class _AdminBottomNavBar extends StatelessWidget {
  const _AdminBottomNavBar({
    required this.selectedIndex,
    required this.onSelect,
  });

  final int selectedIndex;
  final void Function(int index) onSelect;

  @override
  Widget build(BuildContext context) {
    return BottomAppBar(
      color: AppColors.white,
      elevation: 0,
      shape: const CircularNotchedRectangle(),
      notchMargin: 6,
      height: AppSizes.bottomNavHeight,
      padding: EdgeInsets.zero,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _AdminNavItem(
            icon: Icons.dashboard_outlined,
            selectedIcon: Icons.dashboard_rounded,
            label: 'Utama',
            selected: selectedIndex == 0,
            onTap: () => onSelect(0),
          ),
          _AdminNavItem(
            icon: Icons.event_note_outlined,
            selectedIcon: Icons.event_note_rounded,
            label: 'Program',
            selected: selectedIndex == 1,
            onTap: () => onSelect(1),
          ),
          const SizedBox(width: 48), // Ruang takuk FAB
          _AdminNavItem(
            icon: Icons.people_outline_rounded,
            selectedIcon: Icons.people_rounded,
            label: 'Ahli',
            selected: selectedIndex == 3,
            onTap: () => onSelect(3),
          ),
          _AdminNavItem(
            icon: Icons.grid_view_outlined,
            selectedIcon: Icons.grid_view_rounded,
            label: 'Operasi',
            selected: selectedIndex == 4,
            onTap: () => onSelect(4),
          ),
        ],
      ),
    );
  }
}

class _AdminNavItem extends StatelessWidget {
  const _AdminNavItem({
    required this.icon,
    required this.selectedIcon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final IconData selectedIcon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.movementGreen : AppColors.textSecondary;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              selected ? selectedIcon : icon,
              color: color,
              size: 22,
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: AppTextTheme.minSize,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

