import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../member/application/member_providers.dart';
import '../application/event_providers.dart';
import '../data/models/event.dart';

class EventDetailScreen extends ConsumerStatefulWidget {
  const EventDetailScreen({super.key, required this.eventId});

  final int eventId;

  @override
  ConsumerState<EventDetailScreen> createState() => _EventDetailScreenState();
}

class _EventDetailScreenState extends ConsumerState<EventDetailScreen> {
  bool _rsvpLoading = false;
  String? _rsvpError;

  Future<void> _toggleRsvp(String? currentStatus) async {
    setState(() {
      _rsvpLoading = true;
      _rsvpError = null;
    });
    try {
      await ref.read(eventRepositoryProvider).rsvp(
            widget.eventId,
            status: currentStatus == 'going' ? 'declined' : 'going',
          );
      if (!mounted) return;
      ref.invalidate(eventDetailProvider(widget.eventId));
      ref.invalidate(eventsProvider);
      ref.invalidate(memberDashboardProvider);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('RSVP berjaya dikemas kini.')),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _rsvpError = e.message);
    } finally {
      if (mounted) setState(() => _rsvpLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(eventDetailProvider(widget.eventId));

    return Scaffold(
      appBar: AppBar(title: const Text('Butiran Acara')),
      body: detailAsync.when(
        data: (detail) => _DetailContent(
          event: detail.event,
          rsvpLoading: _rsvpLoading,
          rsvpError: _rsvpError,
          onRsvp: _toggleRsvp,
          onRefresh: () async =>
              ref.invalidate(eventDetailProvider(widget.eventId)),
        ),
        loading: () => const _DetailSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(eventDetailProvider(widget.eventId)),
        ),
      ),
    );
  }
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({
    required this.event,
    required this.rsvpLoading,
    required this.rsvpError,
    required this.onRsvp,
    required this.onRefresh,
  });

  final Event? event;
  final bool rsvpLoading;
  final String? rsvpError;
  final ValueChanged<String?> onRsvp;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final title = event?.title ?? 'Acara';
    final description = event?.description;
    final myRsvp = event?.my_rsvp;
    final isGoing = myRsvp == 'going';

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      children: [
        AppImage(
          event?.featured_image_url,
          height: 220,
          width: double.infinity,
          borderRadius: BorderRadius.zero,
        ),
        Padding(
          padding: const EdgeInsets.all(Spacing.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: theme.textTheme.headlineSmall),
              const SizedBox(height: Spacing.md),
              if (event?.organization?.name != null) ...[
                Row(
                  children: [
                    Icon(
                      Icons.apartment,
                      size: 18,
                      color: AppColors.movementGreen,
                    ),
                    const SizedBox(width: 6),
                    Text(
                      event!.organization!.name!,
                      style: theme.textTheme.titleSmall?.copyWith(
                        color: AppColors.movementGreen,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: Spacing.sm),
              ],
              _InfoRow(
                icon: Icons.calendar_today_outlined,
                text: event?.start_formatted ?? '',
              ),
              _InfoRow(
                icon: Icons.location_on_outlined,
                text: event?.location_or_link ?? '',
              ),
              if (event?.rsvp_count != null) ...[
                const SizedBox(height: Spacing.sm),
                _InfoRow(
                  icon: Icons.people_outline,
                  text: '${event!.rsvp_count} peserta',
                ),
              ],
              if (description != null && description.isNotEmpty) ...[
                const SizedBox(height: Spacing.xl),
                Text('Penerangan', style: theme.textTheme.titleLarge),
                const SizedBox(height: Spacing.sm),
                Text(description, style: theme.textTheme.bodyLarge),
              ],
              if (rsvpError != null) ...[
                const SizedBox(height: Spacing.lg),
                Text(
                  rsvpError!,
                  style: const TextStyle(color: AppColors.error),
                ),
              ],
              const SizedBox(height: Spacing.xl),
              FilledButton.icon(
                onPressed: rsvpLoading ? null : () => onRsvp(myRsvp),
                icon: rsvpLoading
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: AppColors.white,
                        ),
                      )
                    : Icon(isGoing ? Icons.event_busy : Icons.event_available),
                label: Text(
                  isGoing ? 'Batalkan Kehadiran' : 'Saya Akan Hadir',
                ),
              ),
            ],
          ),
        ),
      ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: Spacing.sm),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: AppColors.textSecondary),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DetailSkeleton extends StatelessWidget {
  const _DetailSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      children: const [
        SkeletonBox(height: 220, radius: 0),
        Padding(
          padding: EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SkeletonBox(height: 28, width: 260),
              SizedBox(height: 16),
              SkeletonBox(height: 18),
              SizedBox(height: 8),
              SkeletonBox(height: 18),
              SizedBox(height: 24),
              SkeletonBox(height: 120),
            ],
          ),
        ),
      ],
    );
  }
}
