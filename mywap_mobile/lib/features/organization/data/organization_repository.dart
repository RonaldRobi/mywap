import 'dart:convert';

import '../../../core/network/api_client.dart';
import 'models/organization_info.dart';

class OrganizationRepository {
  OrganizationRepository(this._api);
  final ApiClient _api;

  Future<OrganizationInfoData> info() async {
    final data = await _api.get('/organization/info');
    if (data is String) {
      return OrganizationInfoData.fromJson(jsonDecode(data) as Map<String, dynamic>);
    }
    return OrganizationInfoData.fromJson((data as Map<String, dynamic>?) ?? {});
  }
}
