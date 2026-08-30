import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/loading_screen_providers.dart';

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
              _parseColor(config?.backgroundStart ?? '#071525'),
              _parseColor(config?.backgroundEnd ?? '#2F6B32'),
            ],
          ),
        ),
        child: Center(
          child: enabled && config?.gifUrl != null
              ? _LoadingGif(url: config!.gifUrl!)
              : const _FallbackIndicator(),
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
class _FallbackIndicator extends StatelessWidget {
  const _FallbackIndicator();

  @override
  Widget build(BuildContext context) {
    return const Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(
          Icons.volunteer_activism,
          size: 64,
          color: Colors.white70,
        ),
        SizedBox(height: 20),
        SizedBox(
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
