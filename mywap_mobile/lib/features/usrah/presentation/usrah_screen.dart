import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../member/presentation/widgets/notification_bell.dart';
import '../../member/presentation/widgets/shell_scaffold_key.dart';
import '../application/usrah_providers.dart';
import '../data/models/usrah.dart';

class UsrahScreen extends ConsumerWidget {
  const UsrahScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final usrahAsync = ref.watch(usrahProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const AppMenuButton(),
        title: const Text('Usrah'),
        actions: const [NotificationBell(), SizedBox(width: Spacing.sm)],
      ),
      body: usrahAsync.when(
        data: (data) => _UsrahBody(
          data: data,
          onRefresh: () async => ref.invalidate(usrahProvider),
        ),
        loading: () => const _UsrahSkeleton(),
        error: (error, _) => ErrorRetry(
          message:
              error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(usrahProvider),
        ),
      ),
    );
  }
}

class _UsrahBody extends StatelessWidget {
  const _UsrahBody({required this.data, required this.onRefresh});

  final UsrahData data;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      children: [
        const SectionHeader('Kumpulan Usrah Saya'),
        if (data.groups.isEmpty)
          const Padding(
            padding: EdgeInsets.all(Spacing.lg),
            child: EmptyState(
              icon: Icons.groups_outlined,
              message: 'Anda belum menyertai mana-mana kumpulan usrah.',
            ),
          )
        else
          for (final group in data.groups) _GroupCard(group: group),
        const SectionHeader('Sejarah Kehadiran'),
        if (data.attendanceHistory.isEmpty)
          const Padding(
            padding: EdgeInsets.all(Spacing.lg),
            child: EmptyState(
              icon: Icons.fact_check_outlined,
              message: 'Tiada rekod kehadiran buat masa ini.',
            ),
          )
        else
          for (final record in data.attendanceHistory)
            _AttendanceTile(record: record),
        const SizedBox(height: Spacing.xl),
      ],
      ),
    );
  }
}

class _GroupCard extends StatelessWidget {
  const _GroupCard({required this.group});

  final UsrahGroup group;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(group.name ?? '-', style: theme.textTheme.titleMedium),
                ),
                if (group.isLeader) const _LeaderBadge(),
              ],
            ),
            if (group.description != null && group.description!.isNotEmpty) ...[
              const SizedBox(height: Spacing.xs),
              Text(
                group.description!,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
            ],
            const SizedBox(height: Spacing.md),
            _InfoRow(
              icon: Icons.calendar_today_outlined,
              text: group.meetingDay ?? '-',
            ),
            _InfoRow(
              icon: Icons.schedule,
              text: group.meetingTime ?? '-',
            ),
            const SizedBox(height: Spacing.md),
            Text('Ahli (${group.members.length})', style: theme.textTheme.labelLarge),
            const SizedBox(height: Spacing.sm),
            Wrap(
              spacing: Spacing.sm,
              runSpacing: Spacing.sm,
              children: [
                for (final member in group.members)
                  _MemberChip(member: member),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _MemberChip extends StatelessWidget {
  const _MemberChip({required this.member});

  final UsrahMember member;

  @override
  Widget build(BuildContext context) {
    final isLeader = member.role == 'leader' || member.role == 'sub_leader';
    return Chip(
      avatar: Icon(
        isLeader ? Icons.star : Icons.person,
        size: 18,
        color: isLeader ? AppColors.warning : AppColors.textSecondary,
      ),
      label: Text(member.name ?? '-'),
      visualDensity: VisualDensity.compact,
      backgroundColor: isLeader
          ? AppColors.warning.withValues(alpha: 0.12)
          : AppColors.movementOffWhite,
    );
  }
}

class _LeaderBadge extends StatelessWidget {
  const _LeaderBadge();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.sm, vertical: 4),
      decoration: BoxDecoration(
        color: AppColors.movementGreen.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.star, size: 14, color: AppColors.warning),
          SizedBox(width: 4),
          Text(
            'Pemimpin',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppColors.movementGreen,
            ),
          ),
        ],
      ),
    );
  }
}

class _AttendanceTile extends StatelessWidget {
  const _AttendanceTile({required this.record});

  final UsrahAttendance record;

  @override
  Widget build(BuildContext context) {
    final date = _formatDate(record.date);
    final statusLabel = switch (record.status) {
      'present' => 'Hadir',
      'absent' => 'Tidak Hadir',
      _ => record.status ?? '-',
    };
    final color = switch (record.status) {
      'present' => AppColors.success,
      'absent' => AppColors.error,
      _ => AppColors.textSecondary,
    };

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: Spacing.lg, vertical: Spacing.sm),
      child: ListTile(
        leading: Icon(Icons.fact_check_outlined, color: color),
        title: Text(date ?? (record.date ?? '-')),
        subtitle: record.notes != null && record.notes!.isNotEmpty
            ? Text(record.notes!)
            : null,
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: Spacing.sm, vertical: 4),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(999),
          ),
          child: Text(
            statusLabel,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
          ),
        ),
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
      padding: const EdgeInsets.only(top: Spacing.xs),
      child: Row(
        children: [
          Icon(icon, size: 18, color: AppColors.textSecondary),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              text,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            ),
          ),
        ],
      ),
    );
  }
}

class _UsrahSkeleton extends StatelessWidget {
  const _UsrahSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        const SkeletonBox(height: 180, radius: 16),
        const SizedBox(height: Spacing.xl),
        for (var i = 0; i < 4; i++) ...[
          const SkeletonBox(height: 64, radius: 12),
          const SizedBox(height: Spacing.md),
        ],
      ],
    );
  }
}

const List<String> _months = [
  'Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun',
  'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis',
];

String? _formatDate(String? iso) {
  final dt = DateTime.tryParse(iso ?? '');
  if (dt == null) return null;
  return '${dt.day} ${_months[dt.month - 1]} ${dt.year}';
}
