/// News + Articles + Videos data models. Plain immutable classes with manual
/// `fromJson` matching the Laravel `/api/v1` payload keys exactly.
library;

class NewsPost {
  const NewsPost({
    required this.id,
    this.title,
    this.slug,
    this.excerpt,
    this.coverImagePath,
    this.organizationName,
    this.categoryName,
    this.publishedAt,
    this.likesCount = 0,
    this.dislikesCount = 0,
    this.commentsCount = 0,
    this.myReaction,
  });

  final int id;
  final String? title;
  final String? slug;
  final String? excerpt;
  final String? coverImagePath;
  final String? organizationName;
  final String? categoryName;
  final String? publishedAt;
  final int likesCount;
  final int dislikesCount;
  final int commentsCount;
  final String? myReaction;

  factory NewsPost.fromJson(Map<String, dynamic> json) => NewsPost(
        id: _int(json['id']) ?? 0,
        title: json['title'] as String?,
        slug: json['slug'] as String?,
        excerpt: json['excerpt'] as String?,
        coverImagePath: json['cover_image_path'] as String?,
        organizationName: json['organization_name'] as String?,
        categoryName: json['category_name'] as String?,
        publishedAt: json['published_at'] as String?,
        likesCount: _int(json['likes_count']) ?? 0,
        dislikesCount: _int(json['dislikes_count']) ?? 0,
        commentsCount: _int(json['comments_count']) ?? 0,
        myReaction: json['my_reaction'] as String?,
      );
}

class NewsCategory {
  const NewsCategory({this.id, this.name});

  final int? id;
  final String? name;

  factory NewsCategory.fromJson(Map<String, dynamic> json) => NewsCategory(
        id: _int(json['id']),
        name: json['name'] as String?,
      );
}

class NewsDetail {
  const NewsDetail({required this.post, this.comments = const []});

  final NewsDetailPost post;
  final List<Comment> comments;

  factory NewsDetail.fromJson(Map<String, dynamic> json) => NewsDetail(
        post: NewsDetailPost.fromJson(
          (json['post'] as Map<String, dynamic>?) ?? const {},
        ),
        comments: _listOf(json['comments'], Comment.fromJson),
      );
}

class NewsDetailPost {
  const NewsDetailPost({
    required this.id,
    this.title,
    this.excerpt,
    this.content,
    this.coverImagePath,
    this.publishedAt,
    this.organizationName,
    this.category,
    this.authorName,
    this.likesCount = 0,
    this.dislikesCount = 0,
    this.myReaction,
    this.canEdit = false,
  });

  final int id;
  final String? title;
  final String? excerpt;
  final String? content;
  final String? coverImagePath;
  final String? publishedAt;
  final String? organizationName;
  final NewsCategory? category;
  final String? authorName;
  final int likesCount;
  final int dislikesCount;
  final String? myReaction;
  final bool canEdit;

  factory NewsDetailPost.fromJson(Map<String, dynamic> json) => NewsDetailPost(
        id: _int(json['id']) ?? 0,
        title: json['title'] as String?,
        excerpt: json['excerpt'] as String?,
        content: json['content'] as String?,
        coverImagePath: json['cover_image_path'] as String?,
        publishedAt: json['published_at'] as String?,
        organizationName: json['organization_name'] as String?,
        category: json['category'] is Map<String, dynamic>
            ? NewsCategory.fromJson(json['category'] as Map<String, dynamic>)
            : null,
        authorName: json['author_name'] as String?,
        likesCount: _int(json['likes_count']) ?? 0,
        dislikesCount: _int(json['dislikes_count']) ?? 0,
        myReaction: json['my_reaction'] as String?,
        canEdit: json['can_edit'] == true,
      );
}

class Comment {
  const Comment({
    this.id,
    this.content,
    this.userName,
    this.createdAt,
  });

  final int? id;
  final String? content;
  final String? userName;
  final String? createdAt;

  factory Comment.fromJson(Map<String, dynamic> json) => Comment(
        id: _int(json['id']),
        content: json['content'] as String?,
        userName: json['user_name'] as String?,
        createdAt: json['created_at'] as String?,
      );
}

class Article {
  const Article({
    required this.id,
    this.slug,
    this.title,
    this.coverImage,
    this.excerpt,
    this.authorName,
    this.publishedDate,
    this.publicUrl,
    this.isFeatured = false,
    this.categories = const [],
  });

  final int id;
  final String? slug;
  final String? title;
  final String? coverImage;
  final String? excerpt;
  final String? authorName;
  final String? publishedDate;
  final String? publicUrl;
  final bool isFeatured;
  final List<NewsCategory> categories;

  factory Article.fromJson(Map<String, dynamic> json) => Article(
        id: _int(json['id']) ?? 0,
        slug: json['slug'] as String?,
        title: json['title'] as String?,
        coverImage: (json['cover_image'] ?? json['cover_image_path']) as String?,
        excerpt: json['excerpt'] as String?,
        authorName: json['author_name'] as String?,
        publishedDate: json['published_date'] as String?,
        publicUrl: json['public_url'] as String?,
        isFeatured: json['is_featured'] == true,
        categories: _listOf(json['categories'], NewsCategory.fromJson),
      );
}

class ArticleDetail {
  const ArticleDetail({required this.article, this.comments = const []});

  final ArticleDetailPost article;
  final List<Comment> comments;

  factory ArticleDetail.fromJson(Map<String, dynamic> json) => ArticleDetail(
        article: ArticleDetailPost.fromJson(
          (json['article'] as Map<String, dynamic>?) ?? const {},
        ),
        comments: _listOf(json['comments'], Comment.fromJson),
      );
}

class ArticleDetailPost {
  const ArticleDetailPost({
    required this.id,
    this.slug,
    this.title,
    this.excerpt,
    this.content,
    this.coverImagePath,
    this.publishedAt,
    this.organizationName,
    this.authorName,
    this.likesCount = 0,
    this.dislikesCount = 0,
    this.myReaction,
    this.categories = const [],
    this.tags = const [],
    this.gallery = const [],
  });

  final int id;
  final String? slug;
  final String? title;
  final String? excerpt;
  final String? content;
  final String? coverImagePath;
  final String? publishedAt;
  final String? organizationName;
  final String? authorName;
  final int likesCount;
  final int dislikesCount;
  final String? myReaction;
  final List<NewsCategory> categories;
  final List<ArticleTag> tags;
  final List<ArticleMedia> gallery;

  factory ArticleDetailPost.fromJson(Map<String, dynamic> json) =>
      ArticleDetailPost(
        id: _int(json['id']) ?? 0,
        slug: json['slug'] as String?,
        title: json['title'] as String?,
        excerpt: json['excerpt'] as String?,
        content: json['content'] as String?,
        coverImagePath: json['cover_image_path'] as String?,
        publishedAt: json['published_at'] as String?,
        organizationName: json['organization_name'] as String?,
        authorName: json['author_name'] as String?,
        likesCount: _int(json['likes_count']) ?? 0,
        dislikesCount: _int(json['dislikes_count']) ?? 0,
        myReaction: json['my_reaction'] as String?,
        categories: _listOf(json['categories'], NewsCategory.fromJson),
        tags: _listOf(json['tags'], ArticleTag.fromJson),
        gallery: _listOf(json['gallery'], ArticleMedia.fromJson),
      );
}

class ArticleTag {
  const ArticleTag({this.id, this.name});

  final int? id;
  final String? name;

  factory ArticleTag.fromJson(Map<String, dynamic> json) =>
      ArticleTag(id: _int(json['id']), name: json['name'] as String?);
}

class ArticleMedia {
  const ArticleMedia({this.id, this.path, this.caption});

  final int? id;
  final String? path;
  final String? caption;

  factory ArticleMedia.fromJson(Map<String, dynamic> json) => ArticleMedia(
        id: _int(json['id']),
        path: json['path'] as String?,
        caption: json['caption'] as String?,
      );
}

class Video {
  const Video({this.id, this.title, this.youtubeId, this.thumbnailUrl});

  final int? id;
  final String? title;
  final String? youtubeId;
  final String? thumbnailUrl;

  factory Video.fromJson(Map<String, dynamic> json) => Video(
        id: _int(json['id']),
        title: json['title'] as String?,
        youtubeId: json['youtube_id'] as String?,
        thumbnailUrl: json['thumbnail_url'] as String?,
      );

  String get watchUrl =>
      youtubeId == null ? '' : 'https://www.youtube.com/watch?v=$youtubeId';
}

int? _int(dynamic value) {
  if (value is int) return value;
  if (value is String) return int.tryParse(value);
  if (value is num) return value.toInt();
  return null;
}

List<T> _listOf<T>(dynamic value, T Function(Map<String, dynamic>) fromJson) {
  if (value is! List) return const [];
  return value
      .whereType<Map<String, dynamic>>()
      .map(fromJson)
      .toList(growable: false);
}
