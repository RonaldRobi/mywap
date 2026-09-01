import 'dart:convert';

import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/event.dart';
import 'models/event_registration.dart';

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
    await _api.post(
      ApiPaths.eventRsvp(id),
      body: {'status': status},
    );
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

  List<Event> _parseList(dynamic data) {
    if (data is List) {
      return data
          .whereType<Map<String, dynamic>>()
          .map(Event.fromJson)
          .toList(growable: false);
    }
    return const [];
  }
}
