// ignore_for_file: non_constant_identifier_names

/// Payload from `GET /member/card`.
///
/// Envelope: `{ card: {...}, qrPrivate: <svg>, qrPublic: <svg>|null }`.
class MemberCardData {
  const MemberCardData({this.card, this.qrPrivate, this.qrPublic});

  factory MemberCardData.fromJson(Map<String, dynamic> json) {
    final card = json['card'];
    return MemberCardData(
      card: card is Map<String, dynamic> ? MemberCardInfo.fromJson(card) : null,
      qrPrivate: json['qrPrivate'] as String?,
      qrPublic: json['qrPublic'] as String?,
    );
  }

  final MemberCardInfo? card;
  final String? qrPrivate;
  final String? qrPublic;

  /// String encoded inside the QR code (rendered client-side with qr_flutter).
  String? get qrValue => card?.qrValue;
}

class MemberCardInfo {
  const MemberCardInfo({
    this.id,
    this.name,
    this.email,
    this.phone,
    this.branch_name,
    this.locality,
    this.profession,
    this.industry,
    this.member_no,
    this.organization,
    this.photo_url,
    this.member_since,
    this.system_logo_path,
    this.qrValue,
  });

  factory MemberCardInfo.fromJson(Map<String, dynamic> json) {
    final org = json['organization'];
    return MemberCardInfo(
      id: (json['id'] as num?)?.toInt(),
      name: json['name'] as String?,
      email: json['email'] as String?,
      phone: json['phone'] as String?,
      branch_name: json['branch_name'] as String?,
      locality: json['locality'] as String?,
      profession: json['profession'] as String?,
      industry: json['industry'] as String?,
      member_no: json['member_no'] as String?,
      organization: org is Map<String, dynamic> ? MemberCardOrg.fromJson(org) : null,
      photo_url: json['photo_url'] as String?,
      member_since: json['member_since'] as String?,
      system_logo_path: json['system_logo_path'] as String?,
      qrValue: json['qr_value'] as String?,
    );
  }

  final int? id;
  final String? name;
  final String? email;
  final String? phone;
  final String? branch_name;
  final String? locality;
  final String? profession;
  final String? industry;
  final String? member_no;
  final MemberCardOrg? organization;
  final String? photo_url;
  final String? member_since;
  final String? system_logo_path;
  final String? qrValue;
}

class MemberCardOrg {
  const MemberCardOrg({this.name, this.slug, this.logo_path});

  factory MemberCardOrg.fromJson(Map<String, dynamic> json) => MemberCardOrg(
        name: json['name'] as String?,
        slug: json['slug'] as String?,
        logo_path: json['logo_path'] as String?,
      );

  final String? name;
  final String? slug;
  final String? logo_path;
}
