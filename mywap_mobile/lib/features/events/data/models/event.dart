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
    @JsonKey(name: 'registrationForms')
    List<RegistrationForm>? registration_forms,
    @JsonKey(name: 'myRegistration') EventRegistrationSummary? my_registration,
  }) = _EventDetail;

  factory EventDetail.fromJson(Map<String, dynamic> json) =>
      _$EventDetailFromJson(json);
}

/// Pendaftaran ringkas yang dilampirkan pada butiran event.
@JsonSerializable()
class RegistrationForm {
  const RegistrationForm({
    this.id,
    this.title,
    this.description,
    this.price,
    this.paymentRequired = false,
  });

  final int? id;
  final String? title;
  final String? description;
  final double? price;
  @JsonKey(name: 'payment_required')
  final bool paymentRequired;

  factory RegistrationForm.fromJson(Map<String, dynamic> json) =>
      _$RegistrationFormFromJson(json);

  Map<String, dynamic> toJson() => _$RegistrationFormToJson(this);
}

/// Rumusan pendaftaran member untuk satu event (daripada EventDetail ataupun
/// payload borang pendaftaran).
@JsonSerializable()
class EventRegistrationSummary {
  const EventRegistrationSummary({
    this.registrationNo,
    this.status,
    this.statusLabel,
  });

  @JsonKey(name: 'registration_no')
  final String? registrationNo;
  final String? status;
  @JsonKey(name: 'status_label')
  final String? statusLabel;

  factory EventRegistrationSummary.fromJson(Map<String, dynamic> json) =>
      _$EventRegistrationSummaryFromJson(json);

  Map<String, dynamic> toJson() => _$EventRegistrationSummaryToJson(this);
}
