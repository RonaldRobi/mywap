import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/pattern_backdrop.dart';
import '../../auth/application/auth_controller.dart';
import '../../notifications/application/notification_providers.dart';
import '../../profile/application/profile_providers.dart';

/// Hab navigasi utama dengan reka bentuk gaya Apple UI/UX.
///
/// Menggunakan gabungan kad Apple ID, grid pintasan aksi pantas,
/// dan senarai inset grouped dengan lencana warna solid Apple.
class MenuScreen extends ConsumerWidget {
  const MenuScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    final isAuthenticated = user != null;
    final unread =
        isAuthenticated
            ? ref.watch(notificationsControllerProvider).unreadCount
            : 0;

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
                      ? _memberSections(
                        context.l10n,
                        user.roles ?? const [],
                        unread,
                      )
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
      role == 'Admin' ||
      role == 'org-admin' ||
      role == 'Superadmin' ||
      role == 'Admin Cawangan';

  static List<Widget> _memberSections(
    AppLocalizations l10n,
    List<String> roles,
    int unreadNotifications,
  ) {
    final isAdmin = roles.any(_isAdmin);

    return [
      _QuickActionsGrid(isAdmin: isAdmin, unreadCount: unreadNotifications),
      MenuSection(
        title: l10n.menuSectionMembership,
        items: [
          if (!isAdmin)
            MenuEntry(
              icon: Icons.badge_rounded,
              label: l10n.menuCard,
              path: '/card',
              subtitle: l10n.menuCardSubtitle,
              color: const Color(0xFF007AFF),
            ),
          MenuEntry(
            icon: Icons.person_rounded,
            label: l10n.menuProfile,
            path: '/profile',
            subtitle: l10n.menuProfileSubtitle,
            color: const Color(0xFF5856D6),
          ),
          if (!isAdmin)
            MenuEntry(
              icon: Icons.person_add_alt_1_rounded,
              label: l10n.menuInvite,
              path: '/member/referral',
              subtitle: l10n.menuInviteSubtitle,
              color: const Color(0xFF30B0C7),
            ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionFinance,
        items: [
          if (!isAdmin)
            MenuEntry(
              icon: Icons.receipt_long_rounded,
              label: l10n.menuFees,
              path: '/member/financial/overview',
              subtitle: l10n.menuFeesSubtitle,
              color: const Color(0xFF34C759),
            ),
          MenuEntry(
            icon: Icons.volunteer_activism_rounded,
            label: l10n.menuInfaq,
            path: '/infaq',
            subtitle: l10n.menuInfaqSubtitle,
            color: const Color(0xFFFF2D55),
          ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionPrograms,
        items: [
          MenuEntry(
            icon: Icons.event_rounded,
            label: l10n.menuPrograms,
            path: '/events',
            subtitle: l10n.menuProgramsSubtitle,
            color: const Color(0xFF007AFF),
          ),
          MenuEntry(
            icon: Icons.assignment_turned_in_rounded,
            label: l10n.menuMyRegistrations,
            path: '/events/my-registrations',
            subtitle: l10n.menuMyRegistrationsSubtitle,
            color: const Color(0xFFFF9500),
          ),
          if (!isAdmin)
            MenuEntry(
              icon: Icons.diversity_3_rounded,
              label: l10n.menuUsrah,
              path: '/usrah',
              subtitle: l10n.menuUsrahSubtitle,
              color: const Color(0xFFAF52DE),
            ),
          MenuEntry(
            icon: Icons.how_to_vote_rounded,
            label: l10n.menuPolls,
            path: '/polls',
            subtitle: l10n.menuPollsSubtitle,
            color: const Color(0xFF5856D6),
          ),
          MenuEntry(
            icon: Icons.apartment_rounded,
            label: l10n.menuFacilities,
            path: '/facilities',
            subtitle: l10n.menuFacilitiesSubtitle,
            color: const Color(0xFFFF9F0A),
          ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionReading,
        items: [
          MenuEntry(
            icon: Icons.newspaper_rounded,
            label: l10n.menuNews,
            path: '/news',
            subtitle: l10n.menuNewsSubtitle,
            color: const Color(0xFF007AFF),
          ),
          MenuEntry(
            icon: Icons.article_rounded,
            label: l10n.menuArticles,
            path: '/articles',
            subtitle: l10n.menuArticlesSubtitle,
            color: const Color(0xFF30B0C7),
          ),
          MenuEntry(
            icon: Icons.play_circle_rounded,
            label: l10n.menuVideos,
            path: '/videos',
            subtitle: l10n.menuVideosSubtitle,
            color: const Color(0xFFFF3B30),
          ),
          MenuEntry(
            icon: Icons.local_library_rounded,
            label: l10n.menuLibrary,
            path: '/member/library',
            subtitle: l10n.menuLibrarySubtitle,
            color: const Color(0xFF5856D6),
          ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionMall,
        items: [
          MenuEntry(
            icon: Icons.storefront_rounded,
            label: l10n.menuProducts,
            path: '/products',
            subtitle: l10n.menuProductsSubtitle,
            color: const Color(0xFFFF9F0A),
          ),
          MenuEntry(
            icon: Icons.shopping_bag_rounded,
            label: l10n.menuOrders,
            path: '/orders',
            subtitle: l10n.menuOrdersSubtitle,
            color: const Color(0xFFFF9500),
          ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionOrgHelp,
        items: [
          MenuEntry(
            icon: Icons.corporate_fare_rounded,
            label: l10n.menuOrgInfo,
            path: '/organization/info',
            subtitle: l10n.menuOrgInfoSubtitle,
            color: const Color(0xFF1D3557),
          ),
          MenuEntry(
            icon: Icons.campaign_rounded,
            label: l10n.menuAnnouncements,
            path: '/member/announcements',
            subtitle: l10n.menuAnnouncementsSubtitle,
            color: const Color(0xFFFF9500),
          ),
          MenuEntry(
            icon: Icons.notifications_rounded,
            label: l10n.menuNotifications,
            path: '/notifications',
            subtitle: l10n.menuNotificationsSubtitle,
            color: const Color(0xFFFF3B30),
            badge:
                unreadNotifications > 0
                    ? Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 7,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.error,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        unreadNotifications > 99
                            ? '99+'
                            : '$unreadNotifications',
                        style: const TextStyle(
                          color: AppColors.white,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    )
                    : null,
          ),
          MenuEntry(
            icon: Icons.menu_book_rounded,
            label: l10n.menuManual,
            path: '/manual',
            subtitle: l10n.menuManualSubtitle,
            color: const Color(0xFF34C759),
          ),
          if (isAdmin)
            MenuEntry(
              icon: Icons.admin_panel_settings_rounded,
              label: l10n.menuAdmin,
              path: '/admin',
              subtitle: l10n.menuAdminSubtitle,
              color: const Color(0xFF2C3E50),
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
            icon: Icons.home_rounded,
            label: l10n.menuGuestHome,
            path: '/home',
            subtitle: l10n.menuGuestHomeSubtitle,
            color: const Color(0xFF007AFF),
          ),
          MenuEntry(
            icon: Icons.event_rounded,
            label: l10n.menuGuestEvents,
            path: '/events',
            subtitle: l10n.menuGuestEventsSubtitle,
            color: const Color(0xFFFF9500),
          ),
          MenuEntry(
            icon: Icons.corporate_fare_rounded,
            label: l10n.menuOrgInfo,
            path: '/organization/info',
            subtitle: l10n.menuGuestOrgInfoSubtitle,
            color: const Color(0xFF1D3557),
          ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionReading,
        items: [
          MenuEntry(
            icon: Icons.newspaper_rounded,
            label: l10n.menuNews,
            path: '/news',
            subtitle: l10n.menuNewsSubtitle,
            color: const Color(0xFF007AFF),
          ),
          MenuEntry(
            icon: Icons.article_rounded,
            label: l10n.menuArticles,
            path: '/articles',
            subtitle: l10n.menuArticlesSubtitle,
            color: const Color(0xFF30B0C7),
          ),
          MenuEntry(
            icon: Icons.play_circle_rounded,
            label: l10n.menuVideos,
            path: '/videos',
            subtitle: l10n.menuVideosSubtitle,
            color: const Color(0xFFFF3B30),
          ),
        ],
      ),
      MenuSection(
        title: l10n.menuSectionDonation,
        items: [
          MenuEntry(
            icon: Icons.volunteer_activism_rounded,
            label: l10n.menuGuestInfaq,
            path: '/infaq',
            subtitle: l10n.menuGuestInfaqSubtitle,
            color: const Color(0xFFFF2D55),
          ),
        ],
      ),
    ];
  }
}

// ---------------------------------------------------------------------
// Pengepala
// ---------------------------------------------------------------------

/// Pengepala ahli — latar hijau berpola dengan kad Apple ID profil.
class _MemberHeader extends ConsumerWidget {
  const _MemberHeader();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    final profileUser = ref.watch(profileProvider).valueOrNull?.profileUser;
    final photoUrl = profileUser?.photo_url;
    final hasPhoto = photoUrl != null && photoUrl.isNotEmpty;
    final theme = Theme.of(context);
    final orgName = user?.organization?.name ?? profileUser?.organization?.name;

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
                    icon: Icons.settings_rounded,
                    tooltip: context.l10n.menuSettingsTooltip,
                    onTap: () => context.push('/settings'),
                  ),
                ],
              ),
              const SizedBox(height: Spacing.lg),
              Container(
                decoration: BoxDecoration(
                  color: AppColors.white,
                  borderRadius: AppRadius.card,
                  boxShadow: const [
                    BoxShadow(
                      color: Color(0x14000000),
                      blurRadius: 12,
                      offset: Offset(0, 4),
                    ),
                  ],
                ),
                child: Material(
                  color: Colors.transparent,
                  borderRadius: AppRadius.card,
                  child: InkWell(
                    borderRadius: AppRadius.card,
                    onTap: () => context.push('/profile'),
                    child: Padding(
                      padding: const EdgeInsets.all(Spacing.md),
                      child: Row(
                        children: [
                          Container(
                            width: 56,
                            height: 56,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              border: Border.all(
                                color: AppColors.movementGreen.withValues(
                                  alpha: 0.25,
                                ),
                                width: 2,
                              ),
                            ),
                            child: ClipOval(
                              child:
                                  hasPhoto
                                      ? AppImage(
                                        photoUrl,
                                        width: 52,
                                        height: 52,
                                        fit: BoxFit.cover,
                                      )
                                      : Container(
                                        color: AppColors.greenTint,
                                        child: const Icon(
                                          Icons.person_rounded,
                                          color: AppColors.movementGreen,
                                          size: 30,
                                        ),
                                      ),
                            ),
                          ),
                          const SizedBox(width: Spacing.md),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Flexible(
                                      child: Text(
                                        user?.name ??
                                            context.l10n.menuMemberFallbackName,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: theme.textTheme.titleMedium
                                            ?.copyWith(
                                              fontWeight: FontWeight.w700,
                                              fontSize: 16,
                                            ),
                                      ),
                                    ),
                                    if (orgName != null &&
                                        orgName.isNotEmpty) ...[
                                      const SizedBox(width: 6),
                                      Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 6,
                                          vertical: 2,
                                        ),
                                        decoration: BoxDecoration(
                                          color: AppColors.movementGreen
                                              .withValues(alpha: 0.12),
                                          borderRadius: BorderRadius.circular(
                                            6,
                                          ),
                                        ),
                                        child: Text(
                                          orgName,
                                          style: const TextStyle(
                                            fontSize: 10,
                                            fontWeight: FontWeight.w700,
                                            color: AppColors.movementGreen,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  user?.member_no != null &&
                                          user!.member_no!.isNotEmpty
                                      ? 'No. Ahli: ${user.member_no}'
                                      : context.l10n.menuViewProfile,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: AppColors.textSecondary,
                                    fontSize: 12.5,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 5,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.pageBackground,
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  'Profil',
                                  style: theme.textTheme.labelSmall?.copyWith(
                                    fontWeight: FontWeight.w600,
                                    color: AppColors.movementGreen,
                                  ),
                                ),
                                const SizedBox(width: 2),
                                const Icon(
                                  Icons.chevron_right_rounded,
                                  size: 16,
                                  color: AppColors.movementGreen,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
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
                    'MyWAP',
                    style: theme.textTheme.headlineSmall?.copyWith(
                      color: AppColors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  _HeaderIconButton(
                    icon: Icons.settings_rounded,
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
                    child: SizedBox(
                      height: 52,
                      child: FilledButton(
                        style: FilledButton.styleFrom(
                          backgroundColor: AppColors.white,
                          foregroundColor: AppColors.movementDarkGreen,
                          shape: RoundedRectangleBorder(
                            borderRadius: AppRadius.md,
                          ),
                        ),
                        onPressed: () => context.go('/login'),
                        child: Text(
                          context.l10n.login,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: Spacing.md),
                  Expanded(
                    child: SizedBox(
                      height: 52,
                      child: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.white,
                          side: const BorderSide(color: AppColors.white),
                          shape: RoundedRectangleBorder(
                            borderRadius: AppRadius.md,
                          ),
                        ),
                        onPressed: () => context.go('/register'),
                        child: Text(
                          context.l10n.menuRegister,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
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
// Grid Aksi Utama (Apple Quick Action Style)
// ---------------------------------------------------------------------

class _QuickActionItem {
  const _QuickActionItem({
    required this.icon,
    required this.label,
    required this.color,
    required this.path,
    this.badgeCount,
  });

  final IconData icon;
  final String label;
  final Color color;
  final String path;
  final int? badgeCount;
}

class _QuickActionsGrid extends StatelessWidget {
  const _QuickActionsGrid({required this.isAdmin, required this.unreadCount});

  final bool isAdmin;
  final int unreadCount;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final items =
        isAdmin
            ? [
              _QuickActionItem(
                icon: Icons.qr_code_scanner_rounded,
                label: l10n.menuQuickScan,
                color: const Color(0xFF007AFF),
                path: '/admin/fast-scan',
              ),
              _QuickActionItem(
                icon: Icons.admin_panel_settings_rounded,
                label: l10n.menuQuickAdmin,
                color: const Color(0xFF2C3E50),
                path: '/admin',
              ),
              _QuickActionItem(
                icon: Icons.event_rounded,
                label: l10n.menuQuickEvents,
                color: const Color(0xFFFF9500),
                path: '/events',
              ),
              _QuickActionItem(
                icon: Icons.notifications_rounded,
                label: l10n.menuQuickNotifications,
                color: const Color(0xFFFF3B30),
                path: '/notifications',
                badgeCount: unreadCount,
              ),
            ]
            : [
              _QuickActionItem(
                icon: Icons.badge_rounded,
                label: l10n.menuQuickDigitalId,
                color: const Color(0xFF007AFF),
                path: '/card',
              ),
              _QuickActionItem(
                icon: Icons.receipt_long_rounded,
                label: l10n.menuQuickFees,
                color: const Color(0xFF34C759),
                path: '/member/financial/overview',
              ),
              _QuickActionItem(
                icon: Icons.confirmation_number_rounded,
                label: l10n.menuQuickTickets,
                color: const Color(0xFFFF9500),
                path: '/events/my-registrations',
              ),
              _QuickActionItem(
                icon: Icons.notifications_rounded,
                label: l10n.menuQuickNotifications,
                color: const Color(0xFFFF3B30),
                path: '/notifications',
                badgeCount: unreadCount,
              ),
            ];

    return Container(
      margin: const EdgeInsets.only(bottom: Spacing.lg),
      padding: const EdgeInsets.all(Spacing.md),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: AppRadius.card,
        boxShadow: AppShadows.subtle,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(left: 4, bottom: Spacing.sm),
            child: Text(
              l10n.menuPrimaryActions.toUpperCase(),
              style: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w800,
                letterSpacing: 0.8,
                color: AppColors.textSecondary,
              ),
            ),
          ),
          Row(
            children: [
              for (final item in items)
                Expanded(child: _QuickActionTile(item: item)),
            ],
          ),
        ],
      ),
    );
  }
}

class _QuickActionTile extends StatelessWidget {
  const _QuickActionTile({required this.item});

  final _QuickActionItem item;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () => context.push(item.path),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 2),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: item.color,
                      borderRadius: BorderRadius.circular(14),
                      boxShadow: [
                        BoxShadow(
                          color: item.color.withValues(alpha: 0.28),
                          blurRadius: 8,
                          offset: const Offset(0, 3),
                        ),
                      ],
                    ),
                    child: Icon(item.icon, color: AppColors.white, size: 22),
                  ),
                  if (item.badgeCount != null && item.badgeCount! > 0)
                    Positioned(
                      top: -4,
                      right: -4,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 5,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.error,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(
                            color: AppColors.white,
                            width: 1.5,
                          ),
                        ),
                        constraints: const BoxConstraints(minWidth: 18),
                        child: Text(
                          item.badgeCount! > 99 ? '99+' : '${item.badgeCount}',
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: AppColors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            height: 1,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 6),
              SizedBox(
                height: 28,
                child: Center(
                  child: Text(
                    item.label,
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      letterSpacing: -0.2,
                      color: AppColors.textPrimary,
                      height: 1.15,
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

// ---------------------------------------------------------------------
// Seksyen & baris menu (Apple Inset Grouped List)
// ---------------------------------------------------------------------

class MenuSection extends StatelessWidget {
  const MenuSection({super.key, required this.title, required this.items});

  final String title;
  final List<MenuEntry> items;

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
                  _MenuRow(entry: items[i]),
                  if (i != items.length - 1)
                    const Padding(
                      padding: EdgeInsets.only(left: 64),
                      child: Divider(
                        height: 1,
                        thickness: 0.7,
                        color: AppColors.divider,
                      ),
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
    this.color,
    this.badge,
  });

  final IconData icon;
  final String label;
  final String path;
  final String? subtitle;
  final Color? color;
  final Widget? badge;
}

class _MenuRow extends StatelessWidget {
  const _MenuRow({required this.entry});

  final MenuEntry entry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final badgeColor = entry.color ?? AppColors.movementGreen;

    return Semantics(
      button: true,
      label: entry.label,
      child: InkWell(
        onTap: () => context.push(entry.path),
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: Spacing.md,
            vertical: 11,
          ),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: badgeColor,
                  borderRadius: BorderRadius.circular(10),
                  boxShadow: [
                    BoxShadow(
                      color: badgeColor.withValues(alpha: 0.22),
                      blurRadius: 6,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Icon(entry.icon, size: 20, color: AppColors.white),
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
                        fontSize: 15,
                        color: AppColors.textPrimary,
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
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              if (entry.badge != null) ...[
                const SizedBox(width: Spacing.xs),
                entry.badge!,
              ],
              const SizedBox(width: Spacing.xs),
              const Icon(
                Icons.chevron_right_rounded,
                size: 20,
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
