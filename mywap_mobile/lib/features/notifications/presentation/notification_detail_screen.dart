import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../application/notification_providers.dart';
import '../data/models/app_notification.dart';
import 'notification_format.dart';

/// Paparan penuh satu notifikasi. Dibuka bila pengguna menekan kad dalam
/// senarai notifikasi.
class NotificationDetailScreen extends ConsumerStatefulWidget {
  const NotificationDetailScreen({super.key, this.notification});

  final AppNotification? notification;

  @override
  ConsumerState<NotificationDetailScreen> createState() =>
      _NotificationDetailScreenState();
}

class _NotificationDetailScreenState
    extends ConsumerState<NotificationDetailScreen> {
  @override
  void initState() {
    super.initState();
    final notification = widget.notification;
    if (notification == null) return;

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      ref
          .read(notificationsControllerProvider.notifier)
          .markRead(notification);
    });
  }

  @override
  Widget build(BuildContext context) {
    final notification = widget.notification;

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: const Text('Notifikasi'),
      ),
      body:
          notification == null
              ? const _UnavailableNotification()
              : _NotificationDetailBody(notification: notification),
    );
  }
}

class _NotificationDetailBody extends StatelessWidget {
  const _NotificationDetailBody({required this.notification});

  final AppNotification notification;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final title = notificationTitle(notification);
    final body = notificationBody(notification);
    final actionUri = notificationActionUri(notification);

    return SafeArea(
      child: SingleChildScrollView(
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
              children: [
                CircleAvatar(
                  radius: 24,
                  backgroundColor: AppColors.movementSoftGreen,
                  child: Icon(
                    notificationIconFor(notification),
                    color: AppColors.movementNavy,
                    size: 24,
                  ),
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        notificationTypeLabel(notification).toUpperCase(),
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: AppColors.movementGreen,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.8,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        notificationTimeAgo(notification.createdAt),
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: Spacing.lg),
            Text(
              title.isEmpty ? 'Pemberitahuan' : title,
              style: theme.textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
                height: 1.3,
              ),
            ),
            const SizedBox(height: Spacing.lg),
            const Divider(height: 1),
            const SizedBox(height: Spacing.lg),
            if (body.isEmpty)
              Text(
                'Tiada kandungan tambahan untuk notifikasi ini.',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                ),
              )
            else
              SelectableText(
                body,
                style: theme.textTheme.bodyLarge?.copyWith(
                  color: AppColors.textPrimary,
                  height: 1.6,
                ),
              ),
            if (actionUri != null) ...[
              const SizedBox(height: Spacing.xl),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: () => _openAction(context, actionUri),
                  icon: const Icon(Icons.open_in_new_rounded, size: 18),
                  label: const Text('Buka Pautan'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _openAction(BuildContext context, Uri uri) async {
    var opened = false;
    try {
      opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {
      opened = false;
    }

    if (!opened && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pautan tidak dapat dibuka.')),
      );
    }
  }
}

class _UnavailableNotification extends StatelessWidget {
  const _UnavailableNotification();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.notifications_off_outlined,
              size: 48,
              color: AppColors.textSecondary,
            ),
            const SizedBox(height: Spacing.md),
            Text(
              'Notifikasi tidak dapat dibuka.',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
