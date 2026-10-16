// ignore_for_file: non_constant_identifier_names

/// A user manual from `GET /manuals`.
///
/// The backend filters by the caller's role, so a member only ever receives
/// `user` manuals and an organisation admin only receives `admin` manuals.
class Manual {
  const Manual({
    this.id,
    this.title,
    this.description,
    this.audience,
    this.file_url,
    this.updated_at,
  });

  factory Manual.fromJson(Map<String, dynamic> json) => Manual(
        id: (json['id'] as num?)?.toInt(),
        title: json['title'] as String?,
        description: json['description'] as String?,
        audience: json['audience'] as String?,
        file_url: json['file_url'] as String?,
        updated_at: DateTime.tryParse(json['updated_at'] as String? ?? ''),
      );

  final int? id;
  final String? title;
  final String? description;
  final String? audience;
  final String? file_url;
  final DateTime? updated_at;
}
