import 'package:flutter/foundation.dart';
import 'package:local_auth/local_auth.dart';

/// Kaedah biometrik yang patut dipaparkan kepada pengguna.
enum BiometricLabel { faceId, fingerprint }

/// Keputusan label biometrik — fungsi tulen supaya boleh diuji tanpa
/// platform channel.
///
/// `availableBiometrics()` hanya melaporkan biometrik yang **telah
/// didaftarkan**. Jika pengguna belum daftar apa-apa, ia pulangkan senarai
/// kosong — dan kod lama tersalah jatuh ke label "Cap Jari" pada iPhone.
///
/// Heuristik: Face ID jika Face ID didaftarkan; cap jari jika cap jari
/// didaftarkan; jika tiada apa-apa didaftarkan, default kepada Face ID di
/// iOS kerana hampir semua iPhone moden menggunakan Face ID.
BiometricLabel biometricLabelFor({
  required List<BiometricType> available,
  required bool isIOS,
}) {
  if (available.contains(BiometricType.face)) return BiometricLabel.faceId;
  if (available.contains(BiometricType.fingerprint)) {
    return BiometricLabel.fingerprint;
  }
  return isIOS ? BiometricLabel.faceId : BiometricLabel.fingerprint;
}

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

  /// Sama ada label/ikon "Face ID" patut digunakan. Lihat [biometricLabelFor].
  Future<bool> hasFaceId() async {
    return biometricLabelFor(
          available: await availableBiometrics(),
          isIOS: defaultTargetPlatform == TargetPlatform.iOS,
        ) ==
        BiometricLabel.faceId;
  }

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
