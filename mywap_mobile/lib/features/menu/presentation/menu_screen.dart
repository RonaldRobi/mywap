import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../member/presentation/main_shell.dart';

/// Menu grid linking to every feature screen (§5).
class MenuScreen extends StatelessWidget {
  const MenuScreen({super.key});

  static const List<_MenuItem> _items = [
    _MenuItem('Profil', '/profile', Icons.person_outline, 'Maklumat akaun anda'),
    _MenuItem('Kad Ahli', '/card', Icons.qr_code_2, 'Kad digital & QR'),
    _MenuItem('Pengumuman', '/member/announcements', Icons.campaign_outlined, 'Makluman terkini'),
    _MenuItem('Pustaka', '/member/library', Icons.local_library_outlined, 'Bahan bacaan & rujukan'),
    _MenuItem('Berita', '/news', Icons.newspaper, 'Berita terkini'),
    _MenuItem('Artikel', '/articles', Icons.article_outlined, 'Penulisan & rencana'),
    _MenuItem('Video', '/videos', Icons.play_circle_outline, 'Video kuliah & tazkirah'),
    _MenuItem('Infaq', '/infaq', Icons.volunteer_activism_outlined, 'Salurkan sumbangan'),
    _MenuItem('Pasar', '/products', Icons.storefront_outlined, 'Produk untuk dibeli'),
    _MenuItem('Pesanan', '/orders', Icons.receipt_long_outlined, 'Semak status pesanan'),
    _MenuItem('Kemudahan', '/facilities', Icons.apartment, 'Tempah ruang & dewan'),
    _MenuItem('Usrah', '/usrah', Icons.groups_outlined, 'Kumpulan & jadual usrah'),
    _MenuItem('Undian', '/polls', Icons.how_to_vote_outlined, 'Undian & tinjauan'),
    _MenuItem('Direktori', '/directory', Icons.contacts_outlined, 'Direktori ahli'),
    _MenuItem('Chat', '/chat', Icons.chat_outlined, 'Perbualan & mesej'),
    _MenuItem('Notifikasi', '/notifications', Icons.notifications_outlined, 'Pemberitahuan anda'),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Menu'),
        actions: const [LogoutIconButton()],
      ),
      body: GridView.builder(
        padding: const EdgeInsets.all(Spacing.lg),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          mainAxisExtent: 148,
          crossAxisSpacing: Spacing.md,
          mainAxisSpacing: Spacing.md,
        ),
        itemCount: _items.length,
        itemBuilder: (context, index) {
          final item = _items[index];
          return _MenuCard(
            item: item,
            onTap: () => context.push(item.path),
          );
        },
      ),
    );
  }
}

class _MenuItem {
  const _MenuItem(this.label, this.path, this.icon, this.subtitle);

  final String label;
  final String path;
  final IconData icon;
  final String subtitle;
}

class _MenuCard extends StatelessWidget {
  const _MenuCard({required this.item, required this.onTap});

  final _MenuItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(Spacing.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: const BoxDecoration(
                  color: AppColors.movementSoftGreen,
                  shape: BoxShape.circle,
                ),
                child: Icon(item.icon, color: AppColors.movementNavy, size: 26),
              ),
              const SizedBox(height: Spacing.md),
              Text(
                item.label,
                style: theme.textTheme.titleSmall,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 2),
              Text(
                item.subtitle,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
