import 'package:flutter/material.dart';

import '../../../shared/theme/app_colors.dart';

/// Startup screen shown while auth state resolves (§11.5 — native splash
/// preferred; this is a minimal Dart fallback while providers load).
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: AppColors.movementDarkGreen,
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.volunteer_activism, size: 72, color: AppColors.movementSoftGreen),
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
