/// Resolves API/storage host — same base used by [ApiClient] but without the
/// `/api/v1` suffix, since uploaded media (`/storage/...`) is served directly
/// by Laravel, not under the API prefix.
///
/// Defaults to the production host so release builds always talk to
/// `https://mywap.my` even without a `--dart-define`. For local development
/// override it explicitly, e.g. Android emulator:
/// `flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000`.
abstract final class AppEnv {
  static const String apiHost = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://mywap.my',
  );

  /// Turns a backend-relative path into a fully-qualified URL the device can
  /// actually fetch. Absolute URLs (`http://`, `https://`) are returned
  /// unchanged.
  ///
  /// Uploads on Laravel's `public` disk are served under `/storage/`, but many
  /// uploaders store only the bare relative path (e.g. `products/abc.jpg`
  /// from `ProductController`, `library/x.pdf`, `events/x.png`). Paths without
  /// a scheme and without the `/storage/` prefix therefore get `/storage/`
  /// prepended — mirroring the web helper in `resources/js/composables/
  /// useProductImage.js`. Already-prefixed `/storage/...` paths pass through.
  static String resolveUrl(String path) {
    if (path.isEmpty) return path;
    if (path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('//') ||
        path.startsWith('data:') ||
        path.startsWith('blob:')) {
      return path;
    }
    final normalizedHost =
        apiHost.endsWith('/') ? apiHost.substring(0, apiHost.length - 1) : apiHost;
    final normalizedPath = path.startsWith('/storage/')
        ? path
        : '/storage/${path.replaceFirst(RegExp(r'^/+'), '')}';
    return '$normalizedHost$normalizedPath';
  }
}
