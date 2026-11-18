import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
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
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.mediaStatementsTitle),
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
                          Center(child: Text(context.l10n.mediaStatementsEmpty)),
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
                    child: SkeletonBox(height: 90),
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
    return Card(
      clipBehavior: Clip.antiAlias,
      margin: EdgeInsets.zero,
      child: InkWell(
        onTap:
            () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => MediaStatementDetailScreen(id: item.id),
              ),
            ),
        child: Row(
          children: [
            SizedBox(
              width: 96,
              height: 88,
              child:
                  item.featuredImage == null
                      ? const ColoredBox(
                        color: Color(0xFFE7F5EA),
                        child: Icon(Icons.campaign_outlined, size: 28),
                      )
                      : AppImage(
                        item.featuredImage,
                        fit: BoxFit.cover,
                        borderRadius: BorderRadius.zero,
                      ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(Spacing.md),
                child: Text(
                  item.title ?? '-',
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
            ),
          ],
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
    final shareStatement = async.valueOrNull?.statement;
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.mediaStatementsTitle),
        actions: [
          if (shareStatement != null)
            ShareItemIconButton(
              title: shareStatement.title ?? '',
              url: shareStatement.shareUrl,
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
        data:
            (detail) => ListView(
              padding: const EdgeInsets.all(Spacing.lg),
              children: [
                if (detail.statement.featuredImage != null)
                  AppImage(
                    detail.statement.featuredImage,
                    width: double.infinity,
                    fit: BoxFit.cover,
                  ),
                const SizedBox(height: Spacing.lg),
                Text(
                  detail.statement.title ?? '-',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: Spacing.md),
                Align(
                  alignment: Alignment.centerLeft,
                  child: ShareItemButton(
                    title: detail.statement.title ?? '',
                    url: detail.statement.shareUrl,
                  ),
                ),
                const SizedBox(height: Spacing.lg),
                if (detail.statement.pdfUrl != null)
                  FilledButton.icon(
                    onPressed:
                        () => launchUrl(
                          Uri.parse(detail.statement.pdfUrl!),
                          mode: LaunchMode.externalApplication,
                        ),
                    icon: const Icon(Icons.picture_as_pdf_outlined),
                    label: Text(context.l10n.openPdf),
                  ),
                if (detail.content != null &&
                    detail.content!.trim().isNotEmpty) ...[
                  const SizedBox(height: Spacing.lg),
                  HtmlContent(detail.content),
                ],
              ],
            ),
      ),
    );
  }
}
