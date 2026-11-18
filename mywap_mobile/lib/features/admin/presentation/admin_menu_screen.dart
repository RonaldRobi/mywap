import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/constants/app_env.dart';
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

    final roleTitle = user?.adminRoleTitle ?? context.l10n.adminRoleFallback;
    final orgName = user?.organization?.name ?? context.l10n.appTitle;
    final branchName = user?.branch_name;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text(
          context.l10n.adminMenuTitle,
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        actions: [
          IconButton(
            tooltip: context.l10n.logout,
            onPressed: () => ref.read(authControllerProvider.notifier).logout(),
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
                              user?.name ?? context.l10n.adminRoleFallback,
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
                                color:
                                    user?.isSuperadmin == true
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
                                  color:
                                      user?.isSuperadmin == true
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
            context.l10n.adminManagementOperations,
            style: theme.textTheme.titleSmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: Spacing.sm),

          // Pilihan menu operasi pentadbir
          _MenuTile(
            icon: Icons.qr_code_scanner_rounded,
            title: context.l10n.adminScanAttendanceCard,
            subtitle: context.l10n.adminScanAttendanceCardSubtitle,
            onTap: () => context.push('/admin/scan'),
          ),
          _MenuTile(
            icon: Icons.event_note_outlined,
            title: context.l10n.adminProgramAttendanceMenu,
            subtitle: context.l10n.adminProgramAttendanceSubtitle,
            onTap: () => context.push('/admin/attendance'),
          ),
          _MenuTile(
            icon: Icons.people_alt_outlined,
            title: context.l10n.adminMemberDirectory,
            subtitle: context.l10n.adminMemberDirectorySubtitle,
            onTap: () => context.push('/admin/members'),
          ),
          _MenuTile(
            icon: Icons.receipt_long_outlined,
            title: context.l10n.adminFeeCollection,
            subtitle: context.l10n.adminFeeCollectionSubtitle,
            onTap: () => context.push('/admin/fees'),
          ),
          _MenuTile(
            icon: Icons.campaign_outlined,
            title: context.l10n.adminBroadcastMenu,
            subtitle: context.l10n.adminBroadcastMenuSubtitle,
            onTap: () => context.push('/admin/broadcast'),
          ),
          _MenuTile(
            icon: Icons.menu_book_outlined,
            title: context.l10n.adminAdminManual,
            subtitle: context.l10n.adminAdminManualSubtitle,
            onTap: () => context.push('/manual'),
          ),

          const SizedBox(height: Spacing.xl),

          Text(
            context.l10n.adminCreateContent,
            style: theme.textTheme.titleSmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            context.l10n.adminCreateContentNote,
            style: theme.textTheme.bodySmall?.copyWith(
              color: AppColors.textTertiary,
            ),
          ),
          const SizedBox(height: Spacing.sm),

          _MenuTile(
            icon: Icons.event_outlined,
            title: context.l10n.adminCreateProgram,
            subtitle: context.l10n.adminCreateProgramSubtitle,
            badge: 'WEB',
            onTap:
                () => _openWebCreate(
                  context,
                  icon: Icons.event_outlined,
                  title: context.l10n.adminCreateProgramDialogTitle,
                  description: context.l10n.adminCreateProgramDialogBody,
                  path: '/admin/events/create',
                ),
          ),
          _MenuTile(
            icon: Icons.article_outlined,
            title: context.l10n.adminWriteArticle,
            subtitle: context.l10n.adminWriteArticleSubtitle,
            badge: 'WEB',
            onTap:
                () => _openWebCreate(
                  context,
                  icon: Icons.article_outlined,
                  title: context.l10n.adminWriteArticleDialogTitle,
                  description: context.l10n.adminWriteArticleDialogBody,
                  path: '/admin/articles/create',
                ),
          ),
          _MenuTile(
            icon: Icons.newspaper_outlined,
            title: context.l10n.adminLatestInfo,
            subtitle: context.l10n.adminLatestInfoSubtitle,
            badge: 'WEB',
            onTap:
                () => _openWebCreate(
                  context,
                  icon: Icons.newspaper_outlined,
                  title: context.l10n.adminManageLatestInfoDialogTitle,
                  description: context.l10n.adminManageLatestInfoDialogBody,
                  path: '/admin/info-terkini/manage',
                ),
          ),
          _MenuTile(
            icon: Icons.campaign_outlined,
            title: context.l10n.adminMediaStatement,
            subtitle: context.l10n.adminMediaStatementSubtitle,
            badge: 'WEB',
            onTap:
                () => _openWebCreate(
                  context,
                  icon: Icons.campaign_outlined,
                  title: context.l10n.adminCreateMediaStatementDialogTitle,
                  description: context.l10n.adminCreateMediaStatementDialogBody,
                  path: '/admin/media-statements/create',
                ),
          ),
          _MenuTile(
            icon: Icons.assignment_outlined,
            title: context.l10n.adminCreateForm,
            subtitle: context.l10n.adminCreateFormSubtitle,
            badge: 'WEB',
            onTap:
                () => _openWebCreate(
                  context,
                  icon: Icons.assignment_outlined,
                  title: context.l10n.adminCreateFormDialogTitle,
                  description: context.l10n.adminCreateFormDialogBody,
                  path: '/admin/forms/create',
                ),
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
            label: Text(
              context.l10n.adminLogoutAdmin,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}

Future<void> _openWebCreate(
  BuildContext context, {
  required IconData icon,
  required String title,
  required String description,
  required String path,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surface,
    shape: const RoundedRectangleBorder(borderRadius: AppRadius.sheet),
    builder:
        (_) => _WebCreateSheet(
          icon: icon,
          title: title,
          description: description,
          path: path,
        ),
  );
}

class _WebCreateSheet extends StatelessWidget {
  const _WebCreateSheet({
    required this.icon,
    required this.title,
    required this.description,
    required this.path,
  });

  final IconData icon;
  final String title;
  final String description;
  final String path;

  Future<void> _openWeb(BuildContext context) async {
    final uri = Uri.parse('${AppEnv.apiHost}$path');
    final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!ok && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.adminOpenWebFailed)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          Spacing.xl,
          Spacing.md,
          Spacing.xl,
          MediaQuery.viewInsetsOf(context).bottom + Spacing.xl,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.divider,
                  borderRadius: AppRadius.pill,
                ),
              ),
            ),
            const SizedBox(height: Spacing.xl),
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: AppColors.paleGreen,
                borderRadius: AppRadius.md,
              ),
              child: Icon(icon, color: AppColors.movementDarkGreen, size: 28),
            ),
            const SizedBox(height: Spacing.lg),
            Text(
              title,
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: Spacing.sm),
            Text(
              description,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: AppColors.textSecondary,
                height: 1.45,
              ),
            ),
            const SizedBox(height: Spacing.lg),
            Container(
              padding: const EdgeInsets.symmetric(
                horizontal: Spacing.md,
                vertical: Spacing.sm,
              ),
              decoration: BoxDecoration(
                color: AppColors.surfaceMuted,
                borderRadius: AppRadius.sm,
                border: Border.all(color: AppColors.divider),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.link_rounded,
                    size: 18,
                    color: AppColors.textSecondary,
                  ),
                  const SizedBox(width: Spacing.sm),
                  Expanded(
                    child: Text(
                      '${AppEnv.apiHost}$path',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: Spacing.xl),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: () => _openWeb(context),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                  shape: const RoundedRectangleBorder(
                    borderRadius: AppRadius.md,
                  ),
                ),
                icon: const Icon(Icons.open_in_new_rounded, size: 20),
                label: Text(
                  context.l10n.adminOpenWebPortal,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            ),
            const SizedBox(height: Spacing.sm),
            SizedBox(
              width: double.infinity,
              child: TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: Text(context.l10n.close),
              ),
            ),
          ],
        ),
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
    this.badge,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final String? badge;

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
                      Row(
                        children: [
                          Flexible(
                            child: Text(
                              title,
                              style: theme.textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          if (badge != null) ...[
                            const SizedBox(width: Spacing.sm),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 6,
                                vertical: 1,
                              ),
                              decoration: BoxDecoration(
                                color: AppColors.surfaceMuted,
                                borderRadius: AppRadius.xs,
                                border: Border.all(
                                  color: AppColors.inputBorder,
                                ),
                              ),
                              child: Text(
                                badge!,
                                style: const TextStyle(
                                  fontSize: 9,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 0.4,
                                  color: AppColors.textSecondary,
                                ),
                              ),
                            ),
                          ],
                        ],
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
