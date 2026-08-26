import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/providers.dart';
import '../../../core/push/push_providers.dart';
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

final authControllerProvider =
    NotifierProvider<AuthController, AuthState>(AuthController.new);

final currentUserProvider = Provider<User?>((ref) {
  final state = ref.watch(authControllerProvider);
  return state is AuthAuthenticated ? state.user : null;
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
    } on ApiException {
      await storage.delete();
      state = const AuthUnauthenticated();
    }
  }

  Future<bool> login({
    String? email,
    String? icNumber,
    required String password,
  }) async {
    state = const AuthLoading();
    try {
      final user = await ref.read(authRepositoryProvider).login(
            email: email,
            icNumber: icNumber,
            password: password,
          );
      state = AuthAuthenticated(user);
      // Daftar FCM secara fire-and-forget; tidak menghalang aliran log masuk.
      ref.read(pushServiceProvider).init();
      return true;
    } on ApiException catch (e) {
      state = AuthUnauthenticated(error: e.message);
      return false;
    }
  }

  Future<void> logout() async {
    await ref.read(authRepositoryProvider).logout();
    state = const AuthUnauthenticated();
  }
}
