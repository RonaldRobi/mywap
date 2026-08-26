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
        id: json['id'] as int?,
        organizationId: json['organization_id'] as int?,
        organizationName: json['organization_name'] as String?,
        name: json['name'] as String?,
        description: json['description'] as String?,
        location: json['location'] as String?,
        type: json['type'] as String?,
        pricePerUnit: (json['price_per_unit'] as num?)?.toDouble(),
        memberPricePerUnit: (json['member_price_per_unit'] as num?)?.toDouble(),
        capacity: json['capacity'] as int?,
        imagePath: json['image_path'] as String?,
        media: _parseMedia(json['media']),
        isActive: json['is_active'] as bool?,
      );

  /// Primary display image URL (media first, else image_path).
  String? get imageUrl {
    final fromMedia = media.isNotEmpty ? media.first.path : null;
    final raw = (fromMedia != null && fromMedia.isNotEmpty) ? fromMedia : imagePath;
    if (raw == null || raw.isEmpty) return null;
    return raw.startsWith('http') ? raw : '$apiBaseUrl/storage/$raw';
  }

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000',
  );

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
        id: json['id'] as int?,
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

  factory FacilityBooking.fromJson(Map<String, dynamic> json) => FacilityBooking(
        id: json['id'] as int?,
        facilityId: json['facility_id'] as int?,
        facilityName: json['facility_name'] as String?,
        organizationName: json['organization_name'] as String?,
        startDatetime: json['start_datetime'] as String?,
        endDatetime: json['end_datetime'] as String?,
        totalPrice: (json['total_price'] as num?)?.toDouble(),
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

  factory FacilityListData.fromJson(Map<String, dynamic> json) => FacilityListData(
        facilities: _parseList<Facility>(json['facilities'], Facility.fromJson),
        myBookings:
            _parseList<FacilityBooking>(json['myBookings'], FacilityBooking.fromJson),
        isMember: json['isMember'] as bool? ?? false,
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
        facility: json['facility'] is Map<String, dynamic>
            ? Facility.fromJson(json['facility'] as Map<String, dynamic>)
            : null,
        bookings:
            _parseList<FacilityBooking>(json['bookings'], FacilityBooking.fromJson),
        myBookings:
            _parseList<FacilityBooking>(json['myBookings'], FacilityBooking.fromJson),
        isMember: json['isMember'] as bool? ?? false,
      );
}

class BookingResult {
  const BookingResult({this.booking, this.totalPrice, this.bookingStatus});

  final FacilityBooking? booking;
  final double? totalPrice;
  final String? bookingStatus;

  factory BookingResult.fromJson(Map<String, dynamic> json) => BookingResult(
        booking: json['booking'] is Map<String, dynamic>
            ? FacilityBooking.fromJson(json['booking'] as Map<String, dynamic>)
            : null,
        totalPrice: (json['total_price'] as num?)?.toDouble(),
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
