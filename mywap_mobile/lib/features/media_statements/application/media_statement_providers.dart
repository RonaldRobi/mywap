import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/media_statement_repository.dart';

final mediaStatementRepositoryProvider = Provider<MediaStatementRepository>(
  (ref) => MediaStatementRepository(ref.watch(apiClientProvider)),
);

final mediaStatementsProvider = FutureProvider<List<MediaStatement>>(
  (ref) => ref.watch(mediaStatementRepositoryProvider).list(),
);

final mediaStatementDetailProvider =
    FutureProvider.family<MediaStatementDetail, int>(
      (ref, id) => ref.watch(mediaStatementRepositoryProvider).detail(id),
    );
