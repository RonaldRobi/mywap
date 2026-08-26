// ignore_for_file: non_constant_identifier_names
import 'package:freezed_annotation/freezed_annotation.dart';

import 'organization.dart';

part 'user.freezed.dart';
part 'user.g.dart';

@freezed
sealed class User with _$User {
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
    List<String>? roles,
    Organization? organization,
  }) = _User;

  factory User.fromJson(Map<String, dynamic> json) => _$UserFromJson(json);
}
