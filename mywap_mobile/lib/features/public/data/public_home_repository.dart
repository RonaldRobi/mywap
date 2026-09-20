import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';

/// Banner dashboard yang dimuat naik oleh admin (untuk carousel Beranda).
class PublicBanner {
  const PublicBanner({this.id, this.title, this.imagePath, this.linkUrl});

  final int? id;
  final String? title;
  final String? imagePath;
  final String? linkUrl;

  factory PublicBanner.fromJson(Map<String, dynamic> json) => PublicBanner(
    id: (json['id'] as num?)?.toInt(),
    title: json['title'] as String?,
    imagePath: json['image_path'] as String?,
    linkUrl: json['link_url'] as String?,
  );
}

class PublicArticle {
  const PublicArticle({
    this.id,
    this.title,
    this.excerpt,
    this.coverImagePath,
    this.authorName,
    this.publishedAt,
  });

  final int? id;
  final String? title;
  final String? excerpt;
  final String? coverImagePath;
  final String? authorName;
  final String? publishedAt;

  factory PublicArticle.fromJson(Map<String, dynamic> json) => PublicArticle(
    id: (json['id'] as num?)?.toInt(),
    title: json['title'] as String?,
    excerpt: json['excerpt'] as String?,
    coverImagePath: json['cover_image_path'] as String?,
    authorName: json['author_name'] as String?,
    publishedAt: json['published_at'] as String?,
  );
}

class PublicNews {
  const PublicNews({
    this.id,
    this.title,
    this.excerpt,
    this.coverImagePath,
    this.categoryName,
    this.publishedAt,
  });

  final int? id;
  final String? title;
  final String? excerpt;
  final String? coverImagePath;
  final String? categoryName;
  final String? publishedAt;

  factory PublicNews.fromJson(Map<String, dynamic> json) => PublicNews(
    id: (json['id'] as num?)?.toInt(),
    title: json['title'] as String?,
    excerpt: json['excerpt'] as String?,
    coverImagePath: json['cover_image_path'] as String?,
    categoryName: json['category_name'] as String?,
    publishedAt: json['published_at'] as String?,
  );
}

class PublicVideo {
  const PublicVideo({this.id, this.title, this.youtubeId, this.thumbnailUrl});

  final int? id;
  final String? title;
  final String? youtubeId;
  final String? thumbnailUrl;

  factory PublicVideo.fromJson(Map<String, dynamic> json) => PublicVideo(
    id: (json['id'] as num?)?.toInt(),
    title: json['title'] as String?,
    youtubeId: json['youtube_id'] as String?,
    thumbnailUrl: json['thumbnail_url'] as String?,
  );

  String get watchUrl =>
      youtubeId == null ? '' : 'https://www.youtube.com/watch?v=$youtubeId';
}

class PublicLibraryItem {
  const PublicLibraryItem({
    this.id,
    this.title,
    this.category,
    this.filePath,
    this.coverImagePath,
  });

  final int? id;
  final String? title;
  final String? category;
  final String? filePath;
  final String? coverImagePath;

  factory PublicLibraryItem.fromJson(Map<String, dynamic> json) =>
      PublicLibraryItem(
        id: (json['id'] as num?)?.toInt(),
        title: json['title'] as String?,
        category: json['category'] as String?,
        filePath: json['file_path'] as String?,
        coverImagePath: json['cover_image_path'] as String?,
      );
}

class PublicEvent {
  const PublicEvent({
    this.id,
    this.title,
    this.locationOrLink,
    this.startFormatted,
    this.featuredImageUrl,
    this.organizationName,
  });

  final int? id;
  final String? title;
  final String? locationOrLink;
  final String? startFormatted;
  final String? featuredImageUrl;
  final String? organizationName;

  factory PublicEvent.fromJson(Map<String, dynamic> json) => PublicEvent(
    id: (json['id'] as num?)?.toInt(),
    title: json['title'] as String?,
    locationOrLink: json['location_or_link'] as String?,
    startFormatted: json['start_formatted'] as String?,
    featuredImageUrl: json['featured_image_url'] as String?,
    organizationName: json['organization_name'] as String?,
  );
}

class PublicInfaq {
  const PublicInfaq({
    this.id,
    this.title,
    this.slug,
    this.imagePath,
    this.targetAmount,
    this.collectedAmount,
    this.progressPercent,
    this.organizationName,
  });

  final int? id;
  final String? title;
  final String? slug;
  final String? imagePath;
  final double? targetAmount;
  final double? collectedAmount;
  final double? progressPercent;
  final String? organizationName;

  factory PublicInfaq.fromJson(Map<String, dynamic> json) => PublicInfaq(
    id: (json['id'] as num?)?.toInt(),
    title: json['title'] as String?,
    slug: json['slug'] as String?,
    imagePath: json['image_path'] as String?,
    targetAmount: (json['target_amount'] as num?)?.toDouble(),
    collectedAmount: (json['collected_amount'] as num?)?.toDouble(),
    progressPercent: (json['progress_percent'] as num?)?.toDouble(),
    organizationName: json['organization_name'] as String?,
  );
}

/// Payload penuh Beranda awam (`GET /public/home`).
class PublicHomeData {
  const PublicHomeData({
    this.logoUrl,
    this.banners = const [],
    this.articles = const [],
    this.news = const [],
    this.videos = const [],
    this.library = const [],
    this.events = const [],
    this.infaqs = const [],
  });

  final String? logoUrl;
  final List<PublicBanner> banners;
  final List<PublicArticle> articles;
  final List<PublicNews> news;
  final List<PublicVideo> videos;
  final List<PublicLibraryItem> library;
  final List<PublicEvent> events;
  final List<PublicInfaq> infaqs;

  factory PublicHomeData.fromJson(Map<String, dynamic> json) => PublicHomeData(
    logoUrl: json['logo_url'] as String?,
    banners: _listOf(json['banners'], PublicBanner.fromJson),
    articles: _listOf(json['articles'], PublicArticle.fromJson),
    news: _listOf(json['news'], PublicNews.fromJson),
    videos: _listOf(json['videos'], PublicVideo.fromJson),
    library: _listOf(json['library'], PublicLibraryItem.fromJson),
    events: _listOf(json['events'], PublicEvent.fromJson),
    infaqs: _listOf(json['infaqs'], PublicInfaq.fromJson),
  );
}

List<T> _listOf<T>(dynamic value, T Function(Map<String, dynamic>) fromJson) {
  if (value is! List) return const [];
  return value
      .whereType<Map>()
      .map((e) => fromJson(e.cast<String, dynamic>()))
      .toList(growable: false);
}

class PublicHomeRepository {
  PublicHomeRepository(this._api);

  final ApiClient _api;

  Future<PublicHomeData> fetch() async {
    final data = await _api.get(ApiPaths.publicHome);
    if (data is! Map) return const PublicHomeData();
    return PublicHomeData.fromJson(data.cast<String, dynamic>());
  }
}
