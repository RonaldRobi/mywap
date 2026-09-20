import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/public_home_repository.dart';

final publicHomeRepositoryProvider = Provider<PublicHomeRepository>(
  (ref) => PublicHomeRepository(ref.watch(apiClientProvider)),
);

/// Kandungan Beranda awam (banner, artikel, video, pustaka, info terkini,
/// program, infaq) — boleh diakses tanpa log masuk.
final publicHomeProvider = FutureProvider<PublicHomeData>(
  (ref) => ref.watch(publicHomeRepositoryProvider).fetch(),
);
