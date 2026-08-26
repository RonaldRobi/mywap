import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/notification_providers.dart';
import '../data/models/app_notification.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    ref.listen<NotificationsState>(notificationsControllerProvider, (prev, next) {
      final error = next.error;
      if (error != null && error != prev?.error && next.notifications.isNotEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error)),
        );
      }
    });
    final state = ref.watch(notificationsControllerProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Notifikasi')),
      body: state.isLoading
          ? const _NotificationsSkeleton()
          : state.error != null && state.notifications.isEmpty
              ? ErrorRetry(
                  message: state.error ?? 'Ralat tidak dijangka.',
                  onRetry: () => ref
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
                            onPressed: state.isMarking
                                ? null
                                : () => ref
                                    .read(notificationsControllerProvider.notifier)
                                    .markAllRead(),
                            icon: state.isMarking
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.done_all),
                            label: const Text('Tandakan Semua Dibaca'),
                          ),
                        ),
                      ),
                    Expanded(
                      child: state.notifications.isEmpty
                          ? const EmptyState(
                              icon: Icons.notifications_none,
                              message: 'Tiada notifikasi buat masa ini.',
                            )
                          : ListView.builder(
                              padding: const EdgeInsets.only(bottom: Spacing.xl),
                              itemCount: state.notifications.length,
                              itemBuilder: (context, index) => _NotificationCard(
                                notification: state.notifications[index],
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
    final data = notification.data;
    final title = (data['title'] as String?) ?? '';
    final body = (data['content'] as String?) ?? (data['message'] as String?) ?? '';
    final isRead = notification.isRead;

    return Card(
      margin: const EdgeInsets.symmetric(
        horizontal: Spacing.lg,
        vertical: Spacing.sm,
      ),
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CircleAvatar(
              radius: 22,
              backgroundColor: AppColors.movementSoftGreen,
              child: Icon(
                _iconFor(notification),
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
                          title.isEmpty ? 'Pemberitahuan' : title,
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight:
                                isRead ? FontWeight.w500 : FontWeight.w700,
                            color: isRead
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
                    _timeAgo(notification.createdAt),
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
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

class _NotificationsSkeleton extends StatelessWidget {
  const _NotificationsSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      itemCount: 6,
      itemBuilder: (_, __) => const Padding(
        padding: EdgeInsets.only(bottom: Spacing.md),
        child: SkeletonBox(height: 72, radius: 16),
      ),
    );
  }
}

IconData _iconFor(AppNotification notification) {
  final type =
      '${notification.type ?? ''} ${notification.data['type'] ?? ''}'.toLowerCase();
  if (type.contains('announcement')) return Icons.campaign_outlined;
  if (type.contains('infaq')) return Icons.volunteer_activism_outlined;
  if (type.contains('event')) return Icons.event_outlined;
  if (type.contains('fee')) return Icons.receipt_long_outlined;
  if (type.contains('form')) return Icons.assignment_outlined;
  if (type.contains('transition') ||
      type.contains('member') ||
      type.contains('registration')) {
    return Icons.group_add_outlined;
  }
  return Icons.notifications_outlined;
}

String _timeAgo(DateTime? time) {
  if (time == null) return '';
  final diff = DateTime.now().difference(time);
  if (diff.inMinutes < 1) return 'Baru sahaja';
  if (diff.inMinutes < 60) return '${diff.inMinutes} minit lalu';
  if (diff.inHours < 24) return '${diff.inHours} jam lalu';
  if (diff.inDays < 7) return '${diff.inDays} hari lalu';
  final d = time.day.toString().padLeft(2, '0');
  final m = time.month.toString().padLeft(2, '0');
  return '$d/$m/${time.year}';
}
