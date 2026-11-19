import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../../shared/widgets/app_top_bar.dart';
import '../application/notification_providers.dart';
import '../data/models/app_notification.dart';
import 'notification_format.dart';

class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() =>
      _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // Provider dimuat awal oleh loceng notifikasi, jadi muat semula bila
    // skrin dibuka supaya siaran terbaharu tidak tertinggal.
    if (!ref.read(notificationsControllerProvider).isLoading) {
      ref.read(notificationsControllerProvider.notifier).refresh();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      ref.read(notificationsControllerProvider.notifier).refresh();
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<NotificationsState>(notificationsControllerProvider, (
      prev,
      next,
    ) {
      final error = next.error;
      if (error != null &&
          error != prev?.error &&
          next.notifications.isNotEmpty) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error)));
      }
    });
    final state = ref.watch(notificationsControllerProvider);

    return Scaffold(
      appBar: AppTopBar(title: context.l10n.menuNotifications, showBack: true),
      body:
          state.isLoading
              ? const _NotificationsSkeleton()
              : state.error != null && state.notifications.isEmpty
              ? ErrorRetry(
                message: state.error ?? context.l10n.unexpectedError,
                onRetry:
                    () =>
                        ref
                            .read(notificationsControllerProvider.notifier)
                            .retry(),
              )
              : Column(
                children: [
                  if (state.notifications.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(
                        Spacing.lg,
                        Spacing.sm,
                        Spacing.lg,
                        Spacing.xs,
                      ),
                      child: SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed:
                              state.isMarking
                                  ? null
                                  : () =>
                                      ref
                                          .read(
                                            notificationsControllerProvider
                                                .notifier,
                                          )
                                          .markAllRead(),
                          icon:
                              state.isMarking
                                  ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                  : const Icon(Icons.done_all),
                          label: Text(context.l10n.notifMarkAllRead),
                        ),
                      ),
                    ),
                  Expanded(
                    child: RefreshIndicator(
                      onRefresh:
                          () async =>
                              ref
                                  .read(
                                    notificationsControllerProvider.notifier,
                                  )
                                  .retry(),
                      child:
                          state.notifications.isEmpty
                              ? SingleChildScrollView(
                                physics: const AlwaysScrollableScrollPhysics(),
                                child: EmptyState(
                                  icon: Icons.notifications_none,
                                  message: context.l10n.notifEmpty,
                                ),
                              )
                              : ListView.builder(
                                physics: const AlwaysScrollableScrollPhysics(),
                                padding: const EdgeInsets.only(
                                  bottom: Spacing.xl,
                                ),
                                itemCount: state.notifications.length,
                                itemBuilder:
                                    (context, index) => _NotificationCard(
                                      notification: state.notifications[index],
                                    ),
                              ),
                    ),
                  ),
                ],
              ),
    );
  }
}

class _NotificationCard extends ConsumerWidget {
  const _NotificationCard({required this.notification});

  final AppNotification notification;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final title = notificationTitle(notification);
    final body = notificationBody(notification);
    final isRead = notification.isRead;
    final typeLabel = notificationTypeLabel(context, notification);
    final icon = notificationIconFor(notification);
    final timeAgo = notificationTimeAgo(context, notification.createdAt);

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: Spacing.lg, vertical: 5),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color:
              isRead
                  ? AppColors.divider.withValues(alpha: 0.5)
                  : AppColors.movementGreen.withValues(alpha: 0.35),
          width: isRead ? 0.8 : 1.0,
        ),
        boxShadow: AppShadows.subtle,
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () {
            if (!isRead) {
              ref
                  .read(notificationsControllerProvider.notifier)
                  .markRead(notification);
            }
            _showNotificationQuickLook(context, notification);
          },
          child: Padding(
            padding: const EdgeInsets.all(Spacing.md + 2),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Header Bar Gaya iPhone Lockscreen:
                // Logo MyWAP Sebenar + "MyWAP" + Kategori + Masa + Glowing Dot
                Row(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(3),
                      child: Image.asset(
                        'assets/app_icon.png',
                        width: 20,
                        height: 20,
                        fit: BoxFit.cover,
                        errorBuilder:
                            (_, __, ___) => Container(
                              width: 20,
                              height: 20,
                              decoration: BoxDecoration(
                                color: AppColors.movementGreen,
                                borderRadius: BorderRadius.circular(3),
                              ),
                              child: const Icon(
                                Icons.notifications_active_rounded,
                                size: 12,
                                color: AppColors.white,
                              ),
                            ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    const Text(
                      'MyWAP',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: AppColors.movementNavy,
                        letterSpacing: -0.1,
                      ),
                    ),
                    const Text(
                      ' • ',
                      style: TextStyle(
                        fontSize: 11,
                        color: AppColors.textTertiary,
                      ),
                    ),
                    Expanded(
                      child: Text(
                        typeLabel.toUpperCase(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color: AppColors.movementGreen,
                          letterSpacing: 0.4,
                        ),
                      ),
                    ),
                    Text(
                      timeAgo,
                      style: const TextStyle(
                        fontSize: 11,
                        color: AppColors.textTertiary,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    if (!isRead) ...[
                      const SizedBox(width: 6),
                      Container(
                        width: 7,
                        height: 7,
                        decoration: const BoxDecoration(
                          shape: BoxShape.circle,
                          color: AppColors.movementGreen,
                          boxShadow: [
                            BoxShadow(color: Color(0x66147A3D), blurRadius: 4),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: Spacing.sm + 2),

                // Tajuk & Kandungan Notifikasi
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        color:
                            isRead
                                ? AppColors.surfaceMuted
                                : AppColors.greenTint,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Icon(
                        icon,
                        size: 20,
                        color:
                            isRead
                                ? AppColors.textSecondary
                                : AppColors.movementGreen,
                      ),
                    ),
                    const SizedBox(width: Spacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            title.isEmpty
                                ? context.l10n.notifFallbackTitle
                                : title,
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight:
                                  isRead ? FontWeight.w600 : FontWeight.w700,
                              color: AppColors.movementNavy,
                              height: 1.25,
                            ),
                          ),
                          if (body.isNotEmpty) ...[
                            const SizedBox(height: 3),
                            Text(
                              body,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 12.5,
                                color: AppColors.textSecondary,
                                height: 1.35,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(width: 4),
                    const Icon(
                      Icons.chevron_right_rounded,
                      size: 18,
                      color: AppColors.divider,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Modal Popup Quick-Look ala iOS (Interactive Bottom Sheet)
void _showNotificationQuickLook(
  BuildContext context,
  AppNotification notification,
) {
  final title = notificationTitle(notification);
  final body = notificationBody(notification);
  final typeLabel = notificationTypeLabel(context, notification);
  final icon = notificationIconFor(notification);
  final timeAgo = notificationTimeAgo(context, notification.createdAt);
  final internalRoute = notificationInternalRoute(notification);
  final actionUri =
      internalRoute == null ? notificationActionUri(notification) : null;
  final actionLabel =
      notificationActionLabel(notification) ?? context.l10n.notifOpenLink;

  showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (modalContext) {
      return Container(
        decoration: const BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          boxShadow: AppShadows.sheet,
        ),
        padding: const EdgeInsets.fromLTRB(
          Spacing.lg,
          Spacing.sm,
          Spacing.lg,
          Spacing.xxl,
        ),
        child: SafeArea(
          top: false,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Pemegang Seret (Drag Handle)
              Center(
                child: Container(
                  width: 36,
                  height: 4,
                  margin: const EdgeInsets.only(
                    top: Spacing.xs,
                    bottom: Spacing.md,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.divider,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),

              // Bar Atas: App Icon + Header Info + Butang Tutup
              Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(3),
                    child: Image.asset(
                      'assets/app_icon.png',
                      width: 26,
                      height: 26,
                      fit: BoxFit.cover,
                      errorBuilder:
                          (_, __, ___) => Container(
                            width: 26,
                            height: 26,
                            decoration: BoxDecoration(
                              color: AppColors.movementGreen,
                              borderRadius: BorderRadius.circular(3),
                            ),
                            child: const Icon(
                              Icons.notifications_active_rounded,
                              size: 14,
                              color: AppColors.white,
                            ),
                          ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'MyWAP • NOTIFIKASI',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textTertiary,
                          letterSpacing: 0.5,
                        ),
                      ),
                      Text(
                        typeLabel,
                        style: const TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                          color: AppColors.movementGreen,
                        ),
                      ),
                    ],
                  ),
                  const Spacer(),
                  IconButton(
                    icon: const Icon(Icons.close_rounded, size: 20),
                    style: IconButton.styleFrom(
                      backgroundColor: AppColors.surfaceMuted,
                      padding: const EdgeInsets.all(6),
                      minimumSize: const Size(32, 32),
                    ),
                    onPressed: () => Navigator.pop(modalContext),
                  ),
                ],
              ),
              const SizedBox(height: Spacing.md),

              // Kad Maklumat Utama Notifikasi
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(Spacing.lg),
                decoration: BoxDecoration(
                  color: AppColors.pageBackground,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(
                    color: AppColors.divider.withValues(alpha: 0.6),
                    width: 0.8,
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            color: AppColors.greenTint,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Icon(
                            icon,
                            color: AppColors.movementGreen,
                            size: 24,
                          ),
                        ),
                        const SizedBox(width: Spacing.md),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                title.isEmpty
                                    ? context.l10n.notifFallbackTitle
                                    : title,
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.movementNavy,
                                  height: 1.25,
                                ),
                              ),
                              if (timeAgo.isNotEmpty) ...[
                                const SizedBox(height: 3),
                                Text(
                                  timeAgo,
                                  style: const TextStyle(
                                    fontSize: 11.5,
                                    color: AppColors.textTertiary,
                                    fontWeight: FontWeight.w500,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ],
                    ),
                    if (body.isNotEmpty) ...[
                      const SizedBox(height: Spacing.md),
                      Divider(
                        height: 1,
                        color: AppColors.divider.withValues(alpha: 0.5),
                      ),
                      const SizedBox(height: Spacing.md),
                      Text(
                        body,
                        style: const TextStyle(
                          fontSize: 14,
                          height: 1.55,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ],
                  ],
                ),
              ),

              const SizedBox(height: Spacing.lg),

              // Butang Tindakan (CTA)
              if (internalRoute != null) ...[
                FilledButton.icon(
                  onPressed: () {
                    Navigator.pop(modalContext);
                    context.push(internalRoute);
                  },
                  icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                  label: Text(actionLabel),
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.movementGreen,
                    foregroundColor: AppColors.white,
                    minimumSize: const Size(double.infinity, 48),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ] else if (actionUri != null) ...[
                FilledButton.icon(
                  onPressed: () {
                    Navigator.pop(modalContext);
                    launchUrl(actionUri, mode: LaunchMode.externalApplication);
                  },
                  icon: const Icon(Icons.open_in_new_rounded, size: 18),
                  label: Text(actionLabel),
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.movementGreen,
                    foregroundColor: AppColors.white,
                    minimumSize: const Size(double.infinity, 48),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      );
    },
  );
}

class _NotificationsSkeleton extends StatelessWidget {
  const _NotificationsSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      itemCount: 6,
      itemBuilder:
          (_, __) => const Padding(
            padding: EdgeInsets.only(bottom: Spacing.md),
            child: SkeletonBox(height: 72, radius: 16),
          ),
    );
  }
}
