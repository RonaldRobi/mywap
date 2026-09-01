// ignore_for_file: non_constant_identifier_names

/// Payload from `GET /organization/info`.
class OrganizationInfoData {
  const OrganizationInfoData({this.organization, this.chartMembers = const []});

  factory OrganizationInfoData.fromJson(Map<String, dynamic> json) {
    final orgJson = json['organization'];
    final chartJson = json['chart_members'];
    return OrganizationInfoData(
      organization: orgJson is Map<String, dynamic>
          ? OrganizationDetail.fromJson(orgJson)
          : null,
      chartMembers: chartJson is List
          ? chartJson
              .whereType<Map<String, dynamic>>()
              .map(OrgChartMember.fromJson)
              .toList(growable: false)
          : const [],
    );
  }

  final OrganizationDetail? organization;
  final List<OrgChartMember> chartMembers;
}

class OrganizationDetail {
  const OrganizationDetail({
    this.id,
    this.name,
    this.slug,
    this.description,
    this.logo_path,
    this.color_theme,
    this.website_url,
    this.facebook_url,
    this.instagram_url,
    this.twitter_url,
    this.youtube_url,
    this.tiktok_url,
  });

  factory OrganizationDetail.fromJson(Map<String, dynamic> json) =>
      OrganizationDetail(
        id: (json['id'] as num?)?.toInt(),
        name: json['name'] as String?,
        slug: json['slug'] as String?,
        description: json['description'] as String?,
        logo_path: json['logo_path'] as String?,
        color_theme: json['color_theme'] as String?,
        website_url: json['website_url'] as String?,
        facebook_url: json['facebook_url'] as String?,
        instagram_url: json['instagram_url'] as String?,
        twitter_url: json['twitter_url'] as String?,
        youtube_url: json['youtube_url'] as String?,
        tiktok_url: json['tiktok_url'] as String?,
      );

  final int? id;
  final String? name;
  final String? slug;
  final String? description;
  final String? logo_path;
  final String? color_theme;
  final String? website_url;
  final String? facebook_url;
  final String? instagram_url;
  final String? twitter_url;
  final String? youtube_url;
  final String? tiktok_url;
}

class OrgChartMember {
  const OrgChartMember({
    this.id,
    this.name,
    this.position,
    this.email,
    this.image_path,
    this.display_order,
  });

  factory OrgChartMember.fromJson(Map<String, dynamic> json) => OrgChartMember(
        id: (json['id'] as num?)?.toInt(),
        name: json['name'] as String?,
        position: json['position'] as String?,
        email: json['email'] as String?,
        image_path: json['image_path'] as String?,
        display_order: (json['display_order'] as num?)?.toInt(),
      );

  final int? id;
  final String? name;
  final String? position;
  final String? email;
  final String? image_path;
  final int? display_order;
}
