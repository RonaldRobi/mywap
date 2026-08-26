import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:qr_flutter/qr_flutter.dart';

import 'package:mywap_mobile/core/network/api_client.dart';
import 'package:mywap_mobile/core/storage/token_storage.dart';
import 'package:mywap_mobile/features/member/application/member_core_providers.dart';
import 'package:mywap_mobile/features/member/data/member_core_repository.dart';
import 'package:mywap_mobile/features/member/data/models/announcement.dart';
import 'package:mywap_mobile/features/member/data/models/fee_status.dart';
import 'package:mywap_mobile/features/member/data/models/library_item.dart';
import 'package:mywap_mobile/features/member/data/models/member_card_data.dart';
import 'package:mywap_mobile/features/member/presentation/announcements_screen.dart';
import 'package:mywap_mobile/features/member/presentation/fee_status_screen.dart';
import 'package:mywap_mobile/features/member/presentation/library_screen.dart';
import 'package:mywap_mobile/features/member/presentation/member_card_screen.dart';

class _FakeMemberCoreRepository extends MemberCoreRepository {
  _FakeMemberCoreRepository() : super(ApiClient(TokenStorage()));

  List<Announcement> items = [];
  final List<int> reactedIds = [];
  final List<int> readIds = [];

  @override
  Future<List<Announcement>> announcements() async => items;

  @override
  Future<String?> react(int id) async {
    reactedIds.add(id);
    final index = items.indexWhere((a) => a.id == id);
    if (index < 0) return null;
    final current = items[index];
    final hasLiked = current.user_reaction == 'like';
    final updated = Announcement(
      id: current.id,
      title: current.title,
      content: current.content,
      is_pinned: current.is_pinned,
      published_at: current.published_at,
      published_human: current.published_human,
      cover_image_url: current.cover_image_url,
      author_name: current.author_name,
      likes_count: (current.likes_count ?? 0) + (hasLiked ? -1 : 1),
      reads_count: current.reads_count,
      user_reaction: hasLiked ? null : 'like',
      is_read: current.is_read,
      images: current.images,
    );
    items = List.of(items)..[index] = updated;
    return hasLiked ? null : 'like';
  }

  @override
  Future<void> markRead(int id) async {
    readIds.add(id);
    final index = items.indexWhere((a) => a.id == id);
    if (index < 0) return;
    final current = items[index];
    items = List.of(items)
      ..[index] = current.copyWith(
        is_read: true,
        reads_count: (current.reads_count ?? 0) + 1,
      );
  }
}

void main() {
  testWidgets('MemberCardScreen renders card details and QR code',
      (tester) async {
    final data = MemberCardData.fromJson(const {
      'card': {
        'name': 'Ahmad bin Ali',
        'member_no': 'MY12345',
        'member_since': 'Jan 2024',
        'photo_url': '',
        'qr_value': 'https://example.com/card/MY12345',
        'organization': {'name': 'Pertubuhan Majlis', 'slug': 'pm', 'logo_path': ''},
      },
      'qrPrivate': '<svg/>',
      'qrPublic': null,
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [memberCardProvider.overrideWith((ref) async => data)],
        child: const MaterialApp(home: MemberCardScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Kad Ahli'), findsOneWidget);
    expect(find.text('Ahmad bin Ali'), findsOneWidget);
    expect(find.text('No. Ahli: MY12345'), findsOneWidget);
    expect(find.text('Ahli sejak Jan 2024'), findsOneWidget);
    expect(find.text('Pertubuhan Majlis'), findsOneWidget);
    expect(find.byType(QrImageView), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('AnnouncementsScreen lists announcements with pinned badge',
      (tester) async {
    final items = [
      Announcement(
        id: 1,
        title: 'Perjumpaan Bulanan',
        content: 'Butiran penuh perjumpaan akan dikongsikan.',
        is_pinned: true,
        published_human: '1 Jan 2024, 9:00 AM',
        author_name: 'Urusetia',
        likes_count: 2,
        reads_count: 5,
        user_reaction: null,
        is_read: false,
      ),
    ];

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          memberAnnouncementsProvider.overrideWith((ref) async => items),
        ],
        child: const MaterialApp(home: AnnouncementsScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Pengumuman'), findsWidgets);
    expect(find.text('Perjumpaan Bulanan'), findsOneWidget);
    expect(find.text('Disemat'), findsOneWidget);
    expect(find.text('Oleh Urusetia'), findsOneWidget);
    expect(find.text('2'), findsOneWidget);
    expect(find.text('5'), findsOneWidget);
  });

  testWidgets('Announcements react toggles like and refreshes the list',
      (tester) async {
    final repo = _FakeMemberCoreRepository();
    repo.items = [
      Announcement(
        id: 1,
        title: 'Perjumpaan Bulanan',
        content: 'Butiran',
        likes_count: 2,
        reads_count: 0,
        user_reaction: null,
        is_read: false,
      ),
    ];

    await tester.pumpWidget(
      ProviderScope(
        overrides: [memberCoreRepositoryProvider.overrideWithValue(repo)],
        child: const MaterialApp(home: AnnouncementsScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byIcon(Icons.favorite_border), findsOneWidget);

    await tester.tap(find.byIcon(Icons.favorite_border));
    await tester.pumpAndSettle();

    expect(repo.reactedIds, [1]);
    expect(find.byIcon(Icons.favorite), findsOneWidget);
    expect(find.text('3'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.favorite));
    await tester.pumpAndSettle();

    expect(repo.reactedIds, [1, 1]);
    expect(find.byIcon(Icons.favorite_border), findsOneWidget);
    expect(find.text('2'), findsOneWidget);
  });

  testWidgets('Announcements mark read on expand', (tester) async {
    final repo = _FakeMemberCoreRepository();
    repo.items = [
      Announcement(
        id: 7,
        title: 'Pekeliling Baru',
        content: 'Butiran pekeliling penuh.',
        likes_count: 0,
        reads_count: 0,
        user_reaction: null,
        is_read: false,
      ),
    ];

    await tester.pumpWidget(
      ProviderScope(
        overrides: [memberCoreRepositoryProvider.overrideWithValue(repo)],
        child: const MaterialApp(home: AnnouncementsScreen()),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Pekeliling Baru'));
    await tester.pumpAndSettle();

    expect(repo.readIds, [7]);
    expect(repo.items.first.is_read, isTrue);
    expect(find.text('Butiran pekeliling penuh.'), findsOneWidget);
  });

  testWidgets('LibraryScreen renders library items', (tester) async {
    final items = [
      const LibraryItem(
        id: 1,
        title: 'Buku Panduan Keahlian',
        description: 'Panduan lengkap untuk ahli.',
        category: 'Dokumen',
        cover_image_path: '',
        file_path: '/storage/files/guide.pdf',
      ),
    ];

    await tester.pumpWidget(
      ProviderScope(
        overrides: [memberLibraryProvider.overrideWith((ref) async => items)],
        child: const MaterialApp(home: LibraryScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Pustaka'), findsOneWidget);
    expect(find.text('Buku Panduan Keahlian'), findsOneWidget);
    expect(find.text('Dokumen'), findsOneWidget);
  });

  testWidgets('FeeStatusScreen shows due state with amount', (tester) async {
    final fee = FeeStatus.fromJson(const {
      'status': {
        'status': 'due',
        'amount_due': 50.0,
        'last_paid_at': null,
        'last_reference': null,
      },
      'fee_amount': 50.0,
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [memberFeeStatusProvider.overrideWith((ref) async => fee)],
        child: const MaterialApp(home: FeeStatusScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Status Yuran'), findsNWidgets(2));
    expect(find.text('BELUM BAYAR'), findsOneWidget);
    expect(find.text('Amaun Yuran Tahunan'), findsOneWidget);
    expect(find.text('Amaun Belum Dibayar'), findsOneWidget);
    expect(find.text('RM 50.00'), findsNWidgets(2));
  });

  testWidgets('FeeStatusScreen shows active state when paid', (tester) async {
    final fee = FeeStatus.fromJson(const {
      'status': {
        'status': 'active',
        'amount_due': 0.0,
        'last_paid_at': '2024-01-15T00:00:00+08:00',
        'last_reference': 'REF123',
      },
      'fee_amount': 50.0,
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [memberFeeStatusProvider.overrideWith((ref) async => fee)],
        child: const MaterialApp(home: FeeStatusScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('AKTIF'), findsOneWidget);
    expect(find.text('Tarikh Dibayar'), findsOneWidget);
    expect(find.text('15/1/2024'), findsOneWidget);
    expect(find.text('Amaun Belum Dibayar'), findsNothing);
  });
}
