// ignore_for_file: non_constant_identifier_names

/// Payload from `GET /organization/info`.
class OrganizationInfoData {
  const OrganizationInfoData({
    this.organization,
    this.organizations = const [],
    this.chartMembers = const [],
  });

  factory OrganizationInfoData.fromJson(Map<String, dynamic> json) {
    final orgJson = json['organization'];
    final orgsJson = json['organizations'];
    final chartJson = json['chart_members'];
    return OrganizationInfoData(
      organization: orgJson is Map<String, dynamic>
          ? OrganizationDetail.fromJson(orgJson)
          : null,
      organizations: orgsJson is List
          ? orgsJson
              .whereType<Map<String, dynamic>>()
              .map(OrganizationDetail.fromJson)
              .toList(growable: false)
          : const [],
      chartMembers: chartJson is List
          ? chartJson
              .whereType<Map<String, dynamic>>()
              .map(OrgChartMember.fromJson)
              .toList(growable: false)
          : const [],
    );
  }

  final OrganizationDetail? organization;
  final List<OrganizationDetail> organizations;
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
    this.min_age,
    this.max_age,
    this.member_count,
    this.chart_member_count,
    this.is_current = false,
    this.is_selected = false,
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
        min_age: (json['min_age'] as num?)?.toInt(),
        max_age: (json['max_age'] as num?)?.toInt(),
        member_count: (json['member_count'] as num?)?.toInt(),
        chart_member_count: (json['chart_member_count'] as num?)?.toInt(),
        is_current: json['is_current'] == true,
        is_selected: json['is_selected'] == true,
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
  final int? min_age;
  final int? max_age;
  final int? member_count;
  final int? chart_member_count;
  final bool is_current;
  final bool is_selected;
  final String? website_url;
  final String? facebook_url;
  final String? instagram_url;
  final String? twitter_url;
  final String? youtube_url;
  final String? tiktok_url;

  String get ageRangeLabel {
    if (max_age == null) return '${min_age ?? 0}+ tahun';
    return '${min_age ?? 0}–$max_age tahun';
  }
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
