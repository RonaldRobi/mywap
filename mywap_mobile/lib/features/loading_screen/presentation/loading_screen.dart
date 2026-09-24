import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/loading_screen_providers.dart';
import '../../../shared/theme/app_colors.dart';

/// Loading screen yang dipaparkan setiap kali aplikasi dibuka (sebelum auth
/// selesai). Latar belakang gradient + GIF transparen di tengah. Hanya
/// digunakan oleh apps Flutter — web tidak memaparkannya.
class LoadingScreenView extends ConsumerWidget {
  const LoadingScreenView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(loadingScreenControllerProvider);
    final enabled = config?.enabled ?? true;

    return Scaffold(
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              _parseColor(config?.backgroundStart ?? '#12241C'),
              _parseColor(config?.backgroundEnd ?? '#147A3D'),
            ],
          ),
        ),
        child: Center(
          child: enabled && config?.gifUrl != null
              ? _LoadingGif(url: config!.gifUrl!)
              : _FallbackIndicator(logoUrl: config?.logoUrl),
        ),
      ),
    );
  }
}

class _LoadingGif extends StatelessWidget {
  const _LoadingGif({required this.url});
  final String url;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final size = constraints.maxWidth * 0.7;
        return CachedNetworkImage(
          imageUrl: url,
          width: size,
          height: size,
          fit: BoxFit.contain,
          memCacheWidth: 1080,
          memCacheHeight: 1080,
          placeholder: (_, __) => SizedBox(
            width: size,
            height: size,
            child: const Center(
              child: SizedBox(
                width: 28,
                height: 28,
                child: CircularProgressIndicator(
                  strokeWidth: 2.5,
                  color: Colors.white70,
                ),
              ),
            ),
          ),
          errorWidget: (_, __, ___) => const _FallbackIndicator(),
        );
      },
    );
  }
}

/// Fallback apabila tiada GIF dikonfigurasikan (atau gagal dimuat).
/// Memaparkan logo sistem (dimuat naik admin) jika ada, jika tidak ikon.
class _FallbackIndicator extends StatelessWidget {
  const _FallbackIndicator({this.logoUrl});

  final String? logoUrl;

  @override
  Widget build(BuildContext context) {
    final logo = logoUrl;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (logo != null && logo.isNotEmpty)
          Container(
            width: 132,
            height: 132,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(28),
              boxShadow: const [
                BoxShadow(
                  color: AppColors.scrimLight,
                  blurRadius: 24,
                  offset: Offset(0, 10),
                ),
              ],
            ),
            child: CachedNetworkImage(
              imageUrl: logo,
              fit: BoxFit.contain,
              errorWidget:
                  (_, __, ___) => const Icon(
                    Icons.volunteer_activism,
                    size: 56,
                    color: AppColors.movementGreen,
                  ),
            ),
          )
        else
          const Icon(
            Icons.volunteer_activism,
            size: 64,
            color: Colors.white70,
          ),
        const SizedBox(height: 24),
        const SizedBox(
          width: 28,
          height: 28,
          child: CircularProgressIndicator(
            strokeWidth: 2.5,
            color: Colors.white70,
          ),
        ),
      ],
    );
  }
}

Color _parseColor(String value) {
  final normalized = value.replaceFirst('#', '');
  return Color(int.parse('FF$normalized', radix: 16));
}
