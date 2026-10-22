import 'package:flutter/material.dart';

import '../../../../shared/l10n/l10n.dart';

/// Susun atur kad keahlian — port 1:1 daripada design web.
///
/// Sumber rujukan (jangan ubah):
/// `resources/js/Components/MemberCard/layout.js`.
///
/// Semua koordinat dalam piksel kanvas design 638 × 1004 (nisbah kad TNG
/// 54 × 85.6 mm, menegak). Widget kad meniskal keseluruhan kanvas kepada
/// saiz sebenar peranti, jadi angka di sini kekal sama dengan web.
///
/// Logik auto-fit teks (`fitSingleLine`/`wrap`) juga dport — guna
/// `TextPainter` untuk mengukur lebar, sama seperti `measureText()` di web.
abstract final class CardLayout {
  static const double cardW = 638;
  static const double cardH = 1004;

  // Foto ahli (bulatan).
  static const double photoCx = 320;
  static const double photoCy = 428;
  static const double photoR = 130;

  // Nama — 1 atau 2 baris, auto-shrink.
  static const double nameCenterX = 319;
  static const double nameMaxWidth = 520;
  static const double nameLine1 = 600;
  static const double nameLineHeight = 36;
  static const double nameFontSize = 30;
  static const double nameMinFontSize = 20;
  static const FontWeight nameFontWeight = FontWeight.w700;

  // Kotak organisasi.
  static const double orgBoxX = 185;
  static const double orgBoxW = 272;
  static const double orgBoxH = 40;
  static const double orgBoxRadius = 5;
  static const double orgPaddingX = 14;
  static const double orgBaselineOffset = 27;
  static const double orgFontSize = 28;
  static const double orgMinFontSize = 12;
  static const FontWeight orgFontWeight = FontWeight.w800;
  static const double orgLetterSpacing = 1;

  // Nombor ahli.
  static const double memberNoCenterX = 319;
  static const double memberNoFontSize = 20;
  static const double memberNoMinFontSize = 12;
  static const double memberNoMaxWidth = 400;
  static const FontWeight memberNoFontWeight = FontWeight.w700;
  static const double memberNoLetterSpacing = 1;

  // Jarak antara blok.
  static const double nameToOrg = 50;
  static const double orgToMemberNo = 50;

  // Kod QR.
  static const double qrX = 480;
  static const double qrY = 845;
  static const double qrSize = 95;
  static const double qrPadding = 3;
  static const double qrRadius = 3;

  // -------------------------------------------------------------------
  // Pengukuran teks
  // -------------------------------------------------------------------

  static TextPainter _painter(
    String text,
    double size,
    FontWeight weight, {
    double letterSpacing = 0,
  }) {
    return TextPainter(
      text: TextSpan(
        text: text,
        style: TextStyle(
          fontSize: size,
          fontWeight: weight,
          letterSpacing: letterSpacing,
        ),
      ),
      textDirection: TextDirection.ltr,
      maxLines: 1,
    )..layout();
  }

  static double _measure(
    String text,
    double size,
    FontWeight weight, {
    double letterSpacing = 0,
  }) => _painter(text, size, weight, letterSpacing: letterSpacing).width;

  /// Jarak dari bahagian atas kotak teks ke baseline (ascent) — diperlukan
  /// kerana koordinat SVG menggunakan baseline, manakala `Positioned` Flutter
  /// menggunakan bucu atas.
  static double _ascent(TextPainter painter) =>
      painter.computeDistanceToActualBaseline(TextBaseline.alphabetic);
  static List<String> _wrap(
    List<String> words,
    double size,
    FontWeight weight,
    double maxWidth,
  ) {
    final lines = <String>[];
    var current = '';
    for (final word in words) {
      final candidate = current.isEmpty ? word : '$current $word';
      if (current.isEmpty || _measure(candidate, size, weight) <= maxWidth) {
        current = candidate;
      } else {
        lines.add(current);
        current = word;
      }
    }
    if (current.isNotEmpty) lines.add(current);
    return lines;
  }

  /// Saiz font terbesar (≤ [fontSize]) yang muat satu baris dalam [maxWidth].
  static double _fitSingleLine(
    String text, {
    required double fontSize,
    required double minFontSize,
    required FontWeight weight,
    required double maxWidth,
    double letterSpacing = 0,
  }) {
    var size = fontSize;
    while (size > minFontSize &&
        _measure(text, size, weight, letterSpacing: letterSpacing) > maxWidth) {
      size -= 1;
    }
    return size;
  }

  // -------------------------------------------------------------------
  // Susun atur
  // -------------------------------------------------------------------

  static CardNameLayout layoutName(String? raw) {
    final name = (raw ?? '').trim().toUpperCase();
    final safeName = name.isEmpty ? '-' : name;
    final words =
        safeName.split(RegExp(r'\s+')).where((w) => w.isNotEmpty).toList();

    var size = nameFontSize;
    var lines = _wrap(words, size, nameFontWeight, nameMaxWidth);

    while (size > nameMinFontSize &&
        (lines.length > 2 ||
            lines.any(
              (l) => _measure(l, size, nameFontWeight) > nameMaxWidth,
            ))) {
      size -= 1;
      lines = _wrap(words, size, nameFontWeight, nameMaxWidth);
    }

    if (lines.length > 2) {
      // Masih terlalu panjang pada saiz minimum: gabungkan baki ke baris 2.
      lines = [lines.first, lines.sublist(1).join(' ')];
    }
    if (lines.isEmpty) lines = [safeName];

    final baselines =
        lines.length == 1
            ? <double>[nameLine1]
            : <double>[nameLine1, nameLine1 + nameLineHeight];

    final layoutLines = <CardNameLine>[];
    for (var i = 0; i < lines.length; i++) {
      final painter = _painter(lines[i], size, nameFontWeight);
      layoutLines.add(
        CardNameLine(
          text: lines[i],
          fontSize: size,
          top: baselines[i] - _ascent(painter),
        ),
      );
    }

    return CardNameLayout(
      fontSize: size,
      lines: layoutLines,
      bottom: baselines.last + size * 0.22,
    );
  }

  static CardOrgLayout layoutOrganization(String? raw, double nameBottom) {
    final text = (raw ?? '').trim().toUpperCase();
    final safeText =
        text.isEmpty ? currentAppLocalizations.orgFallbackUpper : text;
    final size = _fitSingleLine(
      safeText,
      fontSize: orgFontSize,
      minFontSize: orgMinFontSize,
      weight: orgFontWeight,
      maxWidth: orgBoxW - orgPaddingX * 2,
      letterSpacing: orgLetterSpacing,
    );
    final boxTop = (nameBottom + nameToOrg).roundToDouble();
    final painter = _painter(
      safeText,
      size,
      orgFontWeight,
      letterSpacing: orgLetterSpacing,
    );

    return CardOrgLayout(
      text: safeText,
      fontSize: size,
      boxTop: boxTop,
      textTop: boxTop + orgBaselineOffset - _ascent(painter),
    );
  }

  static CardMemberNoLayout layoutMemberNo(String? raw, double orgBoxTop) {
    final text = (raw ?? '').trim();
    final safeText = text.isEmpty ? '-' : text;
    final size = _fitSingleLine(
      safeText,
      fontSize: memberNoFontSize,
      minFontSize: memberNoMinFontSize,
      weight: memberNoFontWeight,
      maxWidth: memberNoMaxWidth,
      letterSpacing: memberNoLetterSpacing,
    );
    final blockTop = orgBoxTop + orgBoxH + orgToMemberNo;
    final painter = _painter(
      safeText,
      size,
      memberNoFontWeight,
      letterSpacing: memberNoLetterSpacing,
    );

    return CardMemberNoLayout(
      text: safeText,
      fontSize: size,
      top: blockTop + size * 0.72 - _ascent(painter),
    );
  }

  /// Inisial sehingga dua huruf untuk fallback foto (`AH` untuk "Ahmad Hakim").
  static String initials(String? name) {
    final words =
        (name ?? 'U')
            .trim()
            .split(RegExp(r'\s+'))
            .where((w) => w.isNotEmpty)
            .take(2)
            .map((w) => w[0])
            .join();
    return words.isEmpty ? 'U' : words.toUpperCase();
  }
}

/// Satu baris nama yang telah diukur.
class CardNameLine {
  const CardNameLine({
    required this.text,
    required this.fontSize,
    required this.top,
  });

  final String text;
  final double fontSize;

  /// Kedudukan bucu atas dalam koordinat kanvas (belum diskala).
  final double top;
}

class CardNameLayout {
  const CardNameLayout({
    required this.fontSize,
    required this.lines,
    required this.bottom,
  });

  final double fontSize;
  final List<CardNameLine> lines;
  final double bottom;
}

class CardOrgLayout {
  const CardOrgLayout({
    required this.text,
    required this.fontSize,
    required this.boxTop,
    required this.textTop,
  });

  final String text;
  final double fontSize;
  final double boxTop;
  final double textTop;
}

class CardMemberNoLayout {
  const CardMemberNoLayout({
    required this.text,
    required this.fontSize,
    required this.top,
  });

  final String text;
  final double fontSize;
  final double top;
}
