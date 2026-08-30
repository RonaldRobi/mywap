import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../shared/theme/app_colors.dart';
import '../application/auth_controller.dart';
import '../../onboarding/presentation/onboarding_screen.dart';

/// Startup screen shown while auth state resolves (§11.5 — native splash
/// preferred; this is a minimal Dart fallback while providers load).
class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  var _redirected = false;

  @override
  void initState() {
    super.initState();
    ref.listenManual(authControllerProvider, (_, next) => _route(next));
  }

  Future<void> _route(AuthState state) async {
    if (_redirected || state is AuthLoading || !mounted) return;
    _redirected = true;
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
    return const Scaffold(
      backgroundColor: AppColors.movementDarkGreen,
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.volunteer_activism,
              size: 72,
              color: AppColors.movementSoftGreen,
            ),
            SizedBox(height: 16),
            Text(
              'myWAP',
              style: TextStyle(
                color: AppColors.white,
                fontSize: 28,
                fontWeight: FontWeight.w700,
                letterSpacing: 1.5,
              ),
            ),
            SizedBox(height: 24),
            SizedBox(
              width: 32,
              height: 32,
              child: CircularProgressIndicator(
                strokeWidth: 3,
                color: AppColors.movementSoftGreen,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
