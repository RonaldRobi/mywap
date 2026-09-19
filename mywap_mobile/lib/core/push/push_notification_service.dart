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

  /// Saluran notifikasi Android. Mesti sepadan dengan
  /// `com.google.firebase.messaging.default_notification_channel_id` dalam
  /// AndroidManifest.xml.
  static const String _androidChannelId = 'mywap_notifications';

  FirebaseMessaging? _messaging;
  FlutterLocalNotificationsPlugin? _localNotifications;
  String? _lastToken;

  /// Elak memasang listener berulang kali bila [init] dipanggil lebih daripada
  /// sekali (login/logout/restore sesi).
  bool _listenersAttached = false;

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
      // `Firebase.initializeApp` akan lempar bila dipanggil dua kali dalam
      // proses yang sama (cth. logout → login semula). Semak dahulu supaya
      // pendaftaran token tidak terlangkau oleh pengecualian itu.
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp(options: options);
      }
      _messaging = FirebaseMessaging.instance;

      // Paksa auto-init ON → plugin iOS akan panggil
      // `registerForRemoteNotifications` (tanpa ini APNs token boleh kekal
      // null dan `getToken()` tak pernah berjaya).
      try {
        await _messaging!.setAutoInitEnabled(true);
      } catch (_) {
        // Versi platform tertentu mungkin tak sokong — teruskan.
      }

      if (!_listenersAttached) {
        FirebaseMessaging.onBackgroundMessage(
          firebaseMessagingBackgroundHandler,
        );
      }

      await _initLocalNotifications();

      // Tandakan initialized seawal mungkin supaya registerToken() di bawah
      // (dan onTokenRefresh) benar-benar menghantar token ke backend. Sebelum
      // ini flag hanya ditetapkan selepas keseluruhan init() selesai, jadi
      // token pertama TIDAK pernah didaftarkan — punca push tak sampai.
      initialized = true;

      unawaited(_reportDiagnostic('firebase_ok', extra: {
        'apps': Firebase.apps.map((a) => a.name).toList(),
        'device': _deviceName,
      }));

      if (!_listenersAttached) {
        _listenersAttached = true;

        // Pasang listener token SEBELUM meminta token. Di iOS token FCM boleh
        // dijana lewat (selepas APNs token tersedia) — listener awal memastikan
        // token yang tiba lewat tidak terlepas.
        _messaging!.onTokenRefresh.listen((newToken) => registerToken(newToken));

        FirebaseMessaging.onMessage.listen(
          (message) => _handleForeground(message),
        );
        FirebaseMessaging.onMessageOpenedApp.listen(
          (message) => _handleTap(message),
        );
      }

      await _messaging!.requestPermission();

      // Daftar token semasa (dengan tunggu + cuba semula untuk iOS).
      unawaited(_registerCurrentToken());

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
    var reportedApns = false;

    for (var attempt = 0; attempt < 8; attempt++) {
      try {
        if (_isIOS) {
          final apns = await messaging.getAPNSToken();
          apnsPrefix = _prefix(apns);
          if (!reportedApns) {
            reportedApns = true;
            unawaited(_reportDiagnostic('apns_status', extra: {
              'apns': apnsPrefix,
              'attempt': attempt + 1,
            }));
          }
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
        unawaited(_reportDiagnostic('token_error', extra: {
          'apns': apnsPrefix,
          'attempt': attempt + 1,
          'error': lastError,
        }));
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

      // Cipta saluran notifikasi Android (high importance) seawal mungkin.
      // FCM menggunakan saluran ini untuk notifikasi latar (lihat
      // `com.google.firebase.messaging.default_notification_channel_id` dalam
      // AndroidManifest.xml). Tanpa saluran berdaftar, sesetengah OEM
      // menyenyapkan notifikasi latar.
      final androidPlugin = _localNotifications!
          .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>();
      await androidPlugin?.createNotificationChannel(
        const AndroidNotificationChannel(
          _androidChannelId,
          'Notifikasi myWAP',
          description: 'Notifikasi push daripada myWAP',
          importance: Importance.high,
        ),
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
          _androidChannelId,
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

/// Handler mesej FCM ketika app di latar/terminated (data-only). Mesti
/// top-level + `vm:entry-point` supaya boleh dipanggil dalam background
/// isolate. Notifikasi bertajuk (payload `notification`) dipaparkan oleh
/// sistem secara automatik tanpa handler ini.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  if (Firebase.apps.isEmpty) {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
  }
}
