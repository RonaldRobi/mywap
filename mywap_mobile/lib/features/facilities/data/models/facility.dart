import '../../../../core/constants/app_env.dart';
import '../../../../core/utils/json_parsing.dart';

/// Plain immutable model for a bookable facility (§ FLUTTER_PLAN facilities).
class Facility {
  const Facility({
    this.id,
    this.organizationId,
    this.organizationName,
    this.name,
    this.description,
    this.location,
    this.type,
    this.pricePerUnit,
    this.memberPricePerUnit,
    this.capacity,
    this.imagePath,
    this.media = const [],
    this.isActive,
  });

  final int? id;
  final int? organizationId;
  final String? organizationName;
  final String? name;
  final String? description;
  final String? location;
  final String? type;
  final double? pricePerUnit;
  final double? memberPricePerUnit;
  final int? capacity;
  final String? imagePath;
  final List<FacilityMedia> media;
  final bool? isActive;

  factory Facility.fromJson(Map<String, dynamic> json) => Facility(
    id: parseIntValue(json['id']),
    organizationId: parseIntValue(json['organization_id']),
    organizationName: json['organization_name'] as String?,
    name: json['name'] as String?,
    description: json['description'] as String?,
    location: json['location'] as String?,
    type: json['type'] as String?,
    pricePerUnit: parseDoubleValue(json['price_per_unit']),
    memberPricePerUnit: parseDoubleValue(json['member_price_per_unit']),
    capacity: parseIntValue(json['capacity']),
    imagePath: json['image_path'] as String?,
    media: _parseMedia(json['media']),
    isActive:
        json['is_active'] == null ? null : parseBoolValue(json['is_active']),
  );

  /// Primary display image URL (media first, else image_path).
  String? get imageUrl {
    final fromMedia = media.isNotEmpty ? media.first.path : null;
    final raw =
        (fromMedia != null && fromMedia.isNotEmpty) ? fromMedia : imagePath;
    if (raw == null || raw.isEmpty) return null;
    return AppEnv.resolveUrl(raw);
  }

  /// Semua imej galeri (media admin). Jatuh kepada [imageUrl] sahaja bila
  /// admin tiada galeri — pulang senarai kosong jika tiada gambar langsung.
  List<String> get galleryUrls {
    final urls = <String>[];
    for (final item in media) {
      final path = item.path;
      if (path == null || path.isEmpty) continue;
      final url = AppEnv.resolveUrl(path);
      if (!urls.contains(url)) urls.add(url);
    }
    if (urls.isEmpty) {
      final featured = imageUrl;
      if (featured != null) urls.add(featured);
    }
    return List.unmodifiable(urls);
  }

  static List<FacilityMedia> _parseMedia(dynamic value) {
    if (value is! List) return const [];
    return value
        .whereType<Map<String, dynamic>>()
        .map(FacilityMedia.fromJson)
        .toList(growable: false);
  }
}

class FacilityMedia {
  const FacilityMedia({this.id, this.path, this.caption});

  final int? id;
  final String? path;
  final String? caption;

  factory FacilityMedia.fromJson(Map<String, dynamic> json) => FacilityMedia(
    id: parseIntValue(json['id']),
    path: json['path'] as String?,
    caption: json['caption'] as String?,
  );
}

class FacilityBooking {
  const FacilityBooking({
    this.id,
    this.facilityId,
    this.facilityName,
    this.organizationName,
    this.startDatetime,
    this.endDatetime,
    this.totalPrice,
    this.bookingStatus,
    this.paymentStatus,
    this.adminRemarks,
  });

  final int? id;
  final int? facilityId;
  final String? facilityName;
  final String? organizationName;
  final String? startDatetime;
  final String? endDatetime;
  final double? totalPrice;
  final String? bookingStatus;
  final String? paymentStatus;
  final String? adminRemarks;

  factory FacilityBooking.fromJson(Map<String, dynamic> json) =>
      FacilityBooking(
        id: parseIntValue(json['id']),
        facilityId: parseIntValue(json['facility_id']),
        facilityName: json['facility_name'] as String?,
        organizationName: json['organization_name'] as String?,
        startDatetime: json['start_datetime'] as String?,
        endDatetime: json['end_datetime'] as String?,
        totalPrice: parseDoubleValue(json['total_price']),
        bookingStatus: json['booking_status'] as String?,
        paymentStatus: json['payment_status'] as String?,
        adminRemarks: json['admin_remarks'] as String?,
      );
}

class FacilityListData {
  const FacilityListData({
    this.facilities = const [],
    this.myBookings = const [],
    this.isMember = false,
  });

  final List<Facility> facilities;
  final List<FacilityBooking> myBookings;
  final bool isMember;

  factory FacilityListData.fromJson(Map<String, dynamic> json) =>
      FacilityListData(
        facilities: _parseList<Facility>(json['facilities'], Facility.fromJson),
        myBookings: _parseList<FacilityBooking>(
          json['myBookings'],
          FacilityBooking.fromJson,
        ),
        isMember: parseBoolValue(json['isMember']),
      );
}

class FacilityDetailData {
  const FacilityDetailData({
    this.facility,
    this.bookings = const [],
    this.myBookings = const [],
    this.isMember = false,
  });

  final Facility? facility;
  final List<FacilityBooking> bookings;
  final List<FacilityBooking> myBookings;
  final bool isMember;

  factory FacilityDetailData.fromJson(Map<String, dynamic> json) =>
      FacilityDetailData(
        facility:
            json['facility'] is Map<String, dynamic>
                ? Facility.fromJson(json['facility'] as Map<String, dynamic>)
                : null,
        bookings: _parseList<FacilityBooking>(
          json['bookings'],
          FacilityBooking.fromJson,
        ),
        myBookings: _parseList<FacilityBooking>(
          json['myBookings'],
          FacilityBooking.fromJson,
        ),
        isMember: parseBoolValue(json['isMember']),
      );
}

class BookingResult {
  const BookingResult({this.booking, this.totalPrice, this.bookingStatus});

  final FacilityBooking? booking;
  final double? totalPrice;
  final String? bookingStatus;

  factory BookingResult.fromJson(Map<String, dynamic> json) => BookingResult(
    booking:
        json['booking'] is Map<String, dynamic>
            ? FacilityBooking.fromJson(json['booking'] as Map<String, dynamic>)
            : null,
    totalPrice: parseDoubleValue(json['total_price']),
    bookingStatus: json['booking_status'] as String?,
  );
}

List<T> _parseList<T>(
  dynamic value,
  T Function(Map<String, dynamic>) fromJson,
) {
  if (value is! List) return const [];
  return value
      .whereType<Map<String, dynamic>>()
      .map(fromJson)
      .toList(growable: false);
}
