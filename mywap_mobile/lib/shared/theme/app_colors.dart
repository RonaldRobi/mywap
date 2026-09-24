import 'package:flutter/material.dart';

/// Design system colour tokens.
///
/// Redesign goals (audit fasa 1):
/// - Sasaran pengguna: warga emas & kurang mahir IT. Setiap pasangan
///   teks/latar dalam palet ini disahkan >= 4.5:1 (WCAG AA teks normal).
/// - Hijau jenama dikekalkan tetapi dinaikkan ketepuan (S39% -> S72%) supaya
///   tidak kelihatan kusam/tentera.
/// - Neutral ditukar dari hue biru-sejuk (220) ke hue hijau-hangat (~95)
///   supaya terasa komuniti, bukan fintech.
/// - Aksen amber `accent` menggantikan palet pelangi Tailwind yang dulunya
///   hardcoded dalam dashboard.
///
/// JANGAN hardcode `Color(0x...)` di luar fail ini — gunakan token.
abstract final class AppColors {
  // ---------------------------------------------------------------------
  // Jenama
  // ---------------------------------------------------------------------

  /// Teks utama & permukaan paling gelap. Hijau-navy hangat (bukan biru).
  static const Color movementNavy = Color(0xFF12241C);

  /// Hijau gelap — hujung gelap gradien hero, permukaan tekan.
  static const Color movementDarkGreen = Color(0xFF0E5C2E);

  /// Hijau utama jenama. Kontras 5.41:1 atas putih (AA).
  static const Color movementGreen = Color(0xFF147A3D);

  /// Hijau lembut — HIASAN SAHAJA (2.75:1 atas putih).
  /// Jangan guna untuk teks atau ikon yang membawa makna.
  static const Color movementSoftGreen = Color(0xFF4FAE73);

  static const Color movementOffWhite = Color(0xFFF3F6EF);

  // ---------------------------------------------------------------------
  // Permukaan
  // ---------------------------------------------------------------------

  static const Color white = Color(0xFFFFFFFF);

  /// Latar skrin. Hue hangat (90) — dulunya biru-kelabu #F7F8FA (hue 220).
  static const Color pageBackground = Color(0xFFF7F9F5);
  static const Color background = pageBackground;
  static const Color surface = white;
  static const Color surfaceMuted = Color(0xFFF1F4EF);

  /// Permukaan hijau lembut untuk blok/seksyen.
  static const Color softGreenSurface = Color(0xFFEDF4EC);

  /// Latar terpilih (cth. petunjuk navigasi, cip aktif).
  static const Color paleGreen = Color(0xFFDFF1E4);

  /// Latar bulatan ikon. Ganti palet pelangi Tailwind dalam dashboard.
  static const Color greenTint = Color(0xFFE7F5EA);

  /// Latar lembut untuk elemen beraksen amber.
  static const Color accentTint = Color(0xFFFCF3E8);

  // ---------------------------------------------------------------------
  // Teks
  // ---------------------------------------------------------------------

  static const Color textPrimary = movementNavy;

  /// Teks sekunder. Dulunya #6B7684 yang GAGAL AA (4.34:1) atas latar
  /// halaman walaupun digunakan 172 kali. Kini 7.18:1.
  static const Color textSecondary = Color(0xFF4A5560);

  /// Teks tertier. Dulunya #9AA5B1 yang GAGAL teruk (2.36:1). Kini 5.76:1.
  static const Color textTertiary = Color(0xFF59636F);

  static const Color textOnDark = Color(0xFFF3F6EF);

  // ---------------------------------------------------------------------
  // Garisan & sempadan
  // ---------------------------------------------------------------------

  /// Dulunya #E7EAEE — hampir halimunan (1.21:1). Kini 1.46:1.
  static const Color divider = Color(0xFFCFD8CB);

  static const Color inputBorder = Color(0xFFC2CCBF);

  // ---------------------------------------------------------------------
  // Semantik
  // ---------------------------------------------------------------------

  /// Dulunya #E0483F (4.06:1, GAGAL). Kini 6.83:1.
  static const Color error = Color(0xFFB02020);

  /// Dulunya #B4780C (3.73:1, GAGAL). Kini 6.61:1.
  static const Color warning = Color(0xFF8F4B06);

  /// Sengaja sama dengan [movementGreen] — satu hijau jenama sahaja.
  static const Color success = movementGreen;

  /// Aksen amber untuk CTA sekunder, lencana keutamaan dan penekanan.
  /// Inilah pengimbang haba kepada hijau; menghalang rupa "satu warna tech".
  static const Color accent = Color(0xFF9A4A07);

  /// Latar sepia untuk pembaca PDF/dokumen — mengurangkan silau berbanding
  /// putih tulen untuk bacaan panjang.
  static const Color readingSurface = Color(0xFFF2ECE0);

  // ---------------------------------------------------------------------
  // Aksen kategori pintasan (grid dashboard)
  // ---------------------------------------------------------------------
  //
  // NOTA: 10 warna ini adalah sisa palet Tailwind yang sebelum ini
  // hardcoded terus dalam `member_dashboard_screen.dart`. Ia dipindahkan ke
  // sini semata-mata untuk membuang hardcode (fasa 1). Fasa 3 akan
  // meruntuhkannya kepada keluarga hijau + amber sahaja supaya dashboard
  // tidak kelihatan seperti app fintech.

  static const Color shortcutFee = Color(0xFF059669);
  static const Color shortcutBooking = Color(0xFFD97706);
  static const Color shortcutNews = Color(0xFF4F46E5);
  static const Color shortcutInfaq = Color(0xFFE11D48);
  static const Color shortcutCard = Color(0xFF2563EB);
  static const Color shortcutUsrah = Color(0xFF7C3AED);
  static const Color shortcutShop = Color(0xFFEA580C);
  static const Color shortcutReferral = Color(0xFF0D9488);
  static const Color shortcutLibrary = Color(0xFF0F766E);
  static const Color shortcutPoll = Color(0xFF9D174D);

  // ---------------------------------------------------------------------
  // Lapisan & overlay
  // ---------------------------------------------------------------------

  /// Tint berfros untuk pengepala hero & helaian.
  static const Color glassTint = Color(0x1412241C);

  /// Scrim tunggal untuk teks atas imej. Dulunya 4 kelegapan berbeza
  /// (0x55/0x66/0x77 atas navy + 0x66 atas hitam) untuk kesan yang sama.
  static const Color scrim = Color(0x9912241C);

  /// Scrim lebih ringan untuk overlay kecil / penjuru imej.
  static const Color scrimLight = Color(0x5912241C);

  // ---------------------------------------------------------------------
  // Gradien
  // ---------------------------------------------------------------------

  /// Gradien hero jenama — kad ahli, pengepala, FAB.
  static const List<Color> heroGradient = [
    movementDarkGreen,
    movementGreen,
  ];

  /// Gradien mint lembut untuk permukaan sorotan sekunder.
  static const List<Color> mintGradient = [
    Color(0xFF7FC99A),
    movementSoftGreen,
  ];
}
