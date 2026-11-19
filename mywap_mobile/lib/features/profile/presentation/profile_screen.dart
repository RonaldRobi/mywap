import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/position_badge_chip.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../auth/application/auth_controller.dart';
import '../../financial/presentation/widgets/pay_fee_button.dart';
import '../application/profile_providers.dart';
import '../data/models/profile_data.dart';
import 'profile_format.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final current = ref.read(profileProvider);
      if (current.hasValue || current.hasError) {
        ref.invalidate(profileProvider);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final profileAsync = ref.watch(profileProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF2F2F7), // Apple iOS Grouped Background
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(
          context.l10n.profileTitle,
          style: const TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w700,
            color: AppColors.movementNavy,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => context.push('/profile/edit'),
            child: const Text(
              'Ubah',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: AppColors.movementGreen,
              ),
            ),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: profileAsync.when(
        skipLoadingOnRefresh: true,
        data: (data) => _ProfileContent(
          data: data,
          onRefresh: () async => ref.invalidate(profileProvider),
        ),
        loading: () => const _ProfileSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : context.l10n.unexpectedError,
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
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: EmptyState(
            icon: Icons.person_outline,
            message: context.l10n.noProfileData,
          ),
        ),
      );
    }

    final history = data.history ?? const <ProfileHistoryEntry>[];

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.only(bottom: 40),
        children: [
          // 1. Apple ID Centered Hero Profile Header
          _AppleProfileHeader(user: user),
          const SizedBox(height: 16),

          // Peringatan Profil Belum Lengkap (Jika Ada)
          if (!user.isComplete) ...[
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: _AppleNoticeBanner(
                title: context.l10n.profileIncomplete,
                subtitle: context.l10n.completeProfilePrompt,
                actionLabel: context.l10n.complete,
                onPressed: () => context.push('/profile/complete'),
              ),
            ),
            const SizedBox(height: 16),
          ],

          // 2. Status Yuran Keahlian (Apple Inset Card)
          if (user.feeStatus != null) ...[
            _AppleSectionHeader(title: 'YURAN KEAHLIAN'),
            _AppleFeeStatusCard(feeStatus: user.feeStatus!),
            const SizedBox(height: 20),
          ],

          // 3. Maklumat Peribadi (Apple Grouped List - Minimal Text-Driven, No Messy Icons)
          _AppleSectionHeader(title: context.l10n.contactInfoSection.toUpperCase()),
          _AppleGroupedCard(
            children: [
              _AppleRow(
                label: context.l10n.emailLabel,
                value: user.email,
              ),
              _AppleRow(
                label: context.l10n.phoneLabel,
                value: user.phone,
              ),
              _AppleRow(
                label: context.l10n.icNoLabel,
                value: user.ic_number,
              ),
              if (user.gender != null && user.gender!.isNotEmpty)
                _AppleRow(
                  label: 'Jantina',
                  value: user.gender,
                ),
              if (user.age != null)
                _AppleRow(
                  label: 'Umur',
                  value: '${user.age} tahun',
                ),
            ],
          ),
          const SizedBox(height: 20),

          // 4. Kawasan & Organisasi
          _AppleSectionHeader(title: 'ORGANISASI & KAWASAN'),
          _AppleGroupedCard(
            children: [
              _AppleRow(
                label: 'Organisasi',
                value: user.organization?.name,
              ),
              _AppleRow(
                label: context.l10n.branchLabel,
                value: user.branch_name,
              ),
              _AppleRow(
                label: context.l10n.localityLabel,
                value: user.locality,
              ),
              _AppleRow(
                label: context.l10n.addressLabel,
                value: _buildAddress(user),
              ),
            ],
          ),
          const SizedBox(height: 20),

          // 5. Latar Belakang & Kerjaya
          _AppleSectionHeader(title: 'LATAR BELAKANG'),
          _AppleGroupedCard(
            children: [
              _AppleRow(
                label: context.l10n.professionLabel,
                value: user.current_profession,
              ),
              _AppleRow(
                label: context.l10n.educationLabel,
                value: user.education_level,
              ),
              _AppleRow(
                label: context.l10n.emergencyContactLabel,
                value: user.emergency_contact_name != null
                    ? '${user.emergency_contact_name} (${user.emergency_contact_phone ?? '-'})'
                    : null,
              ),
            ],
          ),
          const SizedBox(height: 20),

          // 6. Perjalanan Keahlian (Timeline Link)
          _AppleSectionHeader(title: context.l10n.memberJourney.toUpperCase()),
          _AppleGroupedCard(
            children: [
              if (history.isEmpty)
                const _AppleRow(
                  label: 'Rekod Perjalanan',
                  value: 'Tiada sejarah pertukaran',
                )
              else ...[
                for (final entry in history.take(2))
                  _AppleJourneyRow(entry: entry),
                _AppleActionRow(
                  title: 'Lihat Garis Masa Lengkap (${history.length})',
                  onTap: () => context.push('/profile/journey'),
                ),
              ],
            ],
          ),
          const SizedBox(height: 20),

          // 7. Keselamatan & Tetapan Log Masuk
          _AppleSectionHeader(title: context.l10n.securitySection.toUpperCase()),
          _AppleGroupedCard(
            children: [
              const _AppleBiometricRow(),
              _AppleActionRow(
                title: context.l10n.changePassword,
                onTap: () => context.push('/profile/change-password'),
              ),
              const _AppleProfilePhotoRow(),
            ],
          ),
          const SizedBox(height: 24),

          // 8. Butang Log Keluar & Padam Akaun Destruktif
          _AppleGroupedCard(
            children: [
              _AppleDestructiveRow(
                label: 'Log Keluar',
                color: AppColors.movementGreen,
                onTap: () => ref.read(authControllerProvider.notifier).logout(),
              ),
              const _AppleDeleteAccountRow(),
            ],
          ),
        ],
      ),
    );
  }

  String _buildAddress(ProfileUser user) {
    final area = user.district ?? user.city;
    final parts = [
      user.address_1,
      user.address_2,
      if (user.postcode != null && area != null) '${user.postcode} $area',
      user.state,
    ].where((p) => p != null && p.trim().isNotEmpty).toList();
    return parts.isEmpty ? '' : parts.join(', ');
  }
}

/// Header berpusat gaya Apple ID (Settings Profil).
class _AppleProfileHeader extends StatelessWidget {
  const _AppleProfileHeader({required this.user});

  final ProfileUser user;

  @override
  Widget build(BuildContext context) {
    final fullName = [user.salutation, user.name]
        .where((part) => part != null && part.trim().isNotEmpty)
        .join(' ');

    return Container(
      width: double.infinity,
      color: AppColors.white,
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 24),
      child: Column(
        children: [
          // Avatar Besar Bulat dengan Bayang Halus
          Container(
            width: 80,
            height: 80,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: const Color(0x18000000), width: 1.5),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x10000000),
                  blurRadius: 14,
                  offset: Offset(0, 4),
                ),
              ],
            ),
            child: ClipOval(
              child: user.photo_url != null && user.photo_url!.isNotEmpty
                  ? AppImage(user.photo_url, fit: BoxFit.cover)
                  : Container(
                      color: const Color(0xFFE8F5E9),
                      child: const Center(
                        child: Icon(Icons.person, size: 44, color: AppColors.movementGreen),
                      ),
                    ),
            ),
          ),
          const SizedBox(height: 14),

          // Nama Penuh
          Text(
            fullName.isEmpty ? '-' : fullName,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 21,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.4,
              color: AppColors.movementNavy,
            ),
          ),
          const SizedBox(height: 6),

          // Lencana Nombor Ahli
          if (user.member_no != null && user.member_no!.isNotEmpty) ...[
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: AppColors.movementGreen.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                context.l10n.memberNoShort(user.member_no!),
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: AppColors.movementGreen,
                ),
              ),
            ),
            const SizedBox(height: 6),
          ],

          // Organisasi
          if (user.organization?.name != null)
            Text(
              user.organization!.name!,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w500,
                color: Color(0xFF8E8E93),
              ),
            ),

          if (user.position_badge?.isNotEmpty ?? false) ...[
            const SizedBox(height: 8),
            PositionBadgeChip(badge: user.position_badge!, dark: false),
          ],
        ],
      ),
    );
  }
}

/// Tajuk seksyen Apple Inset Grouped.
class _AppleSectionHeader extends StatelessWidget {
  const _AppleSectionHeader({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(28, 0, 16, 6),
      child: Text(
        title,
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: Color(0xFF8E8E93),
          letterSpacing: 0.2,
        ),
      ),
    );
  }
}

/// Kad berkumpulan Apple Inset (Grouped Inset Card).
class _AppleGroupedCard extends StatelessWidget {
  const _AppleGroupedCard({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0x12000000), width: 0.6),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Column(
          children: [
            for (int i = 0; i < children.length; i++) ...[
              children[i],
              if (i < children.length - 1)
                const Divider(
                  height: 0.5,
                  thickness: 0.5,
                  indent: 16,
                  color: Color(0x18000000),
                ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Baris data Apple: Label di kiri, Nilai di kanan (Tanpa Ikon Berselerak).
class _AppleRow extends StatelessWidget {
  const _AppleRow({required this.label, this.value});

  final String label;
  final String? value;

  @override
  Widget build(BuildContext context) {
    final displayValue = (value == null || value!.trim().isEmpty) ? '-' : value!;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 14.5,
              fontWeight: FontWeight.w500,
              color: AppColors.movementNavy,
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Text(
              displayValue,
              textAlign: TextAlign.end,
              style: const TextStyle(
                fontSize: 14,
                color: Color(0xFF8E8E93),
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}

/// Baris tindakan dengan ikon anak panah (Chevron).
class _AppleActionRow extends StatelessWidget {
  const _AppleActionRow({required this.title, required this.onTap});

  final String title;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
        child: Row(
          children: [
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  fontSize: 14.5,
                  fontWeight: FontWeight.w500,
                  color: AppColors.movementNavy,
                ),
              ),
            ),
            const Icon(Icons.chevron_right, size: 18, color: Color(0xFFC7C7CC)),
          ],
        ),
      ),
    );
  }
}

/// Baris destruktif (Log Keluar / Padam).
class _AppleDestructiveRow extends StatelessWidget {
  const _AppleDestructiveRow({
    required this.label,
    required this.color,
    required this.onTap,
  });

  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 13),
        child: Center(
          child: Text(
            label,
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w600,
              color: color,
            ),
          ),
        ),
      ),
    );
  }
}

/// Kad Yuran Keahlian Apple
class _AppleFeeStatusCard extends StatelessWidget {
  const _AppleFeeStatusCard({required this.feeStatus});

  final ProfileFeeStatus feeStatus;

  @override
  Widget build(BuildContext context) {
    final active = feeStatus.isActive;

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0x12000000), width: 0.6),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Status Yuran Tahunan',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: AppColors.movementNavy,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      active
                          ? (feeStatus.last_paid_at != null
                              ? 'Dibayar: ${feeStatus.last_paid_at}'
                              : 'Keahlian Aktif')
                          : context.l10n.amountDue(
                              ProfileFormat.money(feeStatus.amount_due),
                            ),
                      style: TextStyle(
                        fontSize: 12.5,
                        color: active ? AppColors.textSecondary : const Color(0xFFDC2626),
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: (active ? AppColors.movementGreen : const Color(0xFFDC2626))
                      .withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  active ? 'AKTIF' : 'TERTUNGGAK',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    color: active ? AppColors.movementGreen : const Color(0xFFDC2626),
                  ),
                ),
              ),
            ],
          ),
          if (!active) ...[
            const SizedBox(height: 12),
            const PayFeeButton(),
          ],
        ],
      ),
    );
  }
}

class _AppleJourneyRow extends StatelessWidget {
  const _AppleJourneyRow({required this.entry});

  final ProfileHistoryEntry entry;

  @override
  Widget build(BuildContext context) {
    final from = entry.from_organization?.name;
    final to = entry.to_organization?.name;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  from == null ? (to ?? 'Organisasi') : '$from → $to',
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w600,
                    color: AppColors.movementNavy,
                  ),
                ),
                if (entry.transitioned_at_human != null)
                  Text(
                    entry.transitioned_at_human!,
                    style: const TextStyle(
                      fontSize: 11.5,
                      color: Color(0xFF8E8E93),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _AppleBiometricRow extends ConsumerStatefulWidget {
  const _AppleBiometricRow();

  @override
  ConsumerState<_AppleBiometricRow> createState() => _AppleBiometricRowState();
}

class _AppleBiometricRowState extends ConsumerState<_AppleBiometricRow> {
  bool _busy = false;

  Future<void> _toggle(bool value) async {
    if (_busy) return;
    setState(() => _busy = true);
    final ok = await ref.read(authControllerProvider.notifier).setBiometricEnabled(value);
    if (!mounted) return;
    setState(() => _busy = false);
    if (!ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            value
                ? context.l10n.biometricEnableFailed
                : context.l10n.biometricDisableFailed,
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final supportedAsync = ref.watch(biometricSupportedProvider);
    final enabledAsync = ref.watch(biometricEnabledProvider);
    final hasFaceId = ref.watch(hasFaceIdProvider).valueOrNull ?? false;

    return supportedAsync.maybeWhen(
      data: (supported) {
        if (!supported) return const SizedBox.shrink();
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                hasFaceId ? 'Log Masuk Face ID' : 'Log Masuk Cap Jari',
                style: const TextStyle(
                  fontSize: 14.5,
                  fontWeight: FontWeight.w500,
                  color: AppColors.movementNavy,
                ),
              ),
              Switch.adaptive(
                value: enabledAsync.valueOrNull ?? false,
                activeColor: AppColors.movementGreen,
                onChanged: _busy ? null : _toggle,
              ),
            ],
          ),
        );
      },
      orElse: () => const SizedBox.shrink(),
    );
  }
}

class _AppleProfilePhotoRow extends ConsumerStatefulWidget {
  const _AppleProfilePhotoRow();

  @override
  ConsumerState<_AppleProfilePhotoRow> createState() => _AppleProfilePhotoRowState();
}

class _AppleProfilePhotoRowState extends ConsumerState<_AppleProfilePhotoRow> {
  bool _busy = false;

  Future<void> _pickPhoto() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1200,
      maxHeight: 1200,
      imageQuality: 85,
    );
    if (picked == null || !mounted) return;

    setState(() => _busy = true);
    try {
      final url = await ref.read(profileRepositoryProvider).uploadPhoto(File(picked.path));
      if (!mounted) return;
      ref.invalidate(profileProvider);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            url == null || url.isEmpty
                ? context.l10n.photoUploaded
                : context.l10n.profileUpdated,
          ),
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.unexpectedErrorRetry)),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: _busy ? null : _pickPhoto,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          children: [
            const Expanded(
              child: Text(
                'Tukar Foto Profil',
                style: TextStyle(
                  fontSize: 14.5,
                  fontWeight: FontWeight.w500,
                  color: AppColors.movementNavy,
                ),
              ),
            ),
            if (_busy)
              const SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            else
              const Icon(Icons.chevron_right, size: 18, color: Color(0xFFC7C7CC)),
          ],
        ),
      ),
    );
  }
}

class _AppleDeleteAccountRow extends ConsumerStatefulWidget {
  const _AppleDeleteAccountRow();

  @override
  ConsumerState<_AppleDeleteAccountRow> createState() => _AppleDeleteAccountRowState();
}

class _AppleDeleteAccountRowState extends ConsumerState<_AppleDeleteAccountRow> {
  bool _busy = false;

  Future<void> _confirmDelete() async {
    final controller = TextEditingController();
    final password = await showDialog<String>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: Text(context.l10n.deleteAccount),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                context.l10n.deleteAccountWarning,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: Spacing.md),
              TextField(
                controller: controller,
                obscureText: true,
                autofocus: true,
                decoration: InputDecoration(
                  labelText: context.l10n.passwordLabel,
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: Text(context.l10n.cancel),
            ),
            TextButton(
              style: TextButton.styleFrom(foregroundColor: AppColors.error),
              onPressed: () => Navigator.of(dialogContext).pop(controller.text),
              child: Text(context.l10n.delete),
            ),
          ],
        );
      },
    );

    controller.dispose();

    if (password == null || password.trim().isEmpty || !mounted) return;

    setState(() => _busy = true);
    try {
      await ref.read(profileRepositoryProvider).deleteAccount(password.trim());
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.accountDeleted)),
      );
      ref.read(authControllerProvider.notifier).logout();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      if (mounted) setState(() => _busy = false);
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.unexpectedErrorRetry)),
      );
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _AppleDestructiveRow(
      label: 'Padam Akaun',
      color: const Color(0xFFDC2626),
      onTap: _busy ? () {} : _confirmDelete,
    );
  }
}

class _AppleNoticeBanner extends StatelessWidget {
  const _AppleNoticeBanner({
    required this.title,
    required this.subtitle,
    required this.actionLabel,
    required this.onPressed,
  });

  final String title;
  final String subtitle;
  final String actionLabel;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF3C7),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFDE68A), width: 0.8),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF92400E),
                  ),
                ),
                Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 11.5,
                    color: Color(0xFFB45309),
                  ),
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: onPressed,
            style: TextButton.styleFrom(
              backgroundColor: const Color(0xFFD97706),
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            child: Text(
              actionLabel,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfileSkeleton extends StatelessWidget {
  const _ProfileSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: const [
        Center(child: SkeletonBox(height: 80, width: 80, radius: 40)),
        SizedBox(height: 16),
        Center(child: SkeletonBox(height: 22, width: 180, radius: 6)),
        SizedBox(height: 8),
        Center(child: SkeletonBox(height: 14, width: 120, radius: 4)),
        SizedBox(height: 24),
        SkeletonBox(height: 140, radius: 12),
        SizedBox(height: 20),
        SkeletonBox(height: 180, radius: 12),
      ],
    );
  }
}
