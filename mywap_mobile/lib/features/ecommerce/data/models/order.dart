/// Plain immutable order models. JSON keys match the backend
/// `OrderService::serialize` shape exactly.
library;

import 'parsing.dart';

class OrderItemProduct {
  const OrderItemProduct({this.id, this.name, this.price, this.image});

  final int? id;
  final String? name;
  final double? price;
  final String? image;

  factory OrderItemProduct.fromJson(Map<String, dynamic> json) =>
      OrderItemProduct(
        id: parseIntValue(json['id']),
        name: json['name'] as String?,
        price: parseDoubleValue(json['price']),
        image: json['image'] as String?,
      );
}

class OrderItem {
  const OrderItem({
    this.id,
    this.productId,
    this.product,
    this.productVariationOptionId,
    this.variationSnapshot,
    this.quantity,
    this.price,
  });

  final int? id;
  final int? productId;
  final OrderItemProduct? product;
  final int? productVariationOptionId;
  final String? variationSnapshot;
  final int? quantity;
  final double? price;

  factory OrderItem.fromJson(Map<String, dynamic> json) => OrderItem(
        id: parseIntValue(json['id']),
        productId: parseIntValue(json['product_id']),
        product: json['product'] is Map<String, dynamic>
            ? OrderItemProduct.fromJson(json['product'] as Map<String, dynamic>)
            : null,
        productVariationOptionId:
            parseIntValue(json['product_variation_option_id']),
        variationSnapshot: json['variation_snapshot'] as String?,
        quantity: parseIntValue(json['quantity']),
        price: parseDoubleValue(json['price']),
      );
}

class PaymentInfo {
  const PaymentInfo({
    this.id,
    this.amount,
    this.status,
    this.reference,
    this.gateway,
    this.description,
    this.createdAt,
  });

  final int? id;
  final double? amount;
  final String? status;
  final String? reference;
  final String? gateway;
  final String? description;
  final String? createdAt;

  factory PaymentInfo.fromJson(Map<String, dynamic> json) => PaymentInfo(
        id: parseIntValue(json['id']),
        amount: parseDoubleValue(json['amount']),
        status: json['status'] as String?,
        reference: json['reference'] as String?,
        gateway: json['gateway'] as String?,
        description: json['description'] as String?,
        createdAt: json['created_at'] as String?,
      );
}

class Order {
  const Order({
    this.id,
    this.userId,
    this.organisasiId,
    this.total,
    this.postageCost,
    this.status,
    this.trackingNo,
    this.shippingName,
    this.shippingAddress,
    this.shippingPostcode,
    this.shippingPhone,
    this.courier,
    this.createdAt,
    this.updatedAt,
    this.items = const [],
    this.payments = const [],
  });

  final int? id;
  final int? userId;
  final int? organisasiId;
  final double? total;
  final double? postageCost;
  final String? status;
  final String? trackingNo;
  final String? shippingName;
  final String? shippingAddress;
  final String? shippingPostcode;
  final String? shippingPhone;
  final String? courier;
  final String? createdAt;
  final String? updatedAt;
  final List<OrderItem> items;
  final List<PaymentInfo> payments;

  double get grandTotal => (total ?? 0) + (postageCost ?? 0);

  int get itemCount => items.fold(0, (sum, item) => sum + (item.quantity ?? 0));

  factory Order.fromJson(Map<String, dynamic> json) => Order(
        id: parseIntValue(json['id']),
        userId: parseIntValue(json['user_id']),
        organisasiId: parseIntValue(json['organisasi_id']),
        total: parseDoubleValue(json['total']),
        postageCost: parseDoubleValue(json['postage_cost']),
        status: json['status'] as String?,
        trackingNo: json['tracking_no'] as String?,
        shippingName: json['shipping_name'] as String?,
        shippingAddress: json['shipping_address'] as String?,
        shippingPostcode: json['shipping_postcode'] as String?,
        shippingPhone: json['shipping_phone'] as String?,
        courier: json['courier'] as String?,
        createdAt: json['created_at'] as String?,
        updatedAt: json['updated_at'] as String?,
        items: parseList(json['items'], OrderItem.fromJson),
        payments: parseList(json['payments'], PaymentInfo.fromJson),
      );
}

/// `GET /orders/{id}` returns the serialized order directly (with payments).
class OrderDetail {
  const OrderDetail({this.order});

  final Order? order;

  factory OrderDetail.fromJson(Map<String, dynamic> json) =>
      OrderDetail(order: Order.fromJson(json));
}

/// `POST /orders` → `{ order, payment_url }`.
class CheckoutResult {
  const CheckoutResult({this.order, this.paymentUrl});

  final Order? order;
  final String? paymentUrl;

  factory CheckoutResult.fromJson(Map<String, dynamic> json) => CheckoutResult(
        order: json['order'] is Map<String, dynamic>
            ? Order.fromJson(json['order'] as Map<String, dynamic>)
            : null,
        paymentUrl: json['payment_url'] as String?,
      );
}

/// `POST /orders/{id}/pay` → `{ status, payment_url? }`.
/// (`already_paid`/`error` surface as [ApiException] from the 422 response.)
class PayResult {
  const PayResult({this.status = 'success', this.paymentUrl});

  final String status;
  final String? paymentUrl;

  factory PayResult.fromJson(Map<String, dynamic> json) => PayResult(
        status: json['status'] as String? ?? 'success',
        paymentUrl: json['payment_url'] as String?,
      );
}
