import '../../../core/network/api_client.dart';

class OnboardingSlideData {
  const OnboardingSlideData({
    required this.order,
    required this.title,
    required this.body,
    required this.buttonLabel,
    required this.backgroundStart,
    required this.backgroundEnd,
    required this.textColor,
    this.buttonUrl,
    this.mediaUrl,
    this.mediaType,
  });

  factory OnboardingSlideData.fromJson(Map<String, dynamic> json) =>
      OnboardingSlideData(
        order: (json['order'] as num?)?.toInt() ?? 0,
        title: json['title'] as String? ?? '',
        body: json['body'] as String? ?? '',
        buttonLabel: json['button_label'] as String? ?? '',
        buttonUrl: json['button_url'] as String?,
        backgroundStart: json['background_start'] as String? ?? '#071525',
        backgroundEnd: json['background_end'] as String? ?? '#2F6B32',
        textColor: json['text_color'] as String? ?? '#FFFFFF',
        mediaUrl: json['media_url'] as String?,
        mediaType: json['media_type'] as String?,
      );

  final int order;
  final String title;
  final String body;
  final String buttonLabel;
  final String? buttonUrl;
  final String backgroundStart;
  final String backgroundEnd;
  final String textColor;
  final String? mediaUrl;
  final String? mediaType;
}

class OnboardingRepository {
  OnboardingRepository(this._client);
  final ApiClient _client;

  Future<MobileAuthConfiguration> getConfiguration() async {
    final response = await _client.get('/onboarding');
    if (response is! Map) {
      return const MobileAuthConfiguration(
        slides: [],
        login: MobileLoginBranding(
          title: 'Selamat kembali',
          subtitle: 'Log masuk untuk meneruskan ke myWAP.',
          backgroundStart: '#F4F6F1',
          backgroundEnd: '#EDF5EE',
          accent: '#2F6B32',
        ),
      );
    }
    final slides = (response['slides'] as List? ?? const [])
        .whereType<Map>()
        .map(
          (item) => OnboardingSlideData.fromJson(item.cast<String, dynamic>()),
        )
        .toList(growable: false);
    final login =
        response['login'] is Map<String, dynamic>
            ? MobileLoginBranding.fromJson(
              response['login'] as Map<String, dynamic>,
            )
            : const MobileLoginBranding(
              title: 'Selamat kembali',
              subtitle: 'Log masuk untuk meneruskan ke myWAP.',
              backgroundStart: '#F4F6F1',
              backgroundEnd: '#EDF5EE',
              accent: '#2F6B32',
            );
    return MobileAuthConfiguration(slides: slides, login: login);
  }
}

class MobileLoginBranding {
  const MobileLoginBranding({
    required this.title,
    required this.subtitle,
    required this.backgroundStart,
    required this.backgroundEnd,
    required this.accent,
    this.logoUrl,
    this.imageUrl,
  });
  factory MobileLoginBranding.fromJson(Map<String, dynamic> json) =>
      MobileLoginBranding(
        title: json['title'] as String? ?? 'Selamat kembali',
        subtitle:
            json['subtitle'] as String? ??
            'Log masuk untuk meneruskan ke myWAP.',
        backgroundStart: json['background_start'] as String? ?? '#F4F6F1',
        backgroundEnd: json['background_end'] as String? ?? '#EDF5EE',
        accent: json['accent'] as String? ?? '#2F6B32',
        logoUrl: json['logo_url'] as String?,
        imageUrl: json['image_url'] as String?,
      );
  final String title, subtitle, backgroundStart, backgroundEnd, accent;
  final String? logoUrl, imageUrl;
}

class MobileAuthConfiguration {
  const MobileAuthConfiguration({required this.slides, required this.login});
  final List<OnboardingSlideData> slides;
  final MobileLoginBranding login;
}
