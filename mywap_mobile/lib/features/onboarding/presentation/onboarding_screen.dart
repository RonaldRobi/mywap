import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:video_player/video_player.dart';

import '../../../shared/theme/app_colors.dart';
import '../application/onboarding_providers.dart';
import '../data/onboarding_repository.dart';

const onboardingCompletedKey = 'onboarding_completed_v1';

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
      backgroundStart: '#071525',
      backgroundEnd: '#2F6B32',
      textColor: '#FFFFFF',
    ),
    OnboardingSlideData(
      order: 2,
      title: 'Semua Dalam Satu Tempat',
      body: 'Ikuti acara, urus keahlian dan temui kemudahan organisasi anda.',
      buttonLabel: 'Seterusnya',
      backgroundStart: '#123D2A',
      backgroundEnd: '#6FBF8A',
      textColor: '#FFFFFF',
    ),
    OnboardingSlideData(
      order: 3,
      title: 'Bersedia Untuk Bermula',
      body: 'Log masuk untuk meneruskan ke pengalaman myWAP anda.',
      buttonLabel: 'Mula',
      backgroundStart: '#2F6B32',
      backgroundEnd: '#071525',
      textColor: '#FFFFFF',
    ),
  ];

  Future<void> _complete() async {
    await (await SharedPreferences.getInstance()).setBool(
      onboardingCompletedKey,
      true,
    );
    if (mounted) context.go('/login');
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
        ref.watch(onboardingSlidesProvider).valueOrNull ?? const [];
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
                            color: AppColors.white.withValues(
                              alpha: index == _page ? 1 : .45,
                            ),
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

class _Slide extends StatelessWidget {
  const _Slide({required this.slide});
  final OnboardingSlideData slide;
  @override
  Widget build(BuildContext context) {
    final textColor = _parseColor(slide.textColor);
    return DecoratedBox(
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
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(32, 64, 32, 130),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: Center(
                  child: _Media(url: slide.mediaUrl, type: slide.mediaType),
                ),
              ),
              Text(
                slide.title,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  color: textColor,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
              Text(
                slide.body,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                  color: textColor.withValues(alpha: .88),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Media extends StatelessWidget {
  const _Media({this.url, this.type});
  final String? url;
  final String? type;
  @override
  Widget build(BuildContext context) {
    if (url == null || url!.isEmpty) {
      return const Icon(
        Icons.volunteer_activism_outlined,
        size: 144,
        color: AppColors.white,
      );
    }
    if (type == 'video') return _VideoMedia(url: url!);
    return ClipRRect(
      borderRadius: BorderRadius.circular(28),
      child: Image.network(
        url!,
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
          ? ClipRRect(
            borderRadius: BorderRadius.circular(28),
            child: AspectRatio(
              aspectRatio: _controller.value.aspectRatio,
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
