/// API path constants. All paths are relative to `/api/v1` (set on the Dio
/// [BaseOptions.baseUrl]). Keys must match the Laravel routes in
/// `routes/api/v1/*` exactly.
abstract final class ApiPaths {
  // ---- App config (public, sebelum login) ----
  static const String appConfig = '/app-config';

  // ---- Auth ----
  static const String login = '/auth/login';
  static const String logout = '/auth/logout';
  static const String me = '/auth/me';

  // ---- Member ----
  static const String memberDashboard = '/member/dashboard';
  static const String memberRegistrations = '/member/registrations';
  static const String memberCard = '/member/card';
  static const String memberFeeStatus = '/member/fee-status';
  static const String memberAnnouncements = '/member/announcements';
  static String memberAnnouncementReact(int id) => '/member/announcements/$id/react';
  static String memberAnnouncementRead(int id) => '/member/announcements/$id/read';
  static const String memberLibrary = '/member/library';

  // ---- Events ----
  static const String events = '/events';
  static String eventDetail(int id) => '/events/$id';
  static String eventRsvp(int id) => '/events/$id/rsvp';

  // ---- Profile ----
  static const String profile = '/profile';
  static const String profileComplete = '/profile/complete';
  static const String profileEditMeta = '/profile/edit-meta';

  // ---- News ----
  static const String news = '/news';
  static String newsDetail(int id) => '/news/$id';
  static String newsReact(int id) => '/news/$id/react';
  static String newsComments(int id) => '/news/$id/comments';

  // ---- Articles ----
  static const String articles = '/articles';
  static String articleDetail(int id) => '/articles/$id';
  static String articleReact(int id) => '/articles/$id/react';
  static String articleComments(int id) => '/articles/$id/comments';

  // ---- Videos ----
  static const String videos = '/videos';

  // ---- Infaq ----
  static const String infaq = '/infaq';
  static String infaqDetail(String slug) => '/infaq/$slug';
  static String infaqDonate(String slug) => '/infaq/$slug/donate';

  // ---- Ecommerce ----
  static const String products = '/products';
  static String productDetail(int id) => '/products/$id';
  static const String categories = '/categories';
  static const String orders = '/orders';
  static String orderDetail(int id) => '/orders/$id';
  static String orderPay(int id) => '/orders/$id/pay';

  // ---- Facilities ----
  static const String facilities = '/facilities';
  static String facilityDetail(int id) => '/facilities/$id';
  static String facilityBook(int id) => '/facilities/$id/book';

  // ---- Usrah ----
  static const String usrah = '/usrah';

  // ---- Forms ----
  static String formDetail(String token) => '/forms/$token';
  static String formSubmit(String token) => '/forms/$token/submit';

  // ---- Polls ----
  static const String polls = '/polls';
  static String pollDetail(int id) => '/polls/$id';
  static String pollRespond(int id) => '/polls/$id/respond';
  static String pollResults(int id) => '/polls/$id/results';

  // ---- Directory / Chat / Notifications ----
  static const String directory = '/directory';
  static String publicCard(String memberNo) => '/card/$memberNo';
  static const String chat = '/chat';
  static const String notifications = '/notifications';
  static const String notificationsReadAll = '/notifications/read-all';

  // ---- Push notification / FCM ----
  static const String deviceTokens = '/device-tokens';
}
