// ignore_for_file: non_constant_identifier_names, invalid_annotation_target
import 'package:freezed_annotation/freezed_annotation.dart';

import 'package:mywap_mobile/features/auth/data/models/organization.dart';

part 'event.freezed.dart';
part 'event.g.dart';

@freezed
sealed class Event with _$Event {
  const factory Event({
    int? id,
    String? title,
    String? slug,
    String? description,
    String? type,
    String? status,
    String? status_label,
    String? category,
    String? category_label,
    String? location_or_link,
    String? start_time,
    String? start_formatted,
    String? end_time,
    String? featured_image_url,
    Organization? organization,
    List<Organization>? organizations,
    int? rsvp_count,
    String? my_rsvp,
  }) = _Event;

  factory Event.fromJson(Map<String, dynamic> json) => _$EventFromJson(json);
}

@freezed
sealed class EventDetail with _$EventDetail {
  const factory EventDetail({
    Event? event,
    @JsonKey(name: 'relatedEvents') List<Event>? related_events,
  }) = _EventDetail;

  factory EventDetail.fromJson(Map<String, dynamic> json) => _$EventDetailFromJson(json);
}
