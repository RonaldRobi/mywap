import 'dart:convert';

import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/infaq.dart';

class InfaqRepository {
  InfaqRepository(this._api);

  final ApiClient _api;

  Future<InfaqListData> list() async {
    final data = await _api.get(ApiPaths.infaq);
    return InfaqListData.fromJson(_asMap(data));
  }

  Future<InfaqDetail> detail(String slug) async {
    final data = await _api.get(ApiPaths.infaqDetail(slug));
    return InfaqDetail.fromJson(_asMap(data));
  }

  Future<InfaqDonateResult> donate(
    String slug, {
    required double amount,
    required String donorName,
    required String donorPhone,
    required String donorEmail,
    String? prayerMessage,
    bool isAnonymous = false,
    bool wantsUpdates = false,
    bool isRecurring = false,
    String? frequency,
  }) async {
    final data = await _api.post(
      ApiPaths.infaqDonate(slug),
      body: {
        'amount': amount,
        'donor_name': donorName,
        'donor_phone': donorPhone,
        'donor_email': donorEmail,
        if (prayerMessage != null && prayerMessage.isNotEmpty)
          'prayer_message': prayerMessage,
        'is_anonymous': isAnonymous,
        'wants_updates': wantsUpdates,
        if (isRecurring) 'is_recurring': true,
        if (isRecurring && frequency != null) 'frequency': frequency,
      },
    );

    final map = _asMap(data);
    final status = map['status']?.toString();
    if (status == 'redirect') {
      return InfaqDonateRedirect(paymentUrl: map['payment_url']?.toString() ?? '');
    }
    final donation = map['donation'];
    return InfaqDonateSuccess(
      donation: InfaqDonation.fromJson(
        donation is Map<String, dynamic>
            ? donation
            : (donation is Map ? donation.cast<String, dynamic>() : const {}),
      ),
    );
  }

  static Map<String, dynamic> _asMap(dynamic data) {
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return data.cast<String, dynamic>();
    if (data is String) {
      final decoded = jsonDecode(data);
      if (decoded is Map) return decoded.cast<String, dynamic>();
    }
    return const {};
  }
}
