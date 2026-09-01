/// Resolves API/storage host — same base used by [ApiClient] but without the
/// `/api/v1` suffix, since uploaded media (`/storage/...`) is served directly
/// by Laravel, not under the API prefix.
abstract final class AppEnv {
  static const String apiHost = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000',
  );

  /// Turns a backend-relative path (e.g. `/storage/logos/x.png`) into a
  /// fully-qualified URL the device can actually fetch. Absolute URLs
  /// (`http://`, `https://`) are returned unchanged.
  static String resolveUrl(String path) {
    if (path.isEmpty) return path;
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path;
    }
    if (path.startsWith('//')) {
      return 'https:$path';
    }
    final normalizedHost =
        apiHost.endsWith('/') ? apiHost.substring(0, apiHost.length - 1) : apiHost;
    final normalizedPath = path.startsWith('/') ? path : '/$path';
    return '$normalizedHost$normalizedPath';
  }
}
