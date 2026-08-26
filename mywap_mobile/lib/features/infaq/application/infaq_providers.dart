import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/infaq_repository.dart';
import '../data/models/infaq.dart';

final infaqRepositoryProvider = Provider<InfaqRepository>(
  (ref) => InfaqRepository(ref.watch(apiClientProvider)),
);

final infaqListProvider = FutureProvider<InfaqListData>((ref) async {
  return ref.watch(infaqRepositoryProvider).list();
});

final infaqDetailProvider = FutureProvider.family<InfaqDetail, String>(
  (ref, slug) => ref.watch(infaqRepositoryProvider).detail(slug),
);
