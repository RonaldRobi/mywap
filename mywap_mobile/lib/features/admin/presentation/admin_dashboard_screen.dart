import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../member/presentation/main_shell.dart';
import '../application/admin_providers.dart';
import '../data/models/admin_models.dart';

/// Landing screen for the "Admin" bottom-nav tab (`/admin`).
class AdminDashboardScreen extends ConsumerWidget {
  const AdminDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboardAsync = ref.watch(adminDashboardProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Panel Admin'),
        actions: const [LogoutIconButton()],
      ),
      body: dashboardAsync.when(
        data: (data) => _DashboardContent(data: data),
        loading: () => const _DashboardSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(adminDashboardProvider),
        ),
      ),
    );
  }
}

class _DashboardContent extends ConsumerWidget {
  const _DashboardContent({required this.data});

  final AdminDashboard data;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(adminDashboardProvider),
      child: ListView(
        padding: const EdgeInsets.only(bottom: Spacing.xl),
        children: [
          const SectionHeader('Pengurusan'),
          const _AdminActions(),
          const SizedBox(height: Spacing.sm),
          const SectionHeader('Ringkasan'),
          _StatRow(stats: data.stats),
          const SectionHeader('Hasil Bulanan'),
          if (data.revenueValues.isEmpty)
            const _ChartEmpty()
          else
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.lg),
                  child: _RevenueChart(
                    labels: data.revenueLabels,
                    values: data.revenueValues,
                  ),
                ),
              ),
            ),
          SectionHeader('Aktiviti Terkini', trailing: _count(data.activities.length)),
          if (data.activities.isEmpty)
            const Padding(
              padding: EdgeInsets.all(Spacing.xl),
              child: Center(child: Text('Tiada aktiviti terkini.')),
            )
          else
            for (final activity in data.activities) _ActivityCard(activity: activity),
        ],
      ),
    );
  }

  Widget _count(int count) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
        decoration: BoxDecoration(
          color: AppColors.movementSoftGreen,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Text(
          '$count',
          style: const TextStyle(
            color: AppColors.movementNavy,
            fontWeight: FontWeight.w700,
            fontSize: 14,
          ),
        ),
      );
}

class _AdminActions extends StatelessWidget {
  const _AdminActions();

  static const _actions = [
    _Action('Ahli', '/admin/members', Icons.people_outline),
    _Action('Yuran', '/admin/fees', Icons.payments_outlined),
    _Action('Kehadiran', '/admin/attendance', Icons.qr_code_scanner),
    _Action('Siaran', '/admin/broadcast', Icons.campaign_outlined),
  ];

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
      child: GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 4,
          mainAxisExtent: 96,
          crossAxisSpacing: Spacing.sm,
        ),
        itemCount: _actions.length,
        itemBuilder: (context, index) {
          final action = _actions[index];
          return Card(
            margin: EdgeInsets.zero,
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: () => context.push(action.path),
              child: Padding(
                padding: const EdgeInsets.all(Spacing.sm),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(action.icon, color: AppColors.movementGreen, size: 26),
                    const SizedBox(height: Spacing.sm),
                    Text(
                      action.label,
                      textAlign: TextAlign.center,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.labelMedium,
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _Action {
  const _Action(this.label, this.path, this.icon);

  final String label;
  final String path;
  final IconData icon;
}

class _StatRow extends StatelessWidget {
  const _StatRow({required this.stats});

  final AdminStats stats;

  @override
  Widget build(BuildContext context) {
    final cards = [
      _StatCard(
        label: 'Jumlah Ahli',
        value: '${stats.totalMembers}',
        icon: Icons.group_outlined,
      ),
      _StatCard(
        label: 'Ahli Aktif',
        value: '${stats.activeMembers}',
        icon: Icons.verified_user_outlined,
      ),
      _StatCard(
        label: 'Hasil',
        value: Formatters.currency(stats.totalRevenue),
        icon: Icons.payments_outlined,
      ),
      _StatCard(
        label: 'Acara Akan Datang',
        value: '${stats.upcomingEvents}',
        icon: Icons.event_outlined,
      ),
    ];
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
      child: Row(
        children: [
          for (final card in cards) ...[
            card,
            const SizedBox(width: Spacing.md),
          ],
        ],
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: 150,
      padding: const EdgeInsets.all(Spacing.lg),
      decoration: BoxDecoration(
        color: AppColors.movementDarkGreen,
        borderRadius: AppRadius.lg,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, color: AppColors.movementSoftGreen, size: 22),
          const SizedBox(height: Spacing.sm),
          Text(
            value,
            style: theme.textTheme.headlineSmall?.copyWith(color: AppColors.white),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(color: AppColors.textOnDark),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

class _RevenueChart extends StatelessWidget {
  const _RevenueChart({required this.labels, required this.values});

  final List<String> labels;
  final List<double> values;

  @override
  Widget build(BuildContext context) {
    final maxValue = values.fold<double>(0, (m, v) => v > m ? v : m);
    final groups = [
      for (var i = 0; i < values.length; i++)
        BarChartGroupData(
          x: i,
          barRods: [
            BarChartRodData(
              toY: values[i],
              color: AppColors.movementGreen,
              width: 20,
              borderRadius: const BorderRadius.all(Radius.circular(4)),
            ),
          ],
        ),
    ];

    return SizedBox(
      height: 220,
      child: BarChart(
        BarChartData(
          maxY: maxValue <= 0 ? 1 : maxValue * 1.25,
          alignment: BarChartAlignment.spaceAround,
          barGroups: groups,
          gridData: const FlGridData(show: false),
          borderData: FlBorderData(show: false),
          titlesData: FlTitlesData(
            topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
            rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
            leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
            bottomTitles: AxisTitles(
              sideTitles: SideTitles(
                showTitles: true,
                reservedSize: 34,
                getTitlesWidget: (value, meta) {
                  final index = value.toInt();
                  if (index < 0 || index >= labels.length) {
                    return const SizedBox.shrink();
                  }
                  return Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(
                      labels[index],
                      style: const TextStyle(
                        fontSize: 10,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  );
                },
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ChartEmpty extends StatelessWidget {
  const _ChartEmpty();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.all(Spacing.xl),
      child: Center(child: Text('Tiada data pendapatan bulanan.')),
    );
  }
}

class _ActivityCard extends StatelessWidget {
  const _ActivityCard({required this.activity});

  final AdminActivity activity;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: Spacing.lg, vertical: Spacing.sm),
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: const BoxDecoration(
                color: AppColors.movementSoftGreen,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.campaign_outlined, color: AppColors.movementNavy, size: 20),
            ),
            const SizedBox(width: Spacing.lg),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(activity.title.isEmpty ? 'Aktiviti' : activity.title,
                      style: theme.textTheme.titleSmall),
                  if (activity.description.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      activity.description,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ],
              ),
            ),
            if (activity.createdAt != null) ...[
              const SizedBox(width: Spacing.sm),
              Text(
                Formatters.datetime(activity.createdAt),
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _DashboardSkeleton extends StatelessWidget {
  const _DashboardSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: const [
        SkeletonBox(height: 24, width: 140),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 96),
        SizedBox(height: Spacing.xl),
        SkeletonBox(height: 24, width: 140),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 110),
        SizedBox(height: Spacing.xl),
        SkeletonBox(height: 24, width: 140),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 220),
        SizedBox(height: Spacing.xl),
        SkeletonBox(height: 24, width: 140),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 72),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 72),
      ],
    );
  }
}
