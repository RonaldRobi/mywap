import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/dashboard_data.dart';

class MemberRepository {
  MemberRepository(this._api);

  final ApiClient _api;

  Future<DashboardData> dashboard() async {
    final data = await _api.get(ApiPaths.memberDashboard);
    return DashboardData.fromJson((data as Map<String, dynamic>?) ?? {});
  }
}
