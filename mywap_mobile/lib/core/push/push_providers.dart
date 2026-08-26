import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/providers.dart';
import 'push_notification_service.dart';

/// Shared [PushNotificationService] untuk seluruh app.
final pushServiceProvider = Provider<PushNotificationService>(
  (ref) => PushNotificationService(ref.watch(apiClientProvider)),
);
