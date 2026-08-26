// ignore_for_file: non_constant_identifier_names

/// An announcement item from `GET /member/announcements`.
class Announcement {
  const Announcement({
    this.id,
    this.title,
    this.content,
    this.is_pinned,
    this.published_at,
    this.published_human,
    this.cover_image_url,
    this.author_name,
    this.likes_count,
    this.reads_count,
    this.user_reaction,
    this.is_read,
    this.images = const [],
  });

  factory Announcement.fromJson(Map<String, dynamic> json) {
    final rawImages = json['images'];
    return Announcement(
      id: (json['id'] as num?)?.toInt(),
      title: json['title'] as String?,
      content: json['content'] as String?,
      is_pinned: json['is_pinned'] as bool?,
      published_at: json['published_at'] as String?,
      published_human: json['published_human'] as String?,
      cover_image_url: json['cover_image_url'] as String?,
      author_name: json['author_name'] as String?,
      likes_count: (json['likes_count'] as num?)?.toInt(),
      reads_count: (json['reads_count'] as num?)?.toInt(),
      user_reaction: json['user_reaction'] as String?,
      is_read: json['is_read'] as bool?,
      images: rawImages is List
          ? rawImages
              .whereType<Map<String, dynamic>>()
              .map(AnnouncementImage.fromJson)
              .toList(growable: false)
          : const [],
    );
  }

  Announcement copyWith({
    int? likes_count,
    int? reads_count,
    String? user_reaction,
    bool? is_read,
  }) {
    return Announcement(
      id: id,
      title: title,
      content: content,
      is_pinned: is_pinned,
      published_at: published_at,
      published_human: published_human,
      cover_image_url: cover_image_url,
      author_name: author_name,
      likes_count: likes_count ?? this.likes_count,
      reads_count: reads_count ?? this.reads_count,
      user_reaction: user_reaction ?? this.user_reaction,
      is_read: is_read ?? this.is_read,
      images: images,
    );
  }

  final int? id;
  final String? title;
  final String? content;
  final bool? is_pinned;
  final String? published_at;
  final String? published_human;
  final String? cover_image_url;
  final String? author_name;
  final int? likes_count;
  final int? reads_count;
  final String? user_reaction;
  final bool? is_read;
  final List<AnnouncementImage> images;
}

class AnnouncementImage {
  const AnnouncementImage({this.id, this.url, this.caption});

  factory AnnouncementImage.fromJson(Map<String, dynamic> json) =>
      AnnouncementImage(
        id: (json['id'] as num?)?.toInt(),
        url: json['url'] as String?,
        caption: json['caption'] as String?,
      );

  final int? id;
  final String? url;
  final String? caption;
}
