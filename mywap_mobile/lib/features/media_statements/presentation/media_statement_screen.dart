import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/html_content.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../../shared/widgets/share_item.dart';
import '../application/media_statement_providers.dart';
import '../data/media_statement_repository.dart';

class MediaStatementsScreen extends ConsumerWidget {
  const MediaStatementsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(mediaStatementsProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(
          context.l10n.mediaStatementsTitle,
          style: const TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w700,
            color: AppColors.movementNavy,
          ),
        ),
      ),
      body: async.when(
        data:
            (items) => RefreshIndicator(
              onRefresh: () async => ref.invalidate(mediaStatementsProvider),
              child:
                  items.isEmpty
                      ? ListView(
                        children: [
                          const SizedBox(height: 120),
                          Center(
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  Icons.campaign_outlined,
                                  size: 48,
                                  color: AppColors.textTertiary,
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  context.l10n.mediaStatementsEmpty,
                                  style: const TextStyle(
                                    fontSize: 14,
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      )
                      : ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(Spacing.lg),
                        itemCount: items.length,
                        separatorBuilder:
                            (_, __) => const SizedBox(height: Spacing.md),
                        itemBuilder:
                            (_, index) =>
                                _MediaStatementCard(item: items[index]),
                      ),
            ),
        loading:
            () => ListView.builder(
              padding: const EdgeInsets.all(Spacing.lg),
              itemCount: 4,
              itemBuilder:
                  (_, __) => const Padding(
                    padding: EdgeInsets.only(bottom: Spacing.md),
                    child: SkeletonBox(height: 100, radius: 16),
                  ),
            ),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : context.l10n.unexpectedError,
              onRetry: () => ref.invalidate(mediaStatementsProvider),
            ),
      ),
    );
  }
}

class _MediaStatementCard extends StatelessWidget {
  const _MediaStatementCard({required this.item});

  final MediaStatement item;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap:
            () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => MediaStatementDetailScreen(id: item.id),
              ),
            ),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0x12000000), width: 0.8),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0612241C),
                blurRadius: 10,
                offset: Offset(0, 3),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.all(Spacing.md),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: SizedBox(
                    width: 80,
                    height: 80,
                    child:
                        item.featuredImage == null
                            ? Container(
                              color: const Color(0xFFE8F5E9),
                              child: const Center(
                                child: Icon(
                                  Icons.campaign_rounded,
                                  size: 30,
                                  color: AppColors.movementGreen,
                                ),
                              ),
                            )
                            : AppImage(item.featuredImage, fit: BoxFit.cover),
                  ),
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.movementGreen.withValues(
                            alpha: 0.12,
                          ),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: const Text(
                          'KENYATAAN MEDIA',
                          style: TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.5,
                            color: AppColors.movementGreen,
                          ),
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        item.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w700,
                          color: AppColors.movementNavy,
                          height: 1.25,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          const Icon(
                            Icons.description_outlined,
                            size: 12,
                            color: AppColors.textTertiary,
                          ),
                          const SizedBox(width: 4),
                          const Text(
                            'Siaran Rasmi',
                            style: TextStyle(
                              fontSize: 10.5,
                              color: AppColors.textTertiary,
                            ),
                          ),
                          const Spacer(),
                          const Icon(
                            Icons.arrow_forward_ios_rounded,
                            size: 12,
                            color: Color(0xFFB0B0B5),
                          ),
                        ],
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

class MediaStatementDetailScreen extends ConsumerWidget {
  const MediaStatementDetailScreen({super.key, required this.id});

  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(mediaStatementDetailProvider(id));
    final statement = async.valueOrNull?.statement;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(
          context.l10n.mediaStatementsTitle,
          style: const TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w700,
            color: AppColors.movementNavy,
          ),
        ),
        actions: [
          if (statement != null)
            ShareItemIconButton(
              title: statement.title ?? '',
              url: statement.shareUrl,
            ),
        ],
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : context.l10n.unexpectedError,
              onRetry: () => ref.invalidate(mediaStatementDetailProvider(id)),
            ),
        data: (detail) {
          final s = detail.statement;

          return ListView(
            padding: const EdgeInsets.fromLTRB(
              Spacing.lg,
              Spacing.sm,
              Spacing.lg,
              Spacing.xxl,
            ),
            children: [
              // Badge Dokumen Rasmi
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 4,
                ),
                decoration: BoxDecoration(
                  color: AppColors.movementGreen.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.verified_rounded,
                      size: 13,
                      color: AppColors.movementGreen,
                    ),
                    SizedBox(width: 4),
                    Text(
                      'SIARAN MEDIA RASMI',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.6,
                        color: AppColors.movementGreen,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: Spacing.md),

              // Tajuk Utama
              Text(
                s.title ?? '-',
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.5,
                  color: AppColors.movementNavy,
                  height: 1.25,
                ),
              ),
              const SizedBox(height: Spacing.lg),

              // Poster Gambar Jika Ada
              if (s.featuredImage != null && s.featuredImage!.isNotEmpty) ...[
                ClipRRect(
                  borderRadius: BorderRadius.circular(16),
                  child: AspectRatio(
                    aspectRatio: 16 / 10,
                    child: AppImage(s.featuredImage, fit: BoxFit.cover),
                  ),
                ),
                const SizedBox(height: Spacing.lg),
              ],

              // Butang Muat Turun PDF (Jika Tersedia)
              if (s.pdfUrl != null && s.pdfUrl!.isNotEmpty) ...[
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: FilledButton.icon(
                    style: FilledButton.styleFrom(
                      backgroundColor: const Color(0xFF1E293B),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                      elevation: 0,
                    ),
                    onPressed:
                        () => launchUrl(
                          Uri.parse(s.pdfUrl!),
                          mode: LaunchMode.externalApplication,
                        ),
                    icon: const Icon(Icons.picture_as_pdf_rounded, size: 20),
                    label: Text(
                      context.l10n.openPdf,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: Spacing.lg),
              ],

              // Teks Kandungan Siaran Media
              if (detail.content != null &&
                  detail.content!.trim().isNotEmpty) ...[
                Container(
                  padding: const EdgeInsets.all(Spacing.lg),
                  decoration: BoxDecoration(
                    color: AppColors.white,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: const Color(0x10000000),
                      width: 0.8,
                    ),
                    boxShadow: const [
                      BoxShadow(
                        color: Color(0x0612241C),
                        blurRadius: 10,
                        offset: Offset(0, 3),
                      ),
                    ],
                  ),
                  child: HtmlContent(
                    detail.content,
                    style: const TextStyle(
                      fontSize: 15,
                      height: 1.7,
                      color: Color(0xFF2C3E50),
                    ),
                  ),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}
