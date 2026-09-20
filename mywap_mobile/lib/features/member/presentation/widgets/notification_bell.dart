import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../shared/theme/app_colors.dart';
import '../../../auth/application/auth_controller.dart';
import '../../../notifications/application/notification_providers.dart';

/// Notification bell (top-right) with an unread-count badge — always
/// available regardless of which tab/screen is active, per requirement.
/// Hidden for guests (notifications are a member feature).
class NotificationBell extends ConsumerWidget {
  const NotificationBell({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isAuthenticated = ref.watch(currentUserProvider) != null;
    if (!isAuthenticated) return const SizedBox.shrink();

    final state = ref.watch(notificationsControllerProvider);
    final unread = state.notifications.where((n) => !n.isRead).length;

    return IconButton(
      tooltip: 'Notifikasi',
      onPressed: () => context.push('/notifications'),
      icon: Badge(
        isLabelVisible: unread > 0,
        label: Text(unread > 9 ? '9+' : '$unread'),
        backgroundColor: AppColors.error,
        child: const Icon(Icons.notifications_outlined),
      ),
    );
  }
}
