import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/models/poll.dart';
import '../data/poll_repository.dart';

final pollRepositoryProvider = Provider<PollRepository>(
  (ref) => PollRepository(ref.watch(apiClientProvider)),
);

final pollsProvider = FutureProvider<PollListData>(
  (ref) => ref.watch(pollRepositoryProvider).list(),
);

final pollDetailProvider = FutureProvider.family<Poll, int>(
  (ref, id) => ref.watch(pollRepositoryProvider).detail(id),
);

final pollResultsProvider = FutureProvider.family<PollResults, int>(
  (ref, id) => ref.watch(pollRepositoryProvider).results(id),
);
