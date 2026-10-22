import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// Skeleton placeholder dengan shimmer bergerak semasa kandungan dimuat.
///
/// Satu widget ini digunakan secara meluas (placeholder `AppImage`, rangka
/// pemuatan dashboard/laman utama, dll), jadi animasi di sini memperbaiki
/// semua keadaan pemuatan sekaligus.
///
/// Animasi dihentikan apabila pengguna mematikan animasi pada peranti
/// (`MediaQuery.disableAnimations`) — sasaran pengguna warga emas dan
/// sesiapa yang sensitif kepada gerakan.
class SkeletonBox extends StatefulWidget {
  const SkeletonBox({super.key, this.width, this.height, this.radius = 8});

  final double? width;
  final double? height;
  final double radius;

  @override
  State<SkeletonBox> createState() => _SkeletonBoxState();
}

class _SkeletonBoxState extends State<SkeletonBox>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final reduceMotion = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    if (reduceMotion) {
      _controller.stop();
    } else if (!_controller.isAnimating) {
      _controller.repeat();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        // Sorotan bergerak dari kiri ke kanan; julat -1..1 meliputi seluruh
        // kotak pada setiap kitaran.
        final t = _controller.value;
        return Container(
          width: widget.width,
          height: widget.height,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(widget.radius),
            gradient: LinearGradient(
              begin: Alignment(-1.6 + t * 3.2, -0.3),
              end: Alignment(-0.6 + t * 3.2, 0.3),
              colors: [
                AppColors.surfaceMuted,
                AppColors.paleGreen.withValues(alpha: .55),
                AppColors.surfaceMuted,
              ],
              stops: const [0.0, 0.5, 1.0],
            ),
          ),
        );
      },
    );
  }
}
