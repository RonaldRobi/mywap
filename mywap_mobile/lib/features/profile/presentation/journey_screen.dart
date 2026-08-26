import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/list_card.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/profile_providers.dart';
import '../data/models/profile_data.dart';

class JourneyScreen extends ConsumerWidget {
  const JourneyScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final profileAsync = ref.watch(profileProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Perjalanan Ahli')),
      body: profileAsync.when(
        data: (data) => _JourneyContent(
          history: data.history ?? const <ProfileHistoryEntry>[],
          programs: data.attendedPrograms ?? const <ProfileAttendedProgram>[],
        ),
        loading: () => const _JourneySkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(profileProvider),
        ),
      ),
    );
  }
}

class _JourneyContent extends StatelessWidget {
  const _JourneyContent({required this.history, required this.programs});

  final List<ProfileHistoryEntry> history;
  final List<ProfileAttendedProgram> programs;

  @override
  Widget build(BuildContext context) {
    if (history.isEmpty && programs.isEmpty) {
      return const EmptyState(
        icon: Icons.route_outlined,
        message: 'Tiada rekod perjalanan lagi.',
      );
    }

    return ListView(
      padding: const EdgeInsets.only(bottom: Spacing.xl),
      children: [
        if (history.isNotEmpty) ...[
          const SectionHeader('Perjalanan Organisasi'),
          Card(
            margin: const EdgeInsets.fromLTRB(Spacing.lg, 0, Spacing.lg, Spacing.sm),
            child: Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: Column(
                children: [
                  for (var i = 0; i < history.length; i++) ...[
                    _TimelineEntry(entry: history[i]),
                    if (i != history.length - 1) const _TimelineConnector(),
                  ],
                ],
              ),
            ),
          ),
        ],
        if (programs.isNotEmpty) ...[
          const SectionHeader('Program Dihadiri'),
          for (final program in programs) _ProgramCard(program: program),
        ],
      ],
    );
  }
}

class _TimelineEntry extends StatelessWidget {
  const _TimelineEntry({required this.entry});

  final ProfileHistoryEntry entry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final from = entry.from_organization?.name;
    final to = entry.to_organization?.name;
    final title = from == null ? 'Sertai ${to ?? 'organisasi'}' : '$from → $to';

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Icon(Icons.circle, size: 12, color: AppColors.movementGreen),
        ),
        const SizedBox(width: Spacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: theme.textTheme.titleSmall),
              const SizedBox(height: 2),
              Text(
                entry.transitioned_at_human ?? '-',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _TimelineConnector extends StatelessWidget {
  const _TimelineConnector();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.only(left: 5, top: 4, bottom: 4),
      child: Align(
        alignment: Alignment.centerLeft,
        child: SizedBox(
          height: 18,
          width: 2,
          child: ColoredBox(color: AppColors.divider),
        ),
      ),
    );
  }
}

class _ProgramCard extends StatelessWidget {
  const _ProgramCard({required this.program});

  final ProfileAttendedProgram program;

  @override
  Widget build(BuildContext context) {
    final event = program.event;
    final subtitle = [
      if (program.attended_at_human != null) program.attended_at_human,
      if (event?.location_or_link != null && event!.location_or_link!.isNotEmpty)
        event.location_or_link,
    ].join(' • ');

    return ListCard(
      leading: const Icon(Icons.event_available_outlined, color: AppColors.movementGreen),
      title: event?.title ?? '-',
      subtitle: subtitle.isEmpty ? null : subtitle,
    );
  }
}

class _JourneySkeleton extends StatelessWidget {
  const _JourneySkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: const [
        SkeletonBox(height: 20, width: 220),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 120),
        SizedBox(height: Spacing.xl),
        SkeletonBox(height: 20, width: 180),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 90),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 90),
      ],
    );
  }
}
