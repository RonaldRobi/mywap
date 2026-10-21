import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// Latar hijau bergradien dengan motif geometri jalinan (seamless).
///
/// Digunakan untuk pengepala hab Menu dan mana-mana permukaan jenama yang
/// memerlukan tekstur. Motif dilukis dengan [CustomPainter] — tiada aset imej,
/// jadi ia kekal tajam pada semua ketumpatan skrin dan tidak menambah saiz
/// bundle.
///
/// Motif: kekisi berlian (rhombus lattice) dengan nod kecil pada setiap
/// persilangan — rujukan kepada corak jalinan geometri Islam, tetapi cukup
/// halus supaya teks putih di atasnya kekal >= 4.5:1 (WCAG AA).
class PatternBackdrop extends StatelessWidget {
  const PatternBackdrop({
    super.key,
    required this.child,
    this.borderRadius,
    this.tile = 34,
  });

  final Widget child;

  /// Lengkungan penjuru — biasanya [AppRadius.hero] untuk pengepala.
  final BorderRadius? borderRadius;

  /// Sisi satu petak motif dalam piksel logik. Lebih kecil = lebih padat.
  final double tile;

  @override
  Widget build(BuildContext context) {
    final content = DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: AppColors.patternGradient,
          stops: [0, .55, 1],
        ),
      ),
      child: CustomPaint(
        // Motif dilukis di belakang `child`, bukan di atasnya.
        painter: _LatticePainter(tile: tile),
        child: child,
      ),
    );

    if (borderRadius == null) return content;
    return ClipRRect(borderRadius: borderRadius!, child: content);
  }
}

/// Melukis kekisi berlian seamless.
///
/// Dua set garis serong (+45° dan -45°) menghasilkan jaringan berlian. Kerana
/// garis dilukis merentasi keseluruhan kanvas pada sela tetap, coraknya
/// berulang tanpa jahitan tidak kira saiz kanvas.
class _LatticePainter extends CustomPainter {
  const _LatticePainter({required this.tile});

  final double tile;

  @override
  void paint(Canvas canvas, Size size) {
    if (size.isEmpty) return;

    final line =
        Paint()
          ..color = AppColors.patternStroke
          ..strokeWidth = 1
          ..style = PaintingStyle.stroke
          ..isAntiAlias = true;

    final node =
        Paint()
          ..color = AppColors.patternAccent
          ..style = PaintingStyle.fill
          ..isAntiAlias = true;

    final diagonal = size.width + size.height;

    // Garis serong menurun (↘) dan menaik (↗) pada sela `tile`.
    final path = Path();
    for (double offset = -size.height; offset < diagonal; offset += tile) {
      path
        ..moveTo(offset, 0)
        ..lineTo(offset + size.height, size.height)
        ..moveTo(offset, size.height)
        ..lineTo(offset + size.height, 0);
    }
    canvas.drawPath(path, line);

    // Nod kecil pada setiap persilangan kekisi.
    final step = tile / 2;
    for (double y = 0; y <= size.height + step; y += tile) {
      for (double x = 0; x <= size.width + step; x += tile) {
        canvas.drawCircle(Offset(x, y), 1.6, node);
        canvas.drawCircle(Offset(x + step, y + step), 1.6, node);
      }
    }
  }

  @override
  bool shouldRepaint(_LatticePainter oldDelegate) => oldDelegate.tile != tile;
}
