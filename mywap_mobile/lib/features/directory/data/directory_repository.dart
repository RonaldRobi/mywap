import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/directory_user.dart';

/// Paginated payload for one `GET /directory` request.
class DirectoryData {
  const DirectoryData({
    required this.users,
    required this.industries,
    required this.hasMore,
  });

  final List<DirectoryUser> users;
  final List<String> industries;
  final bool hasMore;
}

class DirectoryRepository {
  DirectoryRepository(this._api);

  final ApiClient _api;

  static const int _pageSize = 16;

  Future<DirectoryData> directory({
    int page = 1,
    String search = '',
    String industry = '',
  }) async {
    final data = await _api.get(
      ApiPaths.directory,
      query: {
        'page': page,
        if (search.isNotEmpty) 'search': search,
        if (industry.isNotEmpty) 'industry': industry,
      },
    );
    final map = data is Map<String, dynamic> ? data : const <String, dynamic>{};
    final users = _parseUsers(map['users']);
    return DirectoryData(
      users: users,
      industries: (map['industries'] as List?)
              ?.whereType<String>()
              .toList(growable: false) ??
          const [],
      hasMore: users.length >= _pageSize,
    );
  }

  List<DirectoryUser> _parseUsers(dynamic value) {
    if (value is! List) return const [];
    return value
        .whereType<Map<String, dynamic>>()
        .map(DirectoryUser.fromJson)
        .toList(growable: false);
  }
}
