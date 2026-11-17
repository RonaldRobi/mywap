import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_text_theme.dart';
import '../../../shared/theme/app_theme.dart';
import '../../auth/application/auth_controller.dart';

/// Skrin Menu & Hab Operasi Pentadbir (`/admin/menu`).
class AdminMenuScreen extends ConsumerWidget {
  const AdminMenuScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    final theme = Theme.of(context);

    final roleTitle = user?.adminRoleTitle ?? 'Pentadbir';
    final orgName = user?.organization?.name ?? 'MyWAP';
    final branchName = user?.branch_name;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: const Text(
          'Menu Pentadbir',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
        actions: [
          IconButton(
            tooltip: context.l10n.logout,
            onPressed: () =>
                ref.read(authControllerProvider.notifier).logout(),
            icon: const Icon(Icons.logout, color: AppColors.movementGreen),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          Spacing.lg,
          Spacing.md,
          Spacing.lg,
          Spacing.xxl,
        ),
        children: [
          // Kad Profil Pentadbir
          Card(
            margin: EdgeInsets.zero,
            color: AppColors.white,
            shape: const RoundedRectangleBorder(
              borderRadius: AppRadius.card,
              side: BorderSide(color: AppColors.divider),
            ),
            child: Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      CircleAvatar(
                        radius: 26,
                        backgroundColor: AppColors.paleGreen,
                        child: Text(
                          (user?.name?.isNotEmpty == true)
                              ? user!.name![0].toUpperCase()
                              : 'A',
                          style: const TextStyle(
                            color: AppColors.movementGreen,
                            fontWeight: FontWeight.w700,
                            fontSize: 22,
                          ),
                        ),
                      ),
                      const SizedBox(width: Spacing.md),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              user?.name ?? 'Pentadbir',
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 4),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 2,
                              ),
                              decoration: BoxDecoration(
                                color: user?.isSuperadmin == true
                                    ? const Color(0xFF1E293B)
                                    : (user?.isBranchAdmin == true
                                        ? AppColors.accentTint
                                        : AppColors.paleGreen),
                                borderRadius: AppRadius.pill,
                              ),
                              child: Text(
                                roleTitle,
                                style: TextStyle(
                                  fontSize: AppTextTheme.minSize,
                                  fontWeight: FontWeight.w700,
                                  color: user?.isSuperadmin == true
                                      ? AppColors.white
                                      : (user?.isBranchAdmin == true
                                          ? AppColors.warning
                                          : AppColors.movementDarkGreen),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: Spacing.md),
                  const Divider(color: AppColors.divider, height: 1),
                  const SizedBox(height: Spacing.md),
                  // Maklumat Org / Cawangan
                  Row(
                    children: [
                      const Icon(
                        Icons.corporate_fare_outlined,
                        size: 18,
                        color: AppColors.textSecondary,
                      ),
                      const SizedBox(width: Spacing.sm),
                      Expanded(
                        child: Text(
                          branchName != null && branchName.isNotEmpty
                              ? '$orgName · $branchName'
                              : orgName,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.textSecondary,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),

          const SizedBox(height: Spacing.xl),

          Text(
            'Fungsi Pengurusan & Operasi',
            style: theme.textTheme.titleSmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: Spacing.sm),

          // Pilihan menu operasi pentadbir
          _MenuTile(
            icon: Icons.qr_code_scanner_rounded,
            title: 'Imbas Kad Kehadiran',
            subtitle: 'Pengimbas pantas kamera untuk kad keahlian peserta di program',
            onTap: () => context.push('/admin/scan'),
          ),
          _MenuTile(
            icon: Icons.event_note_outlined,
            title: 'Program & Kehadiran',
            subtitle: 'Senarai program, rekod kehadiran & status pendaftaran',
            onTap: () => context.push('/admin/attendance'),
          ),
          _MenuTile(
            icon: Icons.people_alt_outlined,
            title: 'Direktori Keahlian',
            subtitle: 'Carian ahli, semakan status aktif & maklumat perhubungan',
            onTap: () => context.push('/admin/members'),
          ),
          _MenuTile(
            icon: Icons.receipt_long_outlined,
            title: 'Kutipan Yuran Ahli',
            subtitle: 'Pantau status bayaran yuran tahunan ahli & ringkasan kutipan',
            onTap: () => context.push('/admin/fees'),
          ),
          _MenuTile(
            icon: Icons.campaign_outlined,
            title: 'Mesej Siaran (Broadcast)',
            subtitle: 'Hantar notifikasi pengumuman segera kepada kumpulan ahli',
            onTap: () => context.push('/admin/broadcast'),
          ),
          _MenuTile(
            icon: Icons.menu_book_outlined,
            title: 'Manual Pengguna Pentadbir',
            subtitle: 'Panduan operasi sistem MyWAP untuk pentadbir',
            onTap: () => context.push('/manual'),
          ),

          const SizedBox(height: Spacing.xl),

          // Butang Log Keluar
          OutlinedButton.icon(
            onPressed: () => ref.read(authControllerProvider.notifier).logout(),
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.error,
              side: const BorderSide(color: AppColors.error),
              minimumSize: const Size.fromHeight(AppSizes.buttonHeight),
              shape: const RoundedRectangleBorder(borderRadius: AppRadius.md),
            ),
            icon: const Icon(Icons.logout_rounded),
            label: const Text(
              'Log Keluar Akaun Pentadbir',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}

class _MenuTile extends StatelessWidget {
  const _MenuTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: Spacing.sm),
      child: Card(
        margin: EdgeInsets.zero,
        color: AppColors.white,
        shape: const RoundedRectangleBorder(
          borderRadius: AppRadius.md,
          side: BorderSide(color: AppColors.divider),
        ),
        child: InkWell(
          borderRadius: AppRadius.md,
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: Spacing.lg,
              vertical: Spacing.md,
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 20,
                  backgroundColor: AppColors.paleGreen,
                  child: Icon(icon, color: AppColors.movementGreen, size: 22),
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
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
    );
  }
}
