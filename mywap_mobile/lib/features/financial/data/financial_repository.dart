import 'dart:convert';

import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/financial_overview.dart';

/// Result from `POST /member/pay-fee` — `redirect` means the caller should
/// open [payment_url] in the payment WebView; `success` means the fee was
/// marked paid immediately (dummy gateway / no live gateway configured).
class FeePayResult {
  const FeePayResult({this.status, this.paymentUrl, this.message});

  final String? status;
  final String? paymentUrl;
  final String? message;

  bool get isRedirect => status == 'redirect';

  bool get isSuccess => status == 'success';

  factory FeePayResult.fromJson(Map<String, dynamic> json) => FeePayResult(
        status: json['status'] as String?,
        paymentUrl: json['payment_url'] as String?,
        message: json['message'] as String?,
      );
}

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

  /// Initiate the annual membership-fee payment (dummy success or gateway
  /// redirect). Throws [ApiException] for guards (life member / already paid).
  Future<FeePayResult> payFee() async {
    final data = await _api.post('/member/pay-fee');
    return FeePayResult.fromJson((data as Map<String, dynamic>?) ?? const {});
  }

  /// Signed (temporary) URL to download the PDF receipt for one successful
  /// payment. Returns null when the backend cannot produce a receipt.
  Future<String?> receiptUrl(int paymentId) async {
    final data = await _api.get(ApiPaths.memberPaymentReceipt(paymentId));
    if (data is Map<String, dynamic>) {
      final url = data['url'];
      return url?.toString();
    }
    return null;
  }
}
