import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';

/// Repository for user-generated content moderation (report & block).
class ModerationRepository {
  ModerationRepository(this._api);

  final ApiClient _api;

  /// Laporkan satu komen. [reportableType] ialah `article_comment` atau
  /// `news_comment`.
  Future<void> reportComment({
    required String reportableType,
    required int reportableId,
    required String reason,
    String? notes,
  }) async {
    await _api.post(
      ApiPaths.reports,
      body: {
        'reportable_type': reportableType,
        'reportable_id': reportableId,
        'reason': reason,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      },
    );
  }

  Future<List<int>> blockedUserIds() async {
    final data = await _api.get(ApiPaths.blocks);
    if (data is! Map) return const [];
    final ids = data['blocked_user_ids'];
    if (ids is! List) return const [];
    return ids.whereType<num>().map((e) => e.toInt()).toList(growable: false);
  }

  Future<void> blockUser(int userId) async {
    await _api.post(ApiPaths.blocks, body: {'blocked_user_id': userId});
  }

  Future<void> unblockUser(int userId) async {
    await _api.delete(ApiPaths.unblock(userId));
  }
}
