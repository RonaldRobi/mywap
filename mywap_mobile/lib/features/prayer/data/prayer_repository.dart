import 'package:dio/dio.dart';
import 'package:geolocator/geolocator.dart';

import '../../../shared/l10n/l10n.dart';

/// Malaysia local time (MYT, UTC+8), used for scene selection and countdown.
DateTime malaysiaNow() =>
    DateTime.now().toUtc().add(const Duration(hours: 8));

class PrayerLocation {
  const PrayerLocation({
    required this.name,
    required this.latitude,
    required this.longitude,
    this.useDevice = false,
  });

  final String name;
  final double latitude;
  final double longitude;
  final bool useDevice;
}

const prayerLocations = <PrayerLocation>[
  PrayerLocation(name: 'Kuala Lumpur', latitude: 3.1390, longitude: 101.6869),
  PrayerLocation(name: 'Shah Alam', latitude: 3.0733, longitude: 101.5185),
  PrayerLocation(name: 'Putrajaya', latitude: 2.9264, longitude: 101.6964),
  PrayerLocation(name: 'Johor Bahru', latitude: 1.4927, longitude: 103.7414),
  PrayerLocation(name: 'George Town', latitude: 5.4141, longitude: 100.3288),
  PrayerLocation(name: 'Ipoh', latitude: 4.5975, longitude: 101.0901),
  PrayerLocation(name: 'Alor Setar', latitude: 6.1184, longitude: 100.3660),
  PrayerLocation(name: 'Kota Bharu', latitude: 6.1254, longitude: 102.2381),
  PrayerLocation(name: 'Kuala Terengganu', latitude: 5.3302, longitude: 103.1408),
  PrayerLocation(name: 'Kuantan', latitude: 3.8077, longitude: 103.3260),
  PrayerLocation(name: 'Seremban', latitude: 2.7297, longitude: 101.9383),
  PrayerLocation(name: 'Melaka', latitude: 2.1896, longitude: 102.2501),
  PrayerLocation(name: 'Kuching', latitude: 1.5533, longitude: 110.3592),
  PrayerLocation(name: 'Kota Kinabalu', latitude: 5.9804, longitude: 116.0735),
];

class PrayerSnapshot {
  const PrayerSnapshot({
    required this.nextName,
    required this.nextTime,
    required this.nextAt,
    required this.hijriDate,
    required this.locationName,
    required this.sky,
    required this.scene,
    required this.prayerTimes,
  });

  final String nextName;
  final String nextTime;
  final DateTime nextAt;
  final String hijriDate;
  final String locationName;
  final PrayerSky sky;
  final PrayerScene scene;
  final Map<String, String> prayerTimes;
}

enum PrayerSky { day, twilight, night }

enum PrayerScene { subuh, day, asar, maghrib, isyak }

class PrayerPermissionDenied implements Exception {
  const PrayerPermissionDenied({required this.permanent});

  final bool permanent;
}

class PrayerLocationDisabled implements Exception {
  const PrayerLocationDisabled();
}

class PrayerGpsUnavailable implements Exception {
  const PrayerGpsUnavailable();
}

class PrayerRepository {
  PrayerRepository(this._client);

  final Dio _client;

  Future<PrayerSnapshot> fetch({PrayerLocation? manualLocation}) async {
    final now = malaysiaNow();
    final double latitude;
    final double longitude;
    final String locationName;

    if (manualLocation != null) {
      latitude = manualLocation.latitude;
      longitude = manualLocation.longitude;
      locationName = manualLocation.name;
    } else {
      final position = await _devicePosition();
      latitude = position.latitude;
      longitude = position.longitude;
      locationName = await _reverseGeocode(latitude, longitude);
    }

    final response = await _client.get(
      'https://api.aladhan.com/v1/timings',
      queryParameters: {
        'latitude': latitude,
        'longitude': longitude,
        'method': 17,
        'date': _apiDate(now),
      },
    );
    final data = response.data['data'] as Map<String, dynamic>;
    final timings = (data['timings'] as Map).cast<String, dynamic>();
    final date = (data['date'] as Map?)?.cast<String, dynamic>();
    final hijri = (date?['hijri'] as Map?)?.cast<String, dynamic>();
    final prayerTimes = <String, String>{
      'Subuh': _rawTime(timings['Fajr']),
      'Zohor': _rawTime(timings['Dhuhr']),
      'Asar': _rawTime(timings['Asr']),
      'Maghrib': _rawTime(timings['Maghrib']),
      'Isyak': _rawTime(timings['Isha']),
    };
    const order = [
      ('Subuh', 'Fajr'),
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
      final candidate = DateTime.utc(
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
      next = DateTime.utc(
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
    final isha = _parseTime(timings['Isha'], now);
    final sky = _skyFor(now, sunrise, sunset, isha);
    final asr = _parseTime(timings['Asr'], now);
    final scene = _sceneFor(now, sunrise, asr, sunset, isha);
    return PrayerSnapshot(
      nextName: nextName,
      nextTime: nextTime,
      nextAt: next,
      hijriDate:
          '${hijri?['day'] ?? ''} ${hijri?['month']?['en'] ?? ''} ${hijri?['year'] ?? ''}'
              .trim(),
      locationName: locationName,
      sky: sky,
      scene: scene,
      prayerTimes: prayerTimes,
    );
  }

  Future<Position> _devicePosition() async {
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
    try {
      return await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
          timeLimit: Duration(seconds: 15),
        ),
      );
    } catch (_) {
      throw const PrayerGpsUnavailable();
    }
  }

  Future<String> _reverseGeocode(double latitude, double longitude) async {
    try {
      final response = await _client.get(
        'https://api.bigdatacloud.net/data/reverse-geocode-client',
        queryParameters: {
          'latitude': latitude,
          'longitude': longitude,
          'localityLanguage': currentAppLocalizations.localeName,
        },
      );
      final data = response.data;
      if (data is! Map) return currentAppLocalizations.prayerLocationUnknown;
      String pick(List<String> keys) {
        for (final key in keys) {
          final value = data[key];
          if (value is String && value.trim().isNotEmpty) return value.trim();
        }
        return '';
      }

      final city = pick(['city', 'locality']);
      final state = pick(['principalSubdivision']);
      if (city.isEmpty) {
        return state.isEmpty
            ? currentAppLocalizations.prayerLocationUnknown
            : state;
      }
      return state.isEmpty || city.toLowerCase() == state.toLowerCase()
          ? city
          : '$city, $state';
    } catch (_) {
      return currentAppLocalizations.prayerLocationUnknown;
    }
  }

  String _apiDate(DateTime value) {
    String pad(int v) => v.toString().padLeft(2, '0');
    return '${pad(value.day)}-${pad(value.month)}-${value.year}';
  }

  String _rawTime(dynamic value) => value.toString().split(' ').first;

  PrayerScene _sceneFor(
    DateTime now,
    DateTime? sunrise,
    DateTime? asr,
    DateTime? sunset,
    DateTime? isha,
  ) {
    if (sunrise != null && now.isBefore(sunrise)) return PrayerScene.subuh;
    if (asr != null && now.isBefore(asr)) return PrayerScene.day;
    if (sunset != null && now.isBefore(sunset)) return PrayerScene.asar;
    if (isha != null && now.isBefore(isha)) return PrayerScene.maghrib;
    return PrayerScene.isyak;
  }

  PrayerSky _skyFor(
    DateTime now,
    DateTime? sunrise,
    DateTime? sunset,
    DateTime? isha,
  ) {
    if (sunrise != null &&
        sunset != null &&
        now.isAfter(sunrise) &&
        now.isBefore(sunset)) {
      return PrayerSky.day;
    }
    if (sunset != null &&
        isha != null &&
        now.isAfter(sunset) &&
        now.isBefore(isha)) {
      return PrayerSky.twilight;
    }
    return PrayerSky.night;
  }

  DateTime? _parseTime(dynamic value, DateTime now) {
    final parts = value.toString().split(' ').first.split(':');
    if (parts.length != 2) return null;
    return DateTime.utc(
      now.year,
      now.month,
      now.day,
      int.tryParse(parts[0]) ?? 0,
      int.tryParse(parts[1]) ?? 0,
    );
  }
}
