// ignore_for_file: non_constant_identifier_names
import 'package:freezed_annotation/freezed_annotation.dart';

part 'organization.freezed.dart';
part 'organization.g.dart';

@freezed
sealed class Organization with _$Organization {
  const factory Organization({
    int? id,
    String? name,
    String? slug,
    String? color_theme,
    String? logo_path,
  }) = _Organization;

  factory Organization.fromJson(Map<String, dynamic> json) => _$OrganizationFromJson(json);
}
