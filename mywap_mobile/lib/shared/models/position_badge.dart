/// Jawatan ahli yang dipaparkan sebagai badge pada profil dan kad digital.
///
/// Hanya jawatan aktif dengan `show_badge` dihantar oleh backend, jadi model
/// ini tidak perlu menilai semula tetapan tersebut.
class PositionBadge {
  const PositionBadge({this.id, this.name, this.category, this.color});

  factory PositionBadge.fromJson(Map<String, dynamic> json) {
    return PositionBadge(
      id: (json['id'] as num?)?.toInt(),
      name: json['name'] as String?,
      category: json['category'] as String?,
      color: json['color'] as String?,
    );
  }

  final int? id;
  final String? name;
  final String? category;
  final String? color;

  bool get isEmpty => (name ?? '').trim().isEmpty;

  bool get isNotEmpty => !isEmpty;
}
