import 'package:dio/dio.dart';

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
    return const ApiException('Masa sambungan tamat. Sila cuba lagi.', statusCode: 0);
  }

  if (error.type == DioExceptionType.connectionError ||
      error.type == DioExceptionType.badCertificate) {
    return const ApiException('Tiada sambungan internet.', statusCode: 0);
  }

  final body = response?.data;
  final message = switch (body) {
    Map<String, dynamic> data when data['message'] is String => data['message'] as String,
    Map<String, dynamic> data when data['errors'] is Map => _firstValidationError(data),
    _ => null,
  };

  return switch (status) {
    401 => const ApiException(
        'Sesi tamat, sila log masuk semula.',
        statusCode: 401,
      ),
    403 => const ApiException('Anda tiada kebenaran untuk akses ini.', statusCode: 403),
    404 => const ApiException('Tidak dijumpai.', statusCode: 404),
    422 => ApiException(
        message ?? 'Sila semak semula maklumat anda.',
        statusCode: 422,
        errors: _extractErrors(body),
      ),
    429 => const ApiException('Terlalu banyak percubaan. Sila cuba lagi kemudian.', statusCode: 429),
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
