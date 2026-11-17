import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../application/prayer_providers.dart';
import '../data/prayer_repository.dart';

class PrayerBanner extends ConsumerWidget {
  const PrayerBanner({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
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
                text: 'Lokasi disekat. Benarkan dalam Tetapan.',
                icon: Icons.settings_outlined,
                onTap: () async {
                  await Geolocator.openAppSettings();
                  ref.invalidate(prayerProvider);
                },
              );
            }
            return _PrayerMessage(
              text: 'Benarkan lokasi untuk waktu solat kawasan anda',
              onTap: () => ref.invalidate(prayerProvider),
            );
          }
          if (error is PrayerLocationDisabled) {
            return _PrayerMessage(
              text:
                  'Perkhidmatan lokasi dimatikan. Hidupkan untuk waktu solat.',
              icon: Icons.location_off_outlined,
              onTap: () async {
                await Geolocator.openLocationSettings();
                ref.invalidate(prayerProvider);
              },
            );
          }
          return _PrayerMessage(
            text: 'Waktu solat tak dapat dimuatkan. Cuba lagi.',
            onTap: () => ref.invalidate(prayerProvider),
          );
        },
        data: (snapshot) => _PrayerContent(snapshot: snapshot),
      ),
    );
  }
}

class _PrayerContent extends StatelessWidget {
  const _PrayerContent({required this.snapshot});
  final PrayerSnapshot snapshot;

  @override
  Widget build(BuildContext context) {
    final minutes = snapshot.remaining.inMinutes;
    return Container(
      height: 64,
      padding: const EdgeInsets.symmetric(horizontal: Spacing.md),
      decoration: BoxDecoration(
        color: AppColors.movementNavy,
        borderRadius: AppRadius.md,
      ),
      child: Row(
        children: [
          Icon(
            snapshot.isDay
                ? Icons.wb_sunny_outlined
                : Icons.nights_stay_outlined,
            color: AppColors.white,
            size: 24,
          ),
          const SizedBox(width: Spacing.sm),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Waktu solat seterusnya',
                  style: Theme.of(
                    context,
                  ).textTheme.bodySmall?.copyWith(color: AppColors.textOnDark),
                ),
                Text(
                  '${snapshot.nextName} ${snapshot.nextTime}',
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: AppColors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          Text(
            '$minutes min lagi\n${snapshot.hijriDate}',
            textAlign: TextAlign.end,
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _PrayerMessage extends StatelessWidget {
  const _PrayerMessage({
    required this.text,
    required this.onTap,
    this.icon = Icons.refresh_outlined,
  });
  final String text;
  final VoidCallback onTap;
  final IconData icon;

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
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
          Icon(icon),
        ],
      ),
    ),
  );
}
