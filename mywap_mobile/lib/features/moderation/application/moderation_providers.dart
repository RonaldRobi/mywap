import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/moderation_repository.dart';

final moderationRepositoryProvider = Provider<ModerationRepository>(
  (ref) => ModerationRepository(ref.watch(apiClientProvider)),
);

/// Senarai id pengguna yang disekat oleh pengguna semasa.
final blockedUserIdsProvider = FutureProvider<List<int>>(
  (ref) => ref.watch(moderationRepositoryProvider).blockedUserIds(),
);
