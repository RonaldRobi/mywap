import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/usrah.dart';

class UsrahRepository {
  UsrahRepository(this._api);

  final ApiClient _api;

  Future<UsrahData> fetch() async {
    final data = await _api.get(ApiPaths.usrah);
    return UsrahData.fromJson(data is Map<String, dynamic> ? data : const {});
  }
}
