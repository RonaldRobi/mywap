/// API path constants. All paths are relative to `/api/v1` (set on the Dio
/// [BaseOptions.baseUrl]). Keys must match the Laravel routes in
/// `routes/api/v1/*` exactly.
abstract final class ApiPaths {
  // ---- App config (public, sebelum login) ----
  static const String appConfig = '/app-config';
  static const String publicHome = '/public/home';

  // ---- Auth ----
  static const String login = '/auth/login';
  static const String logout = '/auth/logout';
  static const String me = '/auth/me';
  static const String register = '/auth/register';
  static String resolveReferral(String code) => '/auth/referral/$code';
  static const String checkMember = '/auth/check-member';
  static const String forgotId = '/auth/forgot-id';
  static const String forgotPassword = '/auth/forgot-password';
  static const String resetPassword = '/auth/reset-password';
  static const String verifyIdentity = '/auth/verify-identity';
  static const String sendOtp = '/auth/send-otp';
  static const String updateAndSendOtp = '/auth/update-and-send-otp';
  static const String verifyOtp = '/auth/verify-otp';

  // ---- Member ----
  static const String memberDashboard = '/member/dashboard';
  static const String memberRegistrations = '/member/registrations';
  static const String memberCard = '/member/card';
  static const String memberCardLetter = '/member/card/letter';
  static const String memberFeeStatus = '/member/fee-status';
  static const String memberPayFee = '/member/pay-fee';
  static const String memberAnnouncements = '/member/announcements';
  static String memberAnnouncementReact(int id) =>
      '/member/announcements/$id/react';
  static String memberAnnouncementRead(int id) =>
      '/member/announcements/$id/read';
  static const String memberLibrary = '/member/library';
  static const String memberReferral = '/member/referral';
  static const String memberFinancialOverview = '/member/financial/overview';
  static String memberPaymentReceipt(int paymentId) =>
      '/member/payments/$paymentId/receipt';

  // ---- Event registration (member) ----
  static String eventRegistrationForm(int eventId, int formId) =>
      '/events/$eventId/registration/$formId';
  static String eventRegistration(int eventId) =>
      '/events/$eventId/registration';

  // ---- Organization ----
  static const String organizationInfo = '/organization/info';

  // ---- Events ----
  static const String events = '/events';
  static String eventDetail(int id) => '/events/$id';
  static String eventRsvp(int id) => '/events/$id/rsvp';
  static String eventCheckIn(int id) => '/events/$id/check-in';

  // ---- Profile ----
  static const String profile = '/profile';
  static const String profileComplete = '/profile/complete';
  static const String profileEditMeta = '/profile/edit-meta';
  static const String profilePassword = '/profile/password';
  static const String profilePhoto = '/profile/photo';

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
  static String orderReceive(int id) => '/orders/$id/receive';

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
  static const String pushDebug = '/push-debug';

  // ---- Moderation (report & block UGC) ----
  static const String reports = '/reports';
  static const String blocks = '/blocks';
  static String unblock(int userId) => '/blocks/$userId';
}
