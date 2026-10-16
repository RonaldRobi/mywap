import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/manual_providers.dart';
import '../data/models/manual.dart';
import 'manual_viewer_screen.dart';

/// Manual Pengguna — senarai PDF panduan yang ditapis mengikut peranan.
///
/// Ahli biasa hanya melihat manual `user`, admin organisasi hanya melihat
/// manual `admin`; penapisan dilakukan oleh backend (`GET /manuals`).
class ManualScreen extends ConsumerWidget {
  const ManualScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final manualsAsync = ref.watch(manualsProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: const Text('Manual Pengguna'),
      ),
      body: manualsAsync.when(
        data: (manuals) {
          if (manuals.isEmpty) {
            return const EmptyState(
              icon: Icons.menu_book_outlined,
              message: 'Tiada manual buat masa ini.',
            );
          }

          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(manualsProvider),
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(Spacing.lg),
              itemCount: manuals.length + 1,
              separatorBuilder: (_, index) =>
                  SizedBox(height: index == 0 ? Spacing.md : Spacing.md),
              itemBuilder: (context, index) {
                if (index == 0) {
                  return const _IntroCard();
                }
                return _ManualCard(
                  manual: manuals[index - 1],
                  onOpen: () => _open(context, manuals[index - 1]),
                );
              },
            ),
          );
        },
        loading: () => ListView.builder(
          padding: const EdgeInsets.all(Spacing.lg),
          itemCount: 4,
          itemBuilder: (_, __) => const Padding(
            padding: EdgeInsets.only(bottom: Spacing.md),
            child: SkeletonBox(radius: 18, height: 96),
          ),
        ),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(manualsProvider),
        ),
      ),
    );
  }

  void _open(BuildContext context, Manual manual) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => ManualViewerScreen(manual: manual)),
    );
  }
}

class _IntroCard extends StatelessWidget {
  const _IntroCard();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(Spacing.lg),
      decoration: BoxDecoration(
        color: AppColors.movementSoftGreen,
        borderRadius: AppRadius.card,
      ),
      child: Row(
        children: [
          const Icon(Icons.info_outline, color: AppColors.movementNavy),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Text(
              'Panduan di bawah dipaparkan mengikut peranan akaun anda.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.movementNavy,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ManualCard extends StatelessWidget {
  const _ManualCard({required this.manual, required this.onOpen});

  final Manual manual;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final updated = _formatDate(manual.updated_at);

    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      elevation: 0,
      color: AppColors.surface,
      shape: RoundedRectangleBorder(borderRadius: AppRadius.card),
      child: InkWell(
        onTap: onOpen,
        child: Padding(
          padding: const EdgeInsets.all(Spacing.lg),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.movementSoftGreen,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.picture_as_pdf_outlined,
                  color: AppColors.movementNavy,
                ),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      manual.title ?? 'Manual',
                      style: theme.textTheme.titleSmall,
                    ),
                    if ((manual.description ?? '').isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        manual.description!,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                    if (updated != null) ...[
                      const SizedBox(height: 6),
                      Text(
                        'Dikemas kini: $updated',
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: AppColors.textTertiary,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: Spacing.sm),
              const Icon(
                Icons.chevron_right,
                color: AppColors.textSecondary,
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String? _formatDate(DateTime? date) {
    if (date == null) return null;
    final local = date.toLocal();
    const months = [
      'Jan',
      'Feb',
      'Mac',
      'Apr',
      'Mei',
      'Jun',
      'Jul',
      'Ogo',
      'Sep',
      'Okt',
      'Nov',
      'Dis',
    ];
    return '${local.day} ${months[local.month - 1]} ${local.year}';
  }
}
