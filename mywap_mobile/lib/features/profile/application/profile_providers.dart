import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/models/profile_data.dart';
import '../data/profile_repository.dart';

final profileRepositoryProvider = Provider<ProfileRepository>(
  (ref) => ProfileRepository(ref.watch(apiClientProvider)),
);

/// Full profile (`GET /profile`). Invalidate after any edit so the screen
/// re-fetches the latest values.
final profileProvider = FutureProvider<ProfileData>((ref) async {
  return ref.watch(profileRepositoryProvider).fetchProfile();
});

/// Form meta for the edit screen (`GET /profile/edit-meta`).
final profileEditMetaProvider = FutureProvider<EditMeta>((ref) async {
  return ref.watch(profileRepositoryProvider).editMeta();
});

/// DOB + gender guesses for the complete-profile screen (`GET /profile/complete`).
final profileCompleteMetaProvider = FutureProvider<CompleteMeta>((ref) async {
  return ref.watch(profileRepositoryProvider).completeMeta();
});
