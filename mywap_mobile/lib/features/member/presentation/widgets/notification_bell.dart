import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../shared/l10n/l10n.dart';
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
    final unread = state.unreadCount;

    return Center(
      child: Tooltip(
        message: context.l10n.menuNotifications,
        child: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: AppColors.white,
            border: Border.all(
              color: AppColors.divider.withValues(alpha: 0.65),
              width: 0.8,
            ),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0F000000),
                blurRadius: 8,
                offset: Offset(0, 2),
              ),
            ],
          ),
          child: Material(
            color: Colors.transparent,
            shape: const CircleBorder(),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: () => context.push('/notifications'),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  const Icon(
                    Icons.notifications_outlined,
                    size: 20,
                    color: AppColors.movementNavy,
                  ),
                  if (unread > 0)
                    Positioned(
                      top: 8,
                      right: 8,
                      child: Container(
                        width: 9,
                        height: 9,
                        decoration: BoxDecoration(
                          color: const Color(0xFFFF3B30), // Apple Red
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: AppColors.white,
                            width: 1.5,
                          ),
                          boxShadow: const [
                            BoxShadow(
                              color: Color(0x40FF3B30),
                              blurRadius: 4,
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
