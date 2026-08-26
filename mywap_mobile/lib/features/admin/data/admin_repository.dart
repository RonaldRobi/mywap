import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import '../../events/data/models/event.dart';
import 'models/admin_models.dart';

/// Repository for the admin API (`/admin/*`, all `auth:sanctum` guarded).
///
/// The admin path constants are intentionally local (the central [ApiPaths]
/// file is out of scope for this feature); only `/events` reuses [ApiPaths].
class AdminRepository {
  AdminRepository(this._api);

  // ---- Local admin paths (not present in `ApiPaths`) ----
  static const _dashboard = '/admin/dashboard';
  static const _members = '/admin/members';
  static const _fees = '/admin/fees';
  static const _attendanceRegistrations = '/admin/attendance/registrations';
  static const _attendanceScan = '/admin/attendance/scan';
  static const _broadcast = '/admin/broadcast';

  final ApiClient _api;

  Future<AdminDashboard> dashboard() async {
    final data = await _api.get(_dashboard);
    return AdminDashboard.fromJson((data as Map<String, dynamic>?) ?? {});
  }

  Future<PaginatedMembers> members({
    String search = '',
    String status = '',
    int page = 1,
  }) async {
    final data = await _api.get(_members, query: {
      if (search.isNotEmpty) 'search': search,
      if (status.isNotEmpty) 'status': status,
      'per_page': 25,
      'page': page,
    });
    return PaginatedMembers.fromJson((data as Map<String, dynamic>?) ?? {});
  }

  Future<FeesData> fees({String status = '', String search = ''}) async {
    final data = await _api.get(_fees, query: {
      if (status.isNotEmpty) 'status': status,
      if (search.isNotEmpty) 'search': search,
    });
    return FeesData.fromJson((data as Map<String, dynamic>?) ?? {});
  }

  Future<AttendanceData> attendanceRegistrations(int eventId) async {
    final data = await _api.get(
      _attendanceRegistrations,
      query: {'event_id': eventId},
    );
    return AttendanceData.fromJson((data as Map<String, dynamic>?) ?? {});
  }

  Future<ScanResult> scan({
    required int eventId,
    required String identifier,
  }) async {
    final data = await _api.post(
      _attendanceScan,
      body: {'event_id': eventId, 'identifier': identifier},
    );
    return ScanResult.fromJson((data as Map<String, dynamic>?) ?? {});
  }

  Future<BroadcastResult> broadcast({
    required String title,
    required String message,
    required String audience,
    int? organizationId,
  }) async {
    final data = await _api.post(
      _broadcast,
      body: {
        'title': title,
        'message': message,
        'audience': audience,
        if (organizationId != null) 'organization_id': organizationId,
      },
    );
    return BroadcastResult.fromJson((data as Map<String, dynamic>?) ?? {});
  }

  /// Upcoming events via the public `/events` endpoint. Admins receive the
  /// events with attendance arrays — used to pick an event for scanning.
  Future<List<Event>> upcomingEvents() async {
    final data = await _api.get(ApiPaths.events, query: {
      'tab': 'upcoming',
      'per_page': 50,
    });
    if (data is List) {
      return data
          .whereType<Map<String, dynamic>>()
          .map(Event.fromJson)
          .toList(growable: false);
    }
    return const [];
  }
}
