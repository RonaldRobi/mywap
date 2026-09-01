import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persists the Sanctum bearer token in the platform secure storage.
class TokenStorage {
  TokenStorage([FlutterSecureStorage? storage])
      : _storage = storage ?? const FlutterSecureStorage();

  static const String _tokenKey = 'auth_token';
  static const String _biometricEnabledKey = 'biometric_enabled';

  final FlutterSecureStorage _storage;

  Future<String?> read() => _storage.read(key: _tokenKey);

  Future<void> write(String token) => _storage.write(key: _tokenKey, value: token);

  Future<void> delete() => _storage.delete(key: _tokenKey);

  /// Sama ada pengguna semasa telah mendayakan buka kunci biometrik
  /// (Face ID / Touch ID / cap jari) untuk akaun ini.
  Future<bool> isBiometricEnabled() async {
    final value = await _storage.read(key: _biometricEnabledKey);
    return value == 'true';
  }

  Future<void> setBiometricEnabled(bool enabled) => _storage.write(
        key: _biometricEnabledKey,
        value: enabled ? 'true' : 'false',
      );
}
