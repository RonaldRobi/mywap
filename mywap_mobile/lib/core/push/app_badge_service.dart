import 'package:app_badge_plus/app_badge_plus.dart';

/// Menyelaraskan badge nombor pada ikon app (home screen) dengan bilangan
/// notifikasi belum dibaca.
///
/// Had platform:
/// - iOS: nombor sentiasa disokong.
/// - Android: nombor hanya pada launcher yang menyokong (Samsung, Xiaomi,
///   Huawei, Oppo, Vivo, Sony, dll). Launcher stok (Pixel) cuma papar titik.
///   Bila app tertutup, badge tetap dikemas kini oleh payload push dari server.
class AppBadgeService {
  AppBadgeService._();

  static bool? _supported;

  /// Set badge ikon kepada [count] (0 = buang badge). Gagal senyap supaya
  /// tidak pernah menjejaskan aliran notifikasi.
  static Future<void> setUnread(int count) async {
    final value = count < 0 ? 0 : count;
    try {
      _supported ??= await AppBadgePlus.isSupported();
      if (_supported != true) return;
      await AppBadgePlus.updateBadge(value);
    } catch (_) {
      // Badge tidak disokong pada peranti/launcher ini.
    }
  }

  /// Buang badge (cth. selepas log keluar).
  static Future<void> clear() => setUnread(0);
}
