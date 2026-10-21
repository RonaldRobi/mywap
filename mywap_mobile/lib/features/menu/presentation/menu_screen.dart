import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/pattern_backdrop.dart';
import '../../auth/application/auth_controller.dart';
import '../../profile/application/profile_providers.dart';

/// Hab navigasi utama — menggantikan sidebar/drawer yang dibuang.
///
/// Mengapa tab dan bukan drawer:
/// - Drawer bersaing dengan gesture "swipe-dari-tepi-untuk-kembali" pada iOS
///   dan predictive-back pada Android. Pengguna hilang kawalan navigasi.
/// - Sasaran pengguna kami ialah warga emas; gesture tersembunyi adalah
///   penghalang. Tab yang sentiasa kelihatan jauh lebih mudah ditemui.
/// - Hab ini boleh dicapai dari mana-mana skrin melalui bar bawah, berbanding
///   drawer lama yang hanya ada butang hamburger pada 8 daripada 80+ skrin.
class MenuScreen extends ConsumerWidget {
  const MenuScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    final isAuthenticated = user != null;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      body: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child:
                isAuthenticated
                    ? const _MemberHeader()
                    : const _GuestHeader(),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.lg,
              Spacing.lg,
              Spacing.lg,
              Spacing.xxl,
            ),
            sliver: SliverList.list(
              children:
                  isAuthenticated
                      ? _memberSections(user.roles ?? const [])
                      : _guestSections(),
            ),
          ),
        ],
      ),
    );
  }

  // -------------------------------------------------------------------
  // Seksyen ahli
  // -------------------------------------------------------------------

  static bool _isAdmin(String? role) =>
      role == 'Admin' || role == 'org-admin' || role == 'Superadmin';

  static List<Widget> _memberSections(List<String> roles) {
    final isAdmin = roles.any(_isAdmin);

    return [
      const MenuSection(
        title: 'Keahlian',
        items: [
          MenuEntry(
            icon: Icons.badge_outlined,
            label: 'Kad Ahli',
            path: '/card',
            subtitle: 'Kad digital & kod QR',
          ),
          MenuEntry(
            icon: Icons.person_outline,
            label: 'Profil Saya',
            path: '/profile',
            subtitle: 'Maklumat & tetapan akaun',
          ),
          MenuEntry(
            icon: Icons.person_add_alt_outlined,
            label: 'Jemput Ahli',
            path: '/member/referral',
            subtitle: 'Kongsi pautan rujukan',
          ),
        ],
      ),
      const MenuSection(
        title: 'Kewangan',
        tint: AppColors.accent,
        items: [
          MenuEntry(
            icon: Icons.receipt_long_outlined,
            label: 'Yuran & Bayaran',
            path: '/member/financial/overview',
            subtitle: 'Status yuran dan resit',
          ),
          MenuEntry(
            icon: Icons.volunteer_activism_outlined,
            label: 'Infaq',
            path: '/infaq',
            subtitle: 'Salurkan sumbangan',
          ),
        ],
      ),
      const MenuSection(
        title: 'Program & Komuniti',
        items: [
          MenuEntry(
            icon: Icons.event_outlined,
            label: 'Program',
            path: '/events',
            subtitle: 'Acara akan datang',
          ),
          MenuEntry(
            icon: Icons.assignment_turned_in_outlined,
            label: 'Pendaftaran Saya',
            path: '/events/my-registrations',
            subtitle: 'Program yang anda sertai',
          ),
          MenuEntry(
            icon: Icons.groups_outlined,
            label: 'Usrah',
            path: '/usrah',
            subtitle: 'Kumpulan & jadual',
          ),
          MenuEntry(
            icon: Icons.how_to_vote_outlined,
            label: 'Undian',
            path: '/polls',
            subtitle: 'Undian & tinjauan',
          ),
          MenuEntry(
            icon: Icons.apartment_outlined,
            label: 'Tempah Fasiliti',
            path: '/facilities',
            subtitle: 'Ruang, dewan & peralatan',
          ),
        ],
      ),
      const MenuSection(
        title: 'Bacaan & Media',
        items: [
          MenuEntry(
            icon: Icons.newspaper_outlined,
            label: 'Info Terkini',
            path: '/news',
            subtitle: 'Berita organisasi',
          ),
          MenuEntry(
            icon: Icons.article_outlined,
            label: 'Artikel',
            path: '/articles',
            subtitle: 'Penulisan & rencana',
          ),
          MenuEntry(
            icon: Icons.play_circle_outline,
            label: 'Video',
            path: '/videos',
            subtitle: 'Kuliah & tazkirah',
          ),
          MenuEntry(
            icon: Icons.local_library_outlined,
            label: 'Pustaka',
            path: '/member/library',
            subtitle: 'Bahan bacaan & rujukan',
          ),
        ],
      ),
      const MenuSection(
        title: 'myWAP Mall',
        tint: AppColors.accent,
        items: [
          MenuEntry(
            icon: Icons.storefront_outlined,
            label: 'Produk',
            path: '/products',
            subtitle: 'Barangan untuk dibeli',
          ),
          MenuEntry(
            icon: Icons.shopping_bag_outlined,
            label: 'Pesanan Saya',
            path: '/orders',
            subtitle: 'Semak status pesanan',
          ),
        ],
      ),
      MenuSection(
        title: 'Organisasi & Bantuan',
        items: [
          const MenuEntry(
            icon: Icons.info_outline,
            label: 'Info Organisasi',
            path: '/organization/info',
            subtitle: 'Carta & maklumat',
          ),
          const MenuEntry(
            icon: Icons.campaign_outlined,
            label: 'Pengumuman',
            path: '/member/announcements',
            subtitle: 'Makluman terkini',
          ),
          const MenuEntry(
            icon: Icons.notifications_outlined,
            label: 'Notifikasi',
            path: '/notifications',
            subtitle: 'Pemberitahuan anda',
          ),
          const MenuEntry(
            icon: Icons.menu_book_outlined,
            label: 'Manual Pengguna',
            path: '/manual',
            subtitle: 'Panduan penggunaan app',
          ),
          if (isAdmin)
            const MenuEntry(
              icon: Icons.admin_panel_settings_outlined,
              label: 'Panel Admin',
              path: '/admin',
              subtitle: 'Pengurusan organisasi',
            ),
        ],
      ),
      const SizedBox(height: Spacing.lg),
      const _LogoutButton(),
    ];
  }

  // -------------------------------------------------------------------
  // Seksyen tetamu
  // -------------------------------------------------------------------

  static List<Widget> _guestSections() {
    return const [
      MenuSection(
        title: 'Terokai',
        items: [
          MenuEntry(
            icon: Icons.home_outlined,
            label: 'Beranda',
            path: '/home',
            subtitle: 'Halaman utama',
          ),
          MenuEntry(
            icon: Icons.event_outlined,
            label: 'Program & Acara',
            path: '/events',
            subtitle: 'Acara terbuka kepada umum',
          ),
          MenuEntry(
            icon: Icons.info_outline,
            label: 'Info Organisasi',
            path: '/organization/info',
            subtitle: 'Kenali kami',
          ),
        ],
      ),
      MenuSection(
        title: 'Bacaan & Media',
        items: [
          MenuEntry(
            icon: Icons.newspaper_outlined,
            label: 'Info Terkini',
            path: '/news',
            subtitle: 'Berita organisasi',
          ),
          MenuEntry(
            icon: Icons.article_outlined,
            label: 'Artikel',
            path: '/articles',
            subtitle: 'Penulisan & rencana',
          ),
          MenuEntry(
            icon: Icons.play_circle_outline,
            label: 'Video',
            path: '/videos',
            subtitle: 'Kuliah & tazkirah',
          ),
        ],
      ),
      MenuSection(
        title: 'Sumbangan',
        tint: AppColors.accent,
        items: [
          MenuEntry(
            icon: Icons.volunteer_activism_outlined,
            label: 'Infaq & Sumbangan',
            path: '/infaq',
            subtitle: 'Salurkan bantuan anda',
          ),
        ],
      ),
    ];
  }
}

// ---------------------------------------------------------------------
// Pengepala
// ---------------------------------------------------------------------

/// Pengepala ahli — latar hijau berpola dengan kad profil terapung.
class _MemberHeader extends ConsumerWidget {
  const _MemberHeader();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    final profileUser = ref.watch(profileProvider).valueOrNull?.profileUser;
    final photoUrl = profileUser?.photo_url;
    final hasPhoto = photoUrl != null && photoUrl.isNotEmpty;
    final theme = Theme.of(context);

    return PatternBackdrop(
      borderRadius: const BorderRadius.only(
        bottomLeft: Radius.circular(32),
        bottomRight: Radius.circular(32),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            Spacing.lg,
            Spacing.lg,
            Spacing.lg,
            Spacing.xl,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Menu',
                    style: theme.textTheme.headlineSmall?.copyWith(
                      color: AppColors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  _HeaderIconButton(
                    icon: Icons.settings_outlined,
                    tooltip: 'Tetapan profil',
                    onTap: () => context.push('/profile'),
                  ),
                ],
              ),
              const SizedBox(height: Spacing.lg),
              // Kad profil putih "terapung" di atas pola — memberi kedalaman
              // tanpa blok gradien tebal seperti pengepala drawer lama.
              Material(
                color: AppColors.white,
                borderRadius: AppRadius.card,
                child: InkWell(
                  borderRadius: AppRadius.card,
                  onTap: () => context.push('/profile'),
                  child: Padding(
                    padding: const EdgeInsets.all(Spacing.md),
                    child: Row(
                      children: [
                        ClipOval(
                          child:
                              hasPhoto
                                  ? AppImage(
                                    photoUrl,
                                    width: 52,
                                    height: 52,
                                    fit: BoxFit.cover,
                                  )
                                  : Container(
                                    width: 52,
                                    height: 52,
                                    color: AppColors.greenTint,
                                    child: const Icon(
                                      Icons.person,
                                      color: AppColors.movementGreen,
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
                                user?.name ?? 'Ahli myWAP',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                user?.member_no ?? 'Lihat profil anda',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: AppColors.textSecondary,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const Icon(
                          Icons.chevron_right_rounded,
                          color: AppColors.textTertiary,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Pengepala tetamu — pola yang sama, dengan ajakan log masuk/daftar.
class _GuestHeader extends StatelessWidget {
  const _GuestHeader();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return PatternBackdrop(
      borderRadius: const BorderRadius.only(
        bottomLeft: Radius.circular(32),
        bottomRight: Radius.circular(32),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            Spacing.lg,
            Spacing.lg,
            Spacing.lg,
            Spacing.xl,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'myWAP',
                style: theme.textTheme.headlineSmall?.copyWith(
                  color: AppColors.white,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: Spacing.xs),
              Text(
                'Kandungan komuniti untuk semua',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: AppColors.textOnDark,
                ),
              ),
              const SizedBox(height: Spacing.lg),
              Row(
                children: [
                  Expanded(
                    child: FilledButton(
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.white,
                        foregroundColor: AppColors.movementDarkGreen,
                      ),
                      onPressed: () => context.go('/login'),
                      child: const Text('Log Masuk'),
                    ),
                  ),
                  const SizedBox(width: Spacing.md),
                  Expanded(
                    child: OutlinedButton(
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.white,
                        side: const BorderSide(color: AppColors.white),
                      ),
                      onPressed: () => context.go('/register'),
                      child: const Text('Daftar Ahli'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Butang ikon bulat separa lut sinar untuk digunakan atas [PatternBackdrop].
class _HeaderIconButton extends StatelessWidget {
  const _HeaderIconButton({
    required this.icon,
    required this.tooltip,
    required this.onTap,
  });

  final IconData icon;
  final String tooltip;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.white.withValues(alpha: .16),
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: Tooltip(
          message: tooltip,
          child: SizedBox(
            width: AppSizes.tapTarget,
            height: AppSizes.tapTarget,
            child: Icon(icon, color: AppColors.white, size: AppSizes.iconMd),
          ),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------
// Seksyen & baris menu
// ---------------------------------------------------------------------

/// Satu kumpulan pautan menu dalam kad putih.
///
/// Tajuk menggunakan huruf biasa (bukan UPPERCASE + letter-spacing seperti
/// sidebar lama) — lebih mudah dibaca, terutamanya pada saiz kecil.
class MenuSection extends StatelessWidget {
  const MenuSection({
    super.key,
    required this.title,
    required this.items,
    this.tint,
  });

  final String title;
  final List<MenuEntry> items;

  /// Aksen ikon untuk seksyen ini. Lalai [AppColors.movementGreen].
  final Color? tint;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(bottom: Spacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.xs,
              0,
              Spacing.xs,
              Spacing.sm,
            ),
            child: Text(
              title,
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ),
          Container(
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: AppRadius.card,
              boxShadow: AppShadows.subtle,
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                for (var i = 0; i < items.length; i++) ...[
                  _MenuRow(entry: items[i], tint: tint),
                  if (i != items.length - 1)
                    const Padding(
                      // Garis pemisah bermula selepas ikon — gaya iOS.
                      padding: EdgeInsets.only(left: 66),
                      child: Divider(height: 1, thickness: 1),
                    ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Data satu baris menu.
class MenuEntry {
  const MenuEntry({
    required this.icon,
    required this.label,
    required this.path,
    this.subtitle,
  });

  final IconData icon;
  final String label;
  final String path;
  final String? subtitle;
}

class _MenuRow extends StatelessWidget {
  const _MenuRow({required this.entry, this.tint});

  final MenuEntry entry;
  final Color? tint;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final accent = tint ?? AppColors.movementGreen;

    return Semantics(
      button: true,
      label: entry.label,
      child: InkWell(
        onTap: () => context.push(entry.path),
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: Spacing.md,
            vertical: Spacing.md,
          ),
          child: Row(
            children: [
              // Ikon dalam bekas bertona — memberi struktur visual yang
              // hilang apabila 18 ikon berkongsi satu warna hijau.
              Container(
                width: 42,
                height: 42,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: .12),
                  borderRadius: AppRadius.md,
                ),
                child: Icon(entry.icon, size: 22, color: accent),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      entry.label,
                      style: theme.textTheme.bodyLarge?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (entry.subtitle != null) ...[
                      const SizedBox(height: 1),
                      Text(
                        entry.subtitle!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: Spacing.sm),
              const Icon(
                Icons.chevron_right_rounded,
                size: AppSizes.iconSm,
                color: AppColors.textTertiary,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Butang log keluar di hujung hab menu ahli.
class _LogoutButton extends ConsumerWidget {
  const _LogoutButton();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return OutlinedButton.icon(
      onPressed: () => _confirm(context, ref),
      icon: const Icon(Icons.logout_rounded, size: AppSizes.iconSm),
      label: const Text('Log Keluar'),
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.error,
        side: const BorderSide(color: AppColors.error),
      ),
    );
  }

  Future<void> _confirm(BuildContext context, WidgetRef ref) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder:
          (dialogContext) => AlertDialog(
            title: const Text('Log Keluar'),
            content: const Text('Anda pasti mahu log keluar dari myWAP?'),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: const Text('Batal'),
              ),
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                style: TextButton.styleFrom(foregroundColor: AppColors.error),
                child: const Text('Log Keluar'),
              ),
            ],
          ),
    );

    if (confirmed ?? false) {
      await ref.read(authControllerProvider.notifier).logout();
    }
  }
}
