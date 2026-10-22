import 'dart:ui' as ui;

import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// Latar hiasan: bentuk organik lut-sinar di atas kanvas [AppColors.pageBackground].
///
/// Direka untuk dipasang sebagai lapisan paling bawah dalam `Stack` pada
/// skrin utama (dashboard ahli & laman utama awam):
///
/// ```dart
/// Scaffold(
///   body: Stack(
///     children: [
///       const Positioned.fill(child: OrganicCanvasBackground()),
///       CustomScrollView(...),
///     ],
///   ),
/// )
/// ```
///
/// Ciri-ciri:
/// - Dilukis dengan `CustomPainter` (tiada aset raster besar).
/// - Bentuk dan kedudukan dikira sebagai peratus `size`, jadi ia berskala
///   pada mana-mana saiz/aspek skrin.
/// - Opacity sangat rendah supaya kontras teks kekal dan tiada gangguan
///   visual kepada kandungan.
/// - `IgnorePointer` + `RepaintBoundary` + `shouldRepaint == false`:
///   tiada interaksi dan tiada kerja lukisan berulang semasa scroll.
class OrganicCanvasBackground extends StatelessWidget {
  const OrganicCanvasBackground({super.key});

  @override
  Widget build(BuildContext context) {
    return const IgnorePointer(
      child: RepaintBoundary(
        child: CustomPaint(painter: _OrganicBlobPainter()),
      ),
    );
  }
}

class _OrganicBlobPainter extends CustomPainter {
  const _OrganicBlobPainter();

  @override
  void paint(Canvas canvas, Size size) {
    if (size.isEmpty) return;

    // Mint pudar — kanan atas, bentuk besar.
    _paintBlob(
      canvas,
      center: Offset(size.width * 0.85, size.height * 0.08),
      radius: size.width * 0.55,
      color: AppColors.movementSoftGreen,
      opacity: .10,
    );

    // Aqua — kiri tengah, mengimbangi hijau supaya tidak 'satu warna'.
    _paintBlob(
      canvas,
      center: Offset(size.width * 0.05, size.height * 0.40),
      radius: size.width * 0.45,
      color: AppColors.organicAqua,
      opacity: .08,
    );

    // Hijau jenama — kanan bawah, paling halus.
    _paintBlob(
      canvas,
      center: Offset(size.width * 0.90, size.height * 0.75),
      radius: size.width * 0.40,
      color: AppColors.movementGreen,
      opacity: .06,
    );
  }

  void _paintBlob(
    Canvas canvas, {
    required Offset center,
    required double radius,
    required Color color,
    required double opacity,
  }) {
    final paint =
        Paint()
          ..shader = ui.Gradient.radial(center, radius, [
            color.withValues(alpha: opacity),
            color.withValues(alpha: 0),
          ])
          // Tepi lembut tanpa perlu blur besar — gradient radial ke alfa 0
          // sudah memberi transisi lancar dan jauh lebih murah daripada
          // MaskFilter.blur pada peranti lama.
          ..isAntiAlias = true;
    canvas.drawCircle(center, radius, paint);
  }

  @override
  bool shouldRepaint(_OrganicBlobPainter oldDelegate) => false;
}
