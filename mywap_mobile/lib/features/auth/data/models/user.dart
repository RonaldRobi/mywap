// ignore_for_file: non_constant_identifier_names
import 'package:freezed_annotation/freezed_annotation.dart';

import 'organization.dart';

part 'user.freezed.dart';
part 'user.g.dart';

@freezed
sealed class User with _$User {
  const User._();

  const factory User({
    int? id,
    String? name,
    String? email,
    String? phone,
    String? ic_number,
    String? member_no,
    String? dob,
    String? gender,
    String? branch_name,
    bool? is_first_login,
    bool? must_change_password,
    String? profile_completed_at,
    List<String>? roles,
    Organization? organization,
  }) = _User;

  factory User.fromJson(Map<String, dynamic> json) => _$UserFromJson(json);

  /// Backend sets `profile_completed_at` once the member fills in the required
  /// fields (phone, education level, current profession). New members who have
  /// never completed their profile have it null.
  bool get hasCompletedProfile => profile_completed_at != null;

  /// Members who still need to complete their profile before using the app.
  bool get needsProfileCompletion => !hasCompletedProfile && !isAdmin;

  /// Admins/Superadmins are exempt from the forced profile-completion flow,
  /// mirroring the web `EnsureProfileIsComplete` middleware.
  bool get isAdmin => (roles ?? const []).any(
    (role) =>
        role == 'Admin' ||
        role == 'org-admin' ||
        role == 'Superadmin' ||
        role == 'Admin Cawangan',
  );

  bool get isSuperadmin => (roles ?? const []).contains('Superadmin');

  bool get isOrgAdmin =>
      (roles ?? const []).any((role) => role == 'Admin' || role == 'org-admin');

  bool get isBranchAdmin =>
      (roles ?? const []).contains('Admin Cawangan') &&
      !isSuperadmin &&
      !isOrgAdmin;

  int? get currentOrganizationId => organization?.id;

  String? get organizationName => organization?.name;

  /// Role label for UI display.
  String get adminRoleTitle {
    if (isSuperadmin) return 'Superadmin';
    if (isBranchAdmin) {
      return branch_name != null && branch_name!.isNotEmpty
          ? 'Admin Cawangan ($branch_name)'
          : 'Admin Cawangan';
    }
    if (isOrgAdmin) {
      return organization?.name != null
          ? 'Admin ${organization!.name}'
          : 'Admin Organisasi';
    }
    return 'Ahli';
  }

  /// Staff accounts manage the mall catalogue and orders. They buy with a
  /// member account or as a guest, never with their staff account.
  bool get isStaff => (roles ?? const []).any(
    (role) =>
        role == 'Admin' ||
        role == 'org-admin' ||
        role == 'Superadmin' ||
        role == 'Admin Cawangan',
  );
}
