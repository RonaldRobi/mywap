/// Member event registration from `GET /member/registrations`.
class EventRegistration {
  const EventRegistration({
    required this.id,
    this.registrationNo,
    this.name,
    this.status,
    this.statusLabel,
    this.formTitle,
    this.paymentStatus,
    this.attended = false,
    this.attendedAt,
    this.createdAt,
    this.event,
  });

  final int id;
  final String? registrationNo;
  final String? name;
  final String? status;
  final String? statusLabel;
  final String? formTitle;
  final String? paymentStatus;
  final bool attended;
  final String? attendedAt;
  final String? createdAt;
  final RegistrationEvent? event;

  factory EventRegistration.fromJson(Map<String, dynamic> json) =>
      EventRegistration(
        id: (json['id'] as num?)?.toInt() ?? 0,
        registrationNo: json['registration_no'] as String?,
        name: json['name'] as String?,
        status: json['status'] as String?,
        statusLabel: json['status_label'] as String?,
        formTitle: json['form_title'] as String?,
        paymentStatus: json['payment_status'] as String?,
        attended: json['attended'] == true,
        attendedAt: json['attended_at'] as String?,
        createdAt: json['created_at'] as String?,
        event: json['event'] is Map<String, dynamic>
            ? RegistrationEvent.fromJson(json['event'] as Map<String, dynamic>)
            : null,
      );

  bool get isPaid => paymentStatus == 'paid' || paymentStatus == 'successful';
}

class RegistrationEvent {
  const RegistrationEvent({
    this.id,
    this.title,
    this.slug,
    this.startFormatted,
  });

  final int? id;
  final String? title;
  final String? slug;
  final String? startFormatted;

  factory RegistrationEvent.fromJson(Map<String, dynamic> json) =>
      RegistrationEvent(
        id: (json['id'] as num?)?.toInt(),
        title: json['title'] as String?,
        slug: json['slug'] as String?,
        startFormatted: json['start_formatted'] as String?,
      );
}
