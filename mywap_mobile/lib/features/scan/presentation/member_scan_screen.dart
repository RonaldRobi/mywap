import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../events/application/event_providers.dart';

/// Floating-button "Imbas QR" — member self check-in ke program/event.
///
/// QR poster event membawa URL berbentuk
/// `https://.../events/{id}/attend/{token}` (lihat routes/web.php
/// `events.attend`). Skrin ini parse URL tersebut lalu panggil
/// `POST /events/{id}/check-in` (JSON, sepadan dgn AttendanceController::scan
/// aliran "ahli login").
class MemberScanScreen extends ConsumerStatefulWidget {
  const MemberScanScreen({super.key});

  @override
  ConsumerState<MemberScanScreen> createState() => _MemberScanScreenState();
}

class _MemberScanScreenState extends ConsumerState<MemberScanScreen> {
  final MobileScannerController _controller = MobileScannerController();
  bool _processing = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    for (final barcode in capture.barcodes) {
      final raw = barcode.rawValue?.trim() ?? '';
      if (raw.isNotEmpty) {
        _handlePayload(raw);
        return;
      }
    }
  }

  /// Cuba ekstrak `{id, token}` dari URL kehadiran. Terima juga format
  /// mentah `id:token` sebagai fallback untuk imbasan manual/ujian.
  ({int id, String token})? _parseAttendanceUrl(String raw) {
    try {
      final uri = Uri.parse(raw);
      final segments = uri.pathSegments;
      final idx = segments.indexOf('events');
      if (idx != -1 &&
          segments.length > idx + 3 &&
          segments[idx + 2] == 'attend') {
        final id = int.tryParse(segments[idx + 1]);
        final token = segments[idx + 3];
        if (id != null && token.isNotEmpty) return (id: id, token: token);
      }
    } catch (_) {
      // Bukan URL — cuba fallback di bawah.
    }

    final parts = raw.split(':');
    if (parts.length == 2) {
      final id = int.tryParse(parts[0]);
      if (id != null && parts[1].isNotEmpty) return (id: id, token: parts[1]);
    }
    return null;
  }

  Future<void> _handlePayload(String raw) async {
    if (_processing) return;
    final parsed = _parseAttendanceUrl(raw);
    if (parsed == null) {
      await _showResultDialog(
        success: false,
        message: 'Kod QR ini bukan kod kehadiran program myWAP.',
      );
      return;
    }

    setState(() => _processing = true);
    try {
      await _controller.stop();
    } catch (_) {}

    try {
      final result = await ref
          .read(eventRepositoryProvider)
          .checkIn(parsed.id, token: parsed.token);
      if (!mounted) return;
      await _showResultDialog(
        success: true,
        message:
            'Kehadiran anda untuk "${result['event_title'] ?? 'program ini'}" telah direkodkan.',
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      await _showResultDialog(success: false, message: e.message);
    } catch (_) {
      if (!mounted) return;
      await _showResultDialog(
        success: false,
        message: 'Ralat tidak dijangka. Sila cuba lagi.',
      );
    }
  }

  Future<void> _showResultDialog({
    required bool success,
    required String message,
  }) async {
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        icon: Icon(
          success ? Icons.check_circle_outline : Icons.error_outline,
          color: success ? AppColors.success : AppColors.error,
          size: 40,
        ),
        title: Text(success ? 'Kehadiran Disahkan' : 'Imbasan Gagal'),
        content: Text(message),
        actions: [
          if (success)
            FilledButton(
              onPressed: () {
                Navigator.of(context).pop();
                if (mounted) context.pop();
              },
              child: const Text('Selesai'),
            )
          else
            TextButton(
              onPressed: () {
                Navigator.of(context).pop();
                setState(() => _processing = false);
                try {
                  _controller.start();
                } catch (_) {}
              },
              child: const Text('Cuba Lagi'),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: AppColors.white,
        title: const Text('Imbas QR Kehadiran'),
      ),
      body: Stack(
        children: [
          MobileScanner(controller: _controller, onDetect: _onDetect),
          // Frame overlay — memberi fokus visual pada kawasan imbasan.
          Center(
            child: Container(
              width: 240,
              height: 240,
              decoration: BoxDecoration(
                border: Border.all(color: AppColors.movementSoftGreen, width: 3),
                borderRadius: AppRadius.xl,
              ),
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.all(Spacing.xl),
                child: Text(
                  'Halakan kamera ke kod QR pada poster/skrin program untuk daftar hadir.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.white,
                  ),
                ),
              ),
            ),
          ),
          if (_processing)
            const Positioned.fill(
              child: ColoredBox(
                color: Colors.black54,
                child: Center(
                  child: CircularProgressIndicator(color: AppColors.white),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
