import 'admin_dashboard_screen.dart';

export 'admin_dashboard_screen.dart';

/// Landing screen for the "Admin" bottom-nav tab.
///
/// The central router (`app_router.dart`) references this file for the
/// `/admin` tab route. The real landing is [AdminDashboardScreen]; this alias
/// keeps the central router untouched.
typedef AdminLandingScreen = AdminDashboardScreen;
