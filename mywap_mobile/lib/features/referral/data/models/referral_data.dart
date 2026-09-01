// ignore_for_file: non_constant_identifier_names

class ReferralData {
  const ReferralData({
    this.referral_link,
    this.member_no,
    this.qr_svg,
    this.stats = const ReferralStats(),
    this.referred_members = const [],
  });

  factory ReferralData.fromJson(Map<String, dynamic> json) {
    final statsJson = json['stats'];
    final membersJson = json['referred_members'];
    return ReferralData(
      referral_link: json['referral_link'] as String?,
      member_no: json['member_no'] as String?,
      qr_svg: json['qr_svg'] as String?,
      stats: statsJson is Map<String, dynamic>
          ? ReferralStats.fromJson(statsJson)
          : const ReferralStats(),
      referred_members: membersJson is List
          ? membersJson
              .whereType<Map<String, dynamic>>()
              .map(ReferredMember.fromJson)
              .toList(growable: false)
          : const [],
    );
  }

  final String? referral_link;
  final String? member_no;
  final String? qr_svg;
  final ReferralStats stats;
  final List<ReferredMember> referred_members;
}

class ReferralStats {
  const ReferralStats({this.total = 0, this.active = 0, this.pending = 0});

  factory ReferralStats.fromJson(Map<String, dynamic> json) => ReferralStats(
        total: (json['total'] as num?)?.toInt() ?? 0,
        active: (json['active'] as num?)?.toInt() ?? 0,
        pending: (json['pending'] as num?)?.toInt() ?? 0,
      );

  final int total;
  final int active;
  final int pending;
}

class ReferredMember {
  const ReferredMember({
    this.id,
    this.name,
    this.member_no,
    this.registered_at,
    this.status,
    this.organization,
  });

  factory ReferredMember.fromJson(Map<String, dynamic> json) => ReferredMember(
        id: (json['id'] as num?)?.toInt(),
        name: json['name'] as String?,
        member_no: json['member_no'] as String?,
        registered_at: json['registered_at'] as String?,
        status: json['status'] as String?,
        organization: json['organization'] as String?,
      );

  final int? id;
  final String? name;
  final String? member_no;
  final String? registered_at;
  final String? status;
  final String? organization;

  bool get isActive => status == 'active';
}
