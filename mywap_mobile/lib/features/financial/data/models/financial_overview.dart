// ignore_for_file: non_constant_identifier_names

class FinancialOverviewData {
  const FinancialOverviewData({
    this.campaigns = const [],
    this.fee_status,
    this.payment_history = const [],
  });

  factory FinancialOverviewData.fromJson(Map<String, dynamic> json) {
    final campaignsJson = json['campaigns'];
    final feeJson = json['fee_status'];
    final historyJson = json['payment_history'];
    return FinancialOverviewData(
      campaigns: campaignsJson is List
          ? campaignsJson
              .whereType<Map<String, dynamic>>()
              .map(FinancialCampaign.fromJson)
              .toList(growable: false)
          : const [],
      fee_status:
          feeJson is Map<String, dynamic> ? FeeStatusSummary.fromJson(feeJson) : null,
      payment_history: historyJson is List
          ? historyJson
              .whereType<Map<String, dynamic>>()
              .map(PaymentHistoryItem.fromJson)
              .toList(growable: false)
          : const [],
    );
  }

  final List<FinancialCampaign> campaigns;
  final FeeStatusSummary? fee_status;
  final List<PaymentHistoryItem> payment_history;
}

class FeeStatusSummary {
  const FeeStatusSummary({this.status, this.amount_due, this.last_paid_at});

  factory FeeStatusSummary.fromJson(Map<String, dynamic> json) => FeeStatusSummary(
        status: json['status'] as String?,
        amount_due: (json['amount_due'] as num?)?.toDouble(),
        last_paid_at: json['last_paid_at'] as String?,
      );

  final String? status;
  final double? amount_due;
  final String? last_paid_at;

  bool get isActive => status == 'active';
}

class FinancialCampaign {
  const FinancialCampaign({
    this.id,
    this.title,
    this.slug,
    this.target_amount,
    this.current_amount,
    this.progress_percent,
  });

  factory FinancialCampaign.fromJson(Map<String, dynamic> json) => FinancialCampaign(
        id: (json['id'] as num?)?.toInt(),
        title: json['title'] as String?,
        slug: json['slug'] as String?,
        target_amount: (json['target_amount'] as num?)?.toDouble(),
        current_amount: (json['current_amount'] as num?)?.toDouble(),
        progress_percent: (json['progress_percent'] as num?)?.toInt(),
      );

  final int? id;
  final String? title;
  final String? slug;
  final double? target_amount;
  final double? current_amount;
  final int? progress_percent;
}

class PaymentHistoryItem {
  const PaymentHistoryItem({
    this.id,
    this.payable_type,
    this.amount,
    this.status,
    this.created_at,
  });

  factory PaymentHistoryItem.fromJson(Map<String, dynamic> json) => PaymentHistoryItem(
        id: (json['id'] as num?)?.toInt(),
        payable_type: json['payable_type'] as String?,
        amount: (json['amount'] as num?)?.toDouble(),
        status: json['status'] as String?,
        created_at: json['created_at'] as String?,
      );

  final int? id;
  final String? payable_type;
  final double? amount;
  final String? status;
  final String? created_at;

  bool get isSuccessful => status == 'successful';
}
