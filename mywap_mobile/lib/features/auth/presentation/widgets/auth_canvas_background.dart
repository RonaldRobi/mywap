import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:flutter/material.dart';

import '../../../../shared/theme/app_colors.dart';

/// Recreates the web app's `.auth-canvas` background (see
/// `resources/css/app.css`) — a soft off-white gradient with layered radial
/// highlights plus a faint arabesque/islimi line motif, radially masked so
/// it fades out towards the edges. Used behind auth screens (login) so the
/// mobile app shares the same premium "brand canvas" look as the web login
/// page, while content sits in a frosted panel on top so it stays legible.
class AuthCanvasBackground extends StatelessWidget {
  const AuthCanvasBackground({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        const ColoredBox(color: AppColors.movementOffWhite),
        CustomPaint(painter: _AuthCanvasPainter(), size: Size.infinite),
        child,
      ],
    );
  }
}

class _AuthCanvasPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;

    // Base diagonal linear gradient (155deg white → soft off-white).
    final linearPaint =
        Paint()
          ..shader = const LinearGradient(
            begin: Alignment(-0.65, -1),
            end: Alignment(0.65, 1),
            colors: [
              AppColors.white,
              AppColors.pageBackground,
              AppColors.softGreenSurface,
            ],
            stops: [0, 0.42, 1],
          ).createShader(rect);
    canvas.drawRect(rect, linearPaint);

    // Layered radial highlights (soft green / dark green glows), matching
    // the web canvas positions (percent-of-viewport anchors).
    _paintRadial(
      canvas,
      size,
      center: Offset(size.width * 0.12, size.height * -0.08),
      radius: math.max(size.width, size.height) * 0.62,
      color: AppColors.movementSoftGreen,
      maxAlpha: 0.22,
    );
    _paintRadial(
      canvas,
      size,
      center: Offset(size.width * 1.05, size.height * 0.04),
      radius: math.max(size.width, size.height) * 0.55,
      color: AppColors.movementGreen,
      maxAlpha: 0.12,
    );
    _paintRadial(
      canvas,
      size,
      center: Offset(size.width * 0.48, size.height * 1.12),
      radius: math.max(size.width, size.height) * 0.5,
      color: AppColors.movementDarkGreen,
      maxAlpha: 0.10,
    );

    // Faint arabesque/islimi motif, tiled and radially masked so it fades
    // towards the edges (mirrors the web `::before` pseudo-element).
    _paintArabesquePattern(canvas, size);
  }

  void _paintRadial(
    Canvas canvas,
    Size size, {
    required Offset center,
    required double radius,
    required Color color,
    required double maxAlpha,
  }) {
    final paint =
        Paint()
          ..shader = ui.Gradient.radial(
            center,
            radius,
            [color.withValues(alpha: maxAlpha), color.withValues(alpha: 0)],
            [0.0, 1.0],
          );
    canvas.drawRect(Offset.zero & size, paint);
  }

  void _paintArabesquePattern(Canvas canvas, Size size) {
    const tile = 66.0; // half of the web's 132px tile, denser on mobile.
    final maskCenter = Offset(size.width * 0.5, size.height * 0.3);
    final maskRadius = math.max(size.width, size.height) * 0.62;

    canvas.saveLayer(Offset.zero & size, Paint());

    final linePaint =
        Paint()
          ..color = AppColors.movementDarkGreen
          ..style = PaintingStyle.stroke
          ..strokeWidth = 1.1
          ..strokeCap = StrokeCap.round;

    final cols = (size.width / tile).ceil() + 1;
    final rows = (size.height / tile).ceil() + 1;
    for (var row = -1; row < rows; row++) {
      for (var col = -1; col < cols; col++) {
        final origin = Offset(col * tile, row * tile);
        _drawMotif(canvas, origin, tile, linePaint);
      }
    }

    // Radial alpha mask — fades the pattern out towards the edges.
    final maskPaint =
        Paint()
          ..blendMode = BlendMode.dstIn
          ..shader = ui.Gradient.radial(
            maskCenter,
            maskRadius,
            [
              AppColors.movementDarkGreen.withValues(alpha: 0.16),
              AppColors.movementDarkGreen.withValues(alpha: 0.16),
              AppColors.movementDarkGreen.withValues(alpha: 0.086),
              AppColors.movementDarkGreen.withValues(alpha: 0),
            ],
            const [0.0, 0.62, 0.88, 1.0],
          );
    canvas.drawRect(Offset.zero & size, maskPaint);

    canvas.restore();
  }

  /// Repeating vertical waves mirror the login reference without competing
  /// with the form.
  void _drawMotif(Canvas canvas, Offset origin, double s, Paint paint) {
    final left = Path()..moveTo(origin.dx + s * .25, origin.dy);
    left.cubicTo(
      origin.dx + s * .65,
      origin.dy + s * .25,
      origin.dx - s * .15,
      origin.dy + s * .5,
      origin.dx + s * .25,
      origin.dy + s * .75,
    );
    left.cubicTo(
      origin.dx + s * .65,
      origin.dy + s,
      origin.dx - s * .15,
      origin.dy + s * 1.25,
      origin.dx + s * .25,
      origin.dy + s * 1.5,
    );
    canvas.drawPath(left, paint);

    final right = Path()..moveTo(origin.dx + s * 1.25, origin.dy);
    right.cubicTo(
      origin.dx + s * .85,
      origin.dy + s * .25,
      origin.dx + s * 1.65,
      origin.dy + s * .5,
      origin.dx + s * 1.25,
      origin.dy + s * .75,
    );
    right.cubicTo(
      origin.dx + s * .85,
      origin.dy + s,
      origin.dx + s * 1.65,
      origin.dy + s * 1.25,
      origin.dx + s * 1.25,
      origin.dy + s * 1.5,
    );
    canvas.drawPath(right, paint);
  }

  @override
  bool shouldRepaint(covariant _AuthCanvasPainter oldDelegate) => false;
}
