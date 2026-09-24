import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:video_player/video_player.dart';

import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../core/constants/prefs_keys.dart';
import '../../loading_screen/application/loading_screen_providers.dart';
import '../application/onboarding_providers.dart';
import '../data/onboarding_repository.dart';

export '../../../core/constants/prefs_keys.dart' show onboardingCompletedKey;

class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});
  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  final _controller = PageController();
  var _page = 0;

  static const _fallbackSlides = [
    OnboardingSlideData(
      order: 1,
      title: 'Selamat Datang ke myWAP',
      body: 'Satu aplikasi untuk keahlian, program dan khidmat gerakan.',
      buttonLabel: 'Seterusnya',
      backgroundStart: '#12241C',
      backgroundEnd: '#147A3D',
      textColor: '#FFFFFF',
      overlayStartColor: '#12241C',
      overlayEndColor: '#12241C',
      overlayStartOpacity: 0,
      overlayEndOpacity: 90,
    ),
    OnboardingSlideData(
      order: 2,
      title: 'Semua Dalam Satu Tempat',
      body: 'Ikuti acara, urus keahlian dan temui kemudahan organisasi anda.',
      buttonLabel: 'Seterusnya',
      backgroundStart: '#0E5C2E',
      backgroundEnd: '#4FAE73',
      textColor: '#FFFFFF',
      overlayStartColor: '#12241C',
      overlayEndColor: '#12241C',
      overlayStartOpacity: 0,
      overlayEndOpacity: 90,
    ),
    OnboardingSlideData(
      order: 3,
      title: 'Bersedia Untuk Bermula',
      body:
          'Terokai berita, artikel dan program tanpa akaun — atau daftar '
          'sebagai ahli untuk akses penuh.',
      buttonLabel: 'Mula',
      backgroundStart: '#147A3D',
      backgroundEnd: '#12241C',
      textColor: '#FFFFFF',
      overlayStartColor: '#12241C',
      overlayEndColor: '#12241C',
      overlayStartOpacity: 0,
      overlayEndOpacity: 90,
    ),
  ];

  Future<void> _complete() async {
    try {
      await (await SharedPreferences.getInstance()).setBool(
        onboardingCompletedKey,
        true,
      );
    } catch (_) {
      // Kegagalan menyimpan status tidak sepatutnya menghalang navigasi.
    }
    if (mounted) context.go('/home');
  }

  Future<void> _next(List<OnboardingSlideData> slides) async {
    final slide = slides[_page];
    if (slide.buttonUrl != null && slide.buttonUrl!.isNotEmpty) {
      await launchUrl(
        Uri.parse(slide.buttonUrl!),
        mode: LaunchMode.externalApplication,
      );
    }
    if (_page == slides.length - 1) return _complete();
    await _controller.nextPage(
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeOutCubic,
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final configured =
        ref.watch(mobileAuthConfigurationProvider).valueOrNull?.slides ??
        const [];
    final slides = configured.length == 3 ? configured : _fallbackSlides;
    return Scaffold(
      body: Stack(
        children: [
          PageView.builder(
            controller: _controller,
            itemCount: slides.length,
            onPageChanged: (page) => setState(() => _page = page),
            itemBuilder: (_, index) => _Slide(slide: slides[index]),
          ),
          SafeArea(
            child: Align(
              alignment: Alignment.topLeft,
              child: Padding(
                padding: const EdgeInsets.only(left: 20, top: 8),
                child: const _OnboardingLogo(),
              ),
            ),
          ),
          SafeArea(
            child: Align(
              alignment: Alignment.topRight,
              child: TextButton(
                onPressed: _complete,
                child: const Text(
                  'Langkau',
                  style: TextStyle(
                    color: AppColors.white,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ),
          SafeArea(
            child: Align(
              alignment: Alignment.bottomCenter,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(24, 0, 24, 28),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: List.generate(
                        slides.length,
                        (index) => AnimatedContainer(
                          duration: const Duration(milliseconds: 180),
                          width: index == _page ? 24 : 7,
                          height: 7,
                          margin: const EdgeInsets.symmetric(horizontal: 3),
                          decoration: BoxDecoration(
                            color:
                                index == _page
                                    ? _parseColor(slides[_page].textColor)
                                    : AppColors.white.withValues(alpha: .45),
                            borderRadius: BorderRadius.circular(99),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: () => _next(slides),
                        style: FilledButton.styleFrom(
                          backgroundColor: AppColors.white,
                          foregroundColor: _parseColor(
                            slides[_page].backgroundStart,
                          ),
                        ),
                        child: Text(
                          slides[_page].buttonLabel.isEmpty
                              ? (_page == slides.length - 1
                                  ? 'Mula'
                                  : 'Seterusnya')
                              : slides[_page].buttonLabel,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _OnboardingLogo extends ConsumerWidget {
  const _OnboardingLogo();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final logo = ref.watch(appLogoProvider);
    if (logo == null || logo.isEmpty) return const SizedBox.shrink();
    return Container(
      width: 46,
      height: 46,
      padding: const EdgeInsets.all(6),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: AppRadius.md,
        boxShadow: const [
          BoxShadow(
            color: AppColors.scrimLight,
            blurRadius: 12,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Image.network(
        logo,
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => const SizedBox.shrink(),
      ),
    );
  }
}

class _Slide extends StatelessWidget {
  const _Slide({required this.slide});
  final OnboardingSlideData slide;
  @override
  Widget build(BuildContext context) {
    final textColor = _parseColor(slide.textColor);
    return Stack(
      fit: StackFit.expand,
      children: [
        DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                _parseColor(slide.backgroundStart),
                _parseColor(slide.backgroundEnd),
              ],
            ),
          ),
          child: _Media(url: slide.mediaUrl, type: slide.mediaType),
        ),
        DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [
                _parseColor(
                  slide.overlayStartColor,
                ).withValues(alpha: slide.overlayStartOpacity / 100),
                _parseColor(
                  slide.overlayEndColor,
                ).withValues(alpha: slide.overlayEndOpacity / 100),
              ],
              stops: _overlayStops(
                slide.overlayStartPosition,
                slide.overlayEndPosition,
              ),
            ),
          ),
        ),
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(32, 0, 32, 132),
            child: Align(
              alignment: Alignment.bottomLeft,
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 250),
                transitionBuilder:
                    (child, animation) => FadeTransition(
                      opacity: animation,
                      child: SlideTransition(
                        position: Tween<Offset>(
                          begin: const Offset(0, .08),
                          end: Offset.zero,
                        ).animate(animation),
                        child: child,
                      ),
                    ),
                child: Column(
                  key: ValueKey(slide.order),
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      slide.title,
                      style: Theme.of(
                        context,
                      ).textTheme.headlineMedium?.copyWith(
                        color: textColor,
                        fontWeight: FontWeight.w700,
                        height: 1.2,
                      ),
                    ),
                    const SizedBox(height: Spacing.lg),
                    Text(
                      slide.body,
                      style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                        color: textColor.withValues(alpha: .9),
                        height: 1.55,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _Media extends StatelessWidget {
  const _Media({this.url, this.type});
  final String? url;
  final String? type;
  @override
  Widget build(BuildContext context) {
    if (url == null || url!.isEmpty) return const SizedBox.expand();
    if (type == 'video') return _VideoMedia(url: url!);
    return ClipRRect(
      borderRadius: BorderRadius.zero,
      child: Image.network(
        url!,
        width: double.infinity,
        height: double.infinity,
        fit: BoxFit.cover,
        errorBuilder:
            (_, __, ___) => const Icon(
              Icons.broken_image_outlined,
              size: 96,
              color: AppColors.white,
            ),
      ),
    );
  }
}

class _VideoMedia extends StatefulWidget {
  const _VideoMedia({required this.url});
  final String url;
  @override
  State<_VideoMedia> createState() => _VideoMediaState();
}

class _VideoMediaState extends State<_VideoMedia> {
  late final VideoPlayerController _controller;
  @override
  void initState() {
    super.initState();
    _controller =
        VideoPlayerController.networkUrl(Uri.parse(widget.url))
          ..setLooping(true)
          ..setVolume(0)
          ..initialize().then((_) {
            _controller.play();
            if (mounted) setState(() {});
          });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) =>
      _controller.value.isInitialized
          ? FittedBox(
            fit: BoxFit.cover,
            clipBehavior: Clip.hardEdge,
            child: SizedBox(
              width: _controller.value.size.width,
              height: _controller.value.size.height,
              child: VideoPlayer(_controller),
            ),
          )
          : const SizedBox(
            width: 72,
            height: 72,
            child: CircularProgressIndicator(color: AppColors.white),
          );
}

Color _parseColor(String value) {
  final normalized = value.replaceFirst('#', '');
  return Color(int.parse('FF$normalized', radix: 16));
}

/// Tukar posisi peratus admin (0–100 dari ATAS skrin) kepada `stops`
/// LinearGradient. Nilai dikepit supaya konfigurasi tersilap tidak
/// menyebabkan assertion Flutter (stops mesti menaik dan dalam 0..1).
List<double> _overlayStops(int startPercent, int endPercent) {
  final start = (startPercent.clamp(0, 100)) / 100;
  final end = (endPercent.clamp(0, 100)) / 100;
  return end <= start ? [start, (start + 0.01).clamp(0.0, 1.0)] : [start, end];
}
