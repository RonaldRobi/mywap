// ignore_for_file: non_constant_identifier_names

import '../../../../core/utils/json_parsing.dart';
import '../../../../shared/l10n/l10n.dart';

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
    final orgs = orgsJson is List
        ? orgsJson
            .whereType<Map<String, dynamic>>()
            .map(OrganizationDetail.fromJson)
            .toList(growable: false)
        : const <OrganizationDetail>[];

    var org = orgJson is Map<String, dynamic>
        ? OrganizationDetail.fromJson(orgJson)
        : null;

    if (org != null && orgs.isNotEmpty) {
      final match = orgs.cast<OrganizationDetail?>().firstWhere(
            (o) =>
                (org!.id != null && o?.id == org.id) ||
                (org.slug != null && o?.slug == org.slug),
            orElse: () => null,
          );
      if (match != null) {
        final memberCount = org.member_count ?? match.member_count;
        final chartCount = org.chart_member_count ?? match.chart_member_count;
        if (memberCount != org.member_count || chartCount != org.chart_member_count) {
          org = org.copyWith(
            member_count: memberCount,
            chart_member_count: chartCount,
          );
        }
      }
    }

    return OrganizationInfoData(
      organization: org,
      organizations: orgs,
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
    this.email,
    this.phone,
    this.website_url,
    this.facebook_url,
    this.instagram_url,
    this.twitter_url,
    this.youtube_url,
    this.tiktok_url,
  });

  factory OrganizationDetail.fromJson(Map<String, dynamic> json) =>
      OrganizationDetail(
        id: parseIntValue(json['id']),
        name: json['name'] as String?,
        slug: json['slug'] as String?,
        description: json['description'] as String?,
        logo_path: json['logo_path'] as String?,
        color_theme: json['color_theme'] as String?,
        min_age: parseIntValue(json['min_age']),
        max_age: parseIntValue(json['max_age']),
        member_count: parseIntValue(json['member_count']),
        chart_member_count: parseIntValue(json['chart_member_count']),
        is_current: json['is_current'] == true,
        is_selected: json['is_selected'] == true,
        email: json['email'] as String?,
        phone: json['phone'] as String?,
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
  final String? email;
  final String? phone;
  final String? website_url;
  final String? facebook_url;
  final String? instagram_url;
  final String? twitter_url;
  final String? youtube_url;
  final String? tiktok_url;

  OrganizationDetail copyWith({
    int? id,
    String? name,
    String? slug,
    String? description,
    String? logo_path,
    String? color_theme,
    int? min_age,
    int? max_age,
    int? member_count,
    int? chart_member_count,
    bool? is_current,
    bool? is_selected,
    String? email,
    String? phone,
    String? website_url,
    String? facebook_url,
    String? instagram_url,
    String? twitter_url,
    String? youtube_url,
    String? tiktok_url,
  }) =>
      OrganizationDetail(
        id: id ?? this.id,
        name: name ?? this.name,
        slug: slug ?? this.slug,
        description: description ?? this.description,
        logo_path: logo_path ?? this.logo_path,
        color_theme: color_theme ?? this.color_theme,
        min_age: min_age ?? this.min_age,
        max_age: max_age ?? this.max_age,
        member_count: member_count ?? this.member_count,
        chart_member_count: chart_member_count ?? this.chart_member_count,
        is_current: is_current ?? this.is_current,
        is_selected: is_selected ?? this.is_selected,
        email: email ?? this.email,
        phone: phone ?? this.phone,
        website_url: website_url ?? this.website_url,
        facebook_url: facebook_url ?? this.facebook_url,
        instagram_url: instagram_url ?? this.instagram_url,
        twitter_url: twitter_url ?? this.twitter_url,
        youtube_url: youtube_url ?? this.youtube_url,
        tiktok_url: tiktok_url ?? this.tiktok_url,
      );

  String get ageRangeLabel {
    if (max_age == null) return currentAppLocalizations.orgAgePlus(min_age ?? 0);
    return currentAppLocalizations.orgAgeRange(min_age ?? 0, max_age!);
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
        id: parseIntValue(json['id']),
        name: json['name'] as String?,
        position: json['position'] as String?,
        email: json['email'] as String?,
        image_path: json['image_path'] as String?,
        display_order: parseIntValue(json['display_order']),
      );

  final int? id;
  final String? name;
  final String? position;
  final String? email;
  final String? image_path;
  final int? display_order;
}
