import 'package:flutter_test/flutter_test.dart';
import 'package:local_auth/local_auth.dart';
import 'package:mywap_mobile/core/biometric/biometric_service.dart';

void main() {
  group('biometricLabelFor', () {
    test('iOS tanpa biometrik didaftarkan -> Face ID', () {
      // Regresi: kod lama memaparkan "Cap Jari" pada iPhone yang belum
      // mendaftarkan Face ID.
      expect(
        biometricLabelFor(available: const [], isIOS: true),
        BiometricLabel.faceId,
      );
    });

    test('Android tanpa biometrik didaftarkan -> cap jari', () {
      expect(
        biometricLabelFor(available: const [], isIOS: false),
        BiometricLabel.fingerprint,
      );
    });

    test('Face ID didaftarkan -> Face ID', () {
      expect(
        biometricLabelFor(available: const [BiometricType.face], isIOS: true),
        BiometricLabel.faceId,
      );
    });

    test('Touch ID didaftarkan pada iPhone -> cap jari', () {
      expect(
        biometricLabelFor(
          available: const [BiometricType.fingerprint],
          isIOS: true,
        ),
        BiometricLabel.fingerprint,
      );
    });

    test('cap jari pada Android -> cap jari', () {
      expect(
        biometricLabelFor(
          available: const [BiometricType.fingerprint],
          isIOS: false,
        ),
        BiometricLabel.fingerprint,
      );
    });
  });
}
