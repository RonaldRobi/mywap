import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../../core/network/providers.dart';
import '../../../../shared/theme/app_colors.dart';
import '../../application/auth_controller.dart';

/// Tawarkan log masuk biometrik selepas pengguna berjaya log masuk secara
/// manual (password / OTP) — hanya sekali sahaja sehingga mereka pilih sendiri
/// di halaman Profil.
///
/// Dialog diletakkan di atas navigator akar (bukan bergantung pada page login)
/// supaya ia tidak hilang apabila go_router mengubah hala ke /dashboard
/// sebaik sahaja sesi menjadi sah.
class BiometricPrompt {
  static const String promptSeenKey = 'biometric_prompt_seen';

  static Future<void> offerEnable(
    BuildContext context,
    WidgetRef ref,
  ) async {
    final navigator = Navigator.of(context, rootNavigator: true);
    final biometric = ref.read(biometricServiceProvider);

    if (!await biometric.isDeviceSupported()) return;
    if (await ref.read(tokenStorageProvider).isBiometricEnabled()) return;

    final prefs = await SharedPreferences.getInstance();
    if (prefs.getBool(promptSeenKey) ?? false) return;

    final hasFaceId = await biometric.hasFaceId();
    if (!navigator.mounted) return;

    final enable = await navigator.push<bool>(
      RawDialogRoute<bool>(
        pageBuilder: (dialogContext, _, __) => AlertDialog(
          icon: Icon(
            hasFaceId ? Icons.face_retouching_natural : Icons.fingerprint,
            color: AppColors.movementGreen,
            size: 40,
          ),
          title: Text(
            hasFaceId ? 'Aktifkan Face ID?' : 'Aktifkan Cap Jari?',
          ),
          content: const Text(
            'Log masuk lebih pantas pada masa akan datang menggunakan '
            'biometrik peranti anda.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(false),
              child: const Text('Nanti'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(dialogContext).pop(true),
              child: const Text('Dayakan'),
            ),
          ],
        ),
        barrierDismissible: false,
        barrierLabel: MaterialLocalizations.of(
          navigator.context,
        ).modalBarrierDismissLabel,
      ),
    );

    // Tandakan sebagai "sudah ditanya" supaya tidak diganggu setiap kali
    // log masuk — pengguna masih boleh dayakan/lumpuhkan di halaman Profil.
    await prefs.setBool(promptSeenKey, true);

    if (enable != true) return;
    await ref.read(authControllerProvider.notifier).setBiometricEnabled(true);
  }
}
