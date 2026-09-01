import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/member_core_providers.dart';
import '../data/models/member_card_data.dart';

class MemberCardScreen extends ConsumerWidget {
  const MemberCardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cardAsync = ref.watch(memberCardProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Kad Ahli')),
      body: cardAsync.when(
        data: (data) => _CardContent(data: data),
        loading: () => const _CardSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : 'Ralat tidak dijangka.',
              onRetry: () => ref.invalidate(memberCardProvider),
            ),
      ),
    );
  }
}

class _CardContent extends ConsumerWidget {
  const _CardContent({required this.data});
  final MemberCardData data;

  @override
  Widget build(BuildContext context, WidgetRef ref) => RefreshIndicator(
    onRefresh: () async => ref.invalidate(memberCardProvider),
    child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(Spacing.lg),
      children: [_MemberCard(card: data.card, qrValue: data.card?.qrValue)],
    ),
  );
}

class _MemberCard extends StatelessWidget {
  const _MemberCard({required this.card, required this.qrValue});
  final MemberCardInfo? card;
  final String? qrValue;

  @override
  Widget build(BuildContext context) {
    final org = card?.organization;
    return Container(
      padding: const EdgeInsets.all(Spacing.xl),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.movementDarkGreen, AppColors.movementGreen],
        ),
        borderRadius: AppRadius.hero,
        boxShadow: const [
          BoxShadow(
            color: Color(0x4D123D2A),
            blurRadius: 24,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .15),
                  borderRadius: AppRadius.md,
                  border: Border.all(color: Colors.white.withValues(alpha: .2)),
                ),
                child: ClipRRect(
                  borderRadius: AppRadius.md,
                  child: AppImage(card?.system_logo_path, fit: BoxFit.contain),
                ),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'myWAP',
                      style: const TextStyle(
                        color: Color(0x99FFFFFF),
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 2,
                      ),
                    ),
                    const Text(
                      'Kad Keahlian',
                      style: TextStyle(
                        color: AppColors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              Column(
                children: [
                  ClipOval(
                    child: AppImage(
                      org?.logo_path,
                      width: 40,
                      height: 40,
                      fit: BoxFit.cover,
                    ),
                  ),
                  SizedBox(
                    width: 80,
                    child: Text(
                      org?.name ?? 'Organisasi',
                      textAlign: TextAlign.center,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Color(0xCCFFFFFF),
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: Spacing.xl),
          ClipRRect(
            borderRadius: AppRadius.lg,
            child: AppImage(
              card?.photo_url,
              width: 96,
              height: 96,
              fit: BoxFit.cover,
            ),
          ),
          const SizedBox(height: Spacing.md),
          Text(
            card?.name ?? '-',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: AppColors.white,
              fontWeight: FontWeight.w800,
            ),
          ),
          Text(
            'Ahli sejak ${card?.member_since ?? '-'}',
            style: const TextStyle(color: Color(0x99FFFFFF), fontSize: 11),
          ),
          const SizedBox(height: Spacing.lg),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: Spacing.sm,
            crossAxisSpacing: Spacing.sm,
            childAspectRatio: 2.3,
            children: [
              _Detail(label: 'EMAIL', value: card?.email),
              _Detail(label: 'TELEFON', value: card?.phone),
              _Detail(label: 'CAWANGAN', value: card?.branch_name),
              _Detail(label: 'PROFESION', value: card?.profession),
              _Detail(label: 'LOKASI', value: card?.locality),
              _Detail(
                label: 'NO. AHLI',
                value:
                    card?.member_no == null
                        ? null
                        : 'No. Ahli: ${card!.member_no}',
              ),
            ],
          ),
          const SizedBox(height: Spacing.lg),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(Spacing.md),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: .1),
              borderRadius: AppRadius.md,
              border: Border.all(color: Colors.white.withValues(alpha: .15)),
            ),
            child: Column(
              children: [
                if (qrValue == null || qrValue!.isEmpty)
                  const Icon(
                    Icons.qr_code_2,
                    size: 88,
                    color: Color(0x99FFFFFF),
                  )
                else
                  QrImageView(
                    data: qrValue!,
                    version: QrVersions.auto,
                    size: 130,
                    padding: const EdgeInsets.all(6),
                    backgroundColor: AppColors.white,
                    gapless: true,
                  ),
                const SizedBox(height: Spacing.sm),
                const Text(
                  'Imbas kod QR untuk pengesahan keahlian.',
                  style: TextStyle(color: Color(0xCCFFFFFF), fontSize: 10),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Detail extends StatelessWidget {
  const _Detail({required this.label, required this.value});
  final String label;
  final String? value;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(Spacing.sm),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: .1),
      borderRadius: AppRadius.md,
      border: Border.all(color: Colors.white.withValues(alpha: .15)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Text(
          label,
          style: const TextStyle(
            color: Color(0x99FFFFFF),
            fontSize: 9,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          value ?? '-',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: AppColors.white,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}

class _CardSkeleton extends StatelessWidget {
  const _CardSkeleton();
  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(Spacing.lg),
    children: const [
      SkeletonBox(height: 460, radius: 28),
      SizedBox(height: Spacing.xl),
      SkeletonBox(height: 250, radius: 20),
    ],
  );
}
