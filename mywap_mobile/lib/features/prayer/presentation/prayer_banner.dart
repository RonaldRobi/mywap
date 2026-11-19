import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../application/prayer_providers.dart';
import '../data/prayer_repository.dart';

class PrayerBanner extends ConsumerStatefulWidget {
  const PrayerBanner({super.key});

  @override
  ConsumerState<PrayerBanner> createState() => _PrayerBannerState();
}

class _PrayerBannerState extends ConsumerState<PrayerBanner> {
  Timer? _refreshTimer;

  @override
  void initState() {
    super.initState();
    _refreshTimer = Timer.periodic(const Duration(minutes: 1), (_) {
      final snapshot = ref.read(prayerProvider).valueOrNull;
      if (snapshot != null && !malaysiaNow().isBefore(snapshot.nextAt)) {
        ref.invalidate(prayerProvider);
      }
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  Future<void> _pickLocation() async {
    final choice = await showModalBottomSheet<PrayerLocation>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(borderRadius: AppRadius.sheet),
      builder: (sheetContext) => const _LocationSheet(),
    );
    if (choice == null || !mounted) return;
    ref.read(prayerManualLocationProvider.notifier).state =
        choice.useDevice ? null : choice;
    ref.invalidate(prayerProvider);
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(prayerProvider);
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.md,
        Spacing.lg,
        Spacing.sm,
      ),
      child: async.when(
        loading:
            () => const SizedBox(
              height: 64,
              child: Center(child: LinearProgressIndicator(minHeight: 2)),
            ),
        error: (error, _) {
          if (error is PrayerPermissionDenied) {
            if (error.permanent) {
              return _PrayerMessage(
                text: context.l10n.prayerLocationBlocked,
                icon: Icons.settings_outlined,
                onTap: () async {
                  await Geolocator.openAppSettings();
                  ref.invalidate(prayerProvider);
                },
                onManualLocation: _pickLocation,
              );
            }
            return _PrayerMessage(
              text: context.l10n.prayerLocationAllow,
              onTap: () => ref.invalidate(prayerProvider),
              onManualLocation: _pickLocation,
            );
          }
          if (error is PrayerLocationDisabled) {
            return _PrayerMessage(
              text: context.l10n.prayerLocationOff,
              icon: Icons.location_off_outlined,
              onTap: () async {
                await Geolocator.openLocationSettings();
                ref.invalidate(prayerProvider);
              },
              onManualLocation: _pickLocation,
            );
          }
          return _PrayerMessage(
            text:
                error is PrayerGpsUnavailable
                    ? context.l10n.prayerGpsUnavailable
                    : context.l10n.prayerLoadFailed,
            onTap: () => ref.invalidate(prayerProvider),
            onManualLocation: _pickLocation,
          );
        },
        data: (snapshot) => _PrayerContent(snapshot: snapshot),
      ),
    );
  }
}

class _PrayerContent extends StatefulWidget {
  const _PrayerContent({required this.snapshot});

  final PrayerSnapshot snapshot;

  @override
  State<_PrayerContent> createState() => _PrayerContentState();
}

class _PrayerContentState extends State<_PrayerContent> {
  Timer? _countdownTimer;

  @override
  void initState() {
    super.initState();
    _countdownTimer = Timer.periodic(
      const Duration(minutes: 1),
      (_) => setState(() {}),
    );
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final snapshot = widget.snapshot;
    final remaining = snapshot.nextAt.difference(malaysiaNow());

    return Material(
      color: Colors.transparent,
      borderRadius: AppRadius.md,
      child: InkWell(
        onTap: () => _showPrayerDetails(context, snapshot),
        borderRadius: AppRadius.md,
        child: SizedBox(
          height: 80,
          child: ClipRRect(
            borderRadius: AppRadius.md,
            child: Stack(
              fit: StackFit.expand,
              children: [
                _PrayerPhoto(scene: snapshot.scene),
                const DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.centerLeft,
                      end: Alignment.centerRight,
                      colors: [
                        Color(0xCC000000),
                        Color(0x4D000000),
                        Color(0x99000000),
                      ],
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: Spacing.md),
                  child: Row(
                    children: [
                      Icon(
                        _sceneIcon(snapshot.scene),
                        color: AppColors.white,
                        size: 26,
                      ),
                      const SizedBox(width: Spacing.sm),
                      Expanded(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                const Icon(
                                  Icons.location_on_outlined,
                                  color: AppColors.white,
                                  size: 12,
                                ),
                                const SizedBox(width: 4),
                                Expanded(
                                  child: Text(
                                    snapshot.locationName,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: AppColors.white,
                                      fontSize: 11,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            Text(
                              '${snapshot.nextName} ${snapshot.nextTime}',
                              style: const TextStyle(
                                color: AppColors.white,
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            _formatRemaining(remaining),
                            style: const TextStyle(
                              color: AppColors.white,
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          Text(
                            snapshot.hijriDate,
                            style: const TextStyle(
                              color: AppColors.white,
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const Positioned(
                  right: 8,
                  bottom: 6,
                  child: Icon(
                    Icons.keyboard_arrow_up_rounded,
                    color: Colors.white70,
                    size: 18,
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

String _formatRemaining(Duration duration) {
  final totalMinutes = duration.inMinutes.clamp(0, 24 * 60);
  if (totalMinutes < 60) {
    return currentAppLocalizations.prayerMinutesLeft(totalMinutes);
  }
  final hours = totalMinutes ~/ 60;
  final minutes = totalMinutes % 60;
  return minutes == 0
      ? currentAppLocalizations.prayerHoursLeft(hours)
      : currentAppLocalizations.prayerHoursMinutesLeft(hours, minutes);
}

IconData _sceneIcon(PrayerScene scene) => switch (scene) {
  PrayerScene.subuh => Icons.nightlight_round,
  PrayerScene.day => Icons.wb_sunny_rounded,
  PrayerScene.asar => Icons.wb_sunny_outlined,
  PrayerScene.maghrib => Icons.wb_twilight_rounded,
  PrayerScene.isyak => Icons.nightlight_round,
};

class _PrayerPhoto extends StatelessWidget {
  const _PrayerPhoto({required this.scene});
  final PrayerScene scene;

  String get _asset => switch (scene) {
    PrayerScene.subuh => 'assets/prayer/prayer-subuh.jpg',
    PrayerScene.day => 'assets/prayer/prayer-day.jpg',
    PrayerScene.asar => 'assets/prayer/prayer-asar.jpg',
    PrayerScene.maghrib => 'assets/prayer/prayer-twilight.jpg',
    PrayerScene.isyak => 'assets/prayer/prayer-night.jpg',
  };

  @override
  Widget build(BuildContext context) {
    return Image.asset(
      _asset,
      fit: BoxFit.cover,
      alignment: Alignment.center,
      filterQuality: FilterQuality.medium,
    );
  }
}

Future<void> _showPrayerDetails(BuildContext context, PrayerSnapshot snapshot) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    backgroundColor: AppColors.surface,
    shape: const RoundedRectangleBorder(borderRadius: AppRadius.sheet),
    builder: (sheetContext) => _PrayerDetailsSheet(snapshot: snapshot),
  );
}

class _PrayerDetailsSheet extends StatefulWidget {
  const _PrayerDetailsSheet({required this.snapshot});

  final PrayerSnapshot snapshot;

  @override
  State<_PrayerDetailsSheet> createState() => _PrayerDetailsSheetState();
}

class _PrayerDetailsSheetState extends State<_PrayerDetailsSheet> {
  static const _order = ['Subuh', 'Zohor', 'Asar', 'Maghrib', 'Isyak'];
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(minutes: 1), (_) {
      if (!malaysiaNow().isBefore(widget.snapshot.nextAt)) {
        if (mounted) Navigator.of(context).pop();
        return;
      }
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final snapshot = widget.snapshot;
    final remaining = snapshot.nextAt.difference(malaysiaNow());

    return SafeArea(
      top: false,
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(
          Spacing.xl,
          Spacing.xs,
          Spacing.xl,
          Spacing.xl,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.l10n.prayerTodayTitle,
                        style: theme.textTheme.headlineSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.5,
                        ),
                      ),
                      const SizedBox(height: Spacing.xs),
                      Text(
                        snapshot.hijriDate,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: AppColors.textSecondary,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: context.l10n.close,
                  onPressed: () => Navigator.of(context).pop(),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
            const SizedBox(height: Spacing.md),
            Row(
              children: [
                const Icon(
                  Icons.location_on_outlined,
                  size: 18,
                  color: AppColors.movementGreen,
                ),
                const SizedBox(width: Spacing.sm),
                Expanded(
                  child: Text(
                    snapshot.locationName,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: Spacing.xl),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(Spacing.lg),
              decoration: const BoxDecoration(
                color: AppColors.movementNavy,
                borderRadius: AppRadius.lg,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.l10n.prayerNext,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.textOnDark,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: Spacing.sm),
                  Wrap(
                    spacing: Spacing.lg,
                    runSpacing: Spacing.xs,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      Text(
                        snapshot.nextName,
                        style: theme.textTheme.titleLarge?.copyWith(
                          color: AppColors.white,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        snapshot.nextTime,
                        style: theme.textTheme.headlineMedium?.copyWith(
                          color: AppColors.white,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.8,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: Spacing.xs),
                  Text(
                    _formatRemaining(remaining),
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: AppColors.textOnDark,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: Spacing.xl),
            Container(
              clipBehavior: Clip.antiAlias,
              decoration: BoxDecoration(
                color: AppColors.surfaceMuted,
                borderRadius: AppRadius.lg,
                border: Border.all(color: AppColors.divider),
              ),
              child: Column(
                children: [
                  for (var index = 0; index < _order.length; index++) ...[
                    _PrayerTimeRow(
                      name: _order[index],
                      time: snapshot.prayerTimes[_order[index]] ?? '--:--',
                      isNext: _order[index] == snapshot.nextName,
                    ),
                    if (index != _order.length - 1)
                      const Divider(height: 1, indent: Spacing.lg),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PrayerTimeRow extends StatelessWidget {
  const _PrayerTimeRow({
    required this.name,
    required this.time,
    required this.isNext,
  });

  final String name;
  final String time;
  final bool isNext;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      constraints: const BoxConstraints(minHeight: 58),
      color: isNext ? AppColors.paleGreen : AppColors.surface,
      padding: const EdgeInsets.symmetric(
        horizontal: Spacing.lg,
        vertical: Spacing.sm,
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: theme.textTheme.bodyLarge?.copyWith(
                    fontWeight: isNext ? FontWeight.w800 : FontWeight.w600,
                    color:
                        isNext
                            ? AppColors.movementDarkGreen
                            : AppColors.textPrimary,
                  ),
                ),
                if (isNext)
                  Text(
                    context.l10n.prayerNext,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.movementDarkGreen,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: Spacing.md),
          Text(
            time,
            style: theme.textTheme.titleMedium?.copyWith(
              color:
                  isNext ? AppColors.movementDarkGreen : AppColors.textPrimary,
              fontWeight: FontWeight.w800,
              fontFeatures: const [FontFeature.tabularFigures()],
            ),
          ),
        ],
      ),
    );
  }
}

class _LocationSheet extends StatelessWidget {
  const _LocationSheet();

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          Spacing.xl,
          Spacing.sm,
          Spacing.xl,
          Spacing.xl,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              context.l10n.prayerSelectLocation,
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: Spacing.xs),
            Text(
              context.l10n.prayerManualLocationHint,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
            ),
            const SizedBox(height: Spacing.md),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(
                Icons.my_location_rounded,
                color: AppColors.movementGreen,
              ),
              title: Text(context.l10n.prayerUseDeviceLocation),
              onTap:
                  () => Navigator.of(context).pop(
                    const PrayerLocation(
                      name: '',
                      latitude: 0,
                      longitude: 0,
                      useDevice: true,
                    ),
                  ),
            ),
            const Divider(height: 1),
            const SizedBox(height: Spacing.xs),
            SizedBox(
              height: 320,
              child: ListView.separated(
                itemCount: prayerLocations.length,
                separatorBuilder:
                    (_, __) =>
                        const Divider(height: 1, color: AppColors.divider),
                itemBuilder: (context, index) {
                  final location = prayerLocations[index];
                  return ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(
                      Icons.location_city_outlined,
                      color: AppColors.textSecondary,
                    ),
                    title: Text(location.name),
                    subtitle: Text(
                      '${location.latitude.toStringAsFixed(2)}, '
                      '${location.longitude.toStringAsFixed(2)}',
                    ),
                    onTap: () => Navigator.of(context).pop(location),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PrayerMessage extends StatelessWidget {
  const _PrayerMessage({
    required this.text,
    required this.onTap,
    this.icon = Icons.refresh_outlined,
    this.onManualLocation,
  });
  final String text;
  final VoidCallback onTap;
  final IconData icon;
  final VoidCallback? onManualLocation;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: AppRadius.md,
    child: Container(
      height: 64,
      padding: const EdgeInsets.symmetric(horizontal: Spacing.md),
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: Border.all(color: AppColors.inputBorder),
        borderRadius: AppRadius.md,
      ),
      child: Row(
        children: [
          const Icon(
            Icons.location_on_outlined,
            color: AppColors.movementGreen,
          ),
          const SizedBox(width: Spacing.sm),
          Expanded(
            child: Text(
              text,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
          if (onManualLocation != null)
            TextButton(
              onPressed: onManualLocation,
              style: TextButton.styleFrom(
                minimumSize: const Size(0, AppSizes.tapTarget),
                padding: const EdgeInsets.symmetric(horizontal: Spacing.sm),
                visualDensity: VisualDensity.compact,
              ),
              child: Text(
                context.l10n.prayerSelectLocation,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
            )
          else
            Icon(icon),
        ],
      ),
    ),
  );
}
