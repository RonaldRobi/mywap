import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/announcement.dart';
import 'models/fee_status.dart';
import 'models/library_item.dart';
import 'models/member_card_data.dart';

/// Data access for the member core domain (card, announcements, library,
/// fee status). See `app/Services/MemberCoreService.php`.
class MemberCoreRepository {
  MemberCoreRepository(this._api);

  final ApiClient _api;

  Future<MemberCardData> card() async {
    final data = await _api.get(ApiPaths.memberCard);
    return MemberCardData.fromJson((data as Map<String, dynamic>?) ?? {});
  }

  Future<List<Announcement>> announcements() async {
    final data = await _api.get(ApiPaths.memberAnnouncements);
    return _parseList(data, Announcement.fromJson);
  }

  /// Toggles the current user's 'like' reaction. Returns the new reaction
  /// (`'like'`) or null when the reaction was removed.
  Future<String?> react(int id) async {
    final data = await _api.post(ApiPaths.memberAnnouncementReact(id));
    if (data is Map<String, dynamic>) {
      return data['reaction'] as String?;
    }
    return null;
  }

  Future<void> markRead(int id) async {
    await _api.post(ApiPaths.memberAnnouncementRead(id));
  }

  Future<List<LibraryItem>> library() async {
    final data = await _api.get(ApiPaths.memberLibrary);
    return _parseList(data, LibraryItem.fromJson);
  }

  Future<FeeStatus> feeStatus() async {
    final data = await _api.get(ApiPaths.memberFeeStatus);
    return FeeStatus.fromJson((data as Map<String, dynamic>?) ?? {});
  }

  List<T> _parseList<T>(
    dynamic data,
    T Function(Map<String, dynamic>) fromJson,
  ) {
    if (data is List) {
      return data
          .whereType<Map<String, dynamic>>()
          .map(fromJson)
          .toList(growable: false);
    }
    return const [];
  }
}
