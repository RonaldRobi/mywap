import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/providers.dart';
import '../../../core/push/push_providers.dart';
import '../../notifications/application/notification_providers.dart';
import '../data/auth_repository.dart';
import '../data/models/user.dart';

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(
    ref.watch(apiClientProvider),
    ref.watch(tokenStorageProvider),
  ),
);

/// [AuthState] reflects the whole-app auth status.
sealed class AuthState {
  const AuthState();
}

/// Auth is still resolving (startup token check, or login in-flight).
class AuthLoading extends AuthState {
  const AuthLoading();
}

class AuthAuthenticated extends AuthState {
  const AuthAuthenticated(this.user);
  final User user;
}

class AuthUnauthenticated extends AuthState {
  const AuthUnauthenticated({this.error});
  final String? error;
}

final authControllerProvider = NotifierProvider<AuthController, AuthState>(
  AuthController.new,
);

final currentUserProvider = Provider<User?>((ref) {
  final state = ref.watch(authControllerProvider);
  return state is AuthAuthenticated ? state.user : null;
});

/// Sama ada peranti menyokong sebarang kaedah biometrik/PIN peranti (tanpa
/// syarat token/flag). Digunakan oleh toggle di halaman Profil & prompt.
final biometricSupportedProvider = FutureProvider<bool>((ref) async {
  final biometric = ref.watch(biometricServiceProvider);
  return biometric.isDeviceSupported();
});

/// Sama ada peranti menyokong Face ID (untuk label/ikon yang sesuai —
/// "Face ID" di iOS vs "Cap Jari" di Android).
final hasFaceIdProvider = FutureProvider<bool>((ref) async {
  final biometric = ref.watch(biometricServiceProvider);
  return biometric.hasFaceId();
});

/// Sama ada pengguna semasa telah mendayakan log masuk biometrik untuk akaun
/// ini (dibaca daripada secure storage).
final biometricEnabledProvider = FutureProvider<bool>((ref) async {
  final storage = ref.watch(tokenStorageProvider);
  return storage.isBiometricEnabled();
});

/// Sama ada peranti menyokong Face ID / cap jari DAN pengguna telah
/// mendayakannya untuk akaun semasa. Digunakan oleh skrin log masuk untuk
/// papar/sorok butang "Log masuk dengan Face ID/Cap Jari".
final biometricAvailableProvider = FutureProvider<bool>((ref) async {
  if (!await ref.watch(biometricSupportedProvider.future)) return false;
  final storage = ref.watch(tokenStorageProvider);
  final hasToken = await storage.read();
  final enabled = await storage.isBiometricEnabled();
  return hasToken != null && hasToken.isNotEmpty && enabled;
});

class AuthController extends Notifier<AuthState> {
  @override
  AuthState build() {
    _restoreSession();
    return const AuthLoading();
  }

  /// Restores the session from the stored token (startup).
  Future<void> _restoreSession() async {
    final storage = ref.read(tokenStorageProvider);
    final token = await storage.read();
    if (token == null || token.isEmpty) {
      state = const AuthUnauthenticated();
      return;
    }
    try {
      final user = await ref.read(authRepositoryProvider).me();
      state = AuthAuthenticated(user);
      _attachPushHandlers();
      ref.read(pushServiceProvider).init();
    } on ApiException {
      await storage.delete();
      state = const AuthUnauthenticated();
    }
  }

  /// Pasang callback push foreground: papar SnackBar & segarkan badge notifikasi.
  void _attachPushHandlers() {
    final push = ref.read(pushServiceProvider);
    push.onMessage = (payload) {
      final data = _decodePayload(payload);
      final title = (data['title'] as String?) ?? '';
      final body = (data['body'] as String?) ?? '';
      final text =
          body.isNotEmpty
              ? body
              : (title.isEmpty ? 'Notifikasi baharu' : title);
      ref
          .read(appMessengerKeyProvider)
          .currentState
          ?.showSnackBar(
            SnackBar(
              content: Text(text, maxLines: 2, overflow: TextOverflow.ellipsis),
            ),
          );
      ref.invalidate(notificationsControllerProvider);
    };
  }

  Map<String, dynamic> _decodePayload(String payload) {
    try {
      final decoded = jsonDecode(payload);
      return decoded is Map<String, dynamic> ? decoded : const {};
    } catch (_) {
      return const {};
    }
  }

  Future<bool> login({
    String? email,
    String? icNumber,
    required String password,
  }) async {
    state = const AuthLoading();
    try {
      final user = await ref
          .read(authRepositoryProvider)
          .login(email: email, icNumber: icNumber, password: password);
      state = AuthAuthenticated(user);
      // Daftar FCM secara fire-and-forget; tidak menghalang aliran log masuk.
      _attachPushHandlers();
      ref.read(pushServiceProvider).init();
      return true;
    } on ApiException catch (e) {
      state = AuthUnauthenticated(error: e.message);
      return false;
    }
  }

  /// Log masuk semula menggunakan Face ID/cap jari — mengesahkan biometrik
  /// peranti lalu memulihkan sesi dari token yang tersimpan (tiada
  /// kata laluan/OTP diperlukan semula).
  Future<bool> loginWithBiometrics() async {
    final biometric = ref.read(biometricServiceProvider);
    final ok = await biometric.authenticate(
      reason: 'Sahkan Face ID / cap jari untuk log masuk ke myWAP',
    );
    if (!ok) return false;

    state = const AuthLoading();
    try {
      final user = await ref.read(authRepositoryProvider).me();
      state = AuthAuthenticated(user);
      _attachPushHandlers();
      ref.read(pushServiceProvider).init();
      return true;
    } on ApiException catch (e) {
      state = AuthUnauthenticated(error: e.message);
      return false;
    }
  }

  /// Dayakan/lumpuhkan log masuk biometrik untuk akaun semasa.
  Future<bool> setBiometricEnabled(bool enabled) async {
    final biometric = ref.read(biometricServiceProvider);
    if (enabled) {
      final supported = await biometric.isDeviceSupported();
      if (!supported) return false;
      final ok = await biometric.authenticate(
        reason: 'Sahkan Face ID / cap jari untuk mendayakan log masuk pantas',
      );
      if (!ok) return false;
    }
    await ref.read(tokenStorageProvider).setBiometricEnabled(enabled);
    ref.invalidate(biometricAvailableProvider);
    ref.invalidate(biometricEnabledProvider);
    return true;
  }

  Future<void> logout() async {
    ref.read(pushServiceProvider).onMessage = null;
    // Buang token peranti sebelum token auth dipadam, supaya peranti ini tidak
    // lagi menerima push untuk akaun yang telah log keluar.
    await ref.read(pushServiceProvider).unregisterToken();
    await ref.read(authRepositoryProvider).logout();
    state = const AuthUnauthenticated();
  }

  /// Sahkan OTP log masuk kali pertama (dengan/tanpa tetapan kata laluan
  /// baharu) dan log masuk terus menggunakan token yang dikembalikan.
  Future<bool> verifyOtp({
    required String icNumber,
    required String code,
    String? password,
    String? passwordConfirmation,
  }) async {
    state = const AuthLoading();
    try {
      final user = await ref
          .read(authRepositoryProvider)
          .verifyOtp(
            icNumber: icNumber,
            code: code,
            password: password,
            passwordConfirmation: passwordConfirmation,
          );
      state = AuthAuthenticated(user);
      _attachPushHandlers();
      ref.read(pushServiceProvider).init();
      return true;
    } on ApiException catch (e) {
      state = AuthUnauthenticated(error: e.message);
      return false;
    }
  }

  /// Kembali ke skrin log masuk selepas ralat OTP/pendaftaran tanpa
  /// membuang mesej ralat sebelumnya secara tiba-tiba.
  void resetToUnauthenticated({String? error}) {
    state = AuthUnauthenticated(error: error);
  }
}
