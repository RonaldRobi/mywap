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

  /// One interlocking-curve motif tile — approximates the islimi pattern
  /// from the web SVG (two waves + petal shapes + a center dot) at a
  /// simplified fidelity suitable for a CustomPainter tile.
  void _drawMotif(Canvas canvas, Offset origin, double s, Paint paint) {
    Offset p(double x, double y) => origin + Offset(x * s, y * s);

    final wave1 =
        Path()
          ..moveTo(p(0, .5).dx, p(0, .5).dy)
          ..cubicTo(
            p(.17, .33).dx,
            p(.17, .33).dy,
            p(.33, .33).dx,
            p(.33, .33).dy,
            p(.5, .5).dx,
            p(.5, .5).dy,
          )
          ..cubicTo(
            p(.67, .67).dx,
            p(.67, .67).dy,
            p(.83, .67).dx,
            p(.83, .67).dy,
            p(1, .5).dx,
            p(1, .5).dy,
          );
    canvas.drawPath(wave1, paint);

    final wave2 =
        Path()
          ..moveTo(p(.5, 0).dx, p(.5, 0).dy)
          ..cubicTo(
            p(.33, .17).dx,
            p(.33, .17).dy,
            p(.33, .33).dx,
            p(.33, .33).dy,
            p(.5, .5).dx,
            p(.5, .5).dy,
          )
          ..cubicTo(
            p(.67, .67).dx,
            p(.67, .67).dy,
            p(.67, .83).dx,
            p(.67, .83).dy,
            p(.5, 1).dx,
            p(.5, 1).dy,
          );
    canvas.drawPath(wave2, paint);

    canvas.drawCircle(p(.5, .5), s * 0.028, paint..style = PaintingStyle.fill);
    paint.style = PaintingStyle.stroke;
  }

  @override
  bool shouldRepaint(covariant _AuthCanvasPainter oldDelegate) => false;
}
