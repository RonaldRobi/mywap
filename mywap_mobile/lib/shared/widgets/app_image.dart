import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../../core/constants/app_env.dart';
import '../theme/app_colors.dart';
import 'skeleton_box.dart';

/// Cached image with skeleton placeholder + friendly error widget (§11.2).
///
/// The backend commonly returns storage paths relative to the API host
/// (e.g. `/storage/logos/x.png`) rather than fully-qualified URLs — these
/// are resolved against [AppEnv.apiHost] here so every screen gets a working
/// image without each call site needing to know about the backend host.
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

    final resolvedUrl = AppEnv.resolveUrl(urlValue);

    // Nyahkod pada saiz paparan (bukan resolusi imej asal) untuk jimat
    // memori bitmap. Bila saiz tak ditetapkan, hadkan pada lebar skrin.
    final dpr = MediaQuery.devicePixelRatioOf(context);
    final hasFiniteWidth = width != null && width!.isFinite && width! > 0;
    final hasFiniteHeight = height != null && height!.isFinite && height! > 0;
    int? memCacheWidth;
    int? memCacheHeight;
    if (hasFiniteWidth) {
      memCacheWidth = (width! * dpr).round();
    } else if (hasFiniteHeight) {
      memCacheHeight = (height! * dpr).round();
    } else {
      final screenWidth = MediaQuery.sizeOf(context).width;
      if (screenWidth.isFinite && screenWidth > 0) {
        memCacheWidth = (screenWidth * dpr).round().clamp(1, 2048);
      }
    }

    return ClipRRect(
      borderRadius: borderRadius ?? BorderRadius.circular(8),
      child: CachedNetworkImage(
        imageUrl: resolvedUrl,
        width: width,
        height: height,
        fit: fit,
        memCacheWidth: memCacheWidth,
        memCacheHeight: memCacheHeight,
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
