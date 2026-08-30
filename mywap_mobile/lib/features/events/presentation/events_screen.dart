import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/event_providers.dart';
import '../data/models/event.dart';
import '../../member/presentation/main_shell.dart';

class EventsScreen extends ConsumerWidget {
  const EventsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final eventsAsync = ref.watch(eventsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Acara'),
        actions: [
          IconButton(
            tooltip: 'Pendaftaran Saya',
            onPressed: () => context.push('/events/my-registrations'),
            icon: const Icon(Icons.event_available_outlined),
          ),
          const LogoutIconButton(),
        ],
      ),
      body: eventsAsync.when(
        data: (events) => _EventsList(events: events),
        loading: () => const _EventsSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : 'Ralat tidak dijangka.',
              onRetry: () => ref.invalidate(eventsProvider),
            ),
      ),
    );
  }
}

class _EventsList extends StatelessWidget {
  const _EventsList({required this.events});

  final List<Event> events;

  @override
  Widget build(BuildContext context) {
    if (events.isEmpty) {
      return const _EventsEmpty();
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.lg,
        Spacing.lg,
        Spacing.xxl,
      ),
      itemCount: events.length + 1,
      separatorBuilder: (_, __) => const SizedBox(height: Spacing.lg),
      itemBuilder: (context, index) {
        if (index == 0) {
          return const _PageIntro(
            title: 'Program & Acara',
            subtitle: 'Ketahui dan sertai program yang akan datang.',
          );
        }
        return _EventCard(event: events[index - 1]);
      },
    );
  }
}

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event});

  final Event event;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final imageUrl = event.featured_image_url;

    return Material(
      color: AppColors.white,
      borderRadius: AppRadius.hero,
      child: InkWell(
        borderRadius: AppRadius.hero,
        onTap: () => context.push('/events/${event.id}'),
        child: ClipRRect(
          borderRadius: AppRadius.hero,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                height: 176,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    if (imageUrl != null && imageUrl.isNotEmpty)
                      AppImage(imageUrl, fit: BoxFit.cover)
                    else
                      Container(color: AppColors.paleGreen),
                    const DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.bottomCenter,
                          end: Alignment.topCenter,
                          colors: [Color(0x77071525), Colors.transparent],
                        ),
                      ),
                    ),
                    if (event.organization?.name != null)
                      Positioned(
                        top: Spacing.md,
                        left: Spacing.md,
                        child: _ImageChip(label: event.organization!.name!),
                      ),
                    Positioned(
                      bottom: Spacing.md,
                      left: Spacing.md,
                      child: _ImageChip(
                        label:
                            event.type == 'physical'
                                ? 'Fizikal'
                                : 'Dalam Talian',
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(Spacing.lg),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      event.title ?? '-',
                      style: theme.textTheme.titleMedium,
                    ),
                    const SizedBox(height: Spacing.sm),
                    Row(
                      children: [
                        const Icon(
                          Icons.calendar_today_outlined,
                          size: 18,
                          color: AppColors.textSecondary,
                        ),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            event.start_formatted ?? '',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        const Icon(
                          Icons.location_on_outlined,
                          size: 18,
                          color: AppColors.textSecondary,
                        ),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            event.location_or_link ?? '',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              color: AppColors.textSecondary,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PageIntro extends StatelessWidget {
  const _PageIntro({required this.title, required this.subtitle});
  final String title;
  final String subtitle;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: Theme.of(
          context,
        ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: Spacing.xs),
      Text(
        subtitle,
        style: Theme.of(
          context,
        ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
      ),
    ],
  );
}

class _ImageChip extends StatelessWidget {
  const _ImageChip({required this.label});
  final String label;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: Spacing.sm, vertical: 5),
    decoration: BoxDecoration(
      color: AppColors.white.withValues(alpha: .94),
      borderRadius: AppRadius.md,
    ),
    child: Text(
      label,
      style: const TextStyle(
        fontSize: 10,
        fontWeight: FontWeight.w700,
        color: AppColors.textPrimary,
      ),
    ),
  );
}

class _EventsEmpty extends StatelessWidget {
  const _EventsEmpty();
  @override
  Widget build(BuildContext context) => Center(
    child: Container(
      margin: const EdgeInsets.all(Spacing.xl),
      padding: const EdgeInsets.all(Spacing.xxl),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: AppRadius.hero,
        border: Border.all(color: AppColors.divider),
      ),
      child: const Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.event_busy_outlined,
            size: 48,
            color: AppColors.textSecondary,
          ),
          SizedBox(height: Spacing.md),
          Text('Tiada program dijadualkan'),
          SizedBox(height: Spacing.xs),
          Text(
            'Program baharu akan muncul di sini apabila dijadualkan.',
            textAlign: TextAlign.center,
          ),
        ],
      ),
    ),
  );
}

class _EventsSkeleton extends StatelessWidget {
  const _EventsSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      itemCount: 6,
      itemBuilder:
          (_, __) => const Padding(
            padding: EdgeInsets.only(bottom: Spacing.lg),
            child: SkeletonBox(height: 240, radius: 16),
          ),
    );
  }
}
