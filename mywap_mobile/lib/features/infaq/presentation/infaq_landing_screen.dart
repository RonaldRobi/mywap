import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../member/presentation/widgets/notification_bell.dart';
import '../../member/presentation/widgets/shell_scaffold_key.dart';
import '../application/infaq_providers.dart';
import '../data/models/infaq.dart';

/// Real implementation of the "Infaq" bottom-nav tab. Alias
/// [InfaqLandingScreen] kept for the central router (app_router.dart).
class InfaqScreen extends ConsumerStatefulWidget {
  const InfaqScreen({super.key});

  @override
  ConsumerState<InfaqScreen> createState() => _InfaqScreenState();
}

typedef InfaqLandingScreen = InfaqScreen;

class _InfaqScreenState extends ConsumerState<InfaqScreen> {
  int? _selectedOrgId;

  @override
  Widget build(BuildContext context) {
    final listAsync = ref.watch(infaqListProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const AppMenuButton(),
        title: const Text('Infaq'),
        actions: const [NotificationBell(), SizedBox(width: Spacing.sm)],
      ),
      body: listAsync.when(
        data:
            (list) => _InfaqList(
              list: list,
              selectedOrgId: _selectedOrgId,
              onOrgSelected: (id) => setState(() => _selectedOrgId = id),
            ),
        loading: () => const _InfaqListSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : 'Ralat tidak dijangka.',
              onRetry: () => ref.invalidate(infaqListProvider),
            ),
      ),
    );
  }
}

class _InfaqList extends ConsumerWidget {
  const _InfaqList({
    required this.list,
    required this.selectedOrgId,
    required this.onOrgSelected,
  });

  final InfaqListData list;
  final int? selectedOrgId;
  final ValueChanged<int?> onOrgSelected;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final hasFilter = list.organizations.isNotEmpty;
    final hasGlobal = list.hasGlobal;
    final infaqs =
        selectedOrgId == null
            ? list.infaqs
            : list.infaqs
                .where((i) => i.organizationId == selectedOrgId)
                .toList();

    return RefreshIndicator(
      onRefresh: () => ref.refresh(infaqListProvider.future),
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverToBoxAdapter(child: _InfaqIntro(infaqs: list.infaqs)),
          if (hasFilter)
            SliverToBoxAdapter(
              child: _OrgFilter(
                organizations: list.organizations,
                hasGlobal: hasGlobal,
                selectedOrgId: selectedOrgId,
                onSelected: onOrgSelected,
              ),
            ),
          if (infaqs.isEmpty)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: EmptyState(
                icon: Icons.volunteer_activism_outlined,
                message: 'Tiada kempen infaq buat masa ini.',
              ),
            )
          else
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(
                Spacing.lg,
                Spacing.sm,
                Spacing.lg,
                Spacing.xl,
              ),
              sliver: SliverList.builder(
                itemCount: infaqs.length,
                itemBuilder: (_, index) => _InfaqCard(infaq: infaqs[index]),
              ),
            ),
        ],
      ),
    );
  }
}

class _OrgFilter extends StatelessWidget {
  const _OrgFilter({
    required this.organizations,
    required this.hasGlobal,
    required this.selectedOrgId,
    required this.onSelected,
  });

  final List<InfaqOrganization> organizations;
  final bool hasGlobal;
  final int? selectedOrgId;
  final ValueChanged<int?> onSelected;

  @override
  Widget build(BuildContext context) {
    final chips = <({int? id, String label})>[
      const (id: null, label: 'Semua'),
      for (final org in organizations) (id: org.id, label: org.name ?? '-'),
    ];

    return SizedBox(
      height: 52,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.fromLTRB(
          Spacing.lg,
          Spacing.sm,
          Spacing.lg,
          0,
        ),
        itemCount: chips.length,
        separatorBuilder: (_, __) => const SizedBox(width: Spacing.sm),
        itemBuilder: (context, index) {
          final chip = chips[index];
          final selected = chip.id == selectedOrgId;
          return ChoiceChip(
            label: Text(chip.label),
            selected: selected,
            onSelected: (_) => onSelected(chip.id),
          );
        },
      ),
    );
  }
}

class _InfaqCard extends StatelessWidget {
  const _InfaqCard({required this.infaq});

  final Infaq infaq;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final imageUrl = infaq.imagePath;
    final progress = ((infaq.progressPercent ?? 0).clamp(0, 100)) / 100;
    final orgName = infaq.organizationName;

    return Card(
      clipBehavior: Clip.antiAlias,
      margin: const EdgeInsets.only(bottom: Spacing.lg),
      child: InkWell(
        onTap: () => context.push('/infaq/${infaq.slug}'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              height: 170,
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
                        colors: [Color(0x66071525), Colors.transparent],
                      ),
                    ),
                  ),
                  Positioned(
                    left: Spacing.md,
                    bottom: Spacing.md,
                    child: _CampaignTag(
                      label:
                          infaq.type == 'progress'
                              ? 'Kutip Dana'
                              : 'Derma Bebas',
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
                  if (orgName != null) ...[
                    Text(
                      orgName,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.movementGreen,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 4),
                  ],
                  Text(
                    infaq.title ?? '-',
                    style: theme.textTheme.titleMedium,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: Spacing.md),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(6),
                    child: LinearProgressIndicator(
                      value: progress,
                      minHeight: 8,
                      backgroundColor: AppColors.divider,
                      color: AppColors.movementGreen,
                    ),
                  ),
                  const SizedBox(height: Spacing.sm),
                  Row(
                    children: [
                      Text(
                        Formatters.currency(infaq.collectedAmount),
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: AppColors.movementGreen,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const Spacer(),
                      Flexible(
                        child: Text(
                          'Sasaran ${Formatters.currency(infaq.targetAmount)}',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.textSecondary,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  if (infaq.daysRunning != null) ...[
                    const SizedBox(height: Spacing.sm),
                    Row(
                      children: [
                        const Icon(
                          Icons.schedule,
                          size: 14,
                          color: AppColors.textSecondary,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '${infaq.daysRunning} hari berjalan',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfaqIntro extends StatelessWidget {
  const _InfaqIntro({required this.infaqs});
  final List<Infaq> infaqs;
  @override
  Widget build(BuildContext context) {
    final total = infaqs.fold<num>(
      0,
      (sum, item) => sum + (item.collectedAmount ?? 0),
    );
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.lg,
        Spacing.lg,
        Spacing.md,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Kempen Infaq',
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: Spacing.xs),
          Text(
            'Sertai kami dalam menyumbang dan membantu mereka yang memerlukan.',
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
          ),
          if (infaqs.isNotEmpty) ...[
            const SizedBox(height: Spacing.lg),
            Row(
              children: [
                _InfaqStat(value: '${infaqs.length}', label: 'KEMPEN AKTIF'),
                const SizedBox(width: Spacing.sm),
                _InfaqStat(
                  value: Formatters.currency(total),
                  label: 'TERKUMPUL',
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _InfaqStat extends StatelessWidget {
  const _InfaqStat({required this.value, required this.label});
  final String value;
  final String label;
  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      padding: const EdgeInsets.all(Spacing.md),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: AppRadius.md,
        border: Border.all(color: AppColors.divider),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.movementGreen,
              fontWeight: FontWeight.w800,
            ),
          ),
          Text(
            label,
            style: const TextStyle(
              fontSize: 9,
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    ),
  );
}

class _CampaignTag extends StatelessWidget {
  const _CampaignTag({required this.label});
  final String label;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: Spacing.sm, vertical: 5),
    decoration: BoxDecoration(
      color: AppColors.white.withValues(alpha: .92),
      borderRadius: BorderRadius.circular(99),
    ),
    child: Text(
      label,
      style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700),
    ),
  );
}

class _InfaqListSkeleton extends StatelessWidget {
  const _InfaqListSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      itemCount: 6,
      itemBuilder:
          (_, __) => const Padding(
            padding: EdgeInsets.only(bottom: Spacing.lg),
            child: SkeletonBox(height: 300, radius: 16),
          ),
    );
  }
}
