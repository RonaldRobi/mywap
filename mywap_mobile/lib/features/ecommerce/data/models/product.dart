/// Plain immutable product models. JSON keys match the backend
/// `ProductService::serialize*` shapes exactly.
library;

import 'parsing.dart';

class Category {
  const Category({this.id, this.name, this.description});

  final int? id;
  final String? name;
  final String? description;

  factory Category.fromJson(Map<String, dynamic> json) => Category(
        id: parseIntValue(json['id']),
        name: json['name'] as String?,
        description: json['description'] as String?,
      );
}

class ProductOrganization {
  const ProductOrganization({this.id, this.name});

  final int? id;
  final String? name;

  factory ProductOrganization.fromJson(Map<String, dynamic> json) =>
      ProductOrganization(
        id: parseIntValue(json['id']),
        name: json['name'] as String?,
      );
}

class ProductVariationOption {
  const ProductVariationOption({
    this.id,
    this.name,
    this.priceAdjustment,
    this.stock,
    this.hexColor,
    this.image,
    this.sortOrder,
  });

  final int? id;
  final String? name;
  final double? priceAdjustment;
  final int? stock;
  final String? hexColor;
  final String? image;
  final int? sortOrder;

  factory ProductVariationOption.fromJson(Map<String, dynamic> json) =>
      ProductVariationOption(
        id: parseIntValue(json['id']),
        name: json['name'] as String?,
        priceAdjustment: parseDoubleValue(json['price_adjustment']),
        stock: parseIntValue(json['stock']),
        hexColor: json['hex_color'] as String?,
        image: json['image'] as String?,
        sortOrder: parseIntValue(json['sort_order']),
      );
}

class ProductVariation {
  const ProductVariation({
    this.id,
    this.name,
    this.type,
    this.required,
    this.sortOrder,
    this.options = const [],
  });

  final int? id;
  final String? name;
  final String? type;
  final bool? required;
  final int? sortOrder;
  final List<ProductVariationOption> options;

  factory ProductVariation.fromJson(Map<String, dynamic> json) =>
      ProductVariation(
        id: parseIntValue(json['id']),
        name: json['name'] as String?,
        type: json['type'] as String?,
        required: json['required'] is bool ? json['required'] as bool : null,
        sortOrder: parseIntValue(json['sort_order']),
        options: parseList(json['options'], ProductVariationOption.fromJson),
      );
}

class Product {
  const Product({
    this.id,
    this.name,
    this.description,
    this.price,
    this.memberPrice,
    this.postageCost,
    this.stock,
    this.categoryId,
    this.organisasiId,
    this.image,
    this.images = const [],
    this.status,
    this.variationsCount,
    this.category,
    this.organization,
    this.isMember = false,
    this.priceForMember,
    this.variations = const [],
  });

  final int? id;
  final String? name;
  final String? description;
  final double? price;
  final double? memberPrice;
  final double? postageCost;
  final int? stock;
  final int? categoryId;
  final int? organisasiId;
  final String? image;
  final List<String> images;
  final bool? status;
  final int? variationsCount;
  final Category? category;
  final ProductOrganization? organization;

  /// `is_member` — the requesting user qualifies for the member price.
  final bool isMember;
  final double? priceForMember;
  final List<ProductVariation> variations;

  /// Primary display image (gallery first, else cover).
  String? get displayImage => images.isNotEmpty ? images.first : image;

  /// Effective price for the current user (member price wins when eligible).
  double get effectivePrice =>
      isMember && priceForMember != null ? priceForMember! : (price ?? 0);

  factory Product.fromJson(Map<String, dynamic> json) => Product(
        id: parseIntValue(json['id']),
        name: json['name'] as String?,
        description: json['description'] as String?,
        price: parseDoubleValue(json['price']),
        memberPrice: parseDoubleValue(json['member_price']),
        postageCost: parseDoubleValue(json['postage_cost']),
        stock: parseIntValue(json['stock']),
        categoryId: parseIntValue(json['category_id']),
        organisasiId: parseIntValue(json['organisasi_id']),
        image: json['image'] as String?,
        images: parseStringList(json['images']),
        status: json['status'] is bool ? json['status'] as bool : null,
        variationsCount: parseIntValue(json['variations_count']),
        category: json['category'] is Map<String, dynamic>
            ? Category.fromJson(json['category'] as Map<String, dynamic>)
            : null,
        organization: json['organization'] is Map<String, dynamic>
            ? ProductOrganization.fromJson(
                json['organization'] as Map<String, dynamic>)
            : null,
        isMember: parseBoolValue(json['is_member']),
        priceForMember: parseDoubleValue(json['price_for_member']),
        variations: parseList(json['variations'], ProductVariation.fromJson),
      );
}

/// `GET /products/{id}` payload: product (with variations) + related products.
class ProductDetail {
  const ProductDetail({this.product, this.relatedProducts = const []});

  final Product? product;
  final List<Product> relatedProducts;

  factory ProductDetail.fromJson(Map<String, dynamic> json) => ProductDetail(
        product: json['product'] is Map<String, dynamic>
            ? Product.fromJson(json['product'] as Map<String, dynamic>)
            : null,
        relatedProducts: parseList(json['relatedProducts'], Product.fromJson),
      );
}
