class PollSummary {
  const PollSummary({
    this.id,
    this.title,
    this.description,
    this.type,
    this.endsAt,
    this.endsAtFormatted,
    this.showResults = false,
    this.isExpired = false,
    this.responseCount = 0,
    this.hasResponded = false,
    this.myResponseId,
  });

  final int? id;
  final String? title;
  final String? description;
  final String? type;
  final String? endsAt;
  final String? endsAtFormatted;
  final bool showResults;
  final bool isExpired;
  final int responseCount;
  final bool hasResponded;
  final int? myResponseId;

  factory PollSummary.fromJson(Map<String, dynamic> json) => PollSummary(
        id: json['id'] as int?,
        title: json['title'] as String?,
        description: json['description'] as String?,
        type: json['type'] as String?,
        endsAt: json['ends_at'] as String?,
        endsAtFormatted: json['ends_at_formatted'] as String?,
        showResults: json['show_results'] as bool? ?? false,
        isExpired: json['is_expired'] as bool? ?? false,
        responseCount: json['response_count'] as int? ?? 0,
        hasResponded: json['has_responded'] as bool? ?? false,
        myResponseId: json['my_response_id'] as int?,
      );
}

class PollListData {
  const PollListData({
    this.availablePolls = const [],
    this.answeredPolls = const [],
  });

  final List<PollSummary> availablePolls;
  final List<PollSummary> answeredPolls;

  factory PollListData.fromJson(Map<String, dynamic> json) => PollListData(
        availablePolls: _parse(json['availablePolls'], PollSummary.fromJson),
        answeredPolls: _parse(json['answeredPolls'], PollSummary.fromJson),
      );
}

class PollOption {
  const PollOption({this.id, this.optionText});

  final int? id;
  final String? optionText;

  factory PollOption.fromJson(Map<String, dynamic> json) => PollOption(
        id: json['id'] as int?,
        optionText: json['option_text'] as String?,
      );
}

class PollQuestion {
  const PollQuestion({this.id, this.questionText, this.type, this.options = const []});

  final int? id;
  final String? questionText;
  final String? type;
  final List<PollOption> options;

  bool get isMultiple =>
      type == 'multiple_choice' || type == 'multiple' || type == 'checkbox';

  factory PollQuestion.fromJson(Map<String, dynamic> json) => PollQuestion(
        id: json['id'] as int?,
        questionText: json['question_text'] as String?,
        type: json['type'] as String?,
        options: _parse(json['options'], PollOption.fromJson),
      );
}

class Poll {
  const Poll({
    this.id,
    this.title,
    this.description,
    this.type,
    this.endsAt,
    this.endsAtFormatted,
    this.showResults = false,
    this.isExpired = false,
    this.questions = const [],
  });

  final int? id;
  final String? title;
  final String? description;
  final String? type;
  final String? endsAt;
  final String? endsAtFormatted;
  final bool showResults;
  final bool isExpired;
  final List<PollQuestion> questions;

  factory Poll.fromJson(Map<String, dynamic> json) => Poll(
        id: json['id'] as int?,
        title: json['title'] as String?,
        description: json['description'] as String?,
        type: json['type'] as String?,
        endsAt: json['ends_at'] as String?,
        endsAtFormatted: json['ends_at_formatted'] as String?,
        showResults: json['show_results'] as bool? ?? false,
        isExpired: json['is_expired'] as bool? ?? false,
        questions: _parse(json['questions'], PollQuestion.fromJson),
      );
}

class PollResultOption {
  const PollResultOption({
    this.id,
    this.optionText,
    this.count = 0,
    this.percentage = 0,
    this.widthPct = 0,
  });

  final int? id;
  final String? optionText;
  final int count;
  final double percentage;
  final double widthPct;

  factory PollResultOption.fromJson(Map<String, dynamic> json) =>
      PollResultOption(
        id: json['id'] as int?,
        optionText: json['option_text'] as String?,
        count: json['count'] as int? ?? 0,
        percentage: (json['percentage'] as num?)?.toDouble() ?? 0,
        widthPct: (json['width_pct'] as num?)?.toDouble() ?? 0,
      );
}

class PollResultQuestion {
  const PollResultQuestion({
    this.id,
    this.questionText,
    this.type,
    this.options = const [],
    this.totalAnswers = 0,
  });

  final int? id;
  final String? questionText;
  final String? type;
  final List<PollResultOption> options;
  final int totalAnswers;

  factory PollResultQuestion.fromJson(Map<String, dynamic> json) =>
      PollResultQuestion(
        id: json['id'] as int?,
        questionText: json['question_text'] as String?,
        type: json['type'] as String?,
        options: _parse(json['options'], PollResultOption.fromJson),
        totalAnswers: json['total_answers'] as int? ?? 0,
      );
}

class PollResults {
  const PollResults({
    this.poll,
    this.questions = const [],
    this.totalResponses = 0,
    this.myAnswers = const [],
  });

  final Poll? poll;
  final List<PollResultQuestion> questions;
  final int totalResponses;
  final List<int> myAnswers;

  factory PollResults.fromJson(Map<String, dynamic> json) => PollResults(
        poll: json['poll'] is Map<String, dynamic>
            ? Poll.fromJson(json['poll'] as Map<String, dynamic>)
            : null,
        questions: _parse(json['questions'], PollResultQuestion.fromJson),
        totalResponses: json['total_responses'] as int? ?? 0,
        myAnswers: _parseIntList(json['my_answers']),
      );
}

List<T> _parse<T>(dynamic value, T Function(Map<String, dynamic>) fromJson) {
  if (value is! List) return const [];
  return value
      .whereType<Map<String, dynamic>>()
      .map(fromJson)
      .toList(growable: false);
}

List<int> _parseIntList(dynamic value) {
  if (value is! List) return const [];
  return value.whereType<int>().toList(growable: false);
}
