import 'dart:convert';

import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/profile_data.dart';

/// Talks to the Profile REST endpoints. Every response is wrapped in the
/// `{ data: ... }` envelope which [ApiClient] unwraps before returning.
class ProfileRepository {
  ProfileRepository(this._api);

  final ApiClient _api;

  /// `GET /profile` — full profile with journey history + attended programs.
  Future<ProfileData> fetchProfile() async {
    final data = await _api.get(ApiPaths.profile);
    return ProfileData.fromJson(_asMap(data));
  }

  /// `GET /profile/edit-meta` — branch options for the edit form.
  Future<EditMeta> editMeta() async {
    final data = await _api.get(ApiPaths.profileEditMeta);
    return EditMeta.fromJson(_asMap(data));
  }

  /// `GET /profile/complete` — DOB + gender guessed from the IC number.
  Future<CompleteMeta> completeMeta() async {
    final data = await _api.get(ApiPaths.profileComplete);
    return CompleteMeta.fromJson(_asMap(data));
  }

  /// `PUT /profile`. [ApiClient] exposes POST only; the Laravel/Symfony stack
  /// honours the `_method` override on JSON bodies, so a POST carrying
  /// `_method: PUT` is routed to the PUT handler.
  Future<ProfileUser> updateProfile(Map<String, dynamic> body) async {
    final data = await _api.post(
      ApiPaths.profile,
      body: {'_method': 'PUT', ...body},
    );
    return ProfileUser.fromJson(_asMap(data));
  }

  /// `POST /profile/complete` — finalizes the mandatory onboarding profile.
  Future<ProfileUser> completeProfile(Map<String, dynamic> body) async {
    final data = await _api.post(ApiPaths.profileComplete, body: body);
    return ProfileUser.fromJson(_asMap(data));
  }

  static Map<String, dynamic> _asMap(dynamic data) {
    if (data is Map<String, dynamic>) return data;
    if (data is String) {
      final decoded = jsonDecode(data);
      if (decoded is Map<String, dynamic>) return decoded;
    }
    return <String, dynamic>{};
  }
}
