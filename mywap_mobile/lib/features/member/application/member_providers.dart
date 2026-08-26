import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/member_repository.dart';
import '../data/models/dashboard_data.dart';

final memberRepositoryProvider = Provider<MemberRepository>(
  (ref) => MemberRepository(ref.watch(apiClientProvider)),
);

final memberDashboardProvider = FutureProvider<DashboardData>((ref) async {
  return ref.watch(memberRepositoryProvider).dashboard();
});
