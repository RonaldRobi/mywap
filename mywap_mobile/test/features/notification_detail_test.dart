import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:mywap_mobile/features/notifications/application/notification_providers.dart';
import 'package:mywap_mobile/features/notifications/data/models/app_notification.dart';
import 'package:mywap_mobile/features/notifications/data/notification_repository.dart';
import 'package:mywap_mobile/features/notifications/presentation/notification_detail_screen.dart';
import 'package:mywap_mobile/features/notifications/presentation/notifications_screen.dart';
import 'package:mywap_mobile/shared/theme/app_theme.dart';

class _FakeNotificationRepository implements NotificationRepository {
  _FakeNotificationRepository(this._items);

  final List<AppNotification> _items;
  final List<String> readIds = [];

  @override
  Future<List<AppNotification>> list() async => List.of(_items);

  @override
  Future<void> read(String id) async {
    readIds.add(id);
  }

  @override
  Future<void> readAll() async {}
}

const _notification = AppNotification(
  id: 'n1',
  type: 'App\\Notifications\\AnnouncementPublishedNotification',
  data: {
    'title': 'Pengumuman Baru',
    'content': 'Kandungan penuh notifikasi yang boleh dibaca oleh ahli.',
    'type': 'announcement',
  },
);

Widget _buildApp(_FakeNotificationRepository repository) {
  final router = GoRouter(
    initialLocation: '/notifications',
    routes: [
      GoRoute(
        path: '/notifications',
        builder: (_, __) => const NotificationsScreen(),
      ),
      GoRoute(
        path: '/notifications/detail',
        builder:
            (_, state) => NotificationDetailScreen(
              notification: state.extra as AppNotification?,
            ),
      ),
    ],
  );

  return ProviderScope(
    overrides: [
      notificationRepositoryProvider.overrideWithValue(repository),
    ],
    child: MaterialApp.router(theme: AppTheme.light, routerConfig: router),
  );
}

void main() {
  testWidgets('tapping a notification opens its full detail and marks it read', (
    tester,
  ) async {
    final repository = _FakeNotificationRepository(const [_notification]);

    await tester.pumpWidget(_buildApp(repository));
    await tester.pumpAndSettle();

    expect(find.text('Pengumuman Baru'), findsOneWidget);

    await tester.tap(find.text('Pengumuman Baru'));
    await tester.pumpAndSettle();

    expect(find.text('PENGUMUMAN'), findsOneWidget);
    expect(
      find.text('Kandungan penuh notifikasi yang boleh dibaca oleh ahli.'),
      findsOneWidget,
    );
    expect(repository.readIds, ['n1']);
  });
}
