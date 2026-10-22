import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../application/notification_providers.dart';
import '../data/models/app_notification.dart';
import 'notification_format.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
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
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.menuNotifications),
      ),
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
                        Spacing.md,
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

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({required this.notification});

  final AppNotification notification;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final title = notificationTitle(notification);
    final body = notificationBody(notification);
    final isRead = notification.isRead;

    return Card(
      margin: const EdgeInsets.symmetric(
        horizontal: Spacing.lg,
        vertical: Spacing.sm,
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/notifications/detail', extra: notification),
        child: Padding(
          padding: const EdgeInsets.all(Spacing.lg),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 22,
                backgroundColor: AppColors.movementSoftGreen,
                child: Icon(
                  notificationIconFor(notification),
                  color: AppColors.movementNavy,
                  size: 22,
                ),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        if (!isRead) ...[
                          Container(
                            width: 8,
                            height: 8,
                            margin: const EdgeInsets.only(right: Spacing.sm),
                            decoration: const BoxDecoration(
                              color: AppColors.movementGreen,
                              shape: BoxShape.circle,
                            ),
                          ),
                        ],
                        Expanded(
                          child: Text(
                            title.isEmpty
                                ? context.l10n.notifFallbackTitle
                                : title,
                            style: theme.textTheme.titleSmall?.copyWith(
                              fontWeight:
                                  isRead ? FontWeight.w500 : FontWeight.w700,
                              color:
                                  isRead
                                      ? AppColors.textSecondary
                                      : AppColors.textPrimary,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (body.isNotEmpty) ...[
                      const SizedBox(height: Spacing.xs),
                      Text(
                        body,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium,
                      ),
                    ],
                    const SizedBox(height: Spacing.xs),
                    Text(
                      notificationTimeAgo(context, notification.createdAt),
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              const Padding(
                padding: EdgeInsets.only(left: Spacing.xs),
                child: Icon(
                  Icons.chevron_right_rounded,
                  size: 20,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
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
