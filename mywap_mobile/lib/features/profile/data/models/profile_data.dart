// ignore_for_file: non_constant_identifier_names
//
// Models for the Profile feature.
//
// JSON keys match `ProfileService::serializeProfile()` / `showPayload()` /
// `completeMeta()` / `editMeta()` in the Laravel backend. Dart field names
// mirror the JSON keys 1:1 (snake_case / camelCase as the API uses them) so
// no `@JsonKey` remapping is required. Models are plain immutable classes
// with manual `fromJson` (no freezed / build_runner).

/// Parses a list of maps into typed models.
List<T> _parseList<T>(
  dynamic value,
  T Function(Map<String, dynamic>) fromJson,
) {
  if (value is! List) return const [];
  return value
      .whereType<Map<String, dynamic>>()
      .map(fromJson)
      .toList(growable: false);
}

Map<String, dynamic>? _asMap(dynamic value) {
  return value is Map<String, dynamic> ? value : null;
}

/// Parses a nullable map, returning `null` when the key is absent.
T? _fromJsonOrNull<T>(
  dynamic value,
  T Function(Map<String, dynamic>) fromJson,
) {
  final map = _asMap(value);
  return map == null ? null : fromJson(map);
}

/// A small organization reference (id + name + slug + theme accent).
class ProfileOrganization {
  const ProfileOrganization({
    this.id,
    this.name,
    this.slug,
    this.color_theme,
  });

  factory ProfileOrganization.fromJson(Map<String, dynamic> json) {
    return ProfileOrganization(
      id: json['id'] as int?,
      name: json['name'] as String?,
      slug: json['slug'] as String?,
      color_theme: json['color_theme'] as String?,
    );
  }

  final int? id;
  final String? name;
  final String? slug;
  final String? color_theme;
}

/// Membership fee status from `FeeService::getStatus()`.
class ProfileFeeStatus {
  const ProfileFeeStatus({
    this.status,
    this.amount_due,
    this.last_paid_at,
    this.last_reference,
  });

  factory ProfileFeeStatus.fromJson(Map<String, dynamic> json) {
    return ProfileFeeStatus(
      status: json['status'] as String?,
      amount_due: (json['amount_due'] as num?)?.toDouble(),
      last_paid_at: json['last_paid_at'] as String?,
      last_reference: json['last_reference'] as String?,
    );
  }

  /// `active` or `due`.
  final String? status;
  final double? amount_due;
  final String? last_paid_at;
  final String? last_reference;

  bool get isActive => status == 'active';
}

/// An organization transition record on the member journey timeline.
class ProfileHistoryEntry {
  const ProfileHistoryEntry({
    this.id,
    this.from_organization,
    this.to_organization,
    this.transitioned_at,
    this.transitioned_at_human,
  });

  factory ProfileHistoryEntry.fromJson(Map<String, dynamic> json) {
    return ProfileHistoryEntry(
      id: json['id'] as int?,
      from_organization: _fromJsonOrNull(
        json['from_organization'],
        ProfileOrganization.fromJson,
      ),
      to_organization: _fromJsonOrNull(
        json['to_organization'],
        ProfileOrganization.fromJson,
      ),
      transitioned_at: json['transitioned_at'] as String?,
      transitioned_at_human: json['transitioned_at_human'] as String?,
    );
  }

  final int? id;
  final ProfileOrganization? from_organization;
  final ProfileOrganization? to_organization;
  final String? transitioned_at;
  final String? transitioned_at_human;
}

/// Small org reference nested inside an attended program event.
class ProfileProgramOrg {
  const ProfileProgramOrg({this.name, this.color_theme});

  factory ProfileProgramOrg.fromJson(Map<String, dynamic> json) {
    return ProfileProgramOrg(
      name: json['name'] as String?,
      color_theme: json['color_theme'] as String?,
    );
  }

  final String? name;
  final String? color_theme;
}

/// An event the member has attended (from `EventRsvp` with status `attended`).
class ProfileProgramEvent {
  const ProfileProgramEvent({
    this.id,
    this.title,
    this.start_formatted,
    this.location_or_link,
    this.organization,
  });

  factory ProfileProgramEvent.fromJson(Map<String, dynamic> json) {
    return ProfileProgramEvent(
      id: json['id'] as int?,
      title: json['title'] as String?,
      start_formatted: json['start_formatted'] as String?,
      location_or_link: json['location_or_link'] as String?,
      organization: _fromJsonOrNull(
        json['organization'],
        ProfileProgramOrg.fromJson,
      ),
    );
  }

  final int? id;
  final String? title;
  final String? start_formatted;
  final String? location_or_link;
  final ProfileProgramOrg? organization;
}

/// An attended program on the member journey.
class ProfileAttendedProgram {
  const ProfileAttendedProgram({
    this.id,
    this.event,
    this.attended_at,
    this.attended_at_human,
  });

  factory ProfileAttendedProgram.fromJson(Map<String, dynamic> json) {
    return ProfileAttendedProgram(
      id: json['id'] as int?,
      event: _fromJsonOrNull(json['event'], ProfileProgramEvent.fromJson),
      attended_at: json['attended_at'] as String?,
      attended_at_human: json['attended_at_human'] as String?,
    );
  }

  final int? id;
  final ProfileProgramEvent? event;
  final String? attended_at;
  final String? attended_at_human;
}

/// The flat profile shape produced by `ProfileService::serializeProfile()`.
class ProfileUser {
  const ProfileUser({
    this.id,
    this.member_no,
    this.name,
    this.email,
    this.phone,
    this.ic_number,
    this.roles,
    this.dob,
    this.age,
    this.gender,
    this.marital_status,
    this.education_level,
    this.current_profession,
    this.industry,
    this.expertise,
    this.topics,
    this.position,
    this.branch_name,
    this.locality,
    this.linkedin_url,
    this.address_1,
    this.address_2,
    this.postcode,
    this.city,
    this.state,
    this.emergency_contact_name,
    this.emergency_contact_phone,
    this.organization,
    this.feeStatus,
    this.photo_url,
  });

  factory ProfileUser.fromJson(Map<String, dynamic> json) {
    return ProfileUser(
      id: json['id'] as int?,
      member_no: json['member_no'] as String?,
      name: json['name'] as String?,
      email: json['email'] as String?,
      phone: json['phone'] as String?,
      ic_number: json['ic_number'] as String?,
      roles: json['roles'] is List
          ? (json['roles'] as List).whereType<String>().toList(growable: false)
          : null,
      dob: json['dob'] as String?,
      age: json['age'] as int?,
      gender: json['gender'] as String?,
      marital_status: json['marital_status'] as String?,
      education_level: json['education_level'] as String?,
      current_profession: json['current_profession'] as String?,
      industry: json['industry'] as String?,
      expertise: json['expertise'] as String?,
      topics: json['topics'] as String?,
      position: json['position'] as String?,
      branch_name: json['branch_name'] as String?,
      locality: json['locality'] as String?,
      linkedin_url: json['linkedin_url'] as String?,
      address_1: json['address_1'] as String?,
      address_2: json['address_2'] as String?,
      postcode: json['postcode'] as String?,
      city: json['city'] as String?,
      state: json['state'] as String?,
      emergency_contact_name: json['emergency_contact_name'] as String?,
      emergency_contact_phone: json['emergency_contact_phone'] as String?,
      organization: _fromJsonOrNull(
        json['organization'],
        ProfileOrganization.fromJson,
      ),
      feeStatus: _fromJsonOrNull(json['feeStatus'], ProfileFeeStatus.fromJson),
      // Backend does not emit a photo on this payload today; kept optional so
      // the UI degrades to a placeholder rather than crashing if added later.
      photo_url:
          json['photo_url'] as String? ?? json['profile_photo_path'] as String?,
    );
  }

  final int? id;
  final String? member_no;
  final String? name;
  final String? email;
  final String? phone;
  final String? ic_number;
  final List<String>? roles;
  final String? dob;
  final int? age;
  final String? gender;
  final String? marital_status;
  final String? education_level;
  final String? current_profession;
  final String? industry;
  final String? expertise;
  final String? topics;
  final String? position;
  final String? branch_name;
  final String? locality;
  final String? linkedin_url;
  final String? address_1;
  final String? address_2;
  final String? postcode;
  final String? city;
  final String? state;
  final String? emergency_contact_name;
  final String? emergency_contact_phone;
  final ProfileOrganization? organization;
  final ProfileFeeStatus? feeStatus;
  final String? photo_url;

  /// Mirrors the backend's completion rule: a profile is complete once phone,
  /// education level and current profession are all filled in.
  bool get isComplete {
    bool has(String? value) => value != null && value.trim().isNotEmpty;
    return has(phone) && has(education_level) && has(current_profession);
  }
}

/// Full profile payload from `GET /profile` (`ProfileService::showPayload()`).
class ProfileData {
  const ProfileData({
    this.profileUser,
    this.history,
    this.attendedPrograms,
  });

  factory ProfileData.fromJson(Map<String, dynamic> json) {
    return ProfileData(
      profileUser: _fromJsonOrNull(json['profileUser'], ProfileUser.fromJson),
      history: _parseList(json['history'], ProfileHistoryEntry.fromJson),
      attendedPrograms: _parseList(
        json['attendedPrograms'],
        ProfileAttendedProgram.fromJson,
      ),
    );
  }

  final ProfileUser? profileUser;
  final List<ProfileHistoryEntry>? history;
  final List<ProfileAttendedProgram>? attendedPrograms;
}

/// Meta for the "complete profile" screen (`ProfileService::completeMeta()`).
class CompleteMeta {
  const CompleteMeta({this.parsedDob, this.parsedGender});

  factory CompleteMeta.fromJson(Map<String, dynamic> json) {
    return CompleteMeta(
      parsedDob: json['parsedDob'] as String?,
      parsedGender: json['parsedGender'] as String?,
    );
  }

  /// `YYYY-MM-DD` extracted from the member's IC number.
  final String? parsedDob;

  /// `lelaki` or `perempuan` guessed from the IC number.
  final String? parsedGender;
}

/// A branch option for the edit form (`ProfileService::editMeta()`).
class ProfileBranch {
  const ProfileBranch({this.id, this.name, this.state});

  factory ProfileBranch.fromJson(Map<String, dynamic> json) {
    return ProfileBranch(
      id: json['id'] as int?,
      name: json['name'] as String?,
      state: json['state'] as String?,
    );
  }

  final int? id;
  final String? name;
  final String? state;
}

/// Pending branch-change request surfaced by `editMeta()`.
class PendingBranchRequest {
  const PendingBranchRequest({this.to_branch});

  factory PendingBranchRequest.fromJson(Map<String, dynamic> json) {
    return PendingBranchRequest(to_branch: json['to_branch'] as String?);
  }

  final String? to_branch;
}

/// Meta for the edit profile form (`ProfileService::editMeta()`).
class EditMeta {
  const EditMeta({
    this.branches,
    this.canEditIcNumber,
    this.pendingBranchRequest,
  });

  factory EditMeta.fromJson(Map<String, dynamic> json) {
    return EditMeta(
      branches: _parseList(json['branches'], ProfileBranch.fromJson),
      canEditIcNumber: json['canEditIcNumber'] as bool?,
      pendingBranchRequest: _fromJsonOrNull(
        json['pendingBranchRequest'],
        PendingBranchRequest.fromJson,
      ),
    );
  }

  final List<ProfileBranch>? branches;
  final bool? canEditIcNumber;
  final PendingBranchRequest? pendingBranchRequest;
}
