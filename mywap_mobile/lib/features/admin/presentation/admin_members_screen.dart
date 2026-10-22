import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/list_card.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../auth/application/auth_controller.dart';
import '../application/admin_providers.dart';
import '../data/models/admin_models.dart';
import '../../../shared/theme/app_text_theme.dart';

/// Admin member directory (`/admin/members`).
class AdminMembersScreen extends ConsumerStatefulWidget {
  const AdminMembersScreen({super.key});

  @override
  ConsumerState<AdminMembersScreen> createState() => _AdminMembersScreenState();
}

class _AdminMembersScreenState extends ConsumerState<AdminMembersScreen> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();
  Timer? _debounce;
  String _status = '';

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.extentAfter < 300) {
      ref.read(adminMembersControllerProvider.notifier).loadMore();
    }
  }

  void _onSearchChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (mounted) {
        ref.read(adminMembersControllerProvider.notifier).search(value);
      }
    });
  }

  void _selectStatus(String status) {
    if (_status == status) return;
    setState(() => _status = status);
    ref.read(adminMembersControllerProvider.notifier).setStatus(status);
  }

  void _showDetail(AdminMember member) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder:
          (_) => _MemberDetailSheet(
            member: member,
            canDelete:
                ref.read(currentUserProvider)?.roles?.contains('Superadmin') ??
                false,
            onToggleActive: () => _toggleActive(member),
            onDelete: () => _deleteMember(member),
          ),
    );
  }

  void _snack(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _toggleActive(AdminMember member) async {
    final id = member.id;
    if (id == null) return;
    final activate = !member.activeFlag;

    final confirmed = await showDialog<bool>(
      context: context,
      builder:
          (dialogContext) => AlertDialog(
            title: Text(
              activate
                  ? context.l10n.adminActivateMemberTitle
                  : context.l10n.adminDeactivateMemberTitle,
            ),
            content: Text('${member.name} (${member.memberNo})'),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: Text(context.l10n.adminCancelButton),
              ),
              FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                child: Text(
                  activate
                      ? context.l10n.adminActivateButton
                      : context.l10n.adminDeactivateButton,
                ),
              ),
            ],
          ),
    );
    if (confirmed != true || !mounted) return;

    try {
      await ref.read(adminMembersControllerProvider.notifier).toggleActive(id);
      if (!mounted) return;
      Navigator.of(context).pop();
      _snack(
        activate
            ? context.l10n.adminMemberReactivated
            : context.l10n.adminMemberDeactivated,
      );
    } on ApiException catch (e) {
      if (mounted) _snack(e.message);
    }
  }

  Future<void> _deleteMember(AdminMember member) async {
    final id = member.id;
    if (id == null) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder:
          (dialogContext) => AlertDialog(
            title: Text(context.l10n.adminDeleteMemberTitle),
            content: Text(context.l10n.adminDeleteMemberMessage(member.name)),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: Text(context.l10n.adminCancelButton),
              ),
              FilledButton(
                style: FilledButton.styleFrom(backgroundColor: AppColors.error),
                onPressed: () => Navigator.of(dialogContext).pop(true),
                child: Text(context.l10n.adminDeleteButton),
              ),
            ],
          ),
    );
    if (confirmed != true || !mounted) return;

    try {
      await ref.read(adminMembersControllerProvider.notifier).deleteMember(id);
      if (!mounted) return;
      Navigator.of(context).pop();
      _snack(context.l10n.adminMemberDeleted);
    } on ApiException catch (e) {
      if (mounted) _snack(e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(adminMembersControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.adminMembersTitle)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.lg,
              Spacing.sm,
              Spacing.lg,
              0,
            ),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: context.l10n.adminSearchMembersHint,
                prefixIcon: const Icon(
                  Icons.search,
                  color: AppColors.textSecondary,
                ),
                isDense: true,
              ),
            ),
          ),
          SizedBox(
            height: 52,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              children: [
                _FilterChip(
                  label: context.l10n.adminFilterAll,
                  selected: _status == '',
                  onTap: () => _selectStatus(''),
                ),
                _FilterChip(
                  label: context.l10n.adminFilterActive,
                  selected: _status == 'active',
                  onTap: () => _selectStatus('active'),
                ),
                _FilterChip(
                  label: context.l10n.adminFilterIncomplete,
                  selected: _status == 'pending',
                  onTap: () => _selectStatus('pending'),
                ),
              ],
            ),
          ),
          Expanded(child: _buildBody(state)),
        ],
      ),
    );
  }

  Widget _buildBody(AdminMembersState state) {
    if (state.items.isEmpty && state.loading) {
      return const _MembersSkeleton();
    }
    if (state.items.isEmpty && state.error != null) {
      return ErrorRetry(
        message: state.error!,
        onRetry:
            () => ref.read(adminMembersControllerProvider.notifier).retry(),
      );
    }
    if (state.items.isEmpty) {
      return EmptyState(
        icon: Icons.people_outline,
        message: context.l10n.adminNoMembersFound,
      );
    }

    final items = state.items;
    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.only(bottom: Spacing.xl),
      itemCount: items.length + (state.hasMore ? 1 : 0),
      itemBuilder: (context, index) {
        if (index >= items.length) {
          return const Padding(
            padding: EdgeInsets.all(Spacing.lg),
            child: Center(
              child: SizedBox(
                width: 24,
                height: 24,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
          );
        }
        final member = items[index];
        return ListCard(
          title: member.name.isEmpty ? context.l10n.adminNoName : member.name,
          subtitle: [
            if (member.memberNo.isNotEmpty)
              context.l10n.adminMemberNoPrefix(member.memberNo),
            if (member.branchName.isNotEmpty) member.branchName,
          ].join(' · '),
          trailing: _StatusBadge(active: member.isActive),
          onTap: () => _showDetail(member),
        );
      },
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(
        right: Spacing.sm,
        top: Spacing.sm,
        bottom: Spacing.sm,
      ),
      child: ChoiceChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) => onTap(),
        showCheckmark: false,
        selectedColor: AppColors.movementDarkGreen,
        labelStyle: TextStyle(
          color: selected ? AppColors.white : AppColors.textPrimary,
          fontWeight: FontWeight.w600,
        ),
        backgroundColor: AppColors.surface,
        side: const BorderSide(color: AppColors.divider),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color:
            active
                ? AppColors.movementSoftGreen
                : AppColors.warning.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Text(
        active
            ? context.l10n.adminFilterActive
            : context.l10n.adminFilterIncomplete,
        style: TextStyle(
          color: active ? AppColors.movementNavy : AppColors.warning,
          fontSize: AppTextTheme.minSize,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _MemberDetailSheet extends StatelessWidget {
  const _MemberDetailSheet({
    required this.member,
    required this.canDelete,
    required this.onToggleActive,
    required this.onDelete,
  });

  final AdminMember member;
  final bool canDelete;
  final VoidCallback onToggleActive;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final rows = <(String, String)>[
      (context.l10n.adminFieldMemberNo, member.memberNo),
      (context.l10n.adminFieldEmail, member.email),
      (context.l10n.adminFieldPhone, member.phone),
      (context.l10n.adminFieldIcNo, member.icNumber),
      (context.l10n.adminFieldBranch, member.branchName),
      (context.l10n.adminFieldOrganization, member.organizationName ?? '-'),
      (
        context.l10n.adminFieldStatus,
        member.isActive
            ? context.l10n.adminFilterActive
            : context.l10n.adminFilterIncomplete,
      ),
      (
        context.l10n.adminFieldAccount,
        member.activeFlag
            ? context.l10n.adminAccountActivated
            : context.l10n.adminAccountDeactivated,
      ),
      (context.l10n.adminFieldRegistered, Formatters.date(member.createdAt)),
      (
        context.l10n.adminFieldProfileCompleted,
        Formatters.date(member.profileCompletedAt),
      ),
    ];

    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(
          Spacing.xl,
          0,
          Spacing.xl,
          Spacing.xl,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              member.name.isEmpty ? context.l10n.adminNoName : member.name,
              style: theme.textTheme.headlineSmall,
            ),
            const SizedBox(height: Spacing.lg),
            for (final (label, value) in rows) ...[
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 110,
                    child: Text(
                      label,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ),
                  Expanded(
                    child: Text(
                      value.isEmpty ? '-' : value,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: Spacing.sm),
            ],
            const SizedBox(height: Spacing.lg),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: onToggleActive,
                icon: Icon(
                  member.activeFlag
                      ? Icons.person_off_outlined
                      : Icons.person_outline,
                ),
                label: Text(
                  member.activeFlag
                      ? context.l10n.adminDeactivateMemberButton
                      : context.l10n.adminReactivateMemberButton,
                ),
              ),
            ),
            if (canDelete) ...[
              const SizedBox(height: Spacing.sm),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.error,
                  ),
                  onPressed: onDelete,
                  icon: const Icon(Icons.delete_outline),
                  label: Text(context.l10n.adminDeleteMemberButton),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _MembersSkeleton extends StatelessWidget {
  const _MembersSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(Spacing.lg),
      itemCount: 6,
      itemBuilder:
          (_, __) => const Padding(
            padding: EdgeInsets.only(bottom: Spacing.md),
            child: SkeletonBox(height: 76),
          ),
    );
  }
}
