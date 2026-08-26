import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/core/network/api_client.dart';
import 'package:mywap_mobile/core/push/push_notification_service.dart';
import 'package:mywap_mobile/core/storage/token_storage.dart';

void main() {
  late PushNotificationService service;

  setUp(() {
    PushNotificationService.initialized = false;
    // ApiClient hanya di-construct; tiada panggilan platform berlaku selagi
    // tiada request dihantar (dan registerToken di-guard oleh initialized flag).
    service = PushNotificationService(ApiClient(TokenStorage()));
  });

  test('initialized starts as false (no Firebase config needed)', () {
    expect(PushNotificationService.initialized, isFalse);
  });

  test('registerToken with initialized=false does not throw', () async {
    await service.registerToken('x');
    expect(PushNotificationService.initialized, isFalse);
  });

  test('registerToken with null token is a no-op even when enabled', () async {
    PushNotificationService.initialized = true;
    await service.registerToken(null);
    expect(PushNotificationService.initialized, isTrue);
  });

  test('unregisterToken without a registered token is a no-op', () async {
    PushNotificationService.initialized = true;
    await service.unregisterToken();
  });
}
