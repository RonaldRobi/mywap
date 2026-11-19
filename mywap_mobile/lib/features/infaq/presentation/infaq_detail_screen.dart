import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../../shared/widgets/share_item.dart';
import '../application/infaq_providers.dart';
import '../data/models/infaq.dart';

class InfaqDetailScreen extends ConsumerStatefulWidget {
  const InfaqDetailScreen({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<InfaqDetailScreen> createState() => _InfaqDetailScreenState();
}

class _InfaqDetailScreenState extends ConsumerState<InfaqDetailScreen> {
  /// Derma diproses di luar app melalui laman web kempen rasmi selaras dengan
  /// garis panduan Apple App Store (Guideline 3.2.2(iv)).
  Future<void> _openDonate(InfaqInfo infaq) async {
    final url = (infaq.isExternal ? infaq.externalUrl : infaq.publicUrl)?.trim();

    if (url == null || url.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.infaqDonationLinkUnavailable)),
      );
      return;
    }

    final uri = Uri.tryParse(url);
    if (uri == null || !uri.hasScheme) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.infaqDonationLinkInvalid)),
      );
      return;
    }

    var opened = false;
    try {
      opened = await launchUrl(uri, mode: LaunchMode.inAppBrowserView);
    } catch (_) {
      opened = false;
    }
    if (!opened) {
      try {
        opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      } catch (_) {
        opened = false;
      }
    }

    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.infaqDonationLinkOpenFailed)),
      );
    }

    if (mounted) {
      ref.invalidate(infaqListProvider);
      ref.invalidate(infaqDetailProvider(widget.slug));
    }
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(infaqDetailProvider(widget.slug));
    final infaq = detailAsync.valueOrNull?.infaq;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        leading: const AppBackButton(fallback: '/infaq'),
        title: Text(
          context.l10n.infaqDetailTitle,
          style: const TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w700,
            color: AppColors.movementNavy,
          ),
        ),
        actions: [
          if (infaq != null)
            ShareItemIconButton(
              title: infaq.title ?? '',
              url: infaq.shareUrl,
              text: infaq.description,
            ),
        ],
      ),
      bottomNavigationBar: detailAsync.hasValue
          ? _StickyDonateBottomBar(
              onDonate: () => _openDonate(detailAsync.value!.infaq),
            )
          : null,
      body: detailAsync.when(
        data: (detail) => _DetailContent(
          detail: detail,
          onDonate: () => _openDonate(detail.infaq),
          onRefresh: () async => ref.invalidate(infaqDetailProvider(widget.slug)),
        ),
        loading: () => const _DetailSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : context.l10n.unexpectedError,
          onRetry: () => ref.invalidate(infaqDetailProvider(widget.slug)),
        ),
      ),
    );
  }
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({
    required this.detail,
    required this.onDonate,
    required this.onRefresh,
  });

  final InfaqDetail detail;
  final VoidCallback onDonate;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final infaq = detail.infaq;
    final progress = ((infaq.progressPercent ?? 0).clamp(0, 100)) / 100;
    final percentInt = (progress * 100).toInt();
    final description = infaq.description;
    final orgName = infaq.organizationName;

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.only(bottom: 32),
        children: [
          // 1. Hero Poster Kempen dengan Radius Mewah & Bayang Ambient
          Padding(
            padding: const EdgeInsets.fromLTRB(Spacing.lg, Spacing.sm, Spacing.lg, 0),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(20),
              child: AspectRatio(
                aspectRatio: 16 / 10,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    if (infaq.imagePath != null && infaq.imagePath!.isNotEmpty)
                      AppImage(infaq.imagePath, fit: BoxFit.cover)
                    else
                      Container(color: const Color(0xFFE8F5E9)),
                    const DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [Colors.transparent, Color(0x65000000)],
                        ),
                      ),
                    ),
                    if (orgName != null)
                      Positioned(
                        left: 12,
                        bottom: 12,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.92),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.verified_rounded, size: 14, color: AppColors.movementGreen),
                              const SizedBox(width: 4),
                              Text(
                                orgName,
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
                  ],
                ),
              ),
            ),
          ),

          Padding(
            padding: const EdgeInsets.all(Spacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // 2. Tajuk Kempen
                Text(
                  infaq.title ?? '-',
                  style: const TextStyle(
                    fontSize: 21,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.4,
                    color: AppColors.movementNavy,
                    height: 1.25,
                  ),
                ),
                const SizedBox(height: Spacing.lg),

                // 3. Kad Prestasi & Kemajuan Sumbangan (Apple Metric Card)
                Container(
                  padding: const EdgeInsets.all(Spacing.lg),
                  decoration: BoxDecoration(
                    color: AppColors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: const Color(0x12000000), width: 0.8),
                    boxShadow: const [
                      BoxShadow(
                        color: Color(0x0812241C),
                        blurRadius: 14,
                        offset: Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Kutipan Terkumpul',
                                  style: TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  Formatters.currency(infaq.collectedAmount),
                                  style: const TextStyle(
                                    fontSize: 24,
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: -0.6,
                                    color: AppColors.movementGreen,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                            decoration: BoxDecoration(
                              color: AppColors.movementGreen.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Text(
                              '$percentInt%',
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                                color: AppColors.movementGreen,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),

                      // Progress Bar Bergradien
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: SizedBox(
                          height: 10,
                          child: Stack(
                            children: [
                              Container(color: const Color(0xFFE5E7EB)),
                              FractionallySizedBox(
                                widthFactor: progress,
                                child: Container(
                                  decoration: const BoxDecoration(
                                    gradient: LinearGradient(
                                      colors: [
                                        AppColors.movementGreen,
                                        Color(0xFF34D399),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),

                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Sasaran Kempen',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w500,
                              color: AppColors.textTertiary,
                            ),
                          ),
                          Text(
                            Formatters.currency(infaq.targetAmount),
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: AppColors.movementNavy,
                            ),
                          ),
                        ],
                      ),
                      const Divider(height: 24, thickness: 0.6),

                      // Statistik Metrik: Baki Hari & Bilangan Penyumbang
                      Row(
                        children: [
                          if (infaq.daysRunning != null)
                            Expanded(
                              child: _MetricTile(
                                icon: Icons.timer_outlined,
                                label: 'Hari Kempen',
                                value: '${infaq.daysRunning} hari',
                              ),
                            ),
                          if (infaq.daysRunning != null && infaq.totalDonors != null)
                            Container(width: 1, height: 28, color: const Color(0x15000000)),
                          if (infaq.totalDonors != null)
                            Expanded(
                              child: _MetricTile(
                                icon: Icons.favorite_border_rounded,
                                label: 'Penyumbang',
                                value: '${infaq.totalDonors} orang',
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: Spacing.xl),

                // 4. Perincian & Keterangan Kempen
                if (description != null && description.isNotEmpty) ...[
                  const Text(
                    'Mengenai Kempen Ini',
                    style: TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.3,
                      color: AppColors.movementNavy,
                    ),
                  ),
                  const SizedBox(height: Spacing.sm),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(Spacing.lg),
                    decoration: BoxDecoration(
                      color: AppColors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0x10000000), width: 0.8),
                    ),
                    child: Text(
                      description,
                      style: const TextStyle(
                        fontSize: 14.5,
                        height: 1.6,
                        color: Color(0xFF2C3E50),
                      ),
                    ),
                  ),
                  const SizedBox(height: Spacing.xl),
                ],

                // 5. Senarai Doa & Sumbangan Terkini
                if (detail.recentDonations.isNotEmpty) ...[
                  const Text(
                    'Doa & Sokongan Terkini',
                    style: TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.3,
                      color: AppColors.movementNavy,
                    ),
                  ),
                  const SizedBox(height: Spacing.sm),
                  Container(
                    decoration: BoxDecoration(
                      color: AppColors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0x10000000), width: 0.8),
                    ),
                    child: Column(
                      children: [
                        for (int i = 0; i < detail.recentDonations.length; i++) ...[
                          _RecentDonationTile(donation: detail.recentDonations[i]),
                          if (i < detail.recentDonations.length - 1)
                            const Divider(height: 1, indent: 56, thickness: 0.6),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: Spacing.xl),
                ],

                // 6. Kempen Infaq Lain
                if (detail.relatedInfaqs.isNotEmpty) ...[
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        l10n.infaqOtherInfaqs,
                        style: const TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.3,
                          color: AppColors.movementNavy,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: Spacing.md),
                  SizedBox(
                    height: 220,
                    child: ListView.separated(
                      clipBehavior: Clip.none,
                      scrollDirection: Axis.horizontal,
                      itemCount: detail.relatedInfaqs.length,
                      separatorBuilder: (_, __) => const SizedBox(width: Spacing.md),
                      itemBuilder: (_, index) => _RelatedInfaqCard(
                        infaq: detail.relatedInfaqs[index],
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MetricTile extends StatelessWidget {
  const _MetricTile({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(icon, size: 18, color: AppColors.movementGreen),
        const SizedBox(width: 8),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: const TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w500,
                color: AppColors.textSecondary,
              ),
            ),
            Text(
              value,
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
                color: AppColors.movementNavy,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _StickyDonateBottomBar extends StatelessWidget {
  const _StickyDonateBottomBar({required this.onDonate});

  final VoidCallback onDonate;

  @override
  Widget build(BuildContext context) {
    final mq = MediaQuery.of(context);
    final bottomInset = mq.padding.bottom > 0 ? mq.padding.bottom : 12.0;

    return Container(
      padding: EdgeInsets.fromLTRB(16, 12, 16, bottomInset),
      decoration: BoxDecoration(
        color: AppColors.white,
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF12241C).withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
        border: Border(
          top: BorderSide(
            color: const Color(0xFF12241C).withValues(alpha: 0.06),
            width: 0.8,
          ),
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            width: double.infinity,
            height: 50,
            child: FilledButton.icon(
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.movementGreen,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
                elevation: 0,
              ),
              onPressed: onDonate,
              icon: const Icon(Icons.volunteer_activism_rounded, size: 20),
              label: const Text(
                'Infaq Sekarang',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  letterSpacing: -0.2,
                ),
              ),
            ),
          ),
          const SizedBox(height: 6),
          const Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.lock_outline_rounded, size: 11, color: AppColors.textTertiary),
              SizedBox(width: 4),
              Text(
                'Sumbangan selamat melalui gerbang rasmi organisasi',
                style: TextStyle(
                  fontSize: 10.5,
                  fontWeight: FontWeight.w500,
                  color: AppColors.textTertiary,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _RecentDonationTile extends StatelessWidget {
  const _RecentDonationTile({required this.donation});

  final RecentDonation donation;

  @override
  Widget build(BuildContext context) {
    final prayer = donation.prayerMessage;
    final name = donation.donorName ?? context.l10n.infaqAnonymousDonor;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.md, vertical: Spacing.md),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: AppColors.movementGreen.withValues(alpha: 0.10),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.favorite_rounded,
              size: 18,
              color: AppColors.movementGreen,
            ),
          ),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        name,
                        style: const TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w700,
                          color: AppColors.movementNavy,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    Text(
                      Formatters.currency(donation.amount),
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                        color: AppColors.movementGreen,
                      ),
                    ),
                  ],
                ),
                if (donation.createdAt != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    donation.createdAt!,
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppColors.textTertiary,
                    ),
                  ),
                ],
                if (prayer != null && prayer.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF9FAFB),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0x0C000000), width: 0.8),
                    ),
                    child: Text(
                      '“$prayer”',
                      style: const TextStyle(
                        fontSize: 12,
                        fontStyle: FontStyle.italic,
                        color: Color(0xFF4B5563),
                        height: 1.35,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RelatedInfaqCard extends StatelessWidget {
  const _RelatedInfaqCard({required this.infaq});

  final Infaq infaq;

  @override
  Widget build(BuildContext context) {
    final progress = ((infaq.progressPercent ?? 0).clamp(0, 100)) / 100;

    return SizedBox(
      width: 220,
      child: Material(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () {
            if (infaq.slug != null) {
              Navigator.of(context).pushReplacement(
                MaterialPageRoute(
                  builder: (_) => InfaqDetailScreen(slug: infaq.slug!),
                ),
              );
            }
          },
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0x12000000), width: 0.8),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x0812241C),
                  blurRadius: 10,
                  offset: Offset(0, 3),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                  child: AspectRatio(
                    aspectRatio: 16 / 9,
                    child: AppImage(
                      infaq.imagePath,
                      fit: BoxFit.cover,
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(Spacing.sm),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        height: 34,
                        child: Text(
                          infaq.title ?? '-',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: AppColors.movementNavy,
                            height: 1.2,
                          ),
                        ),
                      ),
                      const SizedBox(height: 6),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(4),
                        child: LinearProgressIndicator(
                          value: progress,
                          minHeight: 5,
                          backgroundColor: const Color(0xFFE5E7EB),
                          color: AppColors.movementGreen,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        '${Formatters.currency(infaq.collectedAmount)} terkumpul',
                        style: const TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                          color: AppColors.movementGreen,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _DetailSkeleton extends StatelessWidget {
  const _DetailSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: const [
        AspectRatio(aspectRatio: 16 / 10, child: SkeletonBox(radius: 20)),
        SizedBox(height: Spacing.lg),
        SkeletonBox(height: 28, width: 240, radius: 8),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 160, radius: 20),
        SizedBox(height: Spacing.lg),
        SkeletonBox(height: 120, radius: 16),
      ],
    );
  }
}
