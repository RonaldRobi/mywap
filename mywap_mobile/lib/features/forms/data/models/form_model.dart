import '../../../../core/utils/json_parsing.dart';

class FormQuestion {
  const FormQuestion({
    this.id,
    this.label,
    this.type,
    this.options = const [],
    this.required = false,
    this.placeholder,
    this.helpText,
  });

  final int? id;
  final String? label;
  final String? type;
  final List<String> options;
  final bool required;
  final String? placeholder;
  final String? helpText;

  bool get isFile => type == 'file';
  bool get isMultipleChoice => type == 'checkbox';

  factory FormQuestion.fromJson(Map<String, dynamic> json) => FormQuestion(
    id: parseIntValue(json['id']),
    label: json['label'] as String?,
    type: json['type'] as String?,
    options: _parseStrings(json['options']),
    required: parseBoolValue(json['required']),
    placeholder: json['placeholder'] as String?,
    helpText: json['help_text'] as String?,
  );
}

/// A selectable variant of an add-on (e.g. a jersey size).
class FormAddonVariant {
  const FormAddonVariant({this.id, this.name, this.price, this.stock});

  final int? id;
  final String? name;
  final double? price;
  final int? stock;

  factory FormAddonVariant.fromJson(Map<String, dynamic> json) =>
      FormAddonVariant(
        id: parseIntValue(json['id']),
        name: json['name'] as String?,
        price: parseDoubleValue(json['price']),
        stock: parseIntValue(json['stock']),
      );
}

/// An optional add-on / merchandise item purchasable with a registration.
class FormAddon {
  const FormAddon({
    this.id,
    this.name,
    this.description,
    this.price,
    this.stock,
    this.imageUrl,
    this.hasVariants = false,
    this.isRequired = false,
    this.variants = const [],
  });

  final int? id;
  final String? name;
  final String? description;
  final double? price;
  final int? stock;
  final String? imageUrl;
  final bool hasVariants;
  final bool isRequired;
  final List<FormAddonVariant> variants;

  factory FormAddon.fromJson(Map<String, dynamic> json) => FormAddon(
    id: parseIntValue(json['id']),
    name: json['name'] as String?,
    description: json['description'] as String?,
    price: parseDoubleValue(json['price']),
    stock: parseIntValue(json['stock']),
    imageUrl: json['image_url'] as String?,
    hasVariants: parseBoolValue(json['has_variants']),
    isRequired: parseBoolValue(json['is_required']),
    variants: _parseAddonVariants(json['variants']),
  );

  /// Resolve the unit price for a chosen variant, falling back to the item
  /// price when the variant has no explicit price.
  double priceFor(int? variantId) {
    if (!hasVariants) return price ?? 0;
    for (final variant in variants) {
      if (variant.id == variantId) return variant.price ?? price ?? 0;
    }
    return price ?? 0;
  }
}

class FormModel {
  const FormModel({
    this.id,
    this.title,
    this.description,
    this.price,
    this.tiers,
    this.addons = const [],
    this.paymentRequired = false,
    this.terms,
    this.eventId,
    this.shareToken,
    this.organizationName,
    this.branchOptions = const [],
    this.headerImageUrl,
    this.questions = const [],
    this.redirectTo,
  });

  final int? id;
  final String? title;
  final String? description;
  final double? price;
  final List<FormTier>? tiers;
  final List<FormAddon> addons;
  final bool paymentRequired;
  final String? terms;
  final int? eventId;
  final String? shareToken;
  final String? organizationName;
  final List<String> branchOptions;
  final String? headerImageUrl;
  final List<FormQuestion> questions;
  final String? redirectTo;

  factory FormModel.fromJson(Map<String, dynamic> json) => FormModel(
    id: parseIntValue(json['id']),
    title: json['title'] as String?,
    description: json['description'] as String?,
    price: parseDoubleValue(json['price']),
    tiers: _parseTiers(json['price_tiers']),
    addons: _parseAddons(json['items']),
    paymentRequired: parseBoolValue(json['payment_required']),
    terms: json['terms'] as String?,
    eventId: parseIntValue(json['event_id']),
    shareToken: json['share_token'] as String?,
    organizationName: json['organization_name'] as String?,
    branchOptions: _parseStrings(json['branch_options']),
    headerImageUrl: json['header_image_url'] as String?,
    questions: _parseQuestions(json['questions']),
    redirectTo: json['redirect_to'] as String?,
  );
}

class FormTier {
  const FormTier({
    this.label,
    this.price,
    this.isDefault = false,
    this.requiresDocument = false,
    this.description,
  });

  final String? label;
  final double? price;
  final bool isDefault;
  final bool requiresDocument;
  final String? description;

  factory FormTier.fromJson(Map<String, dynamic> json) => FormTier(
    label: json['label'] as String?,
    price: parseDoubleValue(json['price']),
    isDefault: parseBoolValue(json['is_default']),
    requiresDocument: parseBoolValue(json['requires_document']),
    description: json['description'] as String?,
  );
}

List<String> _parseStrings(dynamic value) {
  if (value is! List) return const [];
  return value.whereType<String>().toList(growable: false);
}

List<FormTier>? _parseTiers(dynamic value) {
  if (value is! List || value.isEmpty) return null;
  return value
      .whereType<Map<String, dynamic>>()
      .map(FormTier.fromJson)
      .toList(growable: false);
}

List<FormAddonVariant> _parseAddonVariants(dynamic value) {
  if (value is! List) return const [];
  return value
      .whereType<Map<String, dynamic>>()
      .map(FormAddonVariant.fromJson)
      .toList(growable: false);
}

List<FormAddon> _parseAddons(dynamic value) {
  if (value is! List) return const [];
  return value
      .whereType<Map<String, dynamic>>()
      .map(FormAddon.fromJson)
      .toList(growable: false);
}

List<FormQuestion> _parseQuestions(dynamic value) {
  if (value is! List) return const [];
  return value
      .whereType<Map<String, dynamic>>()
      .map(FormQuestion.fromJson)
      .toList(growable: false);
}
