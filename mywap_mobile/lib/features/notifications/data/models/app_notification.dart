/// A Laravel database notification. Keys match `NotificationService::notifications`.
class AppNotification {
  const AppNotification({
    this.id,
    this.type,
    this.data = const {},
    this.createdAt,
    this.readAt,
  });

  final String? id;
  final String? type;
  final Map<String, dynamic> data;
  final DateTime? createdAt;
  final DateTime? readAt;

  bool get isRead => readAt != null;

  AppNotification copyWith({DateTime? readAt}) => AppNotification(
        id: id,
        type: type,
        data: data,
        createdAt: createdAt,
        readAt: readAt ?? this.readAt,
      );

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    final rawData = json['data'];
    return AppNotification(
      id: json['id']?.toString(),
      type: json['type'] as String?,
      data: rawData is Map<String, dynamic> ? rawData : const <String, dynamic>{},
      createdAt: _parseTime(json['created_at']),
      readAt: _parseTime(json['read_at']),
    );
  }

  static DateTime? _parseTime(dynamic value) {
    if (value is! String) return null;
    final parsed = DateTime.tryParse(value);
    return parsed?.toLocal();
  }
}
