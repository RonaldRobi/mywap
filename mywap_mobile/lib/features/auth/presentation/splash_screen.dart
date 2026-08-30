import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../loading_screen/application/loading_screen_providers.dart';
import '../../loading_screen/presentation/loading_screen.dart';
import '../application/auth_controller.dart';
import '../../onboarding/presentation/onboarding_screen.dart';

/// Startup screen shown while auth state resolves. Memaparkan loading screen
/// GIF (latar gradient + GIF di tengah) yang boleh dikonfigurasi di admin
/// panel — aplikasi Flutter sahaja, web tidak terjejas.
class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  var _redirected = false;
  final _startedAt = DateTime.now();

  @override
  void initState() {
    super.initState();
    ref.listenManual(authControllerProvider, (_, next) => _route(next));
  }

  Future<void> _route(AuthState state) async {
    if (_redirected || state is AuthLoading || !mounted) return;
    _redirected = true;

    // Pastikan loading screen kelihatan sekurang-kurangnya tempoh yang
    // ditetapkan di admin sebelum pergi ke skrin seterusnya.
    final config = ref.read(loadingScreenControllerProvider);
    final duration =
        (config?.enabled ?? true) ? (config?.durationMs ?? 2500) : 0;
    final remaining = duration - DateTime.now().difference(_startedAt).inMilliseconds;
    if (remaining > 0) {
      await Future<void>.delayed(Duration(milliseconds: remaining));
    }
    if (!mounted) return;

    if (state is AuthAuthenticated) {
      context.go('/dashboard');
      return;
    }
    final completed =
        (await SharedPreferences.getInstance()).getBool(
          onboardingCompletedKey,
        ) ??
        false;
    if (mounted) context.go(completed ? '/login' : '/onboarding');
  }

  @override
  Widget build(BuildContext context) {
    _route(ref.watch(authControllerProvider));
    return const LoadingScreenView();
  }
}
