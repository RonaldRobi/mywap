import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/member_core_repository.dart';
import '../data/models/announcement.dart';
import '../data/models/fee_status.dart';
import '../data/models/library_item.dart';
import '../data/models/member_card_data.dart';

final memberCoreRepositoryProvider = Provider<MemberCoreRepository>(
  (ref) => MemberCoreRepository(ref.watch(apiClientProvider)),
);

final memberCardProvider = FutureProvider<MemberCardData>((ref) async {
  return ref.watch(memberCoreRepositoryProvider).card();
});

final memberAnnouncementsProvider = FutureProvider<List<Announcement>>(
  (ref) async {
    return ref.watch(memberCoreRepositoryProvider).announcements();
  },
);

final memberLibraryProvider = FutureProvider<List<LibraryItem>>((ref) async {
  return ref.watch(memberCoreRepositoryProvider).library();
});

final memberFeeStatusProvider = FutureProvider<FeeStatus>((ref) async {
  return ref.watch(memberCoreRepositoryProvider).feeStatus();
});
