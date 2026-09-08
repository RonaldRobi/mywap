import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';

import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/event.dart';
import 'models/event_registration.dart';
import 'models/event_registration_form.dart';

class EventRepository {
  EventRepository(this._api);

  final ApiClient _api;

  Future<List<Event>> list({int page = 1, String tab = 'upcoming'}) async {
    final data = await _api.get(
      ApiPaths.events,
      query: {'tab': tab, 'per_page': 25, 'page': page},
    );
    return _parseList(data);
  }

  Future<EventDetail> detail(int id) async {
    final data = await _api.get(ApiPaths.eventDetail(id));
    if (data is String) {
      return EventDetail.fromJson(jsonDecode(data) as Map<String, dynamic>);
    }
    return EventDetail.fromJson((data as Map<String, dynamic>?) ?? {});
  }

  Future<void> rsvp(int id, {String status = 'going'}) async {
    await _api.post(ApiPaths.eventRsvp(id), body: {'status': status});
  }

  /// Imbas QR poster event untuk rekod kehadiran sendiri (member self
  /// check-in). Mengembalikan `{event_title, registration_no}` bila berjaya.
  Future<Map<String, dynamic>> checkIn(int id, {required String token}) async {
    final data = await _api.post(
      ApiPaths.eventCheckIn(id),
      body: {'token': token},
    );
    return (data as Map<String, dynamic>?) ?? {};
  }

  Future<List<EventRegistration>> myRegistrations({int page = 1}) async {
    final data = await _api.get(
      ApiPaths.memberRegistrations,
      query: {'per_page': 25, 'page': page},
    );
    if (data is! List) return const [];
    return data
        .whereType<Map<String, dynamic>>()
        .map(EventRegistration.fromJson)
        .toList(growable: false);
  }

  /// Borang pendaftaran (member) untuk satu event.
  Future<EventRegistrationData> registrationForm(
    int eventId,
    int formId,
  ) async {
    final data = await _api.get(
      ApiPaths.eventRegistrationForm(eventId, formId),
    );
    return EventRegistrationData.fromJson(_asMap(data));
  }

  /// Hantar pendaftaran event (member). JSON bila tiada fail; multipart bila
  /// ada sebarang fail (jawapan jenis 'file' atau dokumen sokongan tier).
  Future<EventRegistrationResult> submitRegistration({
    required int eventId,
    required int formId,
    required Map<String, dynamic> answers,
    Map<int, File> files = const {},
    String? ticketType,
    String paymentMethod = 'fpx',
    File? document,
  }) async {
    final data =
        files.isNotEmpty || document != null
            ? await _submitRegistrationMultipart(
              eventId: eventId,
              formId: formId,
              answers: answers,
              files: files,
              ticketType: ticketType,
              paymentMethod: paymentMethod,
              document: document,
            )
            : await _api.post(
              ApiPaths.eventRegistration(eventId),
              body: {
                'form_id': formId,
                'answers': answers,
                if (ticketType != null && ticketType.isNotEmpty)
                  'ticket_type': ticketType,
                'payment_method': paymentMethod,
              },
            );
    return EventRegistrationResult.fromJson(_asMap(data));
  }

  Future<dynamic> _submitRegistrationMultipart({
    required int eventId,
    required int formId,
    required Map<String, dynamic> answers,
    required Map<int, File> files,
    required String? ticketType,
    required String paymentMethod,
    required File? document,
  }) async {
    final fields = <String, dynamic>{
      'form_id': formId.toString(),
      for (final entry in answers.entries)
        'answers[${entry.key}]': _stringify(entry.value),
      for (final entry in files.entries)
        'answers[${entry.key}]': MultipartFile.fromFileSync(
          entry.value.path,
          filename: _fileName(entry.value.path),
        ),
      if (document != null)
        'document': MultipartFile.fromFileSync(
          document.path,
          filename: _fileName(document.path),
        ),
      if (ticketType != null && ticketType.isNotEmpty)
        'ticket_type': ticketType,
      'payment_method': paymentMethod,
    };
    return _api.post(
      ApiPaths.eventRegistration(eventId),
      body: FormData.fromMap(fields),
    );
  }

  List<Event> _parseList(dynamic data) {
    if (data is List) {
      return data
          .whereType<Map<String, dynamic>>()
          .map(Event.fromJson)
          .toList(growable: false);
    }
    return const [];
  }

  static Map<String, dynamic> _asMap(dynamic data) {
    if (data is Map<String, dynamic>) return data;
    if (data is String) {
      final decoded = jsonDecode(data);
      if (decoded is Map<String, dynamic>) return decoded;
    }
    return <String, dynamic>{};
  }

  static String _stringify(dynamic value) {
    if (value is List) return value.map((e) => e.toString()).join(', ');
    return value?.toString() ?? '';
  }

  static String _fileName(String path) {
    final normalized = path.replaceAll('\\', '/');
    final index = normalized.lastIndexOf('/');
    return index == -1 ? normalized : normalized.substring(index + 1);
  }
}
