import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/list_card.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/admin_providers.dart';
import '../data/models/admin_models.dart';

/// Per-event attendance + QR scanning (`/admin/attendance/:eventId`).
class AdminAttendanceDetailScreen extends ConsumerStatefulWidget {
  const AdminAttendanceDetailScreen({super.key, required this.eventId});

  final int eventId;

  @override
  ConsumerState<AdminAttendanceDetailScreen> createState() =>
      _AdminAttendanceDetailScreenState();
}

class _AdminAttendanceDetailScreenState
    extends ConsumerState<AdminAttendanceDetailScreen> {
  Future<void> _openScanner() async {
    final result = await Navigator.of(context).push<ScanResult>(
      MaterialPageRoute(
        builder: (_) => _ScannerPage(eventId: widget.eventId),
      ),
    );
    if (result == null || !mounted) return;
    ref.invalidate(adminAttendanceRegistrationsProvider(widget.eventId));
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(result.isOk ? 'Kehadiran direkodkan.' : result.message),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final dataAsync = ref.watch(adminAttendanceRegistrationsProvider(widget.eventId));

    return Scaffold(
      appBar: AppBar(title: const Text('Kehadiran Acara')),
      body: dataAsync.when(
        data: (data) => _AttendanceContent(data: data, onScan: _openScanner),
        loading: () => const _AttendanceSkeleton(),
        error: (error, _) => ErrorRetry(
          message: error is ApiException ? error.message : 'Ralat tidak dijangka.',
          onRetry: () => ref.invalidate(
            adminAttendanceRegistrationsProvider(widget.eventId),
          ),
        ),
      ),
    );
  }
}

class _AttendanceContent extends StatelessWidget {
  const _AttendanceContent({required this.data, required this.onScan});

  final AttendanceData data;
  final VoidCallback onScan;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(Spacing.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                data.event?.title ?? 'Acara',
                style: theme.textTheme.titleLarge,
              ),
              if (data.event?.startTime != null) ...[
                const SizedBox(height: 4),
                Text(
                  Formatters.datetime(data.event!.startTime),
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
              const SizedBox(height: Spacing.lg),
              Row(
                children: [
                  Expanded(
                    child: _StatTile(
                      label: 'Pendaftaran',
                      value: '${data.totalRegistered}',
                      icon: Icons.people_outline,
                    ),
                  ),
                  const SizedBox(width: Spacing.md),
                  Expanded(
                    child: _StatTile(
                      label: 'Hadir',
                      value: '${data.attendedCount}',
                      icon: Icons.check_circle_outline,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        Expanded(
          child: data.registrations.isEmpty
              ? const EmptyState(
                  icon: Icons.event_seat_outlined,
                  message: 'Tiada pendaftaran untuk acara ini.',
                )
              : ListView.builder(
                  padding: const EdgeInsets.only(bottom: Spacing.xl),
                  itemCount: data.registrations.length,
                  itemBuilder: (context, index) {
                    final registration = data.registrations[index];
                    return _RegistrationCard(registration: registration);
                  },
                ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(Spacing.lg, 0, Spacing.lg, Spacing.lg),
            child: FilledButton.icon(
              onPressed: onScan,
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Imbas QR'),
            ),
          ),
        ),
      ],
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({required this.label, required this.value, required this.icon});

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Row(
          children: [
            Icon(icon, color: AppColors.movementGreen, size: 24),
            const SizedBox(width: Spacing.sm),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(value, style: theme.textTheme.titleMedium),
                Text(
                  label,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _RegistrationCard extends StatelessWidget {
  const _RegistrationCard({required this.registration});

  final Registration registration;

  @override
  Widget build(BuildContext context) {
    return ListCard(
      title: registration.name.isEmpty ? 'Tanpa Nama' : registration.name,
      subtitle: [
        if (registration.memberNo.isNotEmpty) 'No. ${registration.memberNo}',
        if (registration.attendedAt != null)
          'Hadir ${Formatters.datetime(registration.attendedAt)}',
      ].join(' · '),
      trailing: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: registration.attended
              ? AppColors.movementSoftGreen
              : AppColors.divider,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Text(
          registration.attended ? 'Hadir' : 'Belum',
          style: TextStyle(
            color: registration.attended ? AppColors.movementNavy : AppColors.textSecondary,
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }
}

/// Full-screen barcode scanner used by the attendance flow.
class _ScannerPage extends ConsumerStatefulWidget {
  const _ScannerPage({required this.eventId});

  final int eventId;

  @override
  ConsumerState<_ScannerPage> createState() => _ScannerPageState();
}

class _ScannerPageState extends ConsumerState<_ScannerPage> {
  final MobileScannerController _scannerController = MobileScannerController();
  bool _processing = false;

  @override
  void dispose() {
    _scannerController.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    for (final barcode in capture.barcodes) {
      final raw = barcode.rawValue?.trim() ?? '';
      if (raw.isNotEmpty) {
        _submitIdentifier(raw);
        return;
      }
    }
  }

  Future<void> _submitIdentifier(String identifier) async {
    final value = identifier.trim();
    if (value.isEmpty || _processing) return;
    setState(() => _processing = true);
    try {
      await _scannerController.stop();
    } catch (_) {
      // Camera may already be stopped — ignore.
    }
    try {
      final result = await ref
          .read(adminRepositoryProvider)
          .scan(eventId: widget.eventId, identifier: value);
      if (!mounted) return;
      await _showScanResult(result);
    } on ApiException catch (e) {
      if (!mounted) return;
      await _showErrorDialog(e.message);
      _resume();
    } catch (_) {
      if (!mounted) return;
      await _showErrorDialog('Ralat tidak dijangka.');
      _resume();
    }
  }

  Future<void> _showScanResult(ScanResult result) async {
    final registration = result.registration;
    if (result.isOk) {
      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (context) => AlertDialog(
          icon: const Icon(Icons.check_circle, color: AppColors.success, size: 40),
          title: const Text('Kehadiran Disahkan'),
          content: Text(
            '${registration?.name ?? 'Ahli'} (${registration?.memberNo ?? '-'}) telah direkodkan hadir.',
          ),
          actions: [
            FilledButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('OK'),
            ),
          ],
        ),
      );
      if (!mounted) return;
      Navigator.of(context).pop(result);
      return;
    }
    await _showErrorDialog(result.message.isEmpty ? 'Imbasan tidak dikenali.' : result.message);
    _resume();
  }

  Future<void> _showErrorDialog(String message) async {
    await showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        icon: const Icon(Icons.error_outline, color: AppColors.error, size: 40),
        title: const Text('Imbasan Gagal'),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  void _resume() {
    _processing = false;
    try {
      _scannerController.start();
    } catch (_) {
      // Ignore — scanning will be attempted again on the next barcode.
    }
  }

  void _manualEntry() async {
    final controller = TextEditingController();
    final identifier = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Imbasan Manual'),
        content: TextField(
          controller: controller,
          autofocus: true,
          decoration: const InputDecoration(
            labelText: 'No. ahli / ID',
            isDense: true,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(controller.text),
            child: const Text('Imbas'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (identifier != null && identifier.trim().isNotEmpty) {
      await _submitIdentifier(identifier);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: AppColors.white,
        title: const Text('Imbas QR'),
      ),
      body: Stack(
        children: [
          MobileScanner(
            controller: _scannerController,
            onDetect: _onDetect,
            errorBuilder: (context, error, child) =>
                _ScannerError(errorCode: error.errorCode),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.all(Spacing.lg),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'Halakan kamera ke kod QR ahli.',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: AppColors.white,
                          ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: Spacing.md),
                    OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.white,
                        side: const BorderSide(color: AppColors.white),
                        minimumSize: const Size.fromHeight(48),
                      ),
                      onPressed: _processing ? null : _manualEntry,
                      icon: const Icon(Icons.keyboard_outlined),
                      label: const Text('Masukkan Secara Manual'),
                    ),
                  ],
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

class _ScannerError extends StatelessWidget {
  const _ScannerError({required this.errorCode});

  final MobileScannerErrorCode errorCode;

  @override
  Widget build(BuildContext context) {
    final isPermission = errorCode == MobileScannerErrorCode.permissionDenied;
    return ColoredBox(
      color: Colors.black,
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(Spacing.xl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.no_photography_outlined, color: AppColors.white, size: 48),
              const SizedBox(height: Spacing.md),
              Text(
                isPermission
                    ? 'Kebenaran kamera diperlukan untuk mengimbas QR.'
                    : 'Kamera tidak dapat diakses.',
                style: const TextStyle(color: AppColors.white),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _AttendanceSkeleton extends StatelessWidget {
  const _AttendanceSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: const [
        SkeletonBox(height: 24, width: 240),
        SizedBox(height: Spacing.md),
        SkeletonBox(height: 96),
        SizedBox(height: Spacing.lg),
        SkeletonBox(height: 76),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 76),
        SizedBox(height: Spacing.sm),
        SkeletonBox(height: 76),
      ],
    );
  }
}
