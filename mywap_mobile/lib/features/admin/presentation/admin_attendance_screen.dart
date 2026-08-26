import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/list_card.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../events/data/models/event.dart';
import '../application/admin_providers.dart';

/// Pick an event whose attendance to manage (`/admin/attendance`).
class AdminAttendanceScreen extends ConsumerWidget {
  const AdminAttendanceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final eventsAsync = ref.watch(adminUpcomingEventsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Kehadiran')),
      body: eventsAsync.when(
        data: (events) => _EventList(events: events),
        loading: () => const _EventsSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(adminUpcomingEventsProvider),
        ),
      ),
    );
  }
}

class _EventList extends StatelessWidget {
  const _EventList({required this.events});

  final List<Event> events;

  @override
  Widget build(BuildContext context) {
    if (events.isEmpty) {
      return const EmptyState(
        icon: Icons.event_outlined,
        message: 'Tiada acara akan datang untuk diuruskan.',
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.symmetric(vertical: Spacing.md),
      itemCount: events.length,
      itemBuilder: (context, index) {
        final event = events[index];
        return ListCard(
          leading: const CircleAvatar(
            radius: 20,
            backgroundColor: AppColors.movementSoftGreen,
            child: Icon(Icons.event, color: AppColors.movementNavy, size: 20),
          ),
          title: event.title ?? '-',
          subtitle: event.start_formatted,
          onTap: () => context.push('/admin/attendance/${event.id}'),
        );
      },
    );
  }
}

class _EventsSkeleton extends StatelessWidget {
  const _EventsSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      itemCount: 6,
      itemBuilder: (_, __) => const Padding(
        padding: EdgeInsets.only(bottom: Spacing.md),
        child: SkeletonBox(height: 76),
      ),
    );
  }
}
