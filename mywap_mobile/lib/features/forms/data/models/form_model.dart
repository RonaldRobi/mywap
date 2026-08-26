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
        id: json['id'] as int?,
        label: json['label'] as String?,
        type: json['type'] as String?,
        options: _parseStrings(json['options']),
        required: json['required'] as bool? ?? false,
        placeholder: json['placeholder'] as String?,
        helpText: json['help_text'] as String?,
      );
}

class FormModel {
  const FormModel({
    this.id,
    this.title,
    this.description,
    this.price,
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
        id: json['id'] as int?,
        title: json['title'] as String?,
        description: json['description'] as String?,
        price: (json['price'] as num?)?.toDouble(),
        paymentRequired: json['payment_required'] as bool? ?? false,
        terms: json['terms'] as String?,
        eventId: json['event_id'] as int?,
        shareToken: json['share_token'] as String?,
        organizationName: json['organization_name'] as String?,
        branchOptions: _parseStrings(json['branch_options']),
        headerImageUrl: json['header_image_url'] as String?,
        questions: _parseQuestions(json['questions']),
        redirectTo: json['redirect_to'] as String?,
      );
}

List<String> _parseStrings(dynamic value) {
  if (value is! List) return const [];
  return value.whereType<String>().toList(growable: false);
}

List<FormQuestion> _parseQuestions(dynamic value) {
  if (value is! List) return const [];
  return value
      .whereType<Map<String, dynamic>>()
      .map(FormQuestion.fromJson)
      .toList(growable: false);
}
