import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/admin/presentation/admin_landing_screen.dart';
import '../../features/admin/presentation/routes.dart';
import '../../features/auth/application/auth_controller.dart';
import '../../features/auth/presentation/first_login_otp_screen.dart';
import '../../features/auth/presentation/forgot_id_screen.dart';
import '../../features/auth/presentation/forgot_password_screen.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/register_screen.dart';
import '../../features/auth/presentation/splash_screen.dart';
import '../../features/onboarding/presentation/onboarding_screen.dart';
import '../../features/directory/presentation/routes.dart';
import '../../features/ecommerce/presentation/routes.dart';
import '../../features/events/presentation/event_detail_screen.dart';
import '../../features/events/presentation/events_screen.dart';
import '../../features/events/presentation/routes.dart';
import '../../features/facilities/presentation/routes.dart';
import '../../features/forms/presentation/routes.dart';
import '../../features/infaq/presentation/infaq_landing_screen.dart';
import '../../features/infaq/presentation/routes.dart';
import '../../features/member/presentation/main_shell.dart';
import '../../features/member/presentation/member_dashboard_screen.dart';
import '../../features/member/presentation/routes.dart';
import '../../features/menu/presentation/menu_screen.dart';
import '../../features/menu/presentation/routes.dart';
import '../../features/financial/presentation/financial_overview_screen.dart';
import '../../features/news/presentation/routes.dart';
import '../../features/organization/presentation/organization_info_screen.dart';
import '../../features/polls/presentation/routes.dart';
import '../../features/profile/presentation/routes.dart';
import '../../features/referral/presentation/referral_screen.dart';
import '../../features/scan/presentation/member_scan_screen.dart';
import '../../features/usrah/presentation/routes.dart';
import '../../shared/screens/route_not_found_screen.dart';

/// Notifies go_router whenever auth state changes so redirects re-evaluate.
class _AuthRefresh extends ChangeNotifier {
  void notify() => notifyListeners();
}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = _AuthRefresh();
  ref.listen(authControllerProvider, (_, __) => refresh.notify());

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: refresh,
    errorBuilder: (context, state) =>
        RouteNotFoundScreen(message: state.error?.toString()),
    redirect: (context, state) {
      final auth = ref.read(authControllerProvider);
      final location = state.matchedLocation;
      final isSplash = location == '/splash';
      final isLogin = location == '/login';
      final isOnboarding = location == '/onboarding';
      final isPublicAuthFlow = location == '/register' ||
          location == '/forgot-password' ||
          location == '/forgot-id' ||
          location == '/first-login';

      if (auth is AuthLoading) {
        return isSplash || isLogin || isOnboarding || isPublicAuthFlow
            ? null
            : '/splash';
      }
      if (auth is AuthAuthenticated) {
        return isSplash || isLogin || isOnboarding ? '/dashboard' : null;
      }
      return isLogin || isSplash || isOnboarding || isPublicAuthFlow
          ? null
          : '/login';
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, __) => const SplashScreen()),
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      GoRoute(
        path: '/register',
        builder: (_, state) => RegisterScreen(
          referralCode: state.uri.queryParameters['ref'],
        ),
      ),
      GoRoute(
        path: '/forgot-password',
        builder: (_, __) => const ForgotPasswordScreen(),
      ),
      GoRoute(
        path: '/forgot-id',
        builder: (_, __) => const ForgotIdScreen(),
      ),
      GoRoute(
        path: '/first-login',
        builder: (_, state) => FirstLoginOtpScreen(
          icNumber: state.uri.queryParameters['ic'],
        ),
      ),
      GoRoute(
        path: '/onboarding',
        builder: (_, __) => const OnboardingScreen(),
      ),
      ShellRoute(
        builder: (_, state, child) => MainShell(child: child),
        routes: [
          GoRoute(
            path: '/dashboard',
            builder: (_, __) => const MemberDashboardScreen(),
          ),
          GoRoute(path: '/events', builder: (_, __) => const EventsScreen()),
          GoRoute(
            path: '/infaq',
            builder: (_, __) => const InfaqLandingScreen(),
          ),
          GoRoute(path: '/menu', builder: (_, __) => const MenuScreen()),
          GoRoute(
            path: '/admin',
            builder: (_, __) => const AdminLandingScreen(),
          ),
        ],
      ),
      // Static event sub-routes must be matched before `/events/:id`.
      ...eventsRoutes,
      GoRoute(
        path: '/events/:id',
        builder:
            (_, state) => EventDetailScreen(
              eventId: int.parse(state.pathParameters['id']!),
            ),
      ),
      GoRoute(path: '/scan', builder: (_, __) => const MemberScanScreen()),
      GoRoute(
        path: '/organization/info',
        builder: (_, __) => const OrganizationInfoScreen(),
      ),
      GoRoute(
        path: '/member/referral',
        builder: (_, __) => const ReferralScreen(),
      ),
      GoRoute(
        path: '/member/financial/overview',
        builder: (_, __) => const FinancialOverviewScreen(),
      ),
      ...memberRoutes,
      ...profileRoutes,
      ...infaqRoutes,
      ...ecommerceRoutes,
      ...newsRoutes,
      ...facilitiesRoutes,
      ...usrahRoutes,
      ...pollsRoutes,
      ...formsRoutes,
      ...directoryRoutes,
      ...adminRoutes,
      ...menuRoutes,
    ],
  );
});
