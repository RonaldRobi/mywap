import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/providers.dart';
import 'push_notification_service.dart';

/// Shared [PushNotificationService] untuk seluruh app.
final pushServiceProvider = Provider<PushNotificationService>(
  (ref) => PushNotificationService(ref.watch(apiClientProvider)),
);

/// [GlobalKey] untuk [ScaffoldMessenger] app (di-pass ke MaterialApp) supaya
/// notifikasi foreground (push masuk semasa app terbuka) dapat dipaparkan
/// sebagai SnackBar dari mana-mana konteks tanpa Navigator.
final appMessengerKeyProvider = Provider<GlobalKey<ScaffoldMessengerState>>(
  (ref) => GlobalKey<ScaffoldMessengerState>(),
);
