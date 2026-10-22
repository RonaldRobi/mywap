import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../member/presentation/widgets/notification_bell.dart';
import '../application/poll_providers.dart';
import '../data/models/poll.dart';

class PollsScreen extends ConsumerWidget {
  const PollsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final pollsAsync = ref.watch(pollsProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.pollTitle),
        actions: const [NotificationBell(), SizedBox(width: Spacing.sm)],
      ),
      body: pollsAsync.when(
        data:
            (data) => _PollsBody(
              data: data,
              onRefresh: () async => ref.invalidate(pollsProvider),
            ),
        loading: () => const _PollsSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException ? error.message : l10n.unexpectedError,
              onRetry: () => ref.invalidate(pollsProvider),
            ),
      ),
    );
  }
}

class _PollsBody extends StatelessWidget {
  const _PollsBody({required this.data, required this.onRefresh});

  final PollListData data;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    if (data.availablePolls.isEmpty && data.answeredPolls.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: EmptyState(
            icon: Icons.poll_outlined,
            message: l10n.pollNoPolls,
          ),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.zero,
        children: [
          SectionHeader(l10n.pollLatestPolls),
          if (data.availablePolls.isEmpty)
            _EmptySection(message: l10n.pollNoOpenPolls)
          else
            for (final poll in data.availablePolls)
              _PollCard(poll: poll, answered: false),
          SectionHeader(l10n.pollAnsweredSection),
          if (data.answeredPolls.isEmpty)
            _EmptySection(message: l10n.pollNoAnswered)
          else
            for (final poll in data.answeredPolls)
              _PollCard(poll: poll, answered: true),
          const SizedBox(height: Spacing.xl),
        ],
      ),
    );
  }
}

class _PollCard extends StatelessWidget {
  const _PollCard({required this.poll, required this.answered});

  final PollSummary poll;
  final bool answered;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(
          horizontal: Spacing.lg,
          vertical: Spacing.sm,
        ),
        leading: CircleAvatar(
          backgroundColor: AppColors.movementSoftGreen.withValues(alpha: 0.25),
          child: const Icon(Icons.poll, color: AppColors.movementGreen),
        ),
        title: Text(poll.title ?? '-', style: theme.textTheme.titleMedium),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (poll.type != null) ...[
              const SizedBox(height: 2),
              Text(
                _typeLabel(context, poll.type!),
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.movementGreen,
                ),
              ),
            ],
            const SizedBox(height: 2),
            Text(
              '${_endsLabel(context, poll)} • '
              '${context.l10n.pollResponsesCount(poll.responseCount)}',
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ),
        trailing: const Icon(
          Icons.chevron_right,
          color: AppColors.textSecondary,
        ),
        onTap: () {
          if (answered) {
            context.push('/polls/${poll.id}/results');
          } else {
            context.push('/polls/${poll.id}');
          }
        },
      ),
    );
  }
}

class _EmptySection extends StatelessWidget {
  const _EmptySection({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(Spacing.lg),
      child: Text(
        message,
        textAlign: TextAlign.center,
        style: Theme.of(
          context,
        ).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
      ),
    );
  }
}

class _PollsSkeleton extends StatelessWidget {
  const _PollsSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        const SkeletonBox(height: 24, width: 180),
        const SizedBox(height: Spacing.md),
        for (var i = 0; i < 5; i++) ...[
          const SkeletonBox(height: 88, radius: 16),
          const SizedBox(height: Spacing.md),
        ],
      ],
    );
  }
}

String _typeLabel(BuildContext context, String type) => switch (type) {
  'poll' => context.l10n.pollTypePoll,
  'survey' => context.l10n.pollTypeSurvey,
  _ => type,
};

String _endsLabel(BuildContext context, PollSummary poll) {
  if (poll.isExpired) return context.l10n.pollEnded;
  if (poll.endsAtFormatted != null && poll.endsAtFormatted!.isNotEmpty) {
    return context.l10n.pollEndsAt(poll.endsAtFormatted!);
  }
  return context.l10n.pollNoEndDate;
}
