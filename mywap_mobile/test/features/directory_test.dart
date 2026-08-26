import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/features/chat/application/chat_providers.dart';
import 'package:mywap_mobile/features/chat/data/chat_repository.dart';
import 'package:mywap_mobile/features/chat/data/models/chat_reply.dart';
import 'package:mywap_mobile/features/chat/presentation/chat_screen.dart';
import 'package:mywap_mobile/features/directory/application/directory_providers.dart';
import 'package:mywap_mobile/features/directory/data/directory_repository.dart';
import 'package:mywap_mobile/features/directory/data/models/directory_user.dart';
import 'package:mywap_mobile/features/directory/presentation/directory_screen.dart';
import 'package:mywap_mobile/features/notifications/application/notification_providers.dart';
import 'package:mywap_mobile/features/notifications/data/models/app_notification.dart';
import 'package:mywap_mobile/features/notifications/data/notification_repository.dart';
import 'package:mywap_mobile/features/notifications/presentation/notifications_screen.dart';
import 'package:mywap_mobile/shared/theme/app_theme.dart';

class _FakeDirectoryRepository implements DirectoryRepository {
  _FakeDirectoryRepository(this._users);

  final List<DirectoryUser> _users;

  @override
  Future<DirectoryData> directory({
    int page = 1,
    String search = '',
    String industry = '',
  }) async {
    final query = search.toLowerCase();
    final filtered = _users.where((user) {
      final name = (user.name ?? '').toLowerCase();
      final ind = (user.industry ?? '').toLowerCase();
      final exp = (user.expertise ?? '').toLowerCase();
      final matchesSearch =
          query.isEmpty || name.contains(query) || ind.contains(query) || exp.contains(query);
      final matchesIndustry =
          industry.isEmpty || (user.industry ?? '') == industry;
      return matchesSearch && matchesIndustry;
    }).toList();
    return DirectoryData(
      users: filtered,
      industries: const ['Teknologi', 'Pendidikan'],
      hasMore: false,
    );
  }
}

class _FakeChatRepository implements ChatRepository {
  _FakeChatRepository(this._replies);

  final Map<String, String> _replies;

  @override
  Future<ChatReply> send(String message) async {
    return ChatReply(reply: _replies[message.trim()] ?? 'Balasan AI.');
  }
}

class _FakeNotificationRepository implements NotificationRepository {
  _FakeNotificationRepository(List<AppNotification> initial)
      : _items = List.of(initial);

  List<AppNotification> _items;
  int readAllCalls = 0;

  @override
  Future<List<AppNotification>> list() async => List.of(_items);

  @override
  Future<void> readAll() async {
    readAllCalls++;
    _items = _items
        .map((n) => AppNotification(
              id: n.id,
              type: n.type,
              data: n.data,
              createdAt: n.createdAt,
              readAt: DateTime.now(),
            ))
        .toList();
  }
}

Widget _wrap(Widget child, List<Override> overrides) {
  return ProviderScope(
    overrides: overrides,
    child: MaterialApp(theme: AppTheme.light, home: child),
  );
}

const _users = [
  DirectoryUser(
    id: 1,
    name: 'Ahmad Faiz',
    industry: 'Teknologi',
    expertise: 'Aplikasi Mudah Alih',
    organizationName: 'Syarikat A',
  ),
  DirectoryUser(
    id: 2,
    name: 'Siti Aminah',
    industry: 'Pendidikan',
    expertise: 'Pengurusan',
    organizationName: 'Syarikat B',
  ),
];

void main() {
  group('DirectoryScreen', () {
    testWidgets('renders member list and industry chips', (tester) async {
      await tester.pumpWidget(
        _wrap(
          const DirectoryScreen(),
          [
            directoryRepositoryProvider.overrideWithValue(
              _FakeDirectoryRepository(_users),
            ),
          ],
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Ahmad Faiz'), findsOneWidget);
      expect(find.text('Siti Aminah'), findsOneWidget);
      expect(find.text('Semua'), findsOneWidget);
      expect(find.text('Teknologi'), findsWidgets);
      expect(find.text('Pendidikan'), findsWidgets);
    });

    testWidgets('search filters the member list (debounced)', (tester) async {
      await tester.pumpWidget(
        _wrap(
          const DirectoryScreen(),
          [
            directoryRepositoryProvider.overrideWithValue(
              _FakeDirectoryRepository(_users),
            ),
          ],
        ),
      );
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextField), 'Ahmad');
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();

      expect(find.text('Ahmad Faiz'), findsOneWidget);
      expect(find.text('Siti Aminah'), findsNothing);
    });
  });

  group('ChatScreen', () {
    testWidgets('sends a message and appends the bot reply', (tester) async {
      final fake = _FakeChatRepository({
        'Apa itu myWAP?': 'myWAP adalah platform untuk pertubuhan Islam.',
      });
      await tester.pumpWidget(
        _wrap(
          const ChatScreen(),
          [chatRepositoryProvider.overrideWithValue(fake)],
        ),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('Tiada mesej lagi'), findsOneWidget);

      await tester.enterText(find.byType(TextField), 'Apa itu myWAP?');
      await tester.tap(find.byIcon(Icons.send));
      await tester.pumpAndSettle();

      expect(find.text('Apa itu myWAP?'), findsOneWidget);
      expect(find.text('myWAP adalah platform untuk pertubuhan Islam.'),
          findsOneWidget);
    });
  });

  group('NotificationsScreen', () {
    testWidgets('lists notifications and marks all read', (tester) async {
      final now = DateTime.now();
      final fake = _FakeNotificationRepository([
        AppNotification(
          id: '1',
          type: 'App\\Notifications\\AnnouncementPublishedNotification',
          data: {'title': 'Pengumuman Baru', 'content': 'Kandungan pengumuman.'},
          createdAt: now.subtract(const Duration(hours: 2)),
        ),
        AppNotification(
          id: '2',
          type: 'App\\Notifications\\FeeReminderNotification',
          data: {'title': 'Yuran 2026', 'content': 'Sila lengkapkan pembayaran.'},
          createdAt: now.subtract(const Duration(days: 1)),
          readAt: now.subtract(const Duration(days: 1)),
        ),
      ]);
      await tester.pumpWidget(
        _wrap(
          const NotificationsScreen(),
          [notificationRepositoryProvider.overrideWithValue(fake)],
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Pengumuman Baru'), findsOneWidget);
      expect(find.text('Yuran 2026'), findsOneWidget);
      expect(find.text('Tandakan Semua Dibaca'), findsOneWidget);

      await tester.tap(find.text('Tandakan Semua Dibaca'));
      await tester.pumpAndSettle();

      expect(fake.readAllCalls, 1);
      expect(find.text('Pengumuman Baru'), findsOneWidget);
      expect(find.text('Yuran 2026'), findsOneWidget);
    });

    testWidgets('shows empty state when there are no notifications', (tester) async {
      await tester.pumpWidget(
        _wrap(
          const NotificationsScreen(),
          [
            notificationRepositoryProvider.overrideWithValue(
              _FakeNotificationRepository(const []),
            ),
          ],
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Tiada notifikasi buat masa ini.'), findsOneWidget);
      expect(find.text('Tandakan Semua Dibaca'), findsNothing);
    });
  });
}
