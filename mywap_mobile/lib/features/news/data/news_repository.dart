import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import 'models/news.dart';

/// Repository for News (Info Terkini), Articles and Videos.
class NewsRepository {
  NewsRepository(this._api);

  final ApiClient _api;

  Future<List<NewsPost>> newsList({int page = 1, int? categoryId}) async {
    final data = await _api.get(
      ApiPaths.news,
      query: {
        'page': page,
        'per_page': 25,
        if (categoryId != null) 'category_id': categoryId,
      },
    );
    return _listOf(data, NewsPost.fromJson);
  }

  Future<NewsDetail> newsDetail(int id) async {
    final data = await _api.get(ApiPaths.newsDetail(id));
    return NewsDetail.fromJson(_asMap(data));
  }

  Future<Map<String, dynamic>> reactNews(int id, String reaction) async {
    final data = await _api.post(
      ApiPaths.newsReact(id),
      body: {'reaction': reaction},
    );
    return _asMap(data);
  }

  Future<Comment> commentNews(int id, String content) async {
    final data = await _api.post(
      ApiPaths.newsComments(id),
      body: {'content': content},
    );
    final map = _asMap(data);
    return Comment.fromJson(
      (map['comment'] as Map<String, dynamic>?) ?? const {},
    );
  }

  Future<List<Article>> articleList({int page = 1}) async {
    final data = await _api.get(ApiPaths.articles, query: {'page': page, 'per_page': 25});
    return _listOf(data, Article.fromJson);
  }

  Future<ArticleDetail> articleDetail(int id) async {
    final data = await _api.get(ApiPaths.articleDetail(id));
    return ArticleDetail.fromJson(_asMap(data));
  }

  Future<Map<String, dynamic>> reactArticle(int id, String reaction) async {
    final data = await _api.post(
      ApiPaths.articleReact(id),
      body: {'reaction': reaction},
    );
    return _asMap(data);
  }

  Future<Comment> commentArticle(int id, String content) async {
    final data = await _api.post(
      ApiPaths.articleComments(id),
      body: {'content': content},
    );
    final map = _asMap(data);
    return Comment.fromJson(
      (map['comment'] as Map<String, dynamic>?) ?? const {},
    );
  }

  Future<List<Video>> videos({int page = 1}) async {
    final data = await _api.get(ApiPaths.videos, query: {'page': page, 'per_page': 25});
    return _listOf(data, Video.fromJson);
  }

  Future<String> watchVideo(Video video) async {
    final url = video.watchUrl;
    if (url.isEmpty) {
      throw const ApiException('Pautan video tidak sah.');
    }
    return url;
  }

  Map<String, dynamic> _asMap(dynamic data) {
    if (data is String) {
      return const {};
    }
    return (data as Map<String, dynamic>?) ?? const {};
  }

  List<T> _listOf<T>(dynamic value, T Function(Map<String, dynamic>) fromJson) {
    if (value is! List) return const [];
    return value
        .whereType<Map<String, dynamic>>()
        .map(fromJson)
        .toList(growable: false);
  }
}
