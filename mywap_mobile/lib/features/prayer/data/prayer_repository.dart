import 'package:dio/dio.dart';
import 'package:geolocator/geolocator.dart';

class PrayerSnapshot {
  const PrayerSnapshot({
    required this.nextName,
    required this.nextTime,
    required this.remaining,
    required this.hijriDate,
    required this.isDay,
  });

  final String nextName;
  final String nextTime;
  final Duration remaining;
  final String hijriDate;
  final bool isDay;
}

class PrayerPermissionDenied implements Exception {
  const PrayerPermissionDenied({required this.permanent});

  final bool permanent;
}

class PrayerLocationDisabled implements Exception {
  const PrayerLocationDisabled();
}

class PrayerRepository {
  PrayerRepository(this._client);

  final Dio _client;

  Future<PrayerSnapshot> fetch() async {
    final permission = await Geolocator.checkPermission();
    final granted =
        permission == LocationPermission.always ||
                permission == LocationPermission.whileInUse
            ? permission
            : await Geolocator.requestPermission();
    if (granted != LocationPermission.always &&
        granted != LocationPermission.whileInUse) {
      throw PrayerPermissionDenied(
        permanent: granted == LocationPermission.deniedForever,
      );
    }
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const PrayerLocationDisabled();
    }

    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.medium,
        timeLimit: Duration(seconds: 15),
      ),
    );
    final response = await _client.get(
      'https://api.aladhan.com/v1/timings',
      queryParameters: {
        'latitude': position.latitude,
        'longitude': position.longitude,
        'method': 17,
      },
    );
    final data = response.data['data'] as Map<String, dynamic>;
    final timings = (data['timings'] as Map).cast<String, dynamic>();
    final date = (data['date'] as Map?)?.cast<String, dynamic>();
    final hijri = (date?['hijri'] as Map?)?.cast<String, dynamic>();
    final now = DateTime.now();
    const order = [
      ('Subuh', 'Fajr'),
      ('Syuruk', 'Sunrise'),
      ('Zohor', 'Dhuhr'),
      ('Asar', 'Asr'),
      ('Maghrib', 'Maghrib'),
      ('Isyak', 'Isha'),
    ];
    DateTime? next;
    String nextName = 'Subuh';
    String nextTime = '--:--';
    for (final entry in order) {
      final raw = (timings[entry.$2] ?? '').toString().split(' ').first;
      final parts = raw.split(':');
      if (parts.length != 2) continue;
      final candidate = DateTime(
        now.year,
        now.month,
        now.day,
        int.tryParse(parts[0]) ?? 0,
        int.tryParse(parts[1]) ?? 0,
      );
      if (candidate.isAfter(now)) {
        next = candidate;
        nextName = entry.$1;
        nextTime = raw;
        break;
      }
    }
    if (next == null) {
      final raw = (timings['Fajr'] ?? '00:00').toString().split(' ').first;
      final parts = raw.split(':');
      next = DateTime(
        now.year,
        now.month,
        now.day + 1,
        int.tryParse(parts[0]) ?? 0,
        int.tryParse(parts[1]) ?? 0,
      );
      nextTime = raw;
    }
    final sunrise = _parseTime(timings['Sunrise'], now);
    final sunset = _parseTime(timings['Sunset'], now);
    final isDay =
        sunrise != null &&
        sunset != null &&
        now.isAfter(sunrise) &&
        now.isBefore(sunset);
    return PrayerSnapshot(
      nextName: nextName,
      nextTime: nextTime,
      remaining: next.difference(now),
      hijriDate:
          '${hijri?['day'] ?? ''} ${hijri?['month']?['en'] ?? ''} ${hijri?['year'] ?? ''}'
              .trim(),
      isDay: isDay,
    );
  }

  DateTime? _parseTime(dynamic value, DateTime now) {
    final parts = value.toString().split(' ').first.split(':');
    if (parts.length != 2) return null;
    return DateTime(
      now.year,
      now.month,
      now.day,
      int.tryParse(parts[0]) ?? 0,
      int.tryParse(parts[1]) ?? 0,
    );
  }
}
