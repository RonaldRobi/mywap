import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../auth/application/auth_controller.dart';
import '../../../shared/theme/app_text_theme.dart';

/// Scaffold wrapping the main member tabs.
///
/// Navigasi (selepas audit UX):
/// - **Tiada drawer.** Drawer `Scaffold` lama memasang pengecam seretan pada
///   tepi kiri skrin, zon yang sama digunakan oleh gesture "swipe untuk
///   kembali" iOS dan predictive-back Android. Drawer menang perebutan itu,
///   jadi pengguna kehilangan gesture kembali pada setiap skrin. Semua
///   pautan kini berada dalam tab `Menu` — rujuk `MenuScreen`.
/// - Bar navigasi bawah dengan butang "Imbas QR" terapung di tengah.
/// - Loceng notifikasi kekal di kanan atas setiap tab (lihat
///   `NotificationBell` — setiap skrin tab merendernya sendiri kerana setiap
///   tab mengekalkan AppBar sendiri).
class MainShell extends StatelessWidget {
  const MainShell({super.key, required this.child});

  final Widget child;

  /// Laluan bagi setiap slot tab. Indeks 2 ialah takuk FAB, bukan tab.
  static const List<String?> _tabPaths = [
    '/dashboard',
    '/events',
    null,
    '/infaq',
    '/menu',
  ];

  int _currentIndex(BuildContext context) {
    final location = GoRouterState.of(context).uri.path;
    if (location.startsWith('/events')) return 1;
    if (location.startsWith('/infaq')) return 3;
    // Profil, admin dan setiap destinasi menu lain berada di bawah tab Menu,
    // jadi tab itu kekal diserlahkan semasa menyemak imbas cabangnya.
    if (location.startsWith('/menu') ||
        location.startsWith('/profile') ||
        location.startsWith('/admin')) {
      return 4;
    }
    return 0;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: child,
      floatingActionButtonLocation: FloatingActionButtonLocation.centerDocked,
      floatingActionButton: _ScanFab(onTap: () => context.push('/scan')),
      bottomNavigationBar: _BottomNavBar(
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
  const _BottomNavBar({required this.selectedIndex, required this.onSelect});

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
          _NavItem(
            icon: Icons.home_outlined,
            selectedIcon: Icons.home,
            label: context.l10n.navHomeMember,
            selected: selectedIndex == 0,
            onTap: () => onSelect(0),
          ),
          _NavItem(
            icon: Icons.event_outlined,
            selectedIcon: Icons.event,
            label: context.l10n.navProgram,
            selected: selectedIndex == 1,
            onTap: () => onSelect(1),
          ),
          const SizedBox(width: 52), // ruang untuk takuk/FAB
          _NavItem(
            icon: Icons.volunteer_activism_outlined,
            selectedIcon: Icons.volunteer_activism,
            label: context.l10n.navInfaq,
            selected: selectedIndex == 3,
            onTap: () => onSelect(3),
          ),
          // Menu menggantikan tab Profil dan Admin: bilangan tab kekal lima
          // untuk semua peranan, dan setiap pautan lama drawer kini ada di
          // sini. Profil ialah kad pertama dalam hab tersebut.
          _NavItem(
            icon: Icons.grid_view_outlined,
            selectedIcon: Icons.grid_view_rounded,
            label: context.l10n.navMenu,
            selected: selectedIndex == 4,
            onTap: () => onSelect(4),
          ),
        ],
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
            Icon(selected ? selectedIcon : icon, color: color, size: 24),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: AppTextTheme.minSize,
                fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
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
      tooltip: context.l10n.logout,
      onPressed: () => ref.read(authControllerProvider.notifier).logout(),
      icon: const Icon(Icons.logout, color: AppColors.movementGreen),
    );
  }
}
