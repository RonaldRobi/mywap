// ignore_for_file: non_constant_identifier_names

/// A library item from `GET /member/library`.
class LibraryItem {
  const LibraryItem({
    this.id,
    this.title,
    this.description,
    this.file_path,
    this.cover_image_path,
    this.category,
  });

  factory LibraryItem.fromJson(Map<String, dynamic> json) => LibraryItem(
        id: (json['id'] as num?)?.toInt(),
        title: json['title'] as String?,
        description: json['description'] as String?,
        file_path: json['file_path'] as String?,
        cover_image_path: json['cover_image_path'] as String?,
        category: json['category'] as String?,
      );

  final int? id;
  final String? title;
  final String? description;
  final String? file_path;
  final String? cover_image_path;
  final String? category;
}
