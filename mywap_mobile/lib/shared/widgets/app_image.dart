import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import 'skeleton_box.dart';

/// Cached image with skeleton placeholder + friendly error widget (§11.2).
class AppImage extends StatelessWidget {
  const AppImage(
    this.url, {
    super.key,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.borderRadius,
  });

  final String? url;
  final double? width;
  final double? height;
  final BoxFit fit;
  final BorderRadius? borderRadius;

  @override
  Widget build(BuildContext context) {
    final urlValue = url;
    if (urlValue == null || urlValue.isEmpty) {
      return _placeholder(width, height);
    }

    return ClipRRect(
      borderRadius: borderRadius ?? BorderRadius.circular(8),
      child: CachedNetworkImage(
        imageUrl: urlValue,
        width: width,
        height: height,
        fit: fit,
        placeholder: (_, __) => SkeletonBox(width: width, height: height),
        errorWidget: (_, __, ___) => _placeholder(width, height),
      ),
    );
  }

  Widget _placeholder(double? width, double? height) {
    return Container(
      width: width,
      height: height,
      color: AppColors.divider,
      alignment: Alignment.center,
      child: const Icon(Icons.image_outlined, color: AppColors.textSecondary, size: 32),
    );
  }
}
