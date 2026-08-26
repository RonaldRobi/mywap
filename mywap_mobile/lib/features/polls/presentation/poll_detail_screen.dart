import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/poll_providers.dart';
import '../data/models/poll.dart';

class PollDetailScreen extends ConsumerStatefulWidget {
  const PollDetailScreen({super.key, required this.pollId});

  final int pollId;

  @override
  ConsumerState<PollDetailScreen> createState() => _PollDetailScreenState();
}

class _PollDetailScreenState extends ConsumerState<PollDetailScreen> {
  final Map<int, List<int>> _selected = {};
  bool _submitting = false;
  String? _submitError;

  @override
  void initState() {
    super.initState();
    ref.read(pollDetailProvider(widget.pollId));
  }

  void _toggleOption(int questionId, int optionId) {
    setState(() {
      final current = _selected[questionId] ?? [];
      if (current.contains(optionId)) {
        _selected[questionId] = List.of(current)..remove(optionId);
      } else {
        _selected[questionId] = [...current, optionId];
      }
    });
  }

  void _selectSingle(int questionId, int optionId) {
    setState(() => _selected[questionId] = [optionId]);
  }

  Future<void> _submit() async {
    final poll = ref.read(pollDetailProvider(widget.pollId)).valueOrNull;
    if (poll == null) return;

    for (final question in poll.questions) {
      if (_selected[question.id] == null || _selected[question.id]!.isEmpty) {
        setState(() => _submitError = 'Sila jawab semua soalan.');
        return;
      }
    }

    setState(() {
      _submitting = true;
      _submitError = null;
    });
    try {
      await ref
          .read(pollRepositoryProvider)
          .respond(widget.pollId, _selected);
      if (!mounted) return;
      ref.invalidate(pollsProvider);
      _goToResults();
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.statusCode == 409) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message)),
        );
        ref.invalidate(pollsProvider);
        _goToResults();
        return;
      }
      setState(() {
        _submitting = false;
        _submitError = e.message;
      });
    }
  }

  void _goToResults() {
    context.pushReplacement('/polls/${widget.pollId}/results');
  }

  @override
  Widget build(BuildContext context) {
    final pollAsync = ref.watch(pollDetailProvider(widget.pollId));

    return Scaffold(
      appBar: AppBar(title: const Text('Butiran Undian')),
      body: pollAsync.when(
        data: (poll) => _PollForm(
          poll: poll,
          selected: _selected,
          submitting: _submitting,
          error: _submitError,
          onToggle: _toggleOption,
          onSelectSingle: _selectSingle,
          onSubmit: _submit,
        ),
        loading: () => const _DetailSkeleton(),
        error: (error, _) => ErrorRetry(
          message:
              error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(pollDetailProvider(widget.pollId)),
        ),
      ),
    );
  }
}

class _PollForm extends StatelessWidget {
  const _PollForm({
    required this.poll,
    required this.selected,
    required this.submitting,
    required this.error,
    required this.onToggle,
    required this.onSelectSingle,
    required this.onSubmit,
  });

  final Poll poll;
  final Map<int, List<int>> selected;
  final bool submitting;
  final String? error;
  final void Function(int questionId, int optionId) onToggle;
  final void Function(int questionId, int optionId) onSelectSingle;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        Text(poll.title ?? '-', style: theme.textTheme.headlineSmall),
        if (poll.description != null && poll.description!.isNotEmpty) ...[
          const SizedBox(height: Spacing.sm),
          Text(
            poll.description!,
            style: theme.textTheme.bodyLarge?.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
        ],
        const SizedBox(height: Spacing.md),
        Text(
          _endsLabel(poll),
          style: theme.textTheme.bodySmall?.copyWith(
            color: AppColors.textSecondary,
          ),
        ),
        const SizedBox(height: Spacing.lg),
        for (var i = 0; i < poll.questions.length; i++)
          _QuestionCard(
            index: i + 1,
            question: poll.questions[i],
            selected: selected[poll.questions[i].id] ?? const [],
            onToggle: onToggle,
            onSelectSingle: onSelectSingle,
          ),
        if (error != null) ...[
          const SizedBox(height: Spacing.md),
          Text(error!, style: const TextStyle(color: AppColors.error)),
        ],
        const SizedBox(height: Spacing.lg),
        FilledButton.icon(
          onPressed: submitting ? null : onSubmit,
          icon: submitting
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.white,
                  ),
                )
              : const Icon(Icons.send),
          label: const Text('Hantar Jawapan'),
        ),
        const SizedBox(height: Spacing.xl),
      ],
    );
  }
}

class _QuestionCard extends StatelessWidget {
  const _QuestionCard({
    required this.index,
    required this.question,
    required this.selected,
    required this.onToggle,
    required this.onSelectSingle,
  });

  final int index;
  final PollQuestion question;
  final List<int> selected;
  final void Function(int questionId, int optionId) onToggle;
  final void Function(int questionId, int optionId) onSelectSingle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final questionId = question.id;

    return Card(
      margin: const EdgeInsets.symmetric(vertical: Spacing.sm),
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '$index. ${question.questionText ?? '-'}',
              style: theme.textTheme.titleMedium,
            ),
            const SizedBox(height: Spacing.sm),
            if (question.isMultiple)
              for (final option in question.options)
                CheckboxListTile(
                  value: selected.contains(option.id),
                  onChanged: (_) =>
                      questionId != null && option.id != null
                          ? onToggle(questionId, option.id!)
                          : null,
                  title: Text(option.optionText ?? '-'),
                  controlAffinity: ListTileControlAffinity.leading,
                  dense: true,
                )
            else
              for (final option in question.options)
                RadioListTile<int?>(
                  value: option.id,
                  groupValue: selected.isEmpty ? null : selected.first,
                  onChanged: questionId == null || option.id == null
                      ? null
                      : (_) => onSelectSingle(questionId, option.id!),
                  title: Text(option.optionText ?? '-'),
                  controlAffinity: ListTileControlAffinity.leading,
                  dense: true,
                ),
          ],
        ),
      ),
    );
  }
}

class _DetailSkeleton extends StatelessWidget {
  const _DetailSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: const [
        SkeletonBox(height: 28, width: 280),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 16),
        SizedBox(height: Spacing.lg),
        SkeletonBox(height: 200, radius: 16),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 200, radius: 16),
      ],
    );
  }
}

String _endsLabel(Poll poll) {
  if (poll.isExpired) return 'Undian telah tamat';
  if (poll.endsAtFormatted != null && poll.endsAtFormatted!.isNotEmpty) {
    return 'Tamat pada ${poll.endsAtFormatted}';
  }
  return 'Tiada tarikh tamat';
}
