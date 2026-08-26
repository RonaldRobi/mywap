import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/list_card.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/directory_providers.dart';
import '../data/models/directory_user.dart';

class DirectoryScreen extends ConsumerStatefulWidget {
  const DirectoryScreen({super.key});

  @override
  ConsumerState<DirectoryScreen> createState() => _DirectoryScreenState();
}

class _DirectoryScreenState extends ConsumerState<DirectoryScreen> {
  final TextEditingController _searchController = TextEditingController();
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 300) {
      ref.read(directoryControllerProvider.notifier).loadMore();
    }
  }

  void _showMemberDetail(DirectoryUser user) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (_) => _MemberDetailSheet(user: user),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(directoryControllerProvider);
    final controller = ref.read(directoryControllerProvider.notifier);

    return Scaffold(
      appBar: AppBar(title: const Text('Direktori')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.lg,
              Spacing.md,
              Spacing.lg,
              Spacing.sm,
            ),
            child: TextField(
              controller: _searchController,
              onChanged: controller.setSearch,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: 'Cari ahli, industri, kepakaran...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: ValueListenableBuilder<TextEditingValue>(
                  valueListenable: _searchController,
                  builder: (_, value, __) {
                    if (value.text.isEmpty) return const SizedBox.shrink();
                    return IconButton(
                      icon: const Icon(Icons.close),
                      onPressed: () {
                        _searchController.clear();
                        controller.setSearch('');
                      },
                    );
                  },
                ),
              ),
            ),
          ),
          if (state.industries.isNotEmpty)
            SizedBox(
              height: 52,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
                itemCount: state.industries.length + 1,
                separatorBuilder: (_, __) => const SizedBox(width: Spacing.sm),
                itemBuilder: (context, index) {
                  final isAll = index == 0;
                  final label = isAll ? 'Semua' : state.industries[index - 1];
                  final selected =
                      isAll ? state.industry.isEmpty : state.industry == label;
                  return ChoiceChip(
                    label: Text(label),
                    selected: selected,
                    onSelected: (_) =>
                        controller.setIndustry(isAll ? null : label),
                  );
                },
              ),
            ),
          Expanded(child: _buildBody(state)),
        ],
      ),
    );
  }

  Widget _buildBody(DirectoryState state) {
    if (state.error != null && state.users.isEmpty) {
      return ErrorRetry(
        message: state.error ?? 'Ralat tidak dijangka.',
        onRetry: () => ref.read(directoryControllerProvider.notifier).retry(),
      );
    }
    if (state.isLoading && state.users.isEmpty) {
      return const _DirectorySkeleton();
    }
    if (state.users.isEmpty) {
      return const EmptyState(
        icon: Icons.people_outline,
        message: 'Tiada ahli dijumpai.',
      );
    }
    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.only(top: Spacing.sm, bottom: Spacing.xl),
      itemCount: state.users.length + (state.isLoadingMore ? 1 : 0),
      itemBuilder: (context, index) {
        if (index >= state.users.length) {
          return const Padding(
            padding: EdgeInsets.symmetric(vertical: Spacing.lg),
            child: Center(
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          );
        }
        final user = state.users[index];
        return _MemberCard(
          user: user,
          onTap: () => _showMemberDetail(user),
        );
      },
    );
  }
}

class _MemberCard extends StatelessWidget {
  const _MemberCard({required this.user, required this.onTap});

  final DirectoryUser user;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final subtitleParts = <String>[
      if (user.industry != null && user.industry!.isNotEmpty) user.industry!,
      if (user.expertise != null && user.expertise!.isNotEmpty) user.expertise!,
      if (user.organizationName != null &&
          user.organizationName!.isNotEmpty)
        user.organizationName!,
    ];
    return ListCard(
      leading: CircleAvatar(
        backgroundColor: AppColors.movementSoftGreen,
        foregroundColor: AppColors.movementNavy,
        child: Text(user.initial),
      ),
      title: user.name ?? '-',
      subtitle: subtitleParts.isEmpty ? null : subtitleParts.join(' • '),
      onTap: onTap,
    );
  }
}

class _MemberDetailSheet extends StatelessWidget {
  const _MemberDetailSheet({required this.user});

  final DirectoryUser user;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final orgName = user.organizationName;
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          Spacing.xl,
          Spacing.xs,
          Spacing.xl,
          Spacing.xl,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: CircleAvatar(
                radius: 36,
                backgroundColor: AppColors.movementSoftGreen,
                foregroundColor: AppColors.movementNavy,
                child: Text(
                  user.initial,
                  style: const TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
            const SizedBox(height: Spacing.lg),
            Center(
              child: Text(
                user.name ?? '-',
                style: theme.textTheme.titleLarge,
                textAlign: TextAlign.center,
              ),
            ),
            if (orgName != null && orgName.isNotEmpty) ...[
              const SizedBox(height: Spacing.xs),
              Center(
                child: Text(
                  orgName,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: AppColors.movementGreen,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
            const SizedBox(height: Spacing.lg),
            if (user.industry != null && user.industry!.isNotEmpty)
              _DetailRow(
                icon: Icons.business_outlined,
                label: 'Industri',
                value: user.industry!,
              ),
            if (user.expertise != null && user.expertise!.isNotEmpty)
              _DetailRow(
                icon: Icons.stars_outlined,
                label: 'Kepakaran',
                value: user.expertise!,
              ),
            _DetailRow(
              icon: Icons.link,
              label: 'LinkedIn',
              value: (user.linkedinUrl == null || user.linkedinUrl!.isEmpty)
                  ? 'Tiada pautan'
                  : user.linkedinUrl!,
            ),
          ],
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(top: Spacing.md),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: AppColors.textSecondary),
          const SizedBox(width: Spacing.md),
          SizedBox(
            width: 96,
            child: Text(
              label,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ),
          Expanded(child: Text(value, style: theme.textTheme.bodyMedium)),
        ],
      ),
    );
  }
}

class _DirectorySkeleton extends StatelessWidget {
  const _DirectorySkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      itemCount: 6,
      itemBuilder: (_, __) => const Padding(
        padding: EdgeInsets.only(bottom: Spacing.md),
        child: SkeletonBox(height: 76, radius: 16),
      ),
    );
  }
}
