import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/prayer_repository.dart';

final prayerRepositoryProvider = Provider<PrayerRepository>(
  (ref) => PrayerRepository(Dio()),
);
final prayerProvider = FutureProvider<PrayerSnapshot>(
  (ref) => ref.watch(prayerRepositoryProvider).fetch(),
);
