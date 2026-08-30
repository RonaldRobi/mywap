import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/loading_screen_repository.dart';

final loadingScreenRepositoryProvider = Provider<LoadingScreenRepository>(
  (ref) => LoadingScreenRepository(ref.watch(apiClientProvider)),
);

/// Konfigurasi loading screen. Nilai awal `null` (belum dimuat) — widget akan
/// papar fallback lalai; kemudian cache tempatan dan respons API mengemas
/// kininya tanpa perlu mula semula.
final loadingScreenControllerProvider =
    NotifierProvider<LoadingScreenController, LoadingScreenConfig?>(
      LoadingScreenController.new,
    );

class LoadingScreenController extends Notifier<LoadingScreenConfig?> {
  @override
  LoadingScreenConfig? build() {
    _load();
    return null;
  }

  Future<void> _load() async {
    final repo = ref.read(loadingScreenRepositoryProvider);

    final cached = await repo.loadCached();
    if (cached != null) {
      state = cached;
    }

    try {
      final fresh = await repo.fetchConfig();
      state = fresh;
    } catch (_) {
      // Guna cache/lalai jika API tidak dapat dihubungi.
    }
  }
}
