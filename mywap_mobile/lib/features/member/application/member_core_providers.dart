import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/network/providers.dart';
import '../data/library_file_service.dart';
import '../data/member_core_repository.dart';
import '../data/models/announcement.dart';
import '../data/models/fee_status.dart';
import '../data/models/library_item.dart';
import '../data/models/member_card_data.dart';

final memberCoreRepositoryProvider = Provider<MemberCoreRepository>(
  (ref) => MemberCoreRepository(ref.watch(apiClientProvider)),
);

final libraryFileServiceProvider = Provider<LibraryFileService>(
  (ref) => LibraryFileService(tokenStorage: ref.watch(tokenStorageProvider)),
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

/// Favourite book ids (per-device, offline). Stored via SharedPreferences.
final libraryFavouritesProvider =
    AsyncNotifierProvider<LibraryFavourites, Set<int>>(LibraryFavourites.new);

class LibraryFavourites extends AsyncNotifier<Set<int>> {
  static const String _prefsKey = 'pustaka_favourites_v1';

  @override
  Future<Set<int>> build() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getStringList(_prefsKey) ?? const <String>[];
    return raw.map(int.tryParse).whereType<int>().toSet();
  }

  Future<void> toggle(int id) async {
    final current = Set<int>.of(state.value ?? const <int>{});
    if (!current.remove(id)) {
      current.add(id);
    }
    state = AsyncData(Set.unmodifiable(current));

    final prefs = await SharedPreferences.getInstance();
    await prefs.setStringList(
      _prefsKey,
      current.map((e) => e.toString()).toList(),
    );
  }
}

/// Last-read page per book, so the reader resumes where the user stopped.
const String _lastPagePrefix = 'pustaka_last_page_v1_';

Future<int> readLastLibraryPage(int id) async {
  final prefs = await SharedPreferences.getInstance();
  return prefs.getInt('$_lastPagePrefix$id') ?? 0;
}

Future<void> saveLastLibraryPage(int id, int page) async {
  final prefs = await SharedPreferences.getInstance();
  await prefs.setInt('$_lastPagePrefix$id', page);
}
