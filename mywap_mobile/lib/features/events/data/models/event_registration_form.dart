import '../../../forms/data/models/form_model.dart';
import 'event.dart';

/// Payload `GET /events/{id}/registration/{formId}` — borang pendaftaran
/// event untuk member (form + rumusan event + branding gateway).
class EventRegistrationData {
  const EventRegistrationData({
    this.form,
    this.event,
    this.paymentGateway,
    this.myRegistration,
  });

  final FormModel? form;
  final EventRegistrationEvent? event;
  final PaymentGatewayBrand? paymentGateway;
  final EventRegistrationSummary? myRegistration;

  factory EventRegistrationData.fromJson(Map<String, dynamic> json) =>
      EventRegistrationData(
        form:
            json['form'] is Map<String, dynamic>
                ? FormModel.fromJson(json['form'] as Map<String, dynamic>)
                : null,
        event:
            json['event'] is Map<String, dynamic>
                ? EventRegistrationEvent.fromJson(
                  json['event'] as Map<String, dynamic>,
                )
                : null,
        paymentGateway:
            json['payment_gateway'] is Map<String, dynamic>
                ? PaymentGatewayBrand.fromJson(
                  json['payment_gateway'] as Map<String, dynamic>,
                )
                : null,
        myRegistration:
            json['my_registration'] is Map<String, dynamic>
                ? EventRegistrationSummary.fromJson(
                  json['my_registration'] as Map<String, dynamic>,
                )
                : null,
      );
}

class EventRegistrationEvent {
  const EventRegistrationEvent({
    this.id,
    this.title,
    this.slug,
    this.startFormatted,
    this.locationOrLink,
    this.organizationName,
  });

  final int? id;
  final String? title;
  final String? slug;
  final String? startFormatted;
  final String? locationOrLink;
  final String? organizationName;

  factory EventRegistrationEvent.fromJson(Map<String, dynamic> json) =>
      EventRegistrationEvent(
        id: json['id'] as int?,
        title: json['title'] as String?,
        slug: json['slug'] as String?,
        startFormatted: json['start_formatted'] as String?,
        locationOrLink: json['location_or_link'] as String?,
        organizationName: json['organization_name'] as String?,
      );
}

class PaymentGatewayBrand {
  const PaymentGatewayBrand({
    this.key,
    this.name,
    this.tagline,
    this.logo,
    this.methods,
  });

  final String? key;
  final String? name;
  final String? tagline;
  final String? logo;
  final String? methods;

  factory PaymentGatewayBrand.fromJson(Map<String, dynamic> json) =>
      PaymentGatewayBrand(
        key: json['key'] as String?,
        name: json['name'] as String?,
        tagline: json['tagline'] as String?,
        logo: json['logo'] as String?,
        methods: json['methods'] as String?,
      );
}

/// Hasil `POST /events/{id}/registration` — `success` atau `redirect`
/// (ke gateway pembayaran), atau ralat dinaikkan sebagai [ApiException].
class EventRegistrationResult {
  const EventRegistrationResult({
    this.status,
    this.message,
    this.paymentUrl,
    this.registration,
  });

  final String? status;
  final String? message;
  final String? paymentUrl;
  final EventRegistrationSummary? registration;

  bool get isRedirect =>
      status == 'redirect' && (paymentUrl?.isNotEmpty ?? false);

  bool get isSuccess => status == 'success';

  factory EventRegistrationResult.fromJson(Map<String, dynamic> json) =>
      EventRegistrationResult(
        status: json['status'] as String?,
        message: json['message'] as String?,
        paymentUrl: json['payment_url'] as String?,
        registration:
            json['registration'] is Map<String, dynamic>
                ? EventRegistrationSummary.fromJson(
                  json['registration'] as Map<String, dynamic>,
                )
                : null,
      );
}
