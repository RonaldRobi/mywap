class UsrahMember {
  const UsrahMember({this.id, this.name, this.role});

  final int? id;
  final String? name;
  final String? role;

  factory UsrahMember.fromJson(Map<String, dynamic> json) => UsrahMember(
        id: json['id'] as int?,
        name: json['name'] as String?,
        role: json['role'] as String? ?? 'member',
      );
}

class UsrahGroup {
  const UsrahGroup({
    this.id,
    this.name,
    this.description,
    this.meetingDay,
    this.meetingTime,
    this.isLeader = false,
    this.members = const [],
  });

  final int? id;
  final String? name;
  final String? description;
  final String? meetingDay;
  final String? meetingTime;
  final bool isLeader;
  final List<UsrahMember> members;

  factory UsrahGroup.fromJson(Map<String, dynamic> json) => UsrahGroup(
        id: json['id'] as int?,
        name: json['name'] as String?,
        description: json['description'] as String?,
        meetingDay: json['meeting_day'] as String?,
        meetingTime: json['meeting_time'] as String?,
        isLeader: json['is_leader'] as bool? ?? false,
        members: _parseMembers(json['members']),
      );
}

class UsrahAttendance {
  const UsrahAttendance({this.date, this.status, this.notes});

  final String? date;
  final String? status;
  final String? notes;

  factory UsrahAttendance.fromJson(Map<String, dynamic> json) =>
      UsrahAttendance(
        date: json['date'] as String?,
        status: json['status'] as String?,
        notes: json['notes'] as String?,
      );
}

class UsrahData {
  const UsrahData({
    this.groups = const [],
    this.attendanceHistory = const [],
  });

  final List<UsrahGroup> groups;
  final List<UsrahAttendance> attendanceHistory;

  factory UsrahData.fromJson(Map<String, dynamic> json) => UsrahData(
        groups: _parse(json['groups'], UsrahGroup.fromJson),
        attendanceHistory:
            _parse(json['attendanceHistory'], UsrahAttendance.fromJson),
      );
}

List<UsrahMember> _parseMembers(dynamic value) {
  if (value is! List) return const [];
  return value
      .whereType<Map<String, dynamic>>()
      .map(UsrahMember.fromJson)
      .toList(growable: false);
}

List<T> _parse<T>(dynamic value, T Function(Map<String, dynamic>) fromJson) {
  if (value is! List) return const [];
  return value
      .whereType<Map<String, dynamic>>()
      .map(fromJson)
      .toList(growable: false);
}
