import 'dart:convert';
import 'dart:io' show Platform;

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../constants/api_paths.dart';
import '../network/api_client.dart';
import 'firebase_options.dart';

/// Push notification (FCM) service. Di-initialize pada runtime dengan
/// [FirebaseOptions] eksplisit — TANPA google-services.json / GoogleService-Info.plist.
///
/// Semua panggilan Firebase dibalut try/catch supaya app/tests tidak pernah crash
/// bila Firebase belum dikonfigurasi (cth. semasa `flutter test`).
class PushNotificationService {
  PushNotificationService(this._api);

  final ApiClient _api;

  /// `true` selepas FCM berjaya initialized dan token didaftarkan.
  static bool initialized = false;

  FirebaseMessaging? _messaging;
  FlutterLocalNotificationsPlugin? _localNotifications;
  String? _lastToken;

  /// Callback untuk notifikasi foreground (paparkan SnackBar di UI).
  void Function(String payload)? onMessage;

  /// Callback bila pengguna tekan notifikasi (navigate ke skrin).
  void Function(String payload)? onMessageTap;

  /// Initialize Firebase + daftar token. Tidak pernah throws — sebarang
  /// ralat hanya set [initialized] = false.
  Future<void> init() async {
    // Firebase belum dikonfigurasi (options kosong) — skip supaya
    // Firebase.initializeApp tidak lempar NSException dan crash app.
    final options = DefaultFirebaseOptions.currentPlatform;
    if (options.apiKey.isEmpty || options.appId.isEmpty) {
      initialized = false;
      return;
    }
    try {
      await Firebase.initializeApp(options: options);
      _messaging = FirebaseMessaging.instance;
      await _initLocalNotifications();

      // Tandakan initialized seawal mungkin supaya registerToken() di bawah
      // (dan onTokenRefresh) benar-benar menghantar token ke backend. Sebelum
      // ini flag hanya ditetapkan selepas keseluruhan init() selesai, jadi
      // token pertama TIDAK pernah didaftarkan — punca push tak sampai.
      initialized = true;

      await _messaging!.requestPermission();
      final token = await _messaging!.getToken();
      await registerToken(token);

      _messaging!.onTokenRefresh.listen((newToken) => registerToken(newToken));
      FirebaseMessaging.onMessage.listen(
        (message) => _handleForeground(message),
      );
      FirebaseMessaging.onMessageOpenedApp.listen(
        (message) => _handleTap(message),
      );
      final initial = await _messaging!.getInitialMessage();
      if (initial != null) _handleTap(initial);
    } catch (_) {
      initialized = false;
    }
  }

  void _handleForeground(RemoteMessage message) {
    final title = message.notification?.title ?? message.data['title'] ?? '';
    final body = message.notification?.body ?? message.data['body'] ?? '';
    _showLocalNotification(title, body, _payload(message));
    onMessage?.call(_payload(message));
  }

  void _handleTap(RemoteMessage message) {
    onMessageTap?.call(_payload(message));
  }

  /// Sediakan flutter_local_notifications supaya mesej FCM yang diterima
  /// semasa app di latar hadapan (foreground) tetap muncul sebagai banner di
  /// notification bar — bukan sekadar SnackBar. Gagal senyap (null) supaya
  /// tidak menjejaskan aliran utama.
  Future<void> _initLocalNotifications() async {
    try {
      const android = AndroidInitializationSettings('@mipmap/ic_launcher');
      const darwin = DarwinInitializationSettings(
        requestAlertPermission: true,
        requestBadgePermission: true,
        requestSoundPermission: true,
      );
      const settings = InitializationSettings(android: android, iOS: darwin);

      _localNotifications = FlutterLocalNotificationsPlugin();
      await _localNotifications!.initialize(
        settings,
        onDidReceiveNotificationResponse: (response) {
          final payload = response.payload;
          if (payload != null && payload.isNotEmpty) {
            onMessageTap?.call(payload);
          }
        },
      );
    } catch (_) {
      _localNotifications = null;
    }
  }

  /// Papar notifikasi tempatan (banner) untuk mesej foreground.
  Future<void> _showLocalNotification(
    String title,
    String body,
    String payload,
  ) async {
    final plugin = _localNotifications;
    if (plugin == null) return;

    try {
      const details = NotificationDetails(
        android: AndroidNotificationDetails(
          'mywap_notifications',
          'Notifikasi myWAP',
          channelDescription: 'Notifikasi push daripada myWAP',
          importance: Importance.high,
          priority: Priority.high,
        ),
        iOS: DarwinNotificationDetails(
          presentAlert: true,
          presentBanner: true,
          presentSound: true,
        ),
      );

      await plugin.show(
        DateTime.now().millisecondsSinceEpoch % 0x7fffffff,
        title.isNotEmpty ? title : 'myWAP',
        body,
        details,
        payload: payload,
      );
    } catch (_) {
      // Abaikan kegagalan paparan banner foreground.
    }
  }

  /// Bina payload JSON ringkas (data + judul/kandungan) supaya UI dapat
  /// memaparkan SnackBar dan melaksanakan navigasi bila mesej diterima.
  String _payload(RemoteMessage message) {
    final data = Map<String, String>.from(message.data);
    data['title'] = message.notification?.title ?? data['title'] ?? '';
    data['body'] = message.notification?.body ?? data['body'] ?? '';
    return jsonEncode(data);
  }

  /// Daftar token FCM ke backend. Tidak buat apa-apa jika token kosong.
  Future<void> registerToken(String? token) async {
    if (token == null || token.isEmpty) return;

    _lastToken = token;
    try {
      await _api.post(
        ApiPaths.deviceTokens,
        body: {
          'token': token,
          'platform': _platformName,
          'device_name': _deviceName,
        },
      );
    } catch (_) {
      // Kegagalan pendaftaran tidak boleh menjejaskan aliran log masuk.
    }
  }

  /// Nyahdaftar token terakhir dari backend.
  Future<void> unregisterToken() async {
    final token = _lastToken;
    if (token == null || token.isEmpty || !initialized) return;

    _lastToken = null;
    try {
      await _api.delete(ApiPaths.deviceTokens, body: {'token': token});
    } catch (_) {
      // Abaikan kegagalan nyahdaftar.
    }
  }

  String get _platformName {
    try {
      return Platform.isIOS ? 'ios' : 'android';
    } catch (_) {
      return 'android';
    }
  }

  String get _deviceName {
    try {
      return Platform.operatingSystemVersion;
    } catch (_) {
      return '';
    }
  }
}
