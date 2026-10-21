import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../auth/application/auth_controller.dart';
import '../../member/presentation/main_shell.dart';
import '../../../shared/theme/app_text_theme.dart';

/// Chooses the correct shell for the current auth state:
/// - logged in  → [MainShell] (member tabs + QR scan FAB)
/// - logged out → [PublicShell] (public tabs + Log Masuk)
///
/// Both shells wrap the same routed `child`, so screens can be reused for
/// members and guests without duplication.
class AdaptiveShell extends ConsumerWidget {
  const AdaptiveShell({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isAuthenticated = ref.watch(currentUserProvider) != null;
    return isAuthenticated
        ? MainShell(child: child)
        : PublicShell(child: child);
  }
}

/// Scaffold wrapping the public (guest) tabs.
///
/// Sama seperti [MainShell], tiada drawer dipasang di sini: pengecam seretan
/// tepi drawer melumpuhkan gesture kembali asli platform. Pautan awam berada
/// dalam tab `Menu`.
class PublicShell extends ConsumerWidget {
  const PublicShell({super.key, required this.child});

  final Widget child;

  /// Laluan bagi setiap slot tab. Indeks 2 ialah takuk FAB, bukan tab.
  static const List<String?> _tabPaths = [
    '/home',
    '/events',
    null,
    '/infaq',
    '/menu',
  ];

  int _currentIndex(BuildContext context) {
    final location = GoRouterState.of(context).uri.path;
    if (location.startsWith('/events')) return 1;
    if (location.startsWith('/infaq')) return 3;
    if (location.startsWith('/menu')) return 4;
    return 0;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      body: child,
      floatingActionButtonLocation: FloatingActionButtonLocation.centerDocked,
      floatingActionButton: _PublicScanFab(onTap: () => context.push('/scan')),
      bottomNavigationBar: _PublicBottomNav(
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

/// Butang tengah "Kamera / Imbas QR" — tetamu boleh imbas kod QR program.
/// Jika mereka belum log masuk, skrin imbasan akan menggesa log masuk untuk
/// merekod kehadiran.
class _PublicScanFab extends StatelessWidget {
  const _PublicScanFab({required this.onTap});
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

class _PublicBottomNav extends StatelessWidget {
  const _PublicBottomNav({required this.selectedIndex, required this.onSelect});

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
            label: 'Beranda',
            selected: selectedIndex == 0,
            onTap: () => onSelect(0),
          ),
          _NavItem(
            icon: Icons.event_outlined,
            selectedIcon: Icons.event,
            label: 'Program',
            selected: selectedIndex == 1,
            onTap: () => onSelect(1),
          ),
          const SizedBox(width: 52), // ruang untuk butang kamera
          _NavItem(
            icon: Icons.volunteer_activism_outlined,
            selectedIcon: Icons.volunteer_activism,
            label: 'Infaq',
            selected: selectedIndex == 3,
            onTap: () => onSelect(3),
          ),
          // Artikel, Video, Info Organisasi dan lain-lain pautan awam kini
          // berada dalam hab Menu, sejajar dengan shell ahli.
          _NavItem(
            icon: Icons.grid_view_outlined,
            selectedIcon: Icons.grid_view_rounded,
            label: 'Menu',
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
