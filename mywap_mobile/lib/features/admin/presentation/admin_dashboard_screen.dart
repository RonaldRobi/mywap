import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/data/models/user.dart';
import '../../events/data/models/event.dart';
import '../application/admin_providers.dart';
import '../data/models/admin_models.dart';

/// Skrin Utama / Dashboard Ruang Kerja Pentadbir (`/admin`).
class AdminDashboardScreen extends ConsumerWidget {
  const AdminDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    final dashboardAsync = ref.watch(adminDashboardProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              context.l10n.adminPanelTitle,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            if (user != null)
              Text(
                user.adminRoleTitle,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  color: AppColors.textSecondary,
                ),
              ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            tooltip: context.l10n.adminDashboardNotificationsTooltip,
            onPressed: () => context.push('/notifications'),
          ),
        ],
      ),
      body: dashboardAsync.when(
        data: (data) => _DashboardContent(user: user, data: data),
        loading: () => const _DashboardSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : context.l10n.adminUnexpectedError,
              onRetry: () => ref.invalidate(adminDashboardProvider),
            ),
      ),
    );
  }
}

class _DashboardContent extends ConsumerWidget {
  const _DashboardContent({required this.user, required this.data});

  final User? user;
  final AdminDashboard data;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final upcomingEventsAsync = ref.watch(adminUpcomingEventsProvider);

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(adminDashboardProvider);
        ref.invalidate(adminUpcomingEventsProvider);
      },
      child: ListView(
        padding: const EdgeInsets.only(bottom: Spacing.xxl),
        children: [
          // 1. Kad Identiti Peranan & Skop
          if (user != null) _RoleBanner(user: user!),

          // 2. Kad Hero: Meja Pendaftaran Acara Terdekat
          upcomingEventsAsync.maybeWhen(
            data: (events) => _HeroEventDesk(events: events),
            orElse: () => const SizedBox.shrink(),
          ),

          const SizedBox(height: Spacing.sm),

          // 3. Tindakan Pantas Pentadbir (4 Grid Butang)
          SectionHeader(context.l10n.adminManagementSection),
          const _AdminActionsGrid(),

          const SizedBox(height: Spacing.sm),

          // 4. Ringkasan Statistik Berdasarkan Skop
          SectionHeader(context.l10n.adminSummarySection),
          _StatRow(stats: data.stats),

          const SizedBox(height: Spacing.sm),

          // 5. Carta Kutipan Yuran Bulanan
          SectionHeader(context.l10n.adminMonthlyRevenueSection),
          if (data.revenueValues.isEmpty)
            const _ChartEmpty()
          else
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              child: Card(
                color: AppColors.white,
                shape: const RoundedRectangleBorder(
                  borderRadius: AppRadius.card,
                  side: BorderSide(color: AppColors.divider),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.lg),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _RevenueHeader(values: data.revenueValues),
                      const SizedBox(height: Spacing.md),
                      _RevenueChart(
                        labels: data.revenueLabels,
                        values: data.revenueValues,
                      ),
                    ],
                  ),
                ),
              ),
            ),

          const SizedBox(height: Spacing.sm),

          // 6. Aktiviti Pentadbiran Terkini
          SectionHeader(
            context.l10n.adminRecentActivitySection,
            trailing: _countBadge(data.activities.length),
          ),
          if (data.activities.isEmpty)
            Padding(
              padding: const EdgeInsets.all(Spacing.xl),
              child: Center(child: Text(context.l10n.adminNoRecentActivity)),
            )
          else
            for (final activity in data.activities)
              _ActivityCard(activity: activity),

          const SizedBox(height: Spacing.lg),

          // 7. Nota Panduan Portal Web
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
            child: Container(
              padding: const EdgeInsets.all(Spacing.md),
              decoration: BoxDecoration(
                color: AppColors.surfaceMuted,
                borderRadius: AppRadius.md,
                border: Border.all(color: AppColors.divider),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.laptop_chromebook_rounded,
                    color: AppColors.textSecondary,
                    size: 20,
                  ),
                  const SizedBox(width: Spacing.sm),
                  Expanded(
                    child: Text(
                      context.l10n.adminWebPortalNote,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _countBadge(int count) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
    decoration: BoxDecoration(
      color: AppColors.paleGreen,
      borderRadius: BorderRadius.circular(10),
    ),
    child: Text(
      '$count',
      style: const TextStyle(
        color: AppColors.movementDarkGreen,
        fontWeight: FontWeight.w700,
        fontSize: 13,
      ),
    ),
  );
}

/// Kad pengenalan peranan & skop tanggungjawab admin
class _RoleBanner extends StatelessWidget {
  const _RoleBanner({required this.user});

  final User user;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isSuper = user.isSuperadmin;
    final isBranch = user.isBranchAdmin;

    Color badgeBg;
    Color badgeText;
    String scopeDesc;

    if (isSuper) {
      badgeBg = const Color(0xFF1E293B);
      badgeText = AppColors.white;
      scopeDesc = context.l10n.adminFullAccessScope;
    } else if (isBranch) {
      badgeBg = AppColors.accentTint;
      badgeText = AppColors.warning;
      scopeDesc = context.l10n.adminBranchScope(
        user.branch_name ?? context.l10n.adminDefaultBranch,
      );
    } else {
      badgeBg = AppColors.paleGreen;
      badgeText = AppColors.movementDarkGreen;
      scopeDesc = context.l10n.adminOrgScope(
        user.organization?.name ?? context.l10n.adminDefaultOrg,
      );
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.sm,
        Spacing.lg,
        Spacing.sm,
      ),
      child: Container(
        padding: const EdgeInsets.all(Spacing.md),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: AppRadius.card,
          border: Border.all(color: AppColors.divider),
          boxShadow: AppShadows.subtle,
        ),
        child: Row(
          children: [
            CircleAvatar(
              radius: 22,
              backgroundColor:
                  isSuper
                      ? const Color(0xFF1E293B)
                      : (isBranch ? AppColors.accent : AppColors.movementGreen),
              child: Icon(
                isSuper
                    ? Icons.shield_outlined
                    : (isBranch
                        ? Icons.domain_rounded
                        : Icons.account_balance_rounded),
                color: AppColors.white,
                size: 22,
              ),
            ),
            const SizedBox(width: Spacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: badgeBg,
                          borderRadius: AppRadius.pill,
                        ),
                        child: Text(
                          user.adminRoleTitle,
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            color: badgeText,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    scopeDesc,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Hero Card Meja Pendaftaran Acara Terdekat (Hero Action)
class _HeroEventDesk extends StatelessWidget {
  const _HeroEventDesk({required this.events});

  final List<Event> events;

  @override
  Widget build(BuildContext context) {
    if (events.isEmpty) {
      return const SizedBox.shrink();
    }

    final activeEvent = events.first;
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.sm,
        Spacing.lg,
        Spacing.sm,
      ),
      child: Card(
        margin: EdgeInsets.zero,
        color: AppColors.movementNavy,
        shape: const RoundedRectangleBorder(borderRadius: AppRadius.card),
        child: Padding(
          padding: const EdgeInsets.all(Spacing.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.movementSoftGreen,
                      borderRadius: AppRadius.pill,
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.fiber_manual_record,
                          color: AppColors.movementNavy,
                          size: 10,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          context.l10n.adminRegistrationDesk,
                          style: const TextStyle(
                            color: AppColors.movementNavy,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Spacer(),
                  if (activeEvent.rsvp_count != null)
                    Text(
                      context.l10n.adminParticipantsCount(activeEvent.rsvp_count!),
                      style: const TextStyle(
                        color: AppColors.movementOffWhite,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                ],
              ),
              const SizedBox(height: Spacing.md),
              Text(
                activeEvent.title ?? context.l10n.adminProgramFallback,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: AppColors.white,
                  fontWeight: FontWeight.w700,
                ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 4),
              if (activeEvent.start_formatted != null)
                Text(
                  activeEvent.start_formatted!,
                  style: const TextStyle(
                    color: AppColors.divider,
                    fontSize: 12,
                  ),
                ),
              const SizedBox(height: Spacing.lg),
              Row(
                children: [
                  Expanded(
                    flex: 3,
                    child: FilledButton.icon(
                      onPressed: () => context.push('/admin/scan'),
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.movementGreen,
                        foregroundColor: AppColors.white,
                        minimumSize: const Size.fromHeight(48),
                        shape: const RoundedRectangleBorder(
                          borderRadius: AppRadius.md,
                        ),
                      ),
                      icon: const Icon(Icons.qr_code_scanner, size: 20),
                      label: Text(
                        context.l10n.adminScanParticipantCard,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                  ),
                  const SizedBox(width: Spacing.sm),
                  Expanded(
                    flex: 2,
                    child: OutlinedButton(
                      onPressed: () {
                        if (activeEvent.id != null) {
                          context.push('/admin/attendance/${activeEvent.id}');
                        }
                      },
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.white,
                        side: const BorderSide(color: AppColors.divider),
                        minimumSize: const Size.fromHeight(48),
                        shape: const RoundedRectangleBorder(
                          borderRadius: AppRadius.md,
                        ),
                      ),
                      child: Text(
                        context.l10n.adminList,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Tindakan Pantas Pentadbir (4 Grid Utama)
class _AdminActionsGrid extends StatelessWidget {
  const _AdminActionsGrid();

  @override
  Widget build(BuildContext context) {
    final actions = [
      _ActionItem(
        label: context.l10n.adminScanCard,
        path: '/admin/scan',
        icon: Icons.qr_code_scanner_rounded,
        color: AppColors.movementGreen,
      ),
      _ActionItem(
        label: context.l10n.adminAttendanceTitle,
        path: '/admin/attendance',
        icon: Icons.event_available_rounded,
        color: AppColors.movementDarkGreen,
      ),
      _ActionItem(
        label: context.l10n.adminMemberDirectory,
        path: '/admin/members',
        icon: Icons.people_alt_rounded,
        color: const Color(0xFF1E293B),
      ),
      _ActionItem(
        label: context.l10n.adminFeesTitle,
        path: '/admin/fees',
        icon: Icons.receipt_long_rounded,
        color: AppColors.warning,
      ),
    ];

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
        itemCount: actions.length,
        itemBuilder: (context, index) {
          final action = actions[index];
          return Card(
            margin: EdgeInsets.zero,
            color: AppColors.white,
            shape: const RoundedRectangleBorder(
              borderRadius: AppRadius.md,
              side: BorderSide(color: AppColors.divider),
            ),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: () => context.push(action.path),
              child: Padding(
                padding: const EdgeInsets.all(Spacing.sm),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircleAvatar(
                      radius: 20,
                      backgroundColor: action.color.withValues(alpha: 0.12),
                      child: Icon(action.icon, color: action.color, size: 22),
                    ),
                    const SizedBox(height: Spacing.xs),
                    Text(
                      action.label,
                      textAlign: TextAlign.center,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: AppColors.movementNavy,
                      ),
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

class _ActionItem {
  const _ActionItem({
    required this.label,
    required this.path,
    required this.icon,
    required this.color,
  });

  final String label;
  final String path;
  final IconData icon;
  final Color color;
}

class _StatRow extends StatelessWidget {
  const _StatRow({required this.stats});

  final AdminStats stats;

  @override
  Widget build(BuildContext context) {
    final cards = [
      _StatCard(
        label: context.l10n.adminStatTotalMembers,
        value: '${stats.totalMembers}',
        icon: Icons.group_outlined,
      ),
      _StatCard(
        label: context.l10n.adminStatActiveMembers,
        value: '${stats.activeMembers}',
        icon: Icons.verified_user_outlined,
      ),
      _StatCard(
        label: context.l10n.adminFeeCollection,
        value: Formatters.currency(stats.totalRevenue),
        icon: Icons.payments_outlined,
      ),
      _StatCard(
        label: context.l10n.adminStatUpcomingEvents,
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
      width: 155,
      padding: const EdgeInsets.all(Spacing.lg),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        border: Border.all(color: AppColors.divider),
        boxShadow: AppShadows.subtle,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          CircleAvatar(
            radius: 18,
            backgroundColor: AppColors.paleGreen,
            child: Icon(icon, color: AppColors.movementDarkGreen, size: 20),
          ),
          const SizedBox(height: Spacing.md),
          Text(
            value,
            style: theme.textTheme.titleLarge?.copyWith(
              color: AppColors.movementNavy,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w500,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

class _RevenueHeader extends StatelessWidget {
  const _RevenueHeader({required this.values});
  final List<double> values;

  @override
  Widget build(BuildContext context) {
    final total = values.fold<double>(0, (sum, v) => sum + v);
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                context.l10n.adminSixMonthCollection,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                Formatters.currency(total),
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: AppColors.movementNavy,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _RevenueChart extends StatelessWidget {
  const _RevenueChart({required this.labels, required this.values});

  final List<String> labels;
  final List<double> values;

  String _shortLabel(String label) {
    final parts = label.trim().split(RegExp(r'\s+'));
    return parts.isNotEmpty ? parts.first : label;
  }

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
              width: 18,
              gradient: const LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [AppColors.movementSoftGreen, AppColors.movementGreen],
              ),
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(5),
                topRight: Radius.circular(5),
              ),
            ),
          ],
        ),
    ];

    return SizedBox(
      height: 220,
      child: BarChart(
        BarChartData(
          maxY: maxValue <= 0 ? 1 : maxValue * 1.2,
          alignment: BarChartAlignment.spaceBetween,
          barGroups: groups,
          gridData: FlGridData(
            show: true,
            drawVerticalLine: false,
            horizontalInterval: maxValue <= 0 ? 1 : (maxValue * 1.2) / 4,
            getDrawingHorizontalLine:
                (_) =>
                    const FlLine(color: AppColors.surfaceMuted, strokeWidth: 1),
          ),
          borderData: FlBorderData(show: false),
          barTouchData: BarTouchData(
            enabled: true,
            touchTooltipData: BarTouchTooltipData(
              getTooltipColor: (_) => AppColors.movementNavy,
              tooltipRoundedRadius: 8,
              tooltipPadding: const EdgeInsets.symmetric(
                horizontal: 10,
                vertical: 6,
              ),
              getTooltipItem: (group, groupIndex, rod, rodIndex) {
                final label =
                    groupIndex < labels.length ? labels[groupIndex] : '';
                return BarTooltipItem(
                  '$label\n',
                  const TextStyle(
                    color: AppColors.white,
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                  ),
                  children: [
                    TextSpan(
                      text: Formatters.currency(rod.toY),
                      style: const TextStyle(
                        color: AppColors.movementSoftGreen,
                        fontWeight: FontWeight.w800,
                        fontSize: 13,
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
          titlesData: FlTitlesData(
            topTitles: const AxisTitles(
              sideTitles: SideTitles(showTitles: false),
            ),
            rightTitles: const AxisTitles(
              sideTitles: SideTitles(showTitles: false),
            ),
            leftTitles: const AxisTitles(
              sideTitles: SideTitles(showTitles: false),
            ),
            bottomTitles: AxisTitles(
              sideTitles: SideTitles(
                showTitles: true,
                reservedSize: 28,
                getTitlesWidget: (value, meta) {
                  final index = value.toInt();
                  if (index < 0 || index >= labels.length) {
                    return const SizedBox.shrink();
                  }
                  return Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(
                      _shortLabel(labels[index]),
                      maxLines: 1,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
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
    return Padding(
      padding: const EdgeInsets.all(Spacing.xl),
      child: Center(child: Text(context.l10n.adminNoMonthlyRevenueData)),
    );
  }
}

class _ActivityCard extends StatelessWidget {
  const _ActivityCard({required this.activity});

  final AdminActivity activity;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.lg, vertical: 4),
      child: Card(
        margin: EdgeInsets.zero,
        color: AppColors.white,
        shape: const RoundedRectangleBorder(
          borderRadius: AppRadius.md,
          side: BorderSide(color: AppColors.divider),
        ),
        child: Padding(
          padding: const EdgeInsets.all(Spacing.md),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: const BoxDecoration(
                  color: AppColors.paleGreen,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.campaign_outlined,
                  color: AppColors.movementDarkGreen,
                  size: 18,
                ),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      activity.title.isEmpty
                          ? context.l10n.adminActivityFallback
                          : activity.title,
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
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
                    fontSize: 11,
                  ),
                ),
              ],
            ],
          ),
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
        SkeletonBox(height: 60),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 140),
        SizedBox(height: Spacing.xl),
        SkeletonBox(height: 96),
        SizedBox(height: Spacing.xl),
        SkeletonBox(height: 110),
        SizedBox(height: Spacing.xl),
        SkeletonBox(height: 220),
      ],
    );
  }
}
