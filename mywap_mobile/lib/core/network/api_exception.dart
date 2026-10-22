/// Friendly API error surfaced to the UI.
class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode, this.errors, this.code});

  final String message;
  final int? statusCode;
  final Map<String, List<String>>? errors;
  final ApiErrorCode? code;

  @override
  String toString() => message;
}

/// Stable error categories for locale-aware messages.
enum ApiErrorCode {
  timeout,
  offline,
  unauthorized,
  forbidden,
  notFound,
  validation,
  tooManyRequests,
  unknown,
}
