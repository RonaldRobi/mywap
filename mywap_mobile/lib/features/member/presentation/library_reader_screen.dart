import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:pdfx/pdfx.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../application/member_core_providers.dart';
import '../data/models/library_item.dart';

/// In-app "buku" reader for Pustaka PDFs.
///
/// The PDF is fetched once via [LibraryFileService] (cached on-device), then
/// shown as full-page book pages — swipe / tap to flip, pinch to zoom, current
/// page is persisted so the reader resumes where the user stopped.
class LibraryReaderScreen extends ConsumerStatefulWidget {
  const LibraryReaderScreen({super.key, required this.item});

  final LibraryItem item;

  @override
  ConsumerState<LibraryReaderScreen> createState() =>
      _LibraryReaderScreenState();
}

enum _ReaderPhase { loadingFile, ready, error }

class _LibraryReaderScreenState extends ConsumerState<LibraryReaderScreen> {
  PdfDocument? _document;
  PdfController? _controller;
  _ReaderPhase _phase = _ReaderPhase.loadingFile;
  double _downloadProgress = 0;
  bool _indeterminate = true;
  int _savedPage = 0;
  String? _errorMessage;

  int get _bookId => widget.item.id ?? 0;

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
      _phase = _ReaderPhase.loadingFile;
      _downloadProgress = 0;
      _indeterminate = true;
      _errorMessage = null;
    });

    try {
      final file = await ref
          .read(libraryFileServiceProvider)
          .fileFor(
            _bookId,
            widget.item.file_path,
            onProgress: (progress) {
              if (!mounted) return;
              setState(() {
                _downloadProgress = progress;
                _indeterminate = false;
              });
            },
          );

      final saved = await readLastLibraryPage(_bookId);
      final document = await PdfDocument.openFile(file.path);
      if (!mounted) {
        await document.close();
        return;
      }

      final first = (saved >= 0 && saved < document.pagesCount)
          ? saved
          : 0;

      _controller?.dispose();
      setState(() {
        _document = document;
        _savedPage = first;
        _phase = _ReaderPhase.ready;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _phase = _ReaderPhase.error;
        _errorMessage =
            'Buku tidak dapat dibuka. Semak sambungan internet dan cuba lagi.';
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
    final page = pageNumber - 1;
    await saveLastLibraryPage(_bookId, page);
  }

  @override
  Widget build(BuildContext context) {
    final favourites =
        ref.watch(libraryFavouritesProvider).value ?? const <int>{};

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(
          widget.item.title ?? 'Buku',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          IconButton(
            tooltip: favourites.contains(_bookId)
                ? 'Buang daripada kegemaran'
                : 'Simpan ke kegemaran',
            onPressed: () {
              ref.read(libraryFavouritesProvider.notifier).toggle(_bookId);
            },
            icon: Icon(
              favourites.contains(_bookId)
                  ? Icons.favorite
                  : Icons.favorite_border,
              color: favourites.contains(_bookId)
                  ? AppColors.error
                  : AppColors.textSecondary,
            ),
          ),
        ],
      ),
      body: _buildBody(),
      bottomNavigationBar: _phase == _ReaderPhase.ready
          ? _ReaderControls(controller: _controller!)
          : null,
    );
  }

  Widget _buildBody() {
    switch (_phase) {
      case _ReaderPhase.loadingFile:
        return _DownloadProgressView(
          indeterminate: _indeterminate,
          progress: _downloadProgress,
        );
      case _ReaderPhase.error:
        return _ReaderError(message: _errorMessage, onRetry: _load);
      case _ReaderPhase.ready:
        _buildController();
        return _buildPdf();
    }
  }

  Widget _buildPdf() {
    final controller = _controller!;
    return PdfView(
      controller: controller,
      scrollDirection: Axis.horizontal,
      onPageChanged: _onPageChanged,
      backgroundDecoration: const BoxDecoration(color: AppColors.readingSurface),
      builders: PdfViewBuilders<DefaultBuilderOptions>(
        options: const DefaultBuilderOptions(),
        documentLoaderBuilder: (_) => const Center(
          child: CircularProgressIndicator(strokeWidth: 2.5),
        ),
        pageLoaderBuilder: (_) => const Center(
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      ),
    );
  }
}

class _ReaderControls extends StatelessWidget {
  const _ReaderControls({required this.controller});

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
              onPressed: controller.page > 1
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
                builder: (_, state, page, pagesCount) => Text(
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
              onPressed: () => controller.nextPage(
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
                  ? 'Menyediakan buku…'
                  : 'Memuat turun buku… ${(progress * 100).round()}%',
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

class _ReaderError extends StatelessWidget {
  const _ReaderError({required this.message, required this.onRetry});

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
            FilledButton(
              onPressed: onRetry,
              child: const Text('Cuba Lagi'),
            ),
          ],
        ),
      ),
    );
  }
}
