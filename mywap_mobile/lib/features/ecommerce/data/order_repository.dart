import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/order.dart';
import 'models/parsing.dart';
import 'paged_result.dart';

class OrderRepository {
  OrderRepository(this._api);

  final ApiClient _api;

  /// Page size (backend whitelist: 15/25/50/100).
  static const int perPage = 25;

  Future<PagedResult<Order>> orders({int page = 1}) async {
    final data = await _api.get(
      ApiPaths.orders,
      query: {'page': page, 'per_page': perPage},
    );
    final items = parseList<Order>(data, Order.fromJson);
    return PagedResult(items: items, hasMore: items.length >= perPage);
  }

  Future<OrderDetail> orderDetail(int id) async {
    final data = await _api.get(ApiPaths.orderDetail(id));
    return OrderDetail.fromJson(
      data is Map<String, dynamic> ? data : const {},
    );
  }

  Future<CheckoutResult> checkout({
    required List<Map<String, dynamic>> products,
    String? shippingName,
    String? shippingPhone,
    String? shippingAddress,
    String? shippingPostcode,
  }) async {
    final data = await _api.post(
      ApiPaths.orders,
      body: {
        'products': products,
        if (shippingName != null && shippingName.trim().isNotEmpty)
          'shipping_name': shippingName.trim(),
        if (shippingPhone != null && shippingPhone.trim().isNotEmpty)
          'shipping_phone': shippingPhone.trim(),
        if (shippingAddress != null && shippingAddress.trim().isNotEmpty)
          'shipping_address': shippingAddress.trim(),
        if (shippingPostcode != null && shippingPostcode.trim().isNotEmpty)
          'shipping_postcode': shippingPostcode.trim(),
      },
    );
    return CheckoutResult.fromJson(
      data is Map<String, dynamic> ? data : const {},
    );
  }

  Future<PayResult> pay(int orderId) async {
    final data = await _api.post(ApiPaths.orderPay(orderId));
    return PayResult.fromJson(
      data is Map<String, dynamic> ? data : const {},
    );
  }
}
