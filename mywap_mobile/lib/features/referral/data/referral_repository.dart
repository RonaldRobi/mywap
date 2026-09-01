import 'dart:convert';

import '../../../core/network/api_client.dart';
import 'models/referral_data.dart';

class ReferralRepository {
  ReferralRepository(this._api);
  final ApiClient _api;

  Future<ReferralData> get() async {
    final data = await _api.get('/member/referral');
    if (data is String) {
      return ReferralData.fromJson(jsonDecode(data) as Map<String, dynamic>);
    }
    return ReferralData.fromJson((data as Map<String, dynamic>?) ?? {});
  }
}
