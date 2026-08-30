import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';

/// Konfigurasi loading screen (GIF + gradient) untuk apps Flutter.
class LoadingScreenConfig {
  const LoadingScreenConfig({
    required this.enabled,
    required this.backgroundStart,
    required this.backgroundEnd,
    required this.durationMs,
    this.gifUrl,
  });

  factory LoadingScreenConfig.fromJson(Map<String, dynamic> json) =>
      LoadingScreenConfig(
        enabled: json['enabled'] as bool? ?? true,
        gifUrl: json['gif_url'] as String?,
        backgroundStart:
            json['background_start'] as String? ?? '#071525',
        backgroundEnd: json['background_end'] as String? ?? '#2F6B32',
        durationMs: (json['duration_ms'] as num?)?.toInt() ?? 2500,
      );

  final bool enabled;
  final String? gifUrl;
  final String backgroundStart;
  final String backgroundEnd;
  final int durationMs;

  Map<String, dynamic> toJson() => {
    'enabled': enabled,
    'gif_url': gifUrl,
    'background_start': backgroundStart,
    'background_end': backgroundEnd,
    'duration_ms': durationMs,
  };
}

class LoadingScreenRepository {
  LoadingScreenRepository(this._client);

  static const _cacheKey = 'loading_screen_config_v1';

  final ApiClient _client;

  /// Ambil konfigurasi terkini daripada API dan kemas kini cache tempatan.
  Future<LoadingScreenConfig> fetchConfig() async {
    final response = await _client.get(ApiPaths.appConfig);
    if (response is Map && response['loading_screen'] is Map) {
      final config = LoadingScreenConfig.fromJson(
        (response['loading_screen'] as Map).cast<String, dynamic>(),
      );
      await _cache(config);
      return config;
    }
    return _fallback();
  }

  /// Konfigurasi yang dicache dari sesi lepas — papar serta-merta semasa
  /// permulaan sebelum respons API tiba.
  Future<LoadingScreenConfig?> loadCached() async {
    try {
      final raw = (await SharedPreferences.getInstance()).getString(_cacheKey);
      if (raw == null || raw.isEmpty) return null;
      return LoadingScreenConfig.fromJson(
        jsonDecode(raw) as Map<String, dynamic>,
      );
    } catch (_) {
      return null;
    }
  }

  LoadingScreenConfig _fallback() => const LoadingScreenConfig(
    enabled: true,
    backgroundStart: '#071525',
    backgroundEnd: '#2F6B32',
    durationMs: 2500,
  );

  Future<void> _cache(LoadingScreenConfig config) async {
    try {
      await (await SharedPreferences.getInstance()).setString(
        _cacheKey,
        jsonEncode(config.toJson()),
      );
    } catch (_) {
      // Kegagalan cache tidak sepatutnya menghalang loading screen.
    }
  }
}
