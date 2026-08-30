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

  Future<List<OnboardingSlideData>> getSlides() async {
    final response = await _client.get('/onboarding');
    if (response is! List) return const [];
    return response
        .whereType<Map>()
        .map(
          (item) => OnboardingSlideData.fromJson(item.cast<String, dynamic>()),
        )
        .toList(growable: false);
  }
}
