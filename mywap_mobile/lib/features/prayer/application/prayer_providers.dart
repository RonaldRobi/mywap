import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/prayer_repository.dart';

final prayerRepositoryProvider = Provider<PrayerRepository>(
  (ref) => PrayerRepository(Dio()),
);

/// Null means GPS. A selected city bypasses GPS entirely.
final prayerManualLocationProvider = StateProvider<PrayerLocation?>(
  (ref) => null,
);

final prayerProvider = FutureProvider<PrayerSnapshot>((ref) {
  final manualLocation = ref.watch(prayerManualLocationProvider);
  return ref
      .watch(prayerRepositoryProvider)
      .fetch(manualLocation: manualLocation);
});
