/// A public directory member. Keys match `DirectoryService::serializeDirectoryUser`.
class DirectoryUser {
  const DirectoryUser({
    this.id,
    this.name,
    this.industry,
    this.expertise,
    this.linkedinUrl,
    this.organizationName,
    this.organizationSlug,
  });

  final int? id;
  final String? name;
  final String? industry;
  final String? expertise;
  final String? linkedinUrl;
  final String? organizationName;
  final String? organizationSlug;

  factory DirectoryUser.fromJson(Map<String, dynamic> json) {
    final org = json['organization'];
    final orgMap = org is Map<String, dynamic> ? org : const <String, dynamic>{};
    return DirectoryUser(
      id: (json['id'] as num?)?.toInt(),
      name: json['name'] as String?,
      industry: json['industry'] as String?,
      expertise: json['expertise'] as String?,
      linkedinUrl: json['linkedin_url'] as String?,
      organizationName: orgMap['name'] as String?,
      organizationSlug: orgMap['slug'] as String?,
    );
  }

  String get initial =>
      (name == null || name!.isEmpty) ? '?' : name![0].toUpperCase();
}
