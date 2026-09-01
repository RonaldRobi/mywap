// ignore_for_file: non_constant_identifier_names, invalid_annotation_target
import 'package:freezed_annotation/freezed_annotation.dart';

import 'package:mywap_mobile/features/auth/data/models/organization.dart';
import 'package:mywap_mobile/features/events/data/models/event.dart';

part 'dashboard_data.freezed.dart';
part 'dashboard_data.g.dart';

@freezed
sealed class DashboardMember with _$DashboardMember {
  const factory DashboardMember({
    String? name,
    String? email,
    String? phone,
    String? branch_name,
    String? locality,
    String? profession,
    String? photo_url,
    String? member_since,
    String? member_no,
    String? system_logo_path,
    Organization? organization,
  }) = _DashboardMember;

  factory DashboardMember.fromJson(Map<String, dynamic> json) =>
      _$DashboardMemberFromJson(json);
}

@freezed
sealed class DashboardBanner with _$DashboardBanner {
  const factory DashboardBanner({
    int? id,
    String? title,
    String? image_path,
    String? link_url,
    String? link_target,
    int? display_order,
    int? organization_id,
  }) = _DashboardBanner;

  factory DashboardBanner.fromJson(Map<String, dynamic> json) =>
      _$DashboardBannerFromJson(json);
}

@freezed
sealed class InfaqItem with _$InfaqItem {
  const factory InfaqItem({
    int? id,
    String? title,
    String? description,
    String? image_path,
    String? type,
    dynamic target_amount,
    dynamic collected_amount,
    num? progress_percent,
    String? public_url,
  }) = _InfaqItem;

  factory InfaqItem.fromJson(Map<String, dynamic> json) => _$InfaqItemFromJson(json);
}

@freezed
sealed class NewsItem with _$NewsItem {
  const factory NewsItem({
    int? id,
    String? title,
    String? excerpt,
    String? cover_image_path,
    String? organization_name,
    String? category_name,
    String? published_at,
  }) = _NewsItem;

  factory NewsItem.fromJson(Map<String, dynamic> json) => _$NewsItemFromJson(json);
}

@freezed
sealed class ArticleItem with _$ArticleItem {
  const factory ArticleItem({
    int? id,
    String? title,
    String? slug,
    String? excerpt,
    String? cover_image_path,
    String? author_name,
    String? organization_name,
    String? published_at,
  }) = _ArticleItem;

  factory ArticleItem.fromJson(Map<String, dynamic> json) => _$ArticleItemFromJson(json);
}

@freezed
sealed class PollPreviewItem with _$PollPreviewItem {
  const factory PollPreviewItem({
    int? id,
    String? title,
    String? type,
    @JsonKey(name: 'ends_at_formatted') String? ends_at_formatted,
    @JsonKey(name: 'response_count') int? response_count,
    @JsonKey(name: 'has_responded') bool? has_responded,
  }) = _PollPreviewItem;

  factory PollPreviewItem.fromJson(Map<String, dynamic> json) =>
      _$PollPreviewItemFromJson(json);
}

@freezed
sealed class DashboardData with _$DashboardData {
  const factory DashboardData({
    DashboardMember? member,
    @JsonKey(name: 'feeStatus') Map<String, dynamic>? fee_status,
    @JsonKey(name: 'feeAmount') double? fee_amount,
    @JsonKey(name: 'nextEvent') Map<String, dynamic>? next_event,
    @JsonKey(name: 'upcomingEvents') List<Event>? upcoming_events,
    List<DashboardBanner>? banners,
    @JsonKey(name: 'infaqItems') List<InfaqItem>? infaq_items,
    @JsonKey(name: 'latestNews') List<NewsItem>? latest_news,
    @JsonKey(name: 'latestArticles') List<ArticleItem>? latest_articles,
    @JsonKey(name: 'activePolls') List<PollPreviewItem>? active_polls,
  }) = _DashboardData;

  factory DashboardData.fromJson(Map<String, dynamic> json) =>
      _$DashboardDataFromJson(json);
}
