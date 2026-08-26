/// List item for the infaq campaign list (GET /infaq) and related items on the
/// detail payload. Keys mirror `InfaqService::index()` / `showDetail()`.
class Infaq {
  const Infaq({
    this.id,
    this.title,
    this.slug,
    this.type,
    this.targetAmount,
    this.collectedAmount,
    this.progressPercent,
    this.imagePath,
    this.publicUrl,
    this.isExternal = false,
    this.externalUrl,
    this.organizationId,
    this.organizationName,
    this.organizationSlug,
    this.daysRunning,
  });

  final int? id;
  final String? title;
  final String? slug;
  final String? type;
  final double? targetAmount;
  final double? collectedAmount;
  final double? progressPercent;
  final String? imagePath;
  final String? publicUrl;
  final bool isExternal;
  final String? externalUrl;
  final int? organizationId;
  final String? organizationName;
  final String? organizationSlug;
  final int? daysRunning;

  factory Infaq.fromJson(Map<String, dynamic> json) => Infaq(
        id: json['id'] as int?,
        title: json['title'] as String?,
        slug: json['slug'] as String?,
        type: json['type'] as String?,
        targetAmount: (json['target_amount'] as num?)?.toDouble(),
        collectedAmount: (json['collected_amount'] as num?)?.toDouble(),
        progressPercent: (json['progress_percent'] as num?)?.toDouble(),
        imagePath: json['image_path'] as String?,
        publicUrl: json['public_url'] as String?,
        isExternal: (json['is_external'] as bool?) ?? false,
        externalUrl: json['external_url'] as String?,
        organizationId: json['organization_id'] as int?,
        organizationName: json['organization_name'] as String?,
        organizationSlug: json['organization_slug'] as String?,
        daysRunning: json['days_running'] as int?,
      );
}

/// The rich infaq object returned under `data.infaq` on GET /infaq/{slug}.
class InfaqInfo {
  const InfaqInfo({
    this.id,
    this.slug,
    this.title,
    this.description,
    this.imagePath,
    this.type,
    this.allowRecurring = false,
    this.targetAmount,
    this.collectedAmount,
    this.progressPercent,
    this.isExternal = false,
    this.externalUrl,
    this.organizationName,
    this.organizationSlug,
    this.organizationLogo,
    this.organizationColor,
    this.totalDonors,
    this.daysRunning,
    this.publicUrl,
    this.year,
    this.month,
    this.day,
  });

  final int? id;
  final String? slug;
  final String? title;
  final String? description;
  final String? imagePath;
  final String? type;
  final bool allowRecurring;
  final double? targetAmount;
  final double? collectedAmount;
  final double? progressPercent;
  final bool isExternal;
  final String? externalUrl;
  final String? organizationName;
  final String? organizationSlug;
  final String? organizationLogo;
  final String? organizationColor;
  final int? totalDonors;
  final int? daysRunning;
  final String? publicUrl;
  final String? year;
  final String? month;
  final String? day;

  factory InfaqInfo.fromJson(Map<String, dynamic> json) => InfaqInfo(
        id: json['id'] as int?,
        slug: json['slug'] as String?,
        title: json['title'] as String?,
        description: json['description'] as String?,
        imagePath: json['image_path'] as String?,
        type: json['type'] as String?,
        allowRecurring: (json['allow_recurring'] as bool?) ?? false,
        targetAmount: (json['target_amount'] as num?)?.toDouble(),
        collectedAmount: (json['collected_amount'] as num?)?.toDouble(),
        progressPercent: (json['progress_percent'] as num?)?.toDouble(),
        isExternal: (json['is_external'] as bool?) ?? false,
        externalUrl: json['external_url'] as String?,
        organizationName: json['organization_name'] as String?,
        organizationSlug: json['organization_slug'] as String?,
        organizationLogo: json['organization_logo'] as String?,
        organizationColor: json['organization_color'] as String?,
        totalDonors: json['total_donors'] as int?,
        daysRunning: json['days_running'] as int?,
        publicUrl: json['public_url'] as String?,
        year: json['year']?.toString(),
        month: json['month']?.toString(),
        day: json['day']?.toString(),
      );
}

/// Full detail payload for GET /infaq/{slug}.
class InfaqDetail {
  const InfaqDetail({
    required this.infaq,
    this.recentDonations = const [],
    this.relatedInfaqs = const [],
  });

  final InfaqInfo infaq;
  final List<RecentDonation> recentDonations;
  final List<Infaq> relatedInfaqs;

  factory InfaqDetail.fromJson(Map<String, dynamic> json) {
    final infaqJson = json['infaq'];
    return InfaqDetail(
      infaq: InfaqInfo.fromJson(
        infaqJson is Map<String, dynamic>
            ? infaqJson
            : (infaqJson is Map ? infaqJson.cast<String, dynamic>() : const {}),
      ),
      recentDonations: _parseList(json['recentDonations'], RecentDonation.fromJson),
      relatedInfaqs: _parseList(json['relatedInfaqs'], Infaq.fromJson),
    );
  }
}

/// A confirmed donation shown on the detail screen (`recentDonations`).
class RecentDonation {
  const RecentDonation({
    this.id,
    this.amount,
    this.createdAt,
    this.donorName,
    this.prayerMessage,
  });

  final int? id;
  final double? amount;

  /// Human-readable relative timestamp (`diffForHumans`, e.g. "2 hours ago").
  final String? createdAt;
  final String? donorName;
  final String? prayerMessage;

  factory RecentDonation.fromJson(Map<String, dynamic> json) => RecentDonation(
        id: json['id'] as int?,
        amount: (json['amount'] as num?)?.toDouble(),
        createdAt: json['created_at'] as String?,
        donorName: json['donor_name'] as String?,
        prayerMessage: json['prayer_message'] as String?,
      );
}

/// Serialized donation returned on a direct-success donate response.
class InfaqDonation {
  const InfaqDonation({
    this.id,
    this.reference,
    this.amount,
    this.status,
    this.donorName,
    this.isAnonymous = false,
    this.isRecurring = false,
    this.frequency,
    this.prayerMessage,
    this.createdAt,
    this.infaqTitle,
    this.infaqSlug,
    this.infaqPublicUrl,
  });

  final int? id;
  final String? reference;
  final double? amount;
  final String? status;
  final String? donorName;
  final bool isAnonymous;
  final bool isRecurring;
  final String? frequency;
  final String? prayerMessage;
  final String? createdAt;
  final String? infaqTitle;
  final String? infaqSlug;
  final String? infaqPublicUrl;

  factory InfaqDonation.fromJson(Map<String, dynamic> json) {
    final infaqJson = json['infaq'];
    final infaqMap = infaqJson is Map<String, dynamic>
        ? infaqJson
        : (infaqJson is Map ? infaqJson.cast<String, dynamic>() : const <String, dynamic>{});
    return InfaqDonation(
      id: json['id'] as int?,
      reference: json['reference'] as String?,
      amount: (json['amount'] as num?)?.toDouble(),
      status: json['status'] as String?,
      donorName: json['donor_name'] as String?,
      isAnonymous: (json['is_anonymous'] as bool?) ?? false,
      isRecurring: (json['is_recurring'] as bool?) ?? false,
      frequency: json['frequency'] as String?,
      prayerMessage: json['prayer_message'] as String?,
      createdAt: json['created_at'] as String?,
      infaqTitle: infaqMap['title'] as String?,
      infaqSlug: infaqMap['slug'] as String?,
      infaqPublicUrl: infaqMap['public_url'] as String?,
    );
  }
}

/// Organization filter chip for the list screen.
class InfaqOrganization {
  const InfaqOrganization({this.id, this.name, this.slug});

  final int? id;
  final String? name;
  final String? slug;

  factory InfaqOrganization.fromJson(Map<String, dynamic> json) => InfaqOrganization(
        id: json['id'] as int?,
        name: json['name'] as String?,
        slug: json['slug'] as String?,
      );
}

/// The `data` envelope of GET /infaq.
class InfaqListData {
  const InfaqListData({
    this.infaqs = const [],
    this.organizations = const [],
    this.hasGlobal = false,
  });

  final List<Infaq> infaqs;
  final List<InfaqOrganization> organizations;
  final bool hasGlobal;

  factory InfaqListData.fromJson(Map<String, dynamic> json) => InfaqListData(
        infaqs: _parseList(json['infaqs'], Infaq.fromJson),
        organizations: _parseList(json['organizations'], InfaqOrganization.fromJson),
        hasGlobal: (json['hasGlobal'] as bool?) ?? false,
      );
}

/// Outcome of POST /infaq/{slug}/donate.
sealed class InfaqDonateResult {
  const InfaqDonateResult();
}

/// Gateway/redirect flow: caller opens [PaymentWebviewScreen].
class InfaqDonateRedirect extends InfaqDonateResult {
  const InfaqDonateRedirect({required this.paymentUrl});

  final String paymentUrl;
}

/// Direct-success flow (no gateway): donation is confirmed immediately.
class InfaqDonateSuccess extends InfaqDonateResult {
  const InfaqDonateSuccess({required this.donation});

  final InfaqDonation donation;
}

List<T> _parseList<T>(dynamic value, T Function(Map<String, dynamic>) fromJson) {
  if (value is! List) return const [];
  return value
      .whereType<Map>()
      .map((e) => fromJson(e.cast<String, dynamic>()))
      .toList(growable: false);
}
