import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../auth/application/auth_controller.dart';
import '../../events/application/event_providers.dart';

/// Sasaran deep link: `/events/{id}/attend/{token}`.
///
/// Dibuka terus daripada pautan Universal Link / App Link (QR program),
/// tanpa perlu membuka skrin kamera. Rekod kehadiran secara automatik:
/// - Ahli login → check-in terus (walk-in disokong backend).
/// - Belum login → dialog gesa log masuk.
class AttendanceCheckInScreen extends ConsumerStatefulWidget {
  const AttendanceCheckInScreen({
    super.key,
    required this.eventId,
    required this.token,
  });

  final int eventId;
  final String token;

  @override
  ConsumerState<AttendanceCheckInScreen> createState() =>
      _AttendanceCheckInScreenState();
}

class _AttendanceCheckInScreenState
    extends ConsumerState<AttendanceCheckInScreen> {
  bool _started = false;

  @override
  void initState() {
    super.initState();
    // Tunggu satu bingkai supaya navigasi selesai sebelum dialog/check-in.
    WidgetsBinding.instance.addPostFrameCallback((_) => _begin());
  }

  Future<void> _begin() async {
    if (_started || !mounted) return;
    _started = true;

    final user = ref.read(currentUserProvider);
    if (user == null) {
      await _promptLogin();
      return;
    }
    await _submit();
  }

  Future<void> _promptLogin() async {
    final goLogin = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        icon: const Icon(
          Icons.lock_outline,
          color: AppColors.movementGreen,
          size: 40,
        ),
        title: const Text('Log Masuk Diperlukan'),
        content: const Text(
          'Kod kehadiran program dikesan. Sila log masuk atau daftar '
          'sebagai ahli untuk merekodkan kehadiran anda.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Log Masuk'),
          ),
        ],
      ),
    );
    if (!mounted) return;
    if (goLogin == true) {
      context.go('/login');
      return;
    }
    // Batal — kembali ke laman utama.
    context.go('/home');
  }

  Future<void> _submit() async {
    try {
      final result = await ref
          .read(eventRepositoryProvider)
          .checkIn(widget.eventId, token: widget.token);
      if (!mounted) return;
      await _showResult(
        success: true,
        message:
            'Kehadiran anda untuk '
            '"${result['event_title'] ?? 'program ini'}" telah direkodkan.',
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      await _showResult(success: false, message: e.message);
    } catch (_) {
      if (!mounted) return;
      await _showResult(
        success: false,
        message: 'Ralat tidak dijangka. Sila cuba lagi.',
      );
    }
  }

  Future<void> _showResult({
    required bool success,
    required String message,
  }) async {
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
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
                Navigator.of(dialogContext).pop();
                if (mounted) context.go('/dashboard');
              },
              child: const Text('Selesai'),
            )
          else
            TextButton(
              onPressed: () {
                Navigator.of(dialogContext).pop();
                if (mounted) context.go('/dashboard');
              },
              child: const Text('Tutup'),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Kehadiran Program')),
      body: const Center(child: CircularProgressIndicator()),
    );
  }
}
