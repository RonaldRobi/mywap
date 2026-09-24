import 'package:flutter/material.dart';

import '../../../../shared/theme/app_colors.dart';
import '../../../../shared/theme/app_theme.dart';
import '../../../../shared/widgets/app_image.dart';
import '../../data/models/facility.dart';

/// Imej utama + galeri kemudahan (PageView + penunjuk halaman).
///
/// - Tiada gambar langsung (admin belum muat naik) → placeholder AppImage.
/// - Satu imej sahaja → papar imej penuh tanpa penunjuk.
/// - Lebih satu imej → boleh leret dengan badge kiraan & titik penunjuk.
class FacilityGallery extends StatefulWidget {
  const FacilityGallery({super.key, required this.facility, this.height = 220});

  final Facility facility;
  final double height;

  @override
  State<FacilityGallery> createState() => _FacilityGalleryState();
}

class _FacilityGalleryState extends State<FacilityGallery> {
  final _controller = PageController();
  int _page = 0;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final urls = widget.facility.galleryUrls;
    final height = widget.height;

    if (urls.length < 2) {
      return AppImage(
        urls.isEmpty ? null : urls.first,
        height: height,
        width: double.infinity,
        fit: BoxFit.cover,
        borderRadius: BorderRadius.zero,
      );
    }

    return SizedBox(
      height: height,
      width: double.infinity,
      child: Stack(
        children: [
          PageView.builder(
            controller: _controller,
            itemCount: urls.length,
            onPageChanged: (index) => setState(() => _page = index),
            itemBuilder:
                (context, index) => AppImage(
                  urls[index],
                  height: height,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  borderRadius: BorderRadius.zero,
                ),
          ),
          Positioned(
            top: Spacing.md,
            right: Spacing.md,
            child: Container(
              padding: const EdgeInsets.symmetric(
                horizontal: Spacing.sm,
                vertical: 4,
              ),
              decoration: BoxDecoration(
                color: AppColors.movementNavy.withValues(alpha: .6),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                '${_page + 1}/${urls.length}',
                style: const TextStyle(
                  color: AppColors.white,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: Spacing.md,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (var i = 0; i < urls.length; i++)
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    margin: const EdgeInsets.symmetric(horizontal: 3),
                    width: i == _page ? 18 : 7,
                    height: 7,
                    decoration: BoxDecoration(
                      color:
                          i == _page
                              ? AppColors.white
                              : AppColors.white.withValues(alpha: .55),
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
