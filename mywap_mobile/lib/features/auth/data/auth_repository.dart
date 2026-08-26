import 'dart:convert';

import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/token_storage.dart';
import 'models/user.dart';

/// Auth endpoints wrapper. UI never touches HTTP directly.
class AuthRepository {
  AuthRepository(this._api, this._tokenStorage);

  final ApiClient _api;
  final TokenStorage _tokenStorage;

  Future<User> login({String? email, String? icNumber, required String password}) async {
    final identifier =
        (email != null && email.isNotEmpty) ? email.trim() : (icNumber?.trim() ?? '');
    final isEmail = identifier.contains('@');
    final data = await _api.post(
      ApiPaths.login,
      body: {
        if (isEmail) 'email': identifier,
        if (!isEmail) 'ic_number': identifier,
        'password': password,
      },
    ) as Map<String, dynamic>;

    final token = data['token'] as String?;
    if (token == null) {
      throw const FormatException('Tiada token dalam respons.');
    }
    await _tokenStorage.write(token);

    final userJson = data['user'];
    return User.fromJson(userJson is Map<String, dynamic> ? userJson : {});
  }

  Future<void> logout() async {
    try {
      await _api.post(ApiPaths.logout);
    } finally {
      await _tokenStorage.delete();
    }
  }

  Future<User> me() async {
    final data = await _api.get(ApiPaths.me);
    if (data is String) {
      return User.fromJson(jsonDecode(data) as Map<String, dynamic>);
    }
    return User.fromJson((data as Map<String, dynamic>?) ?? {});
  }
}
