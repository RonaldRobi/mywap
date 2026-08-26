import 'dart:io' show Platform;

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

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
  String? _lastToken;

  /// Callback untuk notifikasi foreground (paparkan SnackBar di UI).
  void Function(String payload)? onMessage;

  /// Callback bila pengguna tekan notifikasi (navigate ke skrin).
  void Function(String payload)? onMessageTap;

  /// Initialize Firebase + daftar token. Tidak pernah throws — sebarang
  /// ralat hanya set [initialized] = false.
  Future<void> init() async {
    try {
      await Firebase.initializeApp(options: kFirebaseOptions);
      _messaging = FirebaseMessaging.instance;

      await _messaging!.requestPermission();
      final token = await _messaging!.getToken();
      await registerToken(token);

      _messaging!.onTokenRefresh.listen((newToken) => registerToken(newToken));
      FirebaseMessaging.onMessage.listen((message) => _handleForeground(message));
      FirebaseMessaging.onMessageOpenedApp.listen((message) => _handleTap(message));
      final initial = await _messaging!.getInitialMessage();
      if (initial != null) _handleTap(initial);

      initialized = true;
    } catch (_) {
      initialized = false;
    }
  }

  void _handleForeground(RemoteMessage message) {
    onMessage?.call(message.data.toString());
  }

  void _handleTap(RemoteMessage message) {
    onMessageTap?.call(message.data.toString());
  }

  /// Daftar token FCM ke backend. Tidak buat apa-apa jika tidak initialized
  /// atau token kosong.
  Future<void> registerToken(String? token) async {
    if (token == null || token.isEmpty || !initialized) return;

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
      await _api.delete(
        ApiPaths.deviceTokens,
        body: {'token': token},
      );
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
