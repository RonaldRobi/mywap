import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../shared/theme/app_colors.dart';
import '../../../../shared/theme/app_theme.dart';
import '../../../auth/application/auth_controller.dart';

/// Collapsible left sidebar (hamburger menu) — secondary navigation that
/// surfaces every member module grouped the same way as the web app's
/// sidebar (Kandungan / Kewangan / Mall / Perkhidmatan / Sumber & Keahlian /
/// Akaun), so nothing available on web is more than one tap away on mobile.
class AppSidebar extends ConsumerWidget {
  const AppSidebar({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);

    return Drawer(
      backgroundColor: AppColors.white,
      width: 300,
      child: SafeArea(
        child: Column(
          children: [
            _SidebarHeader(name: user?.name, member: user),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(vertical: Spacing.sm),
                children: [
                  _SidebarSection(
                    title: 'Kandungan',
                    items: [
                      _SidebarItem(
                        icon: Icons.info_outline,
                        label: 'Info Organisasi',
                        path: '/organization/info',
                      ),
                      _SidebarItem(
                        icon: Icons.event_outlined,
                        label: 'Program',
                        path: '/events',
                      ),
                      _SidebarItem(
                        icon: Icons.assignment_turned_in_outlined,
                        label: 'Pendaftaran Saya',
                        path: '/events/my-registrations',
                      ),
                      _SidebarItem(
                        icon: Icons.newspaper_outlined,
                        label: 'Info Terkini',
                        path: '/news',
                      ),
                      _SidebarItem(
                        icon: Icons.how_to_vote_outlined,
                        label: 'Undian',
                        path: '/polls',
                      ),
                      _SidebarItem(
                        icon: Icons.article_outlined,
                        label: 'Artikel',
                        path: '/articles',
                      ),
                      _SidebarItem(
                        icon: Icons.play_circle_outline,
                        label: 'Video',
                        path: '/videos',
                      ),
                      _SidebarItem(
                        icon: Icons.groups_outlined,
                        label: 'Usrah',
                        path: '/usrah',
                      ),
                    ],
                  ),
                  _SidebarSection(
                    title: 'Kewangan',
                    items: [
                      _SidebarItem(
                        icon: Icons.receipt_long_outlined,
                        label: 'Yuran & Bayaran',
                        path: '/member/financial/overview',
                      ),
                      _SidebarItem(
                        icon: Icons.volunteer_activism_outlined,
                        label: 'Infaq',
                        path: '/infaq',
                      ),
                    ],
                  ),
                  _SidebarSection(
                    title: 'myWAP Mall',
                    items: [
                      _SidebarItem(
                        icon: Icons.storefront_outlined,
                        label: 'Produk',
                        path: '/products',
                      ),
                      _SidebarItem(
                        icon: Icons.shopping_bag_outlined,
                        label: 'Pesanan Saya',
                        path: '/orders',
                      ),
                    ],
                  ),
                  _SidebarSection(
                    title: 'Perkhidmatan / Fasiliti',
                    items: [
                      _SidebarItem(
                        icon: Icons.apartment_outlined,
                        label: 'Tempah Fasiliti',
                        path: '/facilities',
                      ),
                    ],
                  ),
                  _SidebarSection(
                    title: 'Sumber & Keahlian',
                    items: [
                      _SidebarItem(
                        icon: Icons.local_library_outlined,
                        label: 'Pustaka',
                        path: '/member/library',
                      ),
                      _SidebarItem(
                        icon: Icons.campaign_outlined,
                        label: 'Pengumuman',
                        path: '/member/announcements',
                      ),
                      _SidebarItem(
                        icon: Icons.badge_outlined,
                        label: 'Kad Ahli',
                        path: '/card',
                      ),
                      _SidebarItem(
                        icon: Icons.person_add_alt_outlined,
                        label: 'Jemput Ahli',
                        path: '/member/referral',
                      ),
                      _SidebarItem(
                        icon: Icons.contacts_outlined,
                        label: 'Direktori Ahli',
                        path: '/directory',
                      ),
                      _SidebarItem(
                        icon: Icons.chat_outlined,
                        label: 'Chat',
                        path: '/chat',
                      ),
                    ],
                  ),
                  _SidebarSection(
                    title: 'Akaun',
                    items: [
                      _SidebarItem(
                        icon: Icons.person_outline,
                        label: 'Profil',
                        path: '/profile',
                      ),
                      _SidebarItem(
                        icon: Icons.route_outlined,
                        label: 'Perjalanan',
                        path: '/profile/journey',
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            ListTile(
              leading: const Icon(Icons.logout, color: AppColors.error),
              title: const Text(
                'Log Keluar',
                style: TextStyle(color: AppColors.error, fontWeight: FontWeight.w600),
              ),
              onTap: () => ref.read(authControllerProvider.notifier).logout(),
            ),
            const SizedBox(height: Spacing.sm),
          ],
        ),
      ),
    );
  }
}

class _SidebarHeader extends StatelessWidget {
  const _SidebarHeader({required this.name, required this.member});

  final String? name;
  final dynamic member;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.lg,
        Spacing.lg,
        Spacing.lg,
      ),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: AppColors.heroGradient,
        ),
      ),
      child: Row(
        children: [
          ClipOval(
            child: Container(
              width: 52,
              height: 52,
              color: AppColors.white.withValues(alpha: .16),
              child: const Icon(
                Icons.person,
                color: AppColors.white,
                size: 28,
              ),
            ),
          ),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name ?? 'Ahli myWAP',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppColors.white,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  member?.member_no ?? '',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textOnDark,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SidebarSection extends StatelessWidget {
  const _SidebarSection({required this.title, required this.items});

  final String title;
  final List<_SidebarItem> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            Spacing.lg,
            Spacing.lg,
            Spacing.lg,
            Spacing.xs,
          ),
          child: Text(
            title.toUpperCase(),
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: AppColors.textTertiary,
              letterSpacing: 0.6,
              fontSize: 11,
            ),
          ),
        ),
        for (final item in items) item,
      ],
    );
  }
}

class _SidebarItem extends StatelessWidget {
  const _SidebarItem({
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
      dense: true,
      visualDensity: const VisualDensity(vertical: -1),
      leading: Icon(icon, color: AppColors.movementGreen, size: 22),
      title: Text(
        label,
        style: Theme.of(
          context,
        ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
      ),
      onTap: () {
        Navigator.of(context).pop();
        context.push(path);
      },
    );
  }
}
