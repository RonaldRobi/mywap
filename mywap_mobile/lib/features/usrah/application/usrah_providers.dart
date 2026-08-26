import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/models/usrah.dart';
import '../data/usrah_repository.dart';

final usrahRepositoryProvider = Provider<UsrahRepository>(
  (ref) => UsrahRepository(ref.watch(apiClientProvider)),
);

final usrahProvider = FutureProvider<UsrahData>(
  (ref) => ref.watch(usrahRepositoryProvider).fetch(),
);
