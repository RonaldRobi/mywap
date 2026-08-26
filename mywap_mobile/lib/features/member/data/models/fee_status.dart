// ignore_for_file: non_constant_identifier_names

/// Payload from `GET /member/fee-status`.
///
/// Envelope: `{ status: { status, amount_due, last_paid_at, last_reference },
/// fee_amount }` where the nested `status` is the FeeService status map and
/// `fee_amount` is the org's annual fee.
class FeeStatus {
  const FeeStatus({
    this.status,
    this.amount_due,
    this.last_paid_at,
    this.last_reference,
    this.fee_amount,
  });

  factory FeeStatus.fromJson(Map<String, dynamic> json) {
    final statusMap = json['status'];
    final inner =
        statusMap is Map<String, dynamic> ? statusMap : const <String, dynamic>{};
    return FeeStatus(
      status: inner['status'] as String?,
      amount_due: (inner['amount_due'] as num?)?.toDouble(),
      last_paid_at: inner['last_paid_at'] as String?,
      last_reference: inner['last_reference'] as String?,
      fee_amount: (json['fee_amount'] as num?)?.toDouble(),
    );
  }

  /// `'due'` or `'active'`.
  final String? status;
  final double? amount_due;
  final String? last_paid_at;
  final String? last_reference;
  final double? fee_amount;

  bool get isDue => status == 'due';
}
