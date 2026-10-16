import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:pdfx/pdfx.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../application/manual_providers.dart';
import '../data/models/manual.dart';

/// In-app PDF reader for a single [Manual]. The file is fetched once via
/// [ManualFileService] (cached on-device) then shown page-by-page.
class ManualViewerScreen extends ConsumerStatefulWidget {
  const ManualViewerScreen({super.key, required this.manual});

  final Manual manual;

  @override
  ConsumerState<ManualViewerScreen> createState() => _ManualViewerScreenState();
}

enum _ViewerPhase { loadingFile, ready, error }

class _ManualViewerScreenState extends ConsumerState<ManualViewerScreen> {
  PdfDocument? _document;
  PdfController? _controller;
  _ViewerPhase _phase = _ViewerPhase.loadingFile;
  double _downloadProgress = 0;
  bool _indeterminate = true;
  int _savedPage = 0;
  String? _errorMessage;

  int get _manualId => widget.manual.id ?? 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _controller?.dispose();
    final document = _document;
    if (document != null && !document.isClosed) {
      document.close();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _phase = _ViewerPhase.loadingFile;
      _downloadProgress = 0;
      _indeterminate = true;
      _errorMessage = null;
    });

    try {
      final file = await ref
          .read(manualFileServiceProvider)
          .fileFor(
            _manualId,
            widget.manual.file_url,
            onProgress: (progress) {
              if (!mounted) return;
              setState(() {
                _downloadProgress = progress;
                _indeterminate = false;
              });
            },
          );

      final saved = await _readLastPage(_manualId);
      final document = await PdfDocument.openFile(file.path);
      if (!mounted) {
        await document.close();
        return;
      }

      final first = (saved >= 0 && saved < document.pagesCount) ? saved : 0;

      _controller?.dispose();
      setState(() {
        _document = document;
        _savedPage = first;
        _phase = _ViewerPhase.ready;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _phase = _ViewerPhase.error;
        _errorMessage =
            'Manual tidak dapat dibuka. Semak sambungan internet dan cuba lagi.';
      });
    }
  }

  void _buildController() {
    final document = _document;
    if (document == null || _controller != null) return;
    _controller = PdfController(
      document: Future.value(document),
      initialPage: _savedPage + 1,
    );
  }

  Future<void> _onPageChanged(int pageNumber) async {
    await _saveLastPage(_manualId, pageNumber - 1);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(
          widget.manual.title ?? 'Manual',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ),
      body: _buildBody(),
      bottomNavigationBar:
          _phase == _ViewerPhase.ready
              ? _ViewerControls(controller: _controller!)
              : null,
    );
  }

  Widget _buildBody() {
    switch (_phase) {
      case _ViewerPhase.loadingFile:
        return _DownloadProgressView(
          indeterminate: _indeterminate,
          progress: _downloadProgress,
        );
      case _ViewerPhase.error:
        return _ViewerError(message: _errorMessage, onRetry: _load);
      case _ViewerPhase.ready:
        _buildController();
        return PdfView(
          controller: _controller!,
          scrollDirection: Axis.horizontal,
          onPageChanged: _onPageChanged,
          backgroundDecoration: const BoxDecoration(
            color: AppColors.readingSurface,
          ),
          builders: PdfViewBuilders<DefaultBuilderOptions>(
            options: const DefaultBuilderOptions(),
            documentLoaderBuilder:
                (_) => const Center(
                  child: CircularProgressIndicator(strokeWidth: 2.5),
                ),
            pageLoaderBuilder:
                (_) => const Center(
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
          ),
        );
    }
  }
}

const String _lastPagePrefix = 'manual_last_page_v1_';

Future<int> _readLastPage(int id) async {
  final prefs = await SharedPreferences.getInstance();
  return prefs.getInt('$_lastPagePrefix$id') ?? 0;
}

Future<void> _saveLastPage(int id, int page) async {
  final prefs = await SharedPreferences.getInstance();
  await prefs.setInt('$_lastPagePrefix$id', page);
}

class _ViewerControls extends StatelessWidget {
  const _ViewerControls({required this.controller});

  final PdfController controller;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: Spacing.xs),
        color: AppColors.surface,
        child: Row(
          children: [
            const SizedBox(width: Spacing.sm),
            IconButton(
              tooltip: 'Muka surat sebelumnya',
              onPressed:
                  controller.page > 1
                      ? () => controller.previousPage(
                        duration: const Duration(milliseconds: 180),
                        curve: Curves.easeOut,
                      )
                      : null,
              icon: const Icon(Icons.chevron_left),
            ),
            Expanded(
              child: PdfPageNumber(
                controller: controller,
                builder:
                    (_, state, page, pagesCount) => Text(
                      '$page / ${pagesCount ?? 0}',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.labelMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                        color: AppColors.textSecondary,
                      ),
                    ),
              ),
            ),
            IconButton(
              tooltip: 'Muka surat seterusnya',
              onPressed:
                  () => controller.nextPage(
                    duration: const Duration(milliseconds: 180),
                    curve: Curves.easeOut,
                  ),
              icon: const Icon(Icons.chevron_right),
            ),
            const SizedBox(width: Spacing.sm),
          ],
        ),
      ),
    );
  }
}

class _DownloadProgressView extends StatelessWidget {
  const _DownloadProgressView({
    required this.indeterminate,
    required this.progress,
  });

  final bool indeterminate;
  final double progress;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.xxl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.menu_book_outlined,
              size: 44,
              color: AppColors.movementGreen,
            ),
            const SizedBox(height: Spacing.lg),
            Text(
              indeterminate
                  ? 'Menyediakan manual…'
                  : 'Memuat turun manual… ${(progress * 100).round()}%',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: Spacing.md),
            SizedBox(
              width: 220,
              child: LinearProgressIndicator(
                minHeight: 6,
                borderRadius: BorderRadius.circular(99),
                backgroundColor: AppColors.paleGreen,
                value: indeterminate ? null : progress.clamp(0.0, 1.0),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ViewerError extends StatelessWidget {
  const _ViewerError({required this.message, required this.onRetry});

  final String? message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.xxl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.menu_book_outlined,
              size: 44,
              color: AppColors.textTertiary,
            ),
            const SizedBox(height: Spacing.lg),
            Text(
              message ?? 'Ralat tidak dijangka.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
            const SizedBox(height: Spacing.lg),
            FilledButton(onPressed: onRetry, child: const Text('Cuba Lagi')),
          ],
        ),
      ),
    );
  }
}
