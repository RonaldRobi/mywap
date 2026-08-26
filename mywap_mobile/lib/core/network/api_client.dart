import 'package:dio/dio.dart';

import 'api_interceptor.dart';
import '../storage/token_storage.dart';

/// Thin Dio wrapper shared by all repositories.
///
/// Base URL: `API_BASE_URL` from dart-define (Android emulator default
/// `http://10.0.2.2:8000` → host loopback). `/api/v1` is appended here so
/// repositories only deal with [ApiPaths]-style paths.
class ApiClient {
  ApiClient(this.tokenStorage, {Dio? dio})
      : _dio = dio ??
            Dio(
              BaseOptions(
                baseUrl:
                    '${const String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000')}/api/v1',
                connectTimeout: const Duration(seconds: 15),
                receiveTimeout: const Duration(seconds: 20),
                headers: const {'Accept': 'application/json'},
              ),
            ) {
    _dio.interceptors.add(ApiInterceptor(tokenStorage));
  }

  final TokenStorage tokenStorage;
  final Dio _dio;

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) async {
    try {
      final response = await _dio.get<dynamic>(path, queryParameters: query);
      return _unwrap(response);
    } on DioException catch (e) {
      throw mapApiError(e);
    }
  }

  Future<dynamic> post(String path, {Object? body, Map<String, dynamic>? query}) async {
    try {
      final response = await _dio.post<dynamic>(path, data: body, queryParameters: query);
      return _unwrap(response);
    } on DioException catch (e) {
      throw mapApiError(e);
    }
  }

  Future<dynamic> delete(String path, {Object? body, Map<String, dynamic>? query}) async {
    try {
      final response = await _dio.delete<dynamic>(path, data: body, queryParameters: query);
      return _unwrap(response);
    } on DioException catch (e) {
      throw mapApiError(e);
    }
  }

  /// Response envelope is `{ data: ... }` — return the `data` key directly.
  dynamic _unwrap(Response<dynamic> response) {
    final body = response.data;
    if (body is Map<String, dynamic> && body.containsKey('data')) {
      return body['data'];
    }
    return body;
  }
}
