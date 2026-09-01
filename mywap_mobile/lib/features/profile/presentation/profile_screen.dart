import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/list_card.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/profile_providers.dart';
import '../data/models/profile_data.dart';
import 'profile_format.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final profileAsync = ref.watch(profileProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Profil')),
      body: profileAsync.when(
        data: (data) => _ProfileContent(
          data: data,
          onRefresh: () async => ref.invalidate(profileProvider),
        ),
        loading: () => const _ProfileSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(profileProvider),
        ),
      ),
    );
  }
}

class _ProfileContent extends ConsumerWidget {
  const _ProfileContent({required this.data, required this.onRefresh});

  final ProfileData data;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = data.profileUser;
    if (user == null) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: const SingleChildScrollView(
          physics: AlwaysScrollableScrollPhysics(),
          child: EmptyState(
            icon: Icons.person_outline,
            message: 'Tiada data profil ditemui.',
          ),
        ),
      );
    }

    final history = data.history ?? const <ProfileHistoryEntry>[];

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.only(bottom: Spacing.xl),
      children: [
        _ProfileHeader(user: user),
        if (!user.isComplete) ...[
          const SizedBox(height: Spacing.md),
          _CompleteProfileBanner(onPressed: () => context.push('/profile/complete')),
        ],
        if (user.feeStatus != null) ...[
          const SizedBox(height: Spacing.md),
          _FeeStatusCard(feeStatus: user.feeStatus!),
        ],
        const SectionHeader('Maklumat Perhubungan'),
        _ContactCard(user: user),
        const SectionHeader('Butiran'),
        _DetailsCard(user: user),
        Padding(
          padding: const EdgeInsets.all(Spacing.lg),
          child: FilledButton.icon(
            onPressed: () => context.push('/profile/edit'),
            icon: const Icon(Icons.edit_outlined),
            label: const Text('Edit Profil'),
          ),
        ),
        SectionHeader(
          'Perjalanan Ahli',
          trailing: history.isEmpty
              ? null
              : TextButton(
                  onPressed: () => context.push('/profile/journey'),
                  child: const Text('Lihat Semua'),
                ),
        ),
        if (history.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: Spacing.lg),
            child: EmptyState(
              icon: Icons.route_outlined,
              message: 'Tiada rekod perjalanan lagi.',
            ),
          )
        else ...[
          for (final entry in history.take(3)) _JourneyPreview(entry: entry),
          if (history.length > 3)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
              child: OutlinedButton(
                onPressed: () => context.push('/profile/journey'),
                child: Text('Lihat Semua (${history.length})'),
              ),
            ),
        ],
      ],
      ),
    );
  }
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({required this.user});

  final ProfileUser user;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final roles = user.roles ?? const <String>[];

    return Container(
      padding: const EdgeInsets.all(Spacing.xl),
      decoration: const BoxDecoration(color: AppColors.movementDarkGreen),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              ClipOval(
                child: AppImage(
                  user.photo_url,
                  width: 72,
                  height: 72,
                  fit: BoxFit.cover,
                ),
              ),
              const SizedBox(width: Spacing.lg),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user.name ?? '-',
                      style: theme.textTheme.headlineSmall?.copyWith(
                        color: AppColors.white,
                      ),
                    ),
                    if (user.member_no != null && user.member_no!.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        'No. Ahli: ${user.member_no}',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: AppColors.textOnDark,
                        ),
                      ),
                    ],
                    if (user.organization?.name != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        user.organization!.name!,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: AppColors.movementSoftGreen,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          if (roles.isNotEmpty) ...[
            const SizedBox(height: Spacing.lg),
            Wrap(
              spacing: Spacing.sm,
              runSpacing: Spacing.sm,
              children: [
                for (final role in roles)
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: Spacing.md,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.white.withValues(alpha: 0.14),
                      borderRadius: AppRadius.lg,
                    ),
                    child: Text(
                      role,
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: AppColors.white,
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _CompleteProfileBanner extends StatelessWidget {
  const _CompleteProfileBanner({required this.onPressed});

  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
      child: Card(
        color: AppColors.movementSoftGreen.withValues(alpha: 0.18),
        child: Padding(
          padding: const EdgeInsets.all(Spacing.lg),
          child: Row(
            children: [
              const Icon(Icons.info_outline, color: AppColors.movementGreen),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Profil anda belum lengkap',
                      style: theme.textTheme.titleSmall?.copyWith(
                        color: AppColors.movementNavy,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Lengkapkan maklumat anda untuk pengalaman terbaik.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              FilledButton(
                onPressed: onPressed,
                child: const Text('Lengkapkan'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FeeStatusCard extends StatelessWidget {
  const _FeeStatusCard({required this.feeStatus});

  final ProfileFeeStatus feeStatus;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final active = feeStatus.isActive;
    final accent = active ? AppColors.success : AppColors.warning;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
      child: Card(
        color: accent.withValues(alpha: 0.10),
        child: Padding(
          padding: const EdgeInsets.all(Spacing.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(
                    active ? Icons.verified_outlined : Icons.error_outline,
                    color: accent,
                  ),
                  const SizedBox(width: Spacing.sm),
                  Expanded(
                    child: Text(
                      active ? 'Yuran Ahli Aktif' : 'Yuran Belum Dibayar',
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: AppColors.movementNavy,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: Spacing.sm),
              if (active && feeStatus.last_paid_at != null)
                Text(
                  'Bayaran terakhir: ${feeStatus.last_paid_at}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                )
              else if (!active)
                Text(
                  'Amaun tertunggak: ${ProfileFormat.money(feeStatus.amount_due)}',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: AppColors.movementNavy,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              if (!active && feeStatus.last_reference != null)
                Text(
                  'Rujukan: ${feeStatus.last_reference}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ContactCard extends StatelessWidget {
  const _ContactCard({required this.user});

  final ProfileUser user;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _DetailRow(icon: Icons.mail_outline, label: 'Emel', value: user.email),
        _DetailRow(icon: Icons.phone_outlined, label: 'Telefon', value: user.phone),
        _DetailRow(icon: Icons.badge_outlined, label: 'No. IC', value: user.ic_number),
      ],
    );
  }
}

class _DetailsCard extends StatelessWidget {
  const _DetailsCard({required this.user});

  final ProfileUser user;

  String get _address {
    final parts = [
      user.address_1,
      user.address_2,
      if (user.postcode != null && user.city != null)
        '${user.postcode} ${user.city}',
      user.state,
    ].where((p) => p != null && p.trim().isNotEmpty).toList();
    return parts.isEmpty ? '' : parts.join(', ');
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _DetailRow(icon: Icons.account_tree_outlined, label: 'Cawangan', value: user.branch_name),
        _DetailRow(icon: Icons.place_outlined, label: 'Lokaliti', value: user.locality),
        _DetailRow(icon: Icons.work_outline, label: 'Profesion', value: user.current_profession),
        _DetailRow(icon: Icons.school_outlined, label: 'Pendidikan', value: user.education_level),
        _DetailRow(icon: Icons.home_outlined, label: 'Alamat', value: _address.isEmpty ? null : _address),
        _DetailRow(
          icon: Icons.emergency_outlined,
          label: 'Hubungan Kecemasan',
          value: user.emergency_contact_name != null
              ? '${user.emergency_contact_name} (${user.emergency_contact_phone ?? '-'})'
              : null,
        ),
      ],
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.icon, required this.label, this.value});

  final IconData icon;
  final String label;
  final String? value;

  @override
  Widget build(BuildContext context) {
    return ListCard(
      leading: Icon(icon, color: AppColors.movementGreen, size: 22),
      title: label,
      subtitle: (value == null || value!.trim().isEmpty) ? 'Tidak dinyatakan' : value,
    );
  }
}

class _JourneyPreview extends StatelessWidget {
  const _JourneyPreview({required this.entry});

  final ProfileHistoryEntry entry;

  @override
  Widget build(BuildContext context) {
    final from = entry.from_organization?.name;
    final to = entry.to_organization?.name;

    return ListCard(
      leading: const Icon(Icons.swap_horiz, color: AppColors.movementGreen),
      title: from == null ? 'Sertai ${to ?? 'organisasi'}' : '$from → $to',
      subtitle: entry.transitioned_at_human,
    );
  }
}

class _ProfileSkeleton extends StatelessWidget {
  const _ProfileSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: const [
        SkeletonBox(height: 140, radius: 0),
        SizedBox(height: Spacing.lg),
        Padding(
          padding: EdgeInsets.symmetric(horizontal: Spacing.lg),
          child: SkeletonBox(height: 90),
        ),
        SizedBox(height: Spacing.xl),
        Padding(
          padding: EdgeInsets.symmetric(horizontal: Spacing.lg),
          child: SkeletonBox(height: 20, width: 220),
        ),
        SizedBox(height: Spacing.md),
        Padding(
          padding: EdgeInsets.symmetric(horizontal: Spacing.lg),
          child: SkeletonBox(height: 120),
        ),
        SizedBox(height: Spacing.xl),
        Padding(
          padding: EdgeInsets.symmetric(horizontal: Spacing.lg),
          child: SkeletonBox(height: 180),
        ),
      ],
    );
  }
}
