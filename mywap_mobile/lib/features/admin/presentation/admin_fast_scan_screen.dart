import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/error_retry.dart';
import '../application/admin_providers.dart';

/// Pengimbas Pantas Kad Keahlian untuk Pentadbir (`/admin/scan`).
///
/// Direka khusus untuk meja pendaftaran program:
/// 1. Memilih program aktif (melekat).
/// 2. Imbasan kamera berterusan (rapid continuous scan).
/// 3. Sokong QR kad fizikal/digital, No. Ahli, dan No. Kad Pengenalan.
/// 4. Auto walk-in sekiranya ahli belum mendaftar.
/// 5. Input manual pantas jika kad fizikal rosak/calar.
class AdminFastScanScreen extends ConsumerStatefulWidget {
  const AdminFastScanScreen({super.key, this.initialEventId});

  final int? initialEventId;

  @override
  ConsumerState<AdminFastScanScreen> createState() => _AdminFastScanScreenState();
}

class _AdminFastScanScreenState extends ConsumerState<AdminFastScanScreen> {
  final MobileScannerController _scannerController = MobileScannerController();
  int? _selectedEventId;
  bool _isProcessing = false;
  bool _torchOn = false;
  String? _lastScanFeedback;
  bool _lastScanSuccess = true;
  Timer? _feedbackTimer;

  @override
  void initState() {
    super.initState();
    _selectedEventId = widget.initialEventId;
  }

  @override
  void dispose() {
    _feedbackTimer?.cancel();
    _scannerController.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_isProcessing || _selectedEventId == null) return;

    for (final barcode in capture.barcodes) {
      final raw = barcode.rawValue?.trim() ?? '';
      if (raw.isNotEmpty) {
        _processScan(raw);
        return;
      }
    }
  }

  Future<void> _processScan(String identifier) async {
    final eventId = _selectedEventId;
    if (eventId == null || _isProcessing) return;
    final l10n = context.l10n;

    setState(() => _isProcessing = true);
    HapticFeedback.lightImpact();

    try {
      final result = await ref
          .read(adminRepositoryProvider)
          .scan(eventId: eventId, identifier: identifier);

      ref.invalidate(adminAttendanceRegistrationsProvider(eventId));
      ref.invalidate(adminDashboardProvider);

      final reg = result.registration;
      final name = reg?.name ?? l10n.adminMemberFallback;
      final memberNo = reg?.memberNo ?? '';
      final msg = result.isOk
          ? (memberNo.isEmpty
              ? l10n.adminCheckInRecordedName(name)
              : l10n.adminCheckInRecorded(name, memberNo))
          : (result.message.isNotEmpty
              ? result.message
              : l10n.adminScanNotSuccessful);

      _setFeedback(msg, result.isOk);
      if (result.isOk) {
        HapticFeedback.mediumImpact();
      }
    } on ApiException catch (e) {
      _setFeedback(e.message, false);
      HapticFeedback.heavyImpact();
    } catch (_) {
      _setFeedback(l10n.adminAttendanceRecordError, false);
      HapticFeedback.heavyImpact();
    } finally {
      // Jeda 1.8 saat sebelum menerima imbasan seterusnya supaya tidak terimbas kod yang sama dua kali
      await Future.delayed(const Duration(milliseconds: 1800));
      if (mounted) {
        setState(() => _isProcessing = false);
      }
    }
  }

  void _setFeedback(String message, bool success) {
    _feedbackTimer?.cancel();
    if (!mounted) return;
    setState(() {
      _lastScanFeedback = message;
      _lastScanSuccess = success;
    });
    _feedbackTimer = Timer(const Duration(seconds: 4), () {
      if (mounted) {
        setState(() => _lastScanFeedback = null);
      }
    });
  }

  Future<void> _openManualEntry() async {
    final controller = TextEditingController();
    final eventId = _selectedEventId;
    if (eventId == null) return;

    final identifier = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(borderRadius: AppRadius.sheet),
      builder: (sheetContext) {
        return Padding(
          padding: EdgeInsets.fromLTRB(
            Spacing.lg,
            Spacing.lg,
            Spacing.lg,
            MediaQuery.of(sheetContext).viewInsets.bottom + Spacing.xl,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppColors.divider,
                    borderRadius: AppRadius.pill,
                  ),
                ),
              ),
              const SizedBox(height: Spacing.md),
              Text(
                sheetContext.l10n.adminManualEntry,
                style: Theme.of(sheetContext).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                sheetContext.l10n.adminManualEntryHint,
                style: TextStyle(color: AppColors.textSecondary, fontSize: 13),
              ),
              const SizedBox(height: Spacing.lg),
              TextField(
                controller: controller,
                autofocus: true,
                decoration: InputDecoration(
                  labelText: sheetContext.l10n.adminIcMemberPhoneLabel,
                  hintText: sheetContext.l10n.adminIcMemberPhoneExample,
                  prefixIcon: const Icon(Icons.person_search_outlined),
                ),
                textInputAction: TextInputAction.done,
                onSubmitted: (val) => Navigator.of(sheetContext).pop(val.trim()),
              ),
              const SizedBox(height: Spacing.lg),
              FilledButton(
                onPressed: () => Navigator.of(sheetContext).pop(controller.text.trim()),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(AppSizes.buttonHeight),
                ),
                child: Text(sheetContext.l10n.adminConfirmAttendanceTitle),
              ),
            ],
          ),
        );
      },
    );

    if (identifier != null && identifier.isNotEmpty) {
      _processScan(identifier);
    }
  }

  @override
  Widget build(BuildContext context) {
    final eventsAsync = ref.watch(adminUpcomingEventsProvider);

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black.withValues(alpha: 0.8),
        foregroundColor: AppColors.white,
        title: Text(context.l10n.adminAttendanceCardScanner),
        actions: [
          IconButton(
            icon: Icon(_torchOn ? Icons.flash_on : Icons.flash_off),
            tooltip: context.l10n.adminTorch,
            onPressed: () {
              setState(() => _torchOn = !_torchOn);
              _scannerController.toggleTorch();
            },
          ),
          IconButton(
            icon: const Icon(Icons.flip_camera_ios_outlined),
            tooltip: context.l10n.adminSwitchCamera,
            onPressed: () => _scannerController.switchCamera(),
          ),
        ],
      ),
      body: eventsAsync.when(
        data: (events) {
          if (events.isEmpty) {
            return Center(
              child: Card(
                color: AppColors.white,
                margin: const EdgeInsets.all(Spacing.xl),
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.xl),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.event_busy, color: AppColors.textSecondary, size: 48),
                      const SizedBox(height: Spacing.md),
                      Text(
                        context.l10n.adminNoScheduledPrograms,
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
                      ),
                      const SizedBox(height: Spacing.xs),
                      Text(
                        context.l10n.adminNoScheduledProgramsBody,
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: AppColors.textSecondary, fontSize: 13),
                      ),
                      const SizedBox(height: Spacing.lg),
                      OutlinedButton(
                        onPressed: () => context.pop(),
                        child: Text(context.l10n.back),
                      ),
                    ],
                  ),
                ),
              ),
            );
          }

          // Tetapkan program pilihan awal secara automatik jika belum dipilih
          if (_selectedEventId == null || !events.any((e) => e.id == _selectedEventId)) {
            _selectedEventId = events.first.id;
          }

          final currentEvent = events.firstWhere(
            (e) => e.id == _selectedEventId,
            orElse: () => events.first,
          );

          return Stack(
            fit: StackFit.expand,
            children: [
              // Lapisan Kamera Pengimbas
              MobileScanner(
                controller: _scannerController,
                onDetect: _onDetect,
              ),

              // Garisan Fokus Reticle
              Center(
                child: Container(
                  width: 260,
                  height: 260,
                  decoration: BoxDecoration(
                    border: Border.all(
                      color: _isProcessing
                          ? AppColors.warning
                          : AppColors.movementSoftGreen,
                      width: 3,
                    ),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Stack(
                    children: [
                      if (_isProcessing)
                        const Center(
                          child: CircularProgressIndicator(
                            color: AppColors.white,
                          ),
                        ),
                    ],
                  ),
                ),
              ),

              // Bar Pemilihan Program di Bahagian Atas
              Positioned(
                top: Spacing.md,
                left: Spacing.md,
                right: Spacing.md,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: Spacing.md,
                    vertical: Spacing.sm,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.movementNavy.withValues(alpha: 0.92),
                    borderRadius: AppRadius.md,
                    border: Border.all(color: Colors.white24),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.event_available, color: AppColors.movementSoftGreen, size: 22),
                      const SizedBox(width: Spacing.sm),
                      Expanded(
                        child: DropdownButtonHideUnderline(
                          child: DropdownButton<int>(
                            value: _selectedEventId,
                            dropdownColor: AppColors.movementNavy,
                            iconEnabledColor: AppColors.white,
                            isExpanded: true,
                            items: events.map((ev) {
                              return DropdownMenuItem<int>(
                                value: ev.id,
                                child: Text(
                                  ev.title ?? context.l10n.adminProgramFallback,
                                  style: const TextStyle(
                                    color: AppColors.white,
                                    fontSize: 14,
                                    fontWeight: FontWeight.w600,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              );
                            }).toList(),
                            onChanged: (newId) {
                              if (newId != null) {
                                setState(() => _selectedEventId = newId);
                              }
                            },
                          ),
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.list_alt, color: AppColors.white, size: 20),
                        tooltip: context.l10n.adminParticipantList,
                        onPressed: () {
                          if (currentEvent.id != null) {
                            context.push('/admin/attendance/${currentEvent.id}');
                          }
                        },
                      ),
                    ],
                  ),
                ),
              ),

              // Panduan / Banner Maklum Balas Imbasan
              Positioned(
                bottom: 100,
                left: Spacing.lg,
                right: Spacing.lg,
                child: _lastScanFeedback != null
                    ? Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: Spacing.lg,
                          vertical: Spacing.md,
                        ),
                        decoration: BoxDecoration(
                          color: _lastScanSuccess
                              ? AppColors.movementDarkGreen
                              : AppColors.error,
                          borderRadius: AppRadius.md,
                          boxShadow: AppShadows.card,
                        ),
                        child: Row(
                          children: [
                            Icon(
                              _lastScanSuccess
                                  ? Icons.check_circle_outline
                                  : Icons.error_outline,
                              color: AppColors.white,
                              size: 24,
                            ),
                            const SizedBox(width: Spacing.sm),
                            Expanded(
                              child: Text(
                                _lastScanFeedback!,
                                style: const TextStyle(
                                  color: AppColors.white,
                                  fontSize: 14,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ],
                        ),
                      )
                    : Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: Spacing.md,
                          vertical: Spacing.sm,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.black.withValues(alpha: 0.65),
                          borderRadius: AppRadius.pill,
                        ),
                        child: Text(
                          context.l10n.adminPointCameraMemberCardQr,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: Colors.white70,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
              ),

              // Butang Tindakan Bawah (Kemasukan Manual)
              Positioned(
                bottom: Spacing.lg,
                left: Spacing.lg,
                right: Spacing.lg,
                child: FilledButton.icon(
                  onPressed: _openManualEntry,
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.white,
                    foregroundColor: AppColors.movementNavy,
                    minimumSize: const Size.fromHeight(50),
                    shape: const RoundedRectangleBorder(borderRadius: AppRadius.md),
                  ),
                  icon: const Icon(Icons.keyboard_alt_outlined),
                  label: Text(
                    context.l10n.adminManualInput,
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
              ),
            ],
          );
        },
        loading: () => const Center(
          child: CircularProgressIndicator(color: AppColors.movementGreen),
        ),
        error: (e, _) => Center(
          child: ErrorRetry(
            message: e is ApiException ? e.message : context.l10n.adminLoadEventsFailed,
            onRetry: () => ref.invalidate(adminUpcomingEventsProvider),
          ),
        ),
      ),
    );
  }
}

