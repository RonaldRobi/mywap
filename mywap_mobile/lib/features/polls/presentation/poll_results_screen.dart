import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/poll_providers.dart';
import '../data/models/poll.dart';

class PollResultsScreen extends ConsumerWidget {
  const PollResultsScreen({super.key, required this.pollId});

  final int pollId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final resultsAsync = ref.watch(pollResultsProvider(pollId));

    return Scaffold(
      appBar: AppBar(title: const Text('Keputusan Undian')),
      body: resultsAsync.when(
        data: (results) => _ResultsBody(
          results: results,
          onRefresh: () async => ref.invalidate(pollResultsProvider(pollId)),
        ),
        loading: () => const _ResultsSkeleton(),
        error: (error, _) => ErrorRetry(
          message:
              error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(pollResultsProvider(pollId)),
        ),
      ),
    );
  }
}

class _ResultsBody extends StatelessWidget {
  const _ResultsBody({required this.results, required this.onRefresh});

  final PollResults results;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final title = results.poll?.title ?? 'Keputusan';

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        Text(title, style: theme.textTheme.headlineSmall),
        const SizedBox(height: Spacing.xs),
        Text(
          '${results.totalResponses} respons',
          style: theme.textTheme.bodyMedium?.copyWith(
            color: AppColors.textSecondary,
          ),
        ),
        const SizedBox(height: Spacing.lg),
        for (final question in results.questions) _QuestionResult(question: question),
        const SizedBox(height: Spacing.xl),
      ],
      ),
    );
  }
}

class _QuestionResult extends StatelessWidget {
  const _QuestionResult({required this.question});

  final PollResultQuestion question;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      margin: const EdgeInsets.symmetric(vertical: Spacing.sm),
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(question.questionText ?? '-', style: theme.textTheme.titleMedium),
            const SizedBox(height: Spacing.sm),
            for (final option in question.options) _OptionBar(option: option),
            const SizedBox(height: Spacing.xs),
            Text(
              'Jumlah jawapan: ${question.totalAnswers}',
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _OptionBar extends StatelessWidget {
  const _OptionBar({required this.option});

  final PollResultOption option;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(top: Spacing.sm),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  option.optionText ?? '-',
                  style: theme.textTheme.bodyMedium,
                ),
              ),
              Text(
                '${option.count} (${option.percentage.toStringAsFixed(1)}%)',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.movementGreen,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: (option.widthPct / 100).clamp(0.0, 1.0),
              minHeight: 10,
              backgroundColor: AppColors.divider,
              color: AppColors.movementGreen,
            ),
          ),
        ],
      ),
    );
  }
}

class _ResultsSkeleton extends StatelessWidget {
  const _ResultsSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: const [
        SkeletonBox(height: 28, width: 280),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 200, radius: 16),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 160, radius: 16),
      ],
    );
  }
}
