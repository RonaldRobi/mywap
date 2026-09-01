import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../auth/application/auth_controller.dart';
import 'widgets/app_sidebar.dart';
import 'widgets/shell_scaffold_key.dart';

/// Scaffold wrapping the main member tabs.
///
/// Layout (per redesign requirements):
/// - Collapsible left sidebar (hamburger) — [AppSidebar], opened via
///   [AppMenuButton] from each tab's own AppBar.
/// - Bottom navigation bar with a floating center "Imbas QR" button.
/// - Notification bell stays top-right on every tab (see [NotificationBell]
///   — each tab screen renders it itself since every tab keeps its own
///   AppBar/app bar actions).
class MainShell extends StatelessWidget {
  const MainShell({super.key, required this.child});

  final Widget child;

  int _currentIndex(BuildContext context) {
    final location = GoRouterState.of(context).uri.path;
    if (location.startsWith('/events')) return 1;
    if (location.startsWith('/infaq')) return 3;
    if (location.startsWith('/profile')) return 4;
    if (location.startsWith('/admin')) return 5;
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
      final scaffoldKey = ref.watch(mainShellScaffoldKeyProvider);

      final selectedIndex = _currentIndex(context);
      final maxIndex = isAdmin ? 5 : 4;
      final effectiveIndex = selectedIndex > maxIndex ? 0 : selectedIndex;

      return Scaffold(
        key: scaffoldKey,
        drawer: const AppSidebar(),
        body: child,
        floatingActionButtonLocation:
            FloatingActionButtonLocation.centerDocked,
        floatingActionButton: _ScanFab(
          onTap: () => context.push('/scan'),
        ),
        bottomNavigationBar: _BottomNavBar(
          selectedIndex: effectiveIndex,
          isAdmin: isAdmin,
          onSelect: (index) {
            final router = GoRouter.of(context);
            final paths = [
              '/dashboard',
              '/events',
              null, // center slot — QR scan FAB, not a tab.
              '/infaq',
              '/profile',
              '/admin',
            ];
            final path = paths[index];
            if (path == '/profile') {
              // /profile lives outside the shell's ShellRoute, so push
              // instead of go (keeps the bottom nav/shell chrome visible
              // when the user navigates back).
              context.push('/profile');
              return;
            }
            if (path != null && router.state.matchedLocation != path) {
              router.go(path);
            }
          },
        ),
      );
    });
  }
}

/// Floating center "Scan QR" button — docked into the bottom nav notch.
class _ScanFab extends StatelessWidget {
  const _ScanFab({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 54,
      height: 54,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: AppColors.heroGradient,
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
          child: const Icon(
            Icons.qr_code_scanner_rounded,
            color: AppColors.white,
            size: 25,
          ),
        ),
      ),
    );
  }
}

/// Bottom navigation bar with a notch reserved for the floating QR button.
class _BottomNavBar extends StatelessWidget {
  const _BottomNavBar({
    required this.selectedIndex,
    required this.isAdmin,
    required this.onSelect,
  });

  final int selectedIndex;
  final bool isAdmin;
  final void Function(int index) onSelect;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: BottomAppBar(
        color: AppColors.white,
        elevation: 0,
        shape: const CircularNotchedRectangle(),
        notchMargin: 6,
        height: 56,
        padding: EdgeInsets.zero,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            _NavItem(
              icon: Icons.home_outlined,
              selectedIcon: Icons.home,
              label: 'Utama',
              selected: selectedIndex == 0,
              onTap: () => onSelect(0),
            ),
            _NavItem(
              icon: Icons.event_outlined,
              selectedIcon: Icons.event,
              label: 'Acara',
              selected: selectedIndex == 1,
              onTap: () => onSelect(1),
            ),
            const SizedBox(width: 52), // reserved space for the notch/FAB
            _NavItem(
              icon: Icons.volunteer_activism_outlined,
              selectedIcon: Icons.volunteer_activism,
              label: 'Infaq',
              selected: selectedIndex == 3,
              onTap: () => onSelect(3),
            ),
            _NavItem(
              icon: Icons.person_outline,
              selectedIcon: Icons.person,
              label: 'Profil',
              selected: selectedIndex == 4,
              onTap: () => onSelect(4),
            ),
            if (isAdmin)
              _NavItem(
                icon: Icons.admin_panel_settings_outlined,
                selectedIcon: Icons.admin_panel_settings,
                label: 'Admin',
                selected: selectedIndex == 5,
                onTap: () => onSelect(5),
              ),
          ],
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
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
    return Expanded(
      child: InkWell(
        onTap: onTap,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(selected ? selectedIcon : icon, color: color, size: 22),
            const SizedBox(height: 1),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 10,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w600,
              ),
            ),
          ],
        ),
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
      tooltip: 'Log Keluar',
      onPressed: () => ref.read(authControllerProvider.notifier).logout(),
      icon: const Icon(Icons.logout, color: AppColors.movementGreen),
    );
  }
}
