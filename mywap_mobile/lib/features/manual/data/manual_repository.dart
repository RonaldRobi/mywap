import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/manual.dart';

/// Data access for the Manual Pengguna domain (`GET /manuals`).
class ManualRepository {
  ManualRepository(this._api);

  final ApiClient _api;

  Future<List<Manual>> list() async {
    final data = await _api.get(ApiPaths.manuals);
    if (data is! Map<String, dynamic>) return const [];
    final items = data['manuals'];
    if (items is! List) return const [];
    return items
        .whereType<Map<String, dynamic>>()
        .map(Manual.fromJson)
        .toList(growable: false);
  }
}
