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

    return _persistSession(data);
  }

  Future<void> logout() async {
    try {
      await _api.post(ApiPaths.logout);
    } finally {
      await _tokenStorage.delete();
      await _tokenStorage.setBiometricEnabled(false);
    }
  }

  Future<User> me() async {
    final data = await _api.get(ApiPaths.me);
    if (data is String) {
      return User.fromJson(jsonDecode(data) as Map<String, dynamic>);
    }
    return User.fromJson((data as Map<String, dynamic>?) ?? {});
  }

  /// Daftar ahli baharu — mengembalikan no. ahli & mesej makluman.
  Future<Map<String, dynamic>> register({
    required String name,
    required String email,
    required String icNumber,
    String? phone,
    String? dob,
    int? branchId,
    String? referralCode,
  }) async {
    final data = await _api.post(
      ApiPaths.register,
      body: {
        'name': name,
        'email': email,
        'ic_number': icNumber,
        if (phone != null && phone.isNotEmpty) 'phone': phone,
        if (dob != null && dob.isNotEmpty) 'dob': dob,
        if (branchId != null) 'branch_id': branchId,
        if (referralCode != null && referralCode.isNotEmpty)
          'referral_code': referralCode,
      },
    );
    return (data as Map<String, dynamic>?) ?? {};
  }

  /// Semak kod rujukan (referral) — memaparkan nama penjemput sebelum daftar.
  Future<Map<String, dynamic>?> resolveReferral(String code) async {
    try {
      final data = await _api.get(ApiPaths.resolveReferral(code));
      return data as Map<String, dynamic>?;
    } catch (_) {
      return null;
    }
  }

  /// Semak status ahli (IC/No Ahli/Emel) sebelum log masuk.
  Future<Map<String, dynamic>> checkMember(String identifier) async {
    final data = await _api.post(
      ApiPaths.checkMember,
      body: {'identifier': identifier},
    );
    return (data as Map<String, dynamic>?) ?? {};
  }

  /// Lupa No. Ahli — langkah 1 (IC sahaja) atau langkah 2 (+ tarikh lahir).
  Future<Map<String, dynamic>> forgotId(String icNumber, {String? dob}) async {
    final data = await _api.post(
      ApiPaths.forgotId,
      body: {
        'ic_number': icNumber,
        if (dob != null) 'dob': dob,
      },
    );
    return (data as Map<String, dynamic>?) ?? {};
  }

  /// Lupa kata laluan — hantar pautan reset ke emel berdaftar.
  Future<Map<String, dynamic>> forgotPassword(String icNumber) async {
    final data = await _api.post(
      ApiPaths.forgotPassword,
      body: {'ic_number': icNumber},
    );
    return (data as Map<String, dynamic>?) ?? {};
  }

  /// Log masuk kali pertama — langkah 1: hantar OTP.
  Future<Map<String, dynamic>> sendOtp(String icNumber) async {
    final data = await _api.post(
      ApiPaths.sendOtp,
      body: {'ic_number': icNumber},
    );
    return (data as Map<String, dynamic>?) ?? {};
  }

  /// Log masuk kali pertama — kemas kini emel dahulu, lalu hantar OTP.
  Future<Map<String, dynamic>> updateAndSendOtp(
    String icNumber,
    String email,
  ) async {
    final data = await _api.post(
      ApiPaths.updateAndSendOtp,
      body: {'ic_number': icNumber, 'email': email},
    );
    return (data as Map<String, dynamic>?) ?? {};
  }

  /// Log masuk kali pertama — langkah akhir: sahkan OTP (+ tetapkan kata
  /// laluan baharu). Mengembalikan [User] & menyimpan token Sanctum.
  Future<User> verifyOtp({
    required String icNumber,
    required String code,
    String? password,
    String? passwordConfirmation,
  }) async {
    final data = await _api.post(
      ApiPaths.verifyOtp,
      body: {
        'ic_number': icNumber,
        'code': code,
        if (password != null) 'password': password,
        if (passwordConfirmation != null)
          'password_confirmation': passwordConfirmation,
      },
    ) as Map<String, dynamic>;

    return _persistSession(data);
  }

  Future<User> _persistSession(Map<String, dynamic> data) async {
    final token = data['token'] as String?;
    if (token == null) {
      throw const FormatException('Tiada token dalam respons.');
    }
    await _tokenStorage.write(token);

    final userJson = data['user'];
    return User.fromJson(userJson is Map<String, dynamic> ? userJson : {});
  }
}
