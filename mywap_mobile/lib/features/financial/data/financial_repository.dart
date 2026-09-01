import 'dart:convert';

import '../../../core/network/api_client.dart';
import 'models/financial_overview.dart';

class FinancialRepository {
  FinancialRepository(this._api);
  final ApiClient _api;

  Future<FinancialOverviewData> overview() async {
    final data = await _api.get('/member/financial/overview');
    if (data is String) {
      return FinancialOverviewData.fromJson(jsonDecode(data) as Map<String, dynamic>);
    }
    return FinancialOverviewData.fromJson((data as Map<String, dynamic>?) ?? {});
  }
}
