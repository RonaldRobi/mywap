import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/list_card.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/admin_providers.dart';
import '../data/models/admin_models.dart';

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
      builder: (_) => _MemberDetailSheet(member: member),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(adminMembersControllerProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Ahli')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(Spacing.lg, Spacing.sm, Spacing.lg, 0),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              textInputAction: TextInputAction.search,
              decoration: const InputDecoration(
                hintText: 'Cari nama atau no. ahli',
                prefixIcon: Icon(Icons.search, color: AppColors.textSecondary),
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
                _FilterChip(label: 'Semua', selected: _status == '', onTap: () => _selectStatus('')),
                _FilterChip(label: 'Aktif', selected: _status == 'active', onTap: () => _selectStatus('active')),
                _FilterChip(label: 'Belum Lengkap', selected: _status == 'pending', onTap: () => _selectStatus('pending')),
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
        onRetry: () => ref.read(adminMembersControllerProvider.notifier).retry(),
      );
    }
    if (state.items.isEmpty) {
      return const EmptyState(
        icon: Icons.people_outline,
        message: 'Tiada ahli dijumpai.',
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
          title: member.name.isEmpty ? 'Tanpa Nama' : member.name,
          subtitle: [
            if (member.memberNo.isNotEmpty) 'No. ${member.memberNo}',
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
  const _FilterChip({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: Spacing.sm, top: Spacing.sm, bottom: Spacing.sm),
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
        color: active ? AppColors.movementSoftGreen : AppColors.warning.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Text(
        active ? 'Aktif' : 'Belum Lengkap',
        style: TextStyle(
          color: active ? AppColors.movementNavy : AppColors.warning,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _MemberDetailSheet extends StatelessWidget {
  const _MemberDetailSheet({required this.member});

  final AdminMember member;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final rows = <(String, String)>[
      ('No. Ahli', member.memberNo),
      ('Emel', member.email),
      ('Telefon', member.phone),
      ('No. IC', member.icNumber),
      ('Cawangan', member.branchName),
      ('Organisasi', member.organizationName ?? '-'),
      ('Status', member.isActive ? 'Aktif' : 'Belum Lengkap'),
      ('Didaftarkan', Formatters.date(member.createdAt)),
      ('Profil Lengkap', Formatters.date(member.profileCompletedAt)),
    ];

    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(Spacing.xl, 0, Spacing.xl, Spacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(member.name.isEmpty ? 'Tanpa Nama' : member.name,
                style: theme.textTheme.headlineSmall),
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
      itemBuilder: (_, __) => const Padding(
        padding: EdgeInsets.only(bottom: Spacing.md),
        child: SkeletonBox(height: 76),
      ),
    );
  }
}
