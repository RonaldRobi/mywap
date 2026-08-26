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
import '../../member/presentation/main_shell.dart';
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
        title: const Text('Infaq'),
        actions: const [LogoutIconButton()],
      ),
      body: listAsync.when(
        data: (list) => _InfaqList(
          list: list,
          selectedOrgId: _selectedOrgId,
          onOrgSelected: (id) => setState(() => _selectedOrgId = id),
        ),
        loading: () => const _InfaqListSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
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
    final infaqs = selectedOrgId == null
        ? list.infaqs
        : list.infaqs.where((i) => i.organizationId == selectedOrgId).toList();

    if (list.infaqs.isEmpty) {
      return const EmptyState(
        icon: Icons.volunteer_activism_outlined,
        message: 'Tiada kempen infaq buat masa ini.',
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.refresh(infaqListProvider.future),
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
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
                icon: Icons.filter_alt_off_outlined,
                message: 'Tiada kempen untuk organisasi ini.',
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
        padding: const EdgeInsets.fromLTRB(Spacing.lg, Spacing.sm, Spacing.lg, 0),
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
      child: InkWell(
        onTap: () => context.push('/infaq/${infaq.slug}'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (imageUrl != null && imageUrl.isNotEmpty)
              AppImage(imageUrl, height: 160, width: double.infinity)
            else
              const SizedBox(height: 12),
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

class _InfaqListSkeleton extends StatelessWidget {
  const _InfaqListSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      itemCount: 6,
      itemBuilder: (_, __) => const Padding(
        padding: EdgeInsets.only(bottom: Spacing.lg),
        child: SkeletonBox(height: 300, radius: 16),
      ),
    );
  }
}
