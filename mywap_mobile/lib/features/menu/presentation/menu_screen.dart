import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/l10n/l10n.dart';
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
                isAuthenticated ? const _MemberHeader() : const _GuestHeader(),
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
                      ? _memberSections(context.l10n, user.roles ?? const [])
                      : _guestSections(context.l10n),
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

  static List<Widget> _memberSections(
    AppLocalizations l10n,
    List<String> roles,
  ) {
    final isAdmin = roles.any(_isAdmin);

    return [
      MenuSection(
        title: l10n.menuSectionMembership,
        items: [
          MenuEntry(
            icon: Icons.badge_outlined,
            label: l10n.menuCard,
            path: '/card',
            subtitle: l10n.menuCardSubtitle,
          ),
          MenuEntry(
            icon: Icons.person_outline,
            label: l10n.menuProfile,
            path: '/profile',
            subtitle: l10n.menuProfileSubtitle,
          ),
          MenuEntry(
            icon: Icons.person_add_alt_outlined,
            label: l10n.menuInvite,
            path: '/member/referral',
            subtitle: l10n.menuInviteSubtitle,
          ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionFinance,
        tint: AppColors.accent,
        items: [
          MenuEntry(
            icon: Icons.receipt_long_outlined,
            label: l10n.menuFees,
            path: '/member/financial/overview',
            subtitle: l10n.menuFeesSubtitle,
          ),
          MenuEntry(
            icon: Icons.volunteer_activism_outlined,
            label: l10n.menuInfaq,
            path: '/infaq',
            subtitle: l10n.menuInfaqSubtitle,
          ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionPrograms,
        items: [
          MenuEntry(
            icon: Icons.event_outlined,
            label: l10n.menuPrograms,
            path: '/events',
            subtitle: l10n.menuProgramsSubtitle,
          ),
          MenuEntry(
            icon: Icons.assignment_turned_in_outlined,
            label: l10n.menuMyRegistrations,
            path: '/events/my-registrations',
            subtitle: l10n.menuMyRegistrationsSubtitle,
          ),
          MenuEntry(
            icon: Icons.groups_outlined,
            label: l10n.menuUsrah,
            path: '/usrah',
            subtitle: l10n.menuUsrahSubtitle,
          ),
          MenuEntry(
            icon: Icons.how_to_vote_outlined,
            label: l10n.menuPolls,
            path: '/polls',
            subtitle: l10n.menuPollsSubtitle,
          ),
          MenuEntry(
            icon: Icons.apartment_outlined,
            label: l10n.menuFacilities,
            path: '/facilities',
            subtitle: l10n.menuFacilitiesSubtitle,
          ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionReading,
        items: [
          MenuEntry(
            icon: Icons.newspaper_outlined,
            label: l10n.menuNews,
            path: '/news',
            subtitle: l10n.menuNewsSubtitle,
          ),
          MenuEntry(
            icon: Icons.article_outlined,
            label: l10n.menuArticles,
            path: '/articles',
            subtitle: l10n.menuArticlesSubtitle,
          ),
          MenuEntry(
            icon: Icons.play_circle_outline,
            label: l10n.menuVideos,
            path: '/videos',
            subtitle: l10n.menuVideosSubtitle,
          ),
          MenuEntry(
            icon: Icons.local_library_outlined,
            label: l10n.menuLibrary,
            path: '/member/library',
            subtitle: l10n.menuLibrarySubtitle,
          ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionMall,
        tint: AppColors.accent,
        items: [
          MenuEntry(
            icon: Icons.storefront_outlined,
            label: l10n.menuProducts,
            path: '/products',
            subtitle: l10n.menuProductsSubtitle,
          ),
          MenuEntry(
            icon: Icons.shopping_bag_outlined,
            label: l10n.menuOrders,
            path: '/orders',
            subtitle: l10n.menuOrdersSubtitle,
          ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionOrgHelp,
        items: [
          MenuEntry(
            icon: Icons.info_outline,
            label: l10n.menuOrgInfo,
            path: '/organization/info',
            subtitle: l10n.menuOrgInfoSubtitle,
          ),
          MenuEntry(
            icon: Icons.campaign_outlined,
            label: l10n.menuAnnouncements,
            path: '/member/announcements',
            subtitle: l10n.menuAnnouncementsSubtitle,
          ),
          MenuEntry(
            icon: Icons.notifications_outlined,
            label: l10n.menuNotifications,
            path: '/notifications',
            subtitle: l10n.menuNotificationsSubtitle,
          ),
          MenuEntry(
            icon: Icons.menu_book_outlined,
            label: l10n.menuManual,
            path: '/manual',
            subtitle: l10n.menuManualSubtitle,
          ),
          if (isAdmin)
            MenuEntry(
              icon: Icons.admin_panel_settings_outlined,
              label: l10n.menuAdmin,
              path: '/admin',
              subtitle: l10n.menuAdminSubtitle,
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

  static List<Widget> _guestSections(AppLocalizations l10n) {
    return [
      MenuSection(
        title: l10n.menuSectionExplore,
        items: [
          MenuEntry(
            icon: Icons.home_outlined,
            label: l10n.menuGuestHome,
            path: '/home',
            subtitle: l10n.menuGuestHomeSubtitle,
          ),
          MenuEntry(
            icon: Icons.event_outlined,
            label: l10n.menuGuestEvents,
            path: '/events',
            subtitle: l10n.menuGuestEventsSubtitle,
          ),
          MenuEntry(
            icon: Icons.info_outline,
            label: l10n.menuOrgInfo,
            path: '/organization/info',
            subtitle: l10n.menuGuestOrgInfoSubtitle,
          ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionReading,
        items: [
          MenuEntry(
            icon: Icons.newspaper_outlined,
            label: l10n.menuNews,
            path: '/news',
            subtitle: l10n.menuNewsSubtitle,
          ),
          MenuEntry(
            icon: Icons.article_outlined,
            label: l10n.menuArticles,
            path: '/articles',
            subtitle: l10n.menuArticlesSubtitle,
          ),
          MenuEntry(
            icon: Icons.play_circle_outline,
            label: l10n.menuVideos,
            path: '/videos',
            subtitle: l10n.menuVideosSubtitle,
          ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionDonation,
        tint: AppColors.accent,
        items: [
          MenuEntry(
            icon: Icons.volunteer_activism_outlined,
            label: l10n.menuGuestInfaq,
            path: '/infaq',
            subtitle: l10n.menuGuestInfaqSubtitle,
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
                    context.l10n.menuTitle,
                    style: theme.textTheme.headlineSmall?.copyWith(
                      color: AppColors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  _HeaderIconButton(
                    icon: Icons.settings_outlined,
                    tooltip: context.l10n.menuSettingsTooltip,
                    onTap: () => context.push('/settings'),
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
                                user?.name ??
                                    context.l10n.menuMemberFallbackName,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                user?.member_no ?? context.l10n.menuViewProfile,
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
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'myWAP',
                    style: theme.textTheme.headlineSmall?.copyWith(
                      color: AppColors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  _HeaderIconButton(
                    icon: Icons.settings_outlined,
                    tooltip: context.l10n.menuSettingsTooltip,
                    onTap: () => context.push('/settings'),
                  ),
                ],
              ),
              const SizedBox(height: Spacing.xs),
              Text(
                context.l10n.menuGuestTagline,
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
                      child: Text(context.l10n.login),
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
                      child: Text(context.l10n.menuRegister),
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
              style: Theme.of(
                context,
              ).textTheme.labelLarge?.copyWith(color: AppColors.textSecondary),
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
      label: Text(context.l10n.logout),
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
            title: Text(context.l10n.menuLogoutConfirmTitle),
            content: Text(context.l10n.menuLogoutConfirmMessage),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: Text(context.l10n.cancel),
              ),
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                style: TextButton.styleFrom(foregroundColor: AppColors.error),
                child: Text(context.l10n.logout),
              ),
            ],
          ),
    );

    if (confirmed ?? false) {
      await ref.read(authControllerProvider.notifier).logout();
    }
  }
}
