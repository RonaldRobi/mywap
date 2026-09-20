import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/features/loading_screen/application/loading_screen_providers.dart';
import 'package:mywap_mobile/features/public/application/public_home_providers.dart';
import 'package:mywap_mobile/features/public/data/public_home_repository.dart';
import 'package:mywap_mobile/features/public/presentation/public_home_screen.dart';
import 'package:mywap_mobile/shared/theme/app_theme.dart';

const _data = PublicHomeData(
  articles: [
    PublicArticle(id: 1, title: 'Artikel Khas', authorName: 'Admin'),
  ],
  news: [
    PublicNews(id: 1, title: 'Info Terkini Khas', categoryName: 'Umum'),
  ],
  videos: [
    PublicVideo(id: 1, title: 'Video Khas', youtubeId: 'abc123'),
  ],
  library: [
    PublicLibraryItem(id: 1, title: 'Buku Khas'),
  ],
  events: [
    PublicEvent(id: 1, title: 'Program Khas', startFormatted: '1 Ogos 2026'),
  ],
  infaqs: [
    PublicInfaq(
      id: 1,
      title: 'Infaq Khas',
      slug: 'infaq-khas',
      targetAmount: 10000,
      collectedAmount: 5000,
      progressPercent: 50,
    ),
  ],
);

Widget _wrap() {
  return ProviderScope(
    overrides: [
      publicHomeProvider.overrideWith((ref) async => _data),
      appLogoProvider.overrideWithValue(null),
    ],
    child: MaterialApp(theme: AppTheme.light, home: const PublicHomeScreen()),
  );
}

void main() {
  testWidgets('public home renders sections for guests', (tester) async {
    tester.view.physicalSize = const Size(900, 2400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(_wrap());
    await tester.pumpAndSettle();

    // Dialog log masuk dipaparkan pada pembukaan pertama.
    expect(find.text('Selamat Datang ke myWAP'), findsOneWidget);
    await tester.tap(find.text('Tutup'));
    await tester.pumpAndSettle();

    expect(find.text('Artikel'), findsWidgets);
    expect(find.text('Artikel Khas'), findsOneWidget);
    expect(find.text('Video'), findsWidgets);
    expect(find.text('Video Khas'), findsOneWidget);
    expect(find.text('Pustaka'), findsWidgets);
    expect(find.text('Buku Khas'), findsOneWidget);
    expect(find.text('Info Terkini'), findsWidgets);
    expect(find.text('Info Terkini Khas'), findsOneWidget);
    expect(find.text('Program Akan Datang'), findsWidgets);
    expect(find.text('Program Khas'), findsOneWidget);
    expect(find.text('Infaq'), findsWidgets);
    expect(find.text('Infaq Khas'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
