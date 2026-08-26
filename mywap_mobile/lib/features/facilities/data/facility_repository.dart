import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/facility.dart';

class FacilityRepository {
  FacilityRepository(this._api);

  final ApiClient _api;

  Future<FacilityListData> list() async {
    final data = await _api.get(ApiPaths.facilities);
    return FacilityListData.fromJson(_asMap(data));
  }

  Future<FacilityDetailData> detail(int id) async {
    final data = await _api.get(ApiPaths.facilityDetail(id));
    return FacilityDetailData.fromJson(_asMap(data));
  }

  Future<BookingResult> book(
    int id, {
    required DateTime start,
    required DateTime end,
    String? contactName,
    String? contactPhone,
  }) async {
    final data = await _api.post(
      ApiPaths.facilityBook(id),
      body: {
        'start_datetime': _formatDatetime(start),
        'end_datetime': _formatDatetime(end),
        if (contactName != null && contactName.trim().isNotEmpty)
          'contact_name': contactName.trim(),
        if (contactPhone != null && contactPhone.trim().isNotEmpty)
          'contact_phone': contactPhone.trim(),
      },
    );
    return BookingResult.fromJson(_asMap(data));
  }

  Map<String, dynamic> _asMap(dynamic data) =>
      data is Map<String, dynamic> ? data : const {};

  static String _formatDatetime(DateTime value) {
    String pad(int v) => v.toString().padLeft(2, '0');
    return '${value.year.toString().padLeft(4, '0')}-'
        '${pad(value.month)}-${pad(value.day)} '
        '${pad(value.hour)}:${pad(value.minute)}:00';
  }
}
