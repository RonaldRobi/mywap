import 'package:go_router/go_router.dart';

import 'announcements_screen.dart';
import 'fee_status_screen.dart';
import 'library_screen.dart';
import 'member_card_screen.dart';

/// Routes owned by the member core feature.
final List<RouteBase> memberRoutes = [
  GoRoute(
    path: '/card',
    builder: (_, __) => const MemberCardScreen(),
  ),
  GoRoute(
    path: '/member/announcements',
    builder: (_, __) => const AnnouncementsScreen(),
  ),
  GoRoute(
    path: '/member/library',
    builder: (_, __) => const LibraryScreen(),
  ),
  GoRoute(
    path: '/member/fee-status',
    builder: (_, __) => const FeeStatusScreen(),
  ),
];
