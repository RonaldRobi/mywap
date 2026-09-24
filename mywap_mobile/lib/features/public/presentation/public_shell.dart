import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../auth/application/auth_controller.dart';
import '../../member/presentation/main_shell.dart';
import '../../member/presentation/widgets/shell_scaffold_key.dart';
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
class PublicShell extends ConsumerWidget {
  const PublicShell({super.key, required this.child});

  final Widget child;

  int _currentIndex(BuildContext context) {
    final location = GoRouterState.of(context).uri.path;
    if (location.startsWith('/articles')) return 1;
    if (location.startsWith('/infaq')) return 2;
    if (location.startsWith('/events')) return 3;
    return 0;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final scaffoldKey = ref.watch(publicShellScaffoldKeyProvider);
    final selectedIndex = _currentIndex(context);

    return Scaffold(
      key: scaffoldKey,
      drawer: const PublicDrawer(),
      body: child,
      floatingActionButtonLocation: FloatingActionButtonLocation.centerDocked,
      floatingActionButton: _PublicScanFab(onTap: () => context.push('/scan')),
      bottomNavigationBar: _PublicBottomNav(
        selectedIndex: selectedIndex,
        onSelect: (index) {
          final router = GoRouter.of(context);
          final paths = ['/home', '/articles', '/infaq', '/events'];
          final path = paths[index];
          if (router.state.matchedLocation != path) router.go(path);
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
      height: 60,
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
            icon: Icons.article_outlined,
            selectedIcon: Icons.article,
            label: 'Artikel',
            selected: selectedIndex == 1,
            onTap: () => onSelect(1),
          ),
          const SizedBox(width: 52), // ruang untuk butang kamera
          _NavItem(
            icon: Icons.volunteer_activism_outlined,
            selectedIcon: Icons.volunteer_activism,
            label: 'Infaq',
            selected: selectedIndex == 2,
            onTap: () => onSelect(2),
          ),
          _NavItem(
            icon: Icons.event_outlined,
            selectedIcon: Icons.event,
            label: 'Program',
            selected: selectedIndex == 3,
            onTap: () => onSelect(3),
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

/// Drawer shown to guests — mirrors the public sections of the app.
class PublicDrawer extends StatelessWidget {
  const PublicDrawer({super.key});

  @override
  Widget build(BuildContext context) {
    return Drawer(
      backgroundColor: AppColors.white,
      width: 300,
      child: SafeArea(
        child: Column(
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(Spacing.xl),
              decoration: const BoxDecoration(
                gradient: LinearGradient(colors: AppColors.heroGradient),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'myWAP',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      color: AppColors.white,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: Spacing.xs),
                  const Text(
                    'Kandungan komuniti untuk semua',
                    style: TextStyle(
                      color: AppColors.textOnDark,
                      fontSize: AppTextTheme.minSize,
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(vertical: Spacing.sm),
                children: [
                  _DrawerItem(
                    icon: Icons.home_outlined,
                    label: 'Beranda',
                    path: '/home',
                  ),
                  _DrawerItem(
                    icon: Icons.article_outlined,
                    label: 'Artikel',
                    path: '/articles',
                  ),
                  _DrawerItem(
                    icon: Icons.newspaper_outlined,
                    label: 'Info Terkini',
                    path: '/news',
                  ),
                  _DrawerItem(
                    icon: Icons.event_outlined,
                    label: 'Program & Acara',
                    path: '/events',
                  ),
                  _DrawerItem(
                    icon: Icons.volunteer_activism_outlined,
                    label: 'Infaq & Sumbangan',
                    path: '/infaq',
                  ),
                  _DrawerItem(
                    icon: Icons.play_circle_outline,
                    label: 'Video',
                    path: '/videos',
                  ),
                  _DrawerItem(
                    icon: Icons.info_outline,
                    label: 'Info Organisasi',
                    path: '/organization/info',
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: Column(
                children: [
                  FilledButton(
                    onPressed: () {
                      Navigator.of(context).pop();
                      context.go('/login');
                    },
                    child: const Text('Log Masuk'),
                  ),
                  const SizedBox(height: Spacing.sm),
                  OutlinedButton(
                    onPressed: () {
                      Navigator.of(context).pop();
                      context.go('/register');
                    },
                    child: const Text('Daftar Ahli'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DrawerItem extends StatelessWidget {
  const _DrawerItem({
    required this.icon,
    required this.label,
    required this.path,
  });

  final IconData icon;
  final String label;
  final String path;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon, color: AppColors.movementGreen),
      title: Text(label),
      onTap: () {
        Navigator.of(context).pop();
        context.go(path);
      },
    );
  }
}
