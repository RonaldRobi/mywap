import 'dart:async';
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

      unawaited(_reportDiagnostic('firebase_ok', extra: {
        'apps': Firebase.apps.map((a) => a.name).toList(),
      }));

      // Pasang listener token SEBELUM meminta token. Di iOS token FCM boleh
      // dijana lewat (selepas APNs token tersedia) — listener awal memastikan
      // token yang tiba lewat tidak terlepas.
      _messaging!.onTokenRefresh.listen((newToken) => registerToken(newToken));

      await _messaging!.requestPermission();

      // Daftar token semasa (dengan tunggu + cuba semula untuk iOS).
      unawaited(_registerCurrentToken());

      FirebaseMessaging.onMessage.listen(
        (message) => _handleForeground(message),
      );
      FirebaseMessaging.onMessageOpenedApp.listen(
        (message) => _handleTap(message),
      );
      final initial = await _messaging!.getInitialMessage();
      if (initial != null) _handleTap(initial);
    } catch (e) {
      initialized = false;
      unawaited(_reportDiagnostic('init_error', extra: {'error': e.toString()}));
    }
  }

  /// Hantar diagnostik ringkas ke backend (log server) untuk menyiasat isu
  /// pendaftaran token. Gagal senyap — tidak pernah menjejaskan app.
  Future<void> _reportDiagnostic(
    String stage, {
    Map<String, dynamic>? extra,
  }) async {
    try {
      await _api.post(
        ApiPaths.pushDebug,
        body: {
          'stage': stage,
          'platform': _platformName,
          ...?extra,
        },
      );
    } catch (_) {
      // Abaikan.
    }
  }

  /// Dapatkan token FCM dan daftarkan ke backend. Di iOS, `getToken()` boleh
  /// memulangkan null selagi APNs token belum tersedia — jadi tunggu APNs
  /// token dahulu dan cuba semula (sehingga ~16s) sebelum menyerah.
  Future<void> _registerCurrentToken() async {
    final messaging = _messaging;
    if (messaging == null) return;

    String? apnsPrefix;
    String? tokenPrefix;
    String? lastError;

    for (var attempt = 0; attempt < 8; attempt++) {
      try {
        if (_isIOS) {
          final apns = await messaging.getAPNSToken();
          apnsPrefix = _prefix(apns);
          if (apns == null || apns.isEmpty) {
            await Future<void>.delayed(const Duration(seconds: 2));
            continue;
          }
        }

        final token = await messaging.getToken();
        tokenPrefix = _prefix(token);
        if (token != null && token.isNotEmpty) {
          await registerToken(token);
          unawaited(_reportDiagnostic('token_ok', extra: {
            'apns': apnsPrefix,
            'fcm': tokenPrefix,
            'attempt': attempt + 1,
          }));
          return;
        }
      } catch (e) {
        lastError = e.toString();
      }

      await Future<void>.delayed(const Duration(seconds: 2));
    }

    unawaited(_reportDiagnostic('token_failed', extra: {
      'apns': apnsPrefix,
      'fcm': tokenPrefix,
      'error': lastError,
    }));
  }

  String? _prefix(String? value) =>
      (value == null || value.isEmpty) ? null : value.substring(0, 12);

  bool get _isIOS {
    try {
      return Platform.isIOS;
    } catch (_) {
      return false;
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
