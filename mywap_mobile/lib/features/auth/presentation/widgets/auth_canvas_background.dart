import 'package:flutter/material.dart';

import '../../../../shared/theme/app_colors.dart';

class AuthCanvasBackground extends StatelessWidget {
  const AuthCanvasBackground({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) => Stack(
    fit: StackFit.expand,
    children: [
      const ColoredBox(color: AppColors.authBackground),
      CustomPaint(painter: _IslamicPatternPainter(), size: Size.infinite),
      child,
    ],
  );
}

class _IslamicPatternPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    const tile = 84.0;
    final paint =
        Paint()
          ..color = AppColors.authPattern.withValues(alpha: .58)
          ..style = PaintingStyle.stroke
          ..strokeWidth = 1
          ..strokeCap = StrokeCap.round;

    final columns = (size.width / tile).ceil() + 2;
    final rows = (size.height / tile).ceil() + 2;
    for (var row = -1; row < rows; row++) {
      for (var column = -1; column < columns; column++) {
        _drawTile(canvas, Offset(column * tile, row * tile), tile, paint);
      }
    }
  }

  void _drawTile(Canvas canvas, Offset origin, double size, Paint paint) {
    final x = origin.dx;
    final y = origin.dy;
    final half = size / 2;

    final uprightArch =
        Path()
          ..moveTo(x, y + size)
          ..cubicTo(x, y + half, x + half * .55, y + half * .18, x + half, y)
          ..cubicTo(
            x + half * 1.45,
            y + half * .18,
            x + size,
            y + half,
            x + size,
            y + size,
          );
    canvas.drawPath(uprightArch, paint);

    final invertedArch =
        Path()
          ..moveTo(x, y)
          ..cubicTo(
            x,
            y + half,
            x + half * .55,
            y + half * 1.82,
            x + half,
            y + size,
          )
          ..cubicTo(
            x + half * 1.45,
            y + half * 1.82,
            x + size,
            y + half,
            x + size,
            y,
          );
    canvas.drawPath(invertedArch, paint);
  }

  @override
  bool shouldRepaint(covariant _IslamicPatternPainter oldDelegate) => false;
}
