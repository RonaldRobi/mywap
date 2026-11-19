import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

class ScanViewfinder extends StatelessWidget {
  const ScanViewfinder({super.key, this.isProcessing = false});

  final bool isProcessing;

  @override
  Widget build(BuildContext context) {
    return ExcludeSemantics(
      child: CustomPaint(
        painter: _ScanViewfinderPainter(isProcessing: isProcessing),
        child: const SizedBox.expand(),
      ),
    );
  }
}

class _ScanViewfinderPainter extends CustomPainter {
  const _ScanViewfinderPainter({required this.isProcessing});

  final bool isProcessing;

  @override
  void paint(Canvas canvas, Size size) {
    const inset = 24.0;
    final frameSide = math.min(272.0, size.width - (inset * 2));
    final frame = Rect.fromCenter(
      center: size.center(Offset.zero),
      width: frameSide,
      height: frameSide,
    );
    final hole = RRect.fromRectAndRadius(frame, const Radius.circular(28));
    final overlay =
        Path()
          ..fillType = PathFillType.evenOdd
          ..addRect(Offset.zero & size)
          ..addRRect(hole);

    canvas.drawPath(
      overlay,
      Paint()..color = Colors.black.withValues(alpha: 0.56),
    );

    final framePaint =
        Paint()
          ..color = isProcessing ? AppColors.warning : AppColors.white
          ..style = PaintingStyle.stroke
          ..strokeWidth = 4
          ..strokeCap = StrokeCap.round;
    const cornerLength = 34.0;
    const cornerRadius = 26.0;

    void drawCorner(Offset start, Offset horizontalEnd, Offset verticalEnd) {
      canvas.drawLine(start, horizontalEnd, framePaint);
      canvas.drawLine(start, verticalEnd, framePaint);
    }

    drawCorner(
      Offset(frame.left + cornerRadius, frame.top),
      Offset(frame.left + cornerRadius + cornerLength, frame.top),
      Offset(frame.left, frame.top + cornerRadius),
    );
    drawCorner(
      Offset(frame.right - cornerRadius, frame.top),
      Offset(frame.right - cornerRadius - cornerLength, frame.top),
      Offset(frame.right, frame.top + cornerRadius),
    );
    drawCorner(
      Offset(frame.left + cornerRadius, frame.bottom),
      Offset(frame.left + cornerRadius + cornerLength, frame.bottom),
      Offset(frame.left, frame.bottom - cornerRadius),
    );
    drawCorner(
      Offset(frame.right - cornerRadius, frame.bottom),
      Offset(frame.right - cornerRadius - cornerLength, frame.bottom),
      Offset(frame.right, frame.bottom - cornerRadius),
    );
  }

  @override
  bool shouldRepaint(_ScanViewfinderPainter oldDelegate) {
    return oldDelegate.isProcessing != isProcessing;
  }
}
