import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../features/auth/application/auth_controller.dart';
import '../constants/prefs_keys.dart';
import '../deeplink/deep_links.dart';

/// Destinasi selepas splash atau selepas pengguna memilih bahasa.
///
/// Keutamaan: deep link yang menunggu → dashboard ahli → onboarding (jika
/// belum selesai) → laman awam.
Future<String> resolveStartDestination(WidgetRef ref) async {
  final pending = ref.read(pendingDeepLinkProvider);
  if (pending != null) {
    ref.read(pendingDeepLinkProvider.notifier).state = null;
    return pending;
  }

  if (ref.read(authControllerProvider) is AuthAuthenticated) {
    return '/dashboard';
  }

  final prefs = await SharedPreferences.getInstance();
  final completed = prefs.getBool(onboardingCompletedKey) ?? false;
  return completed ? '/home' : '/onboarding';
}
