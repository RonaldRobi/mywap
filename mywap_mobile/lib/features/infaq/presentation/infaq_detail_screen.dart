import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/payment/payment_webview_screen.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/section_header.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/infaq_providers.dart';
import '../data/models/infaq.dart';
import 'infaq_donate_sheet.dart';

class InfaqDetailScreen extends ConsumerStatefulWidget {
  const InfaqDetailScreen({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<InfaqDetailScreen> createState() => _InfaqDetailScreenState();
}

class _InfaqDetailScreenState extends ConsumerState<InfaqDetailScreen> {
  Future<void> _openDonate(InfaqInfo infaq) async {
    if (infaq.isExternal) {
      final url = infaq.externalUrl;
      if (url == null || url.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pautan pembayaran tidak tersedia.')),
        );
        return;
      }
      await _openWebview(url);
      return;
    }

    final result = await showInfaqDonateSheet(context, infaq: infaq);
    if (result == null || !mounted) return;

    ref.invalidate(infaqListProvider);
    ref.invalidate(infaqDetailProvider(widget.slug));

    switch (result) {
      case InfaqDonateRedirect(:final paymentUrl):
        await _openWebview(paymentUrl);
      case InfaqDonateSuccess(:final donation):
        if (!mounted) return;
        await Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => InfaqDonationSuccessScreen(donation: donation),
          ),
        );
    }
  }

  Future<void> _openWebview(String url) async {
    final paid = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => PaymentWebviewScreen(paymentUrl: url, title: 'Pembayaran Infaq'),
      ),
    );
    if (!mounted) return;
    if (paid == true) {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => const InfaqDonationSuccessScreen(),
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pembayaran dibatalkan.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(infaqDetailProvider(widget.slug));

    return Scaffold(
      appBar: AppBar(title: const Text('Butiran Infaq')),
      body: detailAsync.when(
        data: (detail) => _DetailContent(
          detail: detail,
          onDonate: () => _openDonate(detail.infaq),
        ),
        loading: () => const _DetailSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(infaqDetailProvider(widget.slug)),
        ),
      ),
    );
  }
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({required this.detail, required this.onDonate});

  final InfaqDetail detail;
  final VoidCallback onDonate;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final infaq = detail.infaq;
    final progress = ((infaq.progressPercent ?? 0).clamp(0, 100)) / 100;
    final description = infaq.description;
    final orgName = infaq.organizationName;

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        AppImage(
          infaq.imagePath,
          height: 220,
          width: double.infinity,
          borderRadius: BorderRadius.zero,
        ),
        Padding(
          padding: const EdgeInsets.all(Spacing.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (orgName != null) ...[
                Row(
                  children: [
                    const Icon(
                      Icons.apartment,
                      size: 18,
                      color: AppColors.movementGreen,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        orgName,
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: AppColors.movementGreen,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: Spacing.sm),
              ],
              Text(infaq.title ?? '-', style: theme.textTheme.headlineSmall),
              const SizedBox(height: Spacing.sm),
              if (infaq.daysRunning != null || infaq.totalDonors != null)
                Row(
                  children: [
                    if (infaq.daysRunning != null)
                      _InfoRow(
                        icon: Icons.schedule,
                        text: '${infaq.daysRunning} hari berjalan',
                      ),
                    if (infaq.daysRunning != null && infaq.totalDonors != null)
                      const SizedBox(width: Spacing.md),
                    if (infaq.totalDonors != null)
                      _InfoRow(
                        icon: Icons.people_outline,
                        text: '${infaq.totalDonors} penyumbang',
                      ),
                  ],
                ),
              const SizedBox(height: Spacing.lg),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: LinearProgressIndicator(
                  value: progress,
                  minHeight: 10,
                  backgroundColor: AppColors.divider,
                  color: AppColors.movementGreen,
                ),
              ),
              const SizedBox(height: Spacing.sm),
              Row(
                children: [
                  Text(
                    Formatters.currency(infaq.collectedAmount),
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: AppColors.movementGreen,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    'Sasaran ${Formatters.currency(infaq.targetAmount)}',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
              if (description != null && description.isNotEmpty) ...[
                const SizedBox(height: Spacing.xl),
                Text('Penerangan', style: theme.textTheme.titleLarge),
                const SizedBox(height: Spacing.sm),
                Text(description, style: theme.textTheme.bodyLarge),
              ],
              const SizedBox(height: Spacing.xl),
              FilledButton.icon(
                onPressed: onDonate,
                icon: const Icon(Icons.volunteer_activism_outlined),
                label: const Text('Sumbang Sekarang'),
              ),
            ],
          ),
        ),
        if (detail.recentDonations.isNotEmpty) ...[
          const SectionHeader('Sumbangan Terkini'),
          for (final donation in detail.recentDonations)
            _RecentDonationTile(donation: donation),
        ],
        if (detail.relatedInfaqs.isNotEmpty) ...[
          const SectionHeader('Infaq Lain'),
          SizedBox(
            height: 210,
            child: ListView.separated(
              padding: const EdgeInsets.symmetric(
                horizontal: Spacing.lg,
                vertical: Spacing.sm,
              ),
              scrollDirection: Axis.horizontal,
              itemCount: detail.relatedInfaqs.length,
              separatorBuilder: (_, __) => const SizedBox(width: Spacing.md),
              itemBuilder: (_, index) => _RelatedInfaqCard(
                infaq: detail.relatedInfaqs[index],
              ),
            ),
          ),
        ],
        const SizedBox(height: Spacing.xl),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 18, color: AppColors.textSecondary),
        const SizedBox(width: 6),
        Text(
          text,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: AppColors.textSecondary,
              ),
        ),
      ],
    );
  }
}

class _RecentDonationTile extends StatelessWidget {
  const _RecentDonationTile({required this.donation});

  final RecentDonation donation;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final prayer = donation.prayerMessage;
    final name = donation.donorName ?? 'Hamba Allah';

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.lg, vertical: Spacing.sm),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            radius: 18,
            backgroundColor: AppColors.movementSoftGreen.withValues(alpha: 0.25),
            child: const Icon(
              Icons.person,
              size: 20,
              color: AppColors.movementGreen,
            ),
          ),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        name,
                        style: theme.textTheme.titleSmall,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    Text(
                      Formatters.currency(donation.amount),
                      style: theme.textTheme.titleSmall?.copyWith(
                        color: AppColors.movementGreen,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
                if (donation.createdAt != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    donation.createdAt!,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
                if (prayer != null && prayer.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    '"$prayer"',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: AppColors.textSecondary,
                      fontStyle: FontStyle.italic,
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
    final theme = Theme.of(context);
    final progress = ((infaq.progressPercent ?? 0).clamp(0, 100)) / 100;

    return SizedBox(
      width: 220,
      child: Card(
        clipBehavior: Clip.antiAlias,
        margin: EdgeInsets.zero,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AppImage(
              infaq.imagePath,
              height: 90,
              width: double.infinity,
              borderRadius: BorderRadius.zero,
            ),
            Padding(
              padding: const EdgeInsets.all(Spacing.md),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    infaq.title ?? '-',
                    style: theme.textTheme.titleSmall,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: Spacing.sm),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: progress,
                      minHeight: 6,
                      backgroundColor: AppColors.divider,
                      color: AppColors.movementGreen,
                    ),
                  ),
                  const SizedBox(height: Spacing.sm),
                  Text(
                    '${Formatters.currency(infaq.collectedAmount)} / '
                    '${Formatters.currency(infaq.targetAmount)}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
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

class _DetailSkeleton extends StatelessWidget {
  const _DetailSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      children: const [
        SkeletonBox(height: 220, radius: 0),
        Padding(
          padding: EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SkeletonBox(height: 28, width: 260),
              SizedBox(height: 16),
              SkeletonBox(height: 18),
              SizedBox(height: 12),
              SkeletonBox(height: 10),
              SizedBox(height: 8),
              SkeletonBox(height: 18, width: 200),
              SizedBox(height: 24),
              SkeletonBox(height: 120),
            ],
          ),
        ),
      ],
    );
  }
}
