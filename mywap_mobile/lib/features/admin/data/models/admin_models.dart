// Plain immutable models for the admin API contract.
//
// No codegen (freezed/json_serializable) — manual `fromJson` per the admin
// feature spec. Keys mirror the `/admin/*` responses exactly.

int _toInt(dynamic v) => v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;

double _toDouble(dynamic v) =>
    v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0;

String _toStr(dynamic v) => v?.toString() ?? '';

bool _toBool(dynamic v) => v == true || v == 1 || v == '1' || v == 'true';

List<String> _toStringList(dynamic v) {
  if (v is List) return v.map(_toStr).toList(growable: false);
  return const [];
}

List<double> _toDoubleList(dynamic v) {
  if (v is List) return v.map(_toDouble).toList(growable: false);
  return const [];
}

List<Map<String, dynamic>> _toMapList(dynamic v) {
  if (v is List) return v.whereType<Map<String, dynamic>>().toList(growable: false);
  return const [];
}

Map<String, dynamic> _toMap(dynamic v) =>
    v is Map<String, dynamic> ? v : const <String, dynamic>{};

/// `GET /admin/dashboard` → `data.stats`.
class AdminStats {
  const AdminStats({
    this.totalMembers = 0,
    this.activeMembers = 0,
    this.pendingMembers = 0,
    this.totalEvents = 0,
    this.upcomingEvents = 0,
    this.totalRevenue = 0,
    this.pendingPayments = 0,
  });

  final int totalMembers;
  final int activeMembers;
  final int pendingMembers;
  final int totalEvents;
  final int upcomingEvents;
  final double totalRevenue;
  final int pendingPayments;

  factory AdminStats.fromJson(Map<String, dynamic> json) => AdminStats(
        totalMembers: _toInt(json['total_members']),
        activeMembers: _toInt(json['active_members']),
        pendingMembers: _toInt(json['pending_members']),
        totalEvents: _toInt(json['total_events']),
        upcomingEvents: _toInt(json['upcoming_events']),
        totalRevenue: _toDouble(json['total_revenue']),
        pendingPayments: _toInt(json['pending_payments']),
      );
}

/// `GET /admin/dashboard` → `data.recent_activities[]`.
class AdminActivity {
  const AdminActivity({
    this.id,
    this.type = '',
    this.title = '',
    this.description = '',
    this.createdAt,
  });

  final int? id;
  final String type;
  final String title;
  final String description;
  final DateTime? createdAt;

  factory AdminActivity.fromJson(Map<String, dynamic> json) => AdminActivity(
        id: json['id'] is num ? (json['id'] as num).toInt() : null,
        type: _toStr(json['type']),
        title: _toStr(json['title']),
        description: _toStr(json['description']),
        createdAt: DateTime.tryParse(_toStr(json['created_at'])),
      );
}

/// `GET /admin/dashboard` → `data`.
class AdminDashboard {
  const AdminDashboard({
    required this.stats,
    this.activities = const [],
    this.revenueLabels = const [],
    this.revenueValues = const [],
  });

  final AdminStats stats;
  final List<AdminActivity> activities;
  final List<String> revenueLabels;
  final List<double> revenueValues;

  factory AdminDashboard.fromJson(Map<String, dynamic> json) {
    final revenue = _toMap(json['revenue_by_month']);
    return AdminDashboard(
      stats: AdminStats.fromJson(_toMap(json['stats'])),
      activities: _toMapList(json['recent_activities'])
          .map(AdminActivity.fromJson)
          .toList(growable: false),
      revenueLabels: _toStringList(revenue['labels']),
      revenueValues: _toDoubleList(revenue['values']),
    );
  }
}

/// `GET /admin/members` → `data[]`.
class AdminMember {
  const AdminMember({
    this.id,
    this.name = '',
    this.memberNo = '',
    this.email = '',
    this.phone = '',
    this.icNumber = '',
    this.branchName = '',
    this.organizationName,
    this.organizationId,
    this.status = '',
    this.createdAt,
    this.profileCompletedAt,
  });

  final int? id;
  final String name;
  final String memberNo;
  final String email;
  final String phone;
  final String icNumber;
  final String branchName;
  final String? organizationName;
  final int? organizationId;
  final String status; // 'active' | 'pending'
  final DateTime? createdAt;
  final DateTime? profileCompletedAt;

  bool get isActive => status == 'active';

  factory AdminMember.fromJson(Map<String, dynamic> json) {
    final organization = _toMap(json['organization']);
    return AdminMember(
      id: json['id'] is num ? (json['id'] as num).toInt() : null,
      name: _toStr(json['name']),
      memberNo: _toStr(json['member_no']),
      email: _toStr(json['email']),
      phone: _toStr(json['phone']),
      icNumber: _toStr(json['ic_number']),
      branchName: _toStr(json['branch_name']),
      organizationName: organization.isEmpty ? null : _toStr(organization['name']),
      organizationId:
          organization['id'] is num ? (organization['id'] as num).toInt() : null,
      status: _toStr(json['status']),
      createdAt: DateTime.tryParse(_toStr(json['created_at'])),
      profileCompletedAt: DateTime.tryParse(_toStr(json['profile_completed_at'])),
    );
  }
}

/// `GET /admin/members` → paginated envelope (`data` + `meta`).
class PaginatedMembers {
  const PaginatedMembers({
    this.items = const [],
    this.currentPage = 1,
    this.lastPage = 1,
    this.perPage = 25,
    this.total = 0,
  });

  final List<AdminMember> items;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  factory PaginatedMembers.fromJson(Map<String, dynamic> json) {
    final meta = _toMap(json['meta']);
    return PaginatedMembers(
      items: _toMapList(json['data']).map(AdminMember.fromJson).toList(growable: false),
      currentPage: _toInt(meta['current_page']),
      lastPage: _toInt(meta['last_page']),
      perPage: _toInt(meta['per_page']),
      total: _toInt(meta['total']),
    );
  }
}

/// `GET /admin/fees` → `data.fees[]`.
class AdminFee {
  const AdminFee({
    this.id,
    this.userId,
    this.name = '',
    this.memberNo = '',
    this.year = '',
    this.amount = 0,
    this.status = '',
    this.paidAt,
  });

  final int? id;
  final int? userId;
  final String name;
  final String memberNo;
  final String year;
  final double amount;
  final String status; // 'paid' | 'pending'
  final DateTime? paidAt;

  bool get isPaid => status == 'paid';

  factory AdminFee.fromJson(Map<String, dynamic> json) => AdminFee(
        id: json['id'] is num ? (json['id'] as num).toInt() : null,
        userId: json['user_id'] is num ? (json['user_id'] as num).toInt() : null,
        name: _toStr(json['name']),
        memberNo: _toStr(json['member_no']),
        year: _toStr(json['year']),
        amount: _toDouble(json['amount']),
        status: _toStr(json['status']),
        paidAt: DateTime.tryParse(_toStr(json['paid_at'])),
      );
}

/// `GET /admin/fees` → `data.summary`.
class FeesSummary {
  const FeesSummary({
    this.totalMembers = 0,
    this.paidCount = 0,
    this.pendingCount = 0,
    this.revenue = 0,
  });

  final int totalMembers;
  final int paidCount;
  final int pendingCount;
  final double revenue;

  factory FeesSummary.fromJson(Map<String, dynamic> json) => FeesSummary(
        totalMembers: _toInt(json['total_members']),
        paidCount: _toInt(json['paid_count']),
        pendingCount: _toInt(json['pending_count']),
        revenue: _toDouble(json['revenue']),
      );
}

/// `GET /admin/fees` → `data`.
class FeesData {
  const FeesData({required this.summary, this.fees = const []});

  final FeesSummary summary;
  final List<AdminFee> fees;

  factory FeesData.fromJson(Map<String, dynamic> json) => FeesData(
        summary: FeesSummary.fromJson(_toMap(json['summary'])),
        fees: _toMapList(json['fees']).map(AdminFee.fromJson).toList(growable: false),
      );
}

/// `GET /admin/attendance/registrations` → `data.event`.
class AttendanceEvent {
  const AttendanceEvent({this.id, this.title = '', this.startTime});

  final int? id;
  final String title;
  final DateTime? startTime;

  factory AttendanceEvent.fromJson(Map<String, dynamic> json) => AttendanceEvent(
        id: json['id'] is num ? (json['id'] as num).toInt() : null,
        title: _toStr(json['title']),
        startTime: DateTime.tryParse(_toStr(json['start_time'])),
      );
}

/// `GET /admin/attendance/registrations` → `data.registrations[]`.
class Registration {
  const Registration({
    this.id,
    this.name = '',
    this.memberNo = '',
    this.status = '',
    this.attended = false,
    this.attendedAt,
  });

  final int? id;
  final String name;
  final String memberNo;
  final String status;
  final bool attended;
  final DateTime? attendedAt;

  factory Registration.fromJson(Map<String, dynamic> json) => Registration(
        id: json['id'] is num ? (json['id'] as num).toInt() : null,
        name: _toStr(json['name']),
        memberNo: _toStr(json['member_no']),
        status: _toStr(json['status']),
        attended: _toBool(json['attended']),
        attendedAt: DateTime.tryParse(_toStr(json['attended_at'])),
      );
}

/// `GET /admin/attendance/registrations` → `data`.
class AttendanceData {
  const AttendanceData({
    this.event,
    this.totalRegistered = 0,
    this.attendedCount = 0,
    this.registrations = const [],
  });

  final AttendanceEvent? event;
  final int totalRegistered;
  final int attendedCount;
  final List<Registration> registrations;

  factory AttendanceData.fromJson(Map<String, dynamic> json) {
    final stats = _toMap(json['stats']);
    return AttendanceData(
      event: json['event'] is Map<String, dynamic>
          ? AttendanceEvent.fromJson(json['event'] as Map<String, dynamic>)
          : null,
      totalRegistered: _toInt(stats['total_registered']),
      attendedCount: _toInt(stats['attended_count']),
      registrations: _toMapList(json['registrations'])
          .map(Registration.fromJson)
          .toList(growable: false),
    );
  }
}

/// `POST /admin/attendance/scan` → `data`.
class ScanResult {
  const ScanResult({
    this.status = 'error',
    this.message = '',
    this.registration,
  });

  final String status; // 'ok' | 'error'
  final String message;
  final Registration? registration;

  bool get isOk => status == 'ok';

  factory ScanResult.fromJson(Map<String, dynamic> json) {
    final registration = json['registration'];
    return ScanResult(
      status: _toStr(json['status']),
      message: _toStr(json['message']),
      registration: registration is Map<String, dynamic>
          ? Registration.fromJson(registration)
          : null,
    );
  }
}

/// `POST /admin/broadcast` → `data`.
class BroadcastResult {
  const BroadcastResult({this.success = false, this.message = ''});

  final bool success;
  final String message;

  factory BroadcastResult.fromJson(Map<String, dynamic> json) => BroadcastResult(
        success: _toBool(json['success']),
        message: _toStr(json['message']),
      );
}
