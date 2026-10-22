import 'package:dio/dio.dart';

import '../../shared/l10n/l10n.dart';
import '../storage/token_storage.dart';
import 'api_exception.dart';

/// Attaches the Bearer token on every request (when present) and maps Dio
/// errors to a friendly [ApiException] with Malay messages.
class ApiInterceptor extends Interceptor {
  ApiInterceptor(this._tokenStorage);

  final TokenStorage _tokenStorage;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await _tokenStorage.read();
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    handler.next(err);
  }
}

/// Maps a [DioException] to a friendly [ApiException].
ApiException mapApiError(DioException error) {
  final response = error.response;
  final status = response?.statusCode;

  if (error.type == DioExceptionType.connectionTimeout ||
      error.type == DioExceptionType.sendTimeout ||
      error.type == DioExceptionType.receiveTimeout) {
    return ApiException(
      currentAppLocalizations.errorTimeout,
      statusCode: 0,
      code: ApiErrorCode.timeout,
    );
  }

  if (error.type == DioExceptionType.connectionError ||
      error.type == DioExceptionType.badCertificate) {
    return ApiException(
      currentAppLocalizations.errorOffline,
      statusCode: 0,
      code: ApiErrorCode.offline,
    );
  }

  final body = response?.data;
  final message = switch (body) {
    Map<String, dynamic> data when data['message'] is String =>
      data['message'] as String,
    Map<String, dynamic> data when data['errors'] is Map =>
      _firstValidationError(data),
    _ => null,
  };

  return switch (status) {
    401 => ApiException(
      currentAppLocalizations.errorSessionExpired,
      statusCode: 401,
      code: ApiErrorCode.unauthorized,
    ),
    403 => ApiException(
      currentAppLocalizations.errorForbidden,
      statusCode: 403,
      code: ApiErrorCode.forbidden,
    ),
    404 => ApiException(
      currentAppLocalizations.errorNotFound,
      statusCode: 404,
      code: ApiErrorCode.notFound,
    ),
    422 => ApiException(
      message ?? currentAppLocalizations.errorValidation,
      statusCode: 422,
      errors: _extractErrors(body),
      code: ApiErrorCode.validation,
    ),
    429 => ApiException(
      currentAppLocalizations.errorTooMany,
      statusCode: 429,
      code: ApiErrorCode.tooManyRequests,
    ),
    _ => ApiException(
      message ?? 'Ralat tidak dijangka. Sila cuba lagi.',
      statusCode: status,
    ),
  };
}

String? _firstValidationError(Map<String, dynamic> data) {
  final errors = data['errors'];
  if (errors is Map) {
    for (final entry in errors.values) {
      if (entry is List && entry.isNotEmpty) {
        return entry.first.toString();
      }
    }
  }
  return null;
}

Map<String, List<String>>? _extractErrors(dynamic body) {
  if (body is! Map<String, dynamic>) return null;
  final errors = body['errors'];
  if (errors is! Map) return null;
  final result = <String, List<String>>{};
  errors.forEach((key, value) {
    if (value is List) {
      result[key.toString()] = value.map((e) => e.toString()).toList();
    }
  });
  return result.isEmpty ? null : result;
}
