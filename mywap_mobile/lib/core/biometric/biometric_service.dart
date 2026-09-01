import 'package:local_auth/local_auth.dart';

/// Thin wrapper around `local_auth` — Face ID (iOS) / fingerprint & face
/// unlock (Android). Biometrics only gate access to an *already existing*
/// session (the Sanctum token stays in secure storage); we never store the
/// password itself, so there is nothing sensitive for local_auth to unlock
/// beyond "let the cached session back in".
class BiometricService {
  BiometricService([LocalAuthentication? auth])
      : _auth = auth ?? LocalAuthentication();

  final LocalAuthentication _auth;

  /// Sama ada peranti ini menyokong sebarang kaedah biometrik/PIN peranti.
  Future<bool> isDeviceSupported() async {
    try {
      final canCheck = await _auth.canCheckBiometrics;
      final supported = await _auth.isDeviceSupported();
      return canCheck || supported;
    } catch (_) {
      return false;
    }
  }

  /// Jenis biometrik yang tersedia (untuk memilih label/ikon yang sesuai —
  /// "Face ID" di iOS, "Cap Jari" di Android).
  Future<List<BiometricType>> availableBiometrics() async {
    try {
      return await _auth.getAvailableBiometrics();
    } catch (_) {
      return const [];
    }
  }

  Future<bool> hasFaceId() async =>
      (await availableBiometrics()).contains(BiometricType.face);

  /// Minta pengesahan biometrik peranti. Kembalikan `true` jika berjaya.
  Future<bool> authenticate({String? reason}) async {
    try {
      return await _auth.authenticate(
        localizedReason: reason ?? 'Sahkan identiti anda untuk log masuk',
        options: const AuthenticationOptions(
          biometricOnly: false,
          stickyAuth: true,
          useErrorDialogs: true,
        ),
      );
    } catch (_) {
      return false;
    }
  }
}
