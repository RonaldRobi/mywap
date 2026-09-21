import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/core/network/api_exception.dart';
import 'package:mywap_mobile/core/utils/formatters.dart';
import 'package:mywap_mobile/features/auth/application/auth_controller.dart';
import 'package:mywap_mobile/features/auth/data/models/user.dart';
import 'package:mywap_mobile/features/infaq/application/infaq_providers.dart';
import 'package:mywap_mobile/features/infaq/data/infaq_repository.dart';
import 'package:mywap_mobile/features/infaq/data/models/infaq.dart';
import 'package:mywap_mobile/features/infaq/presentation/infaq_detail_screen.dart';
import 'package:mywap_mobile/features/infaq/presentation/infaq_landing_screen.dart';

class _FakeAuthController extends AuthController {
  _FakeAuthController(this._initial);

  final AuthState _initial;

  @override
  AuthState build() => _initial;
}

class _FakeInfaqRepository implements InfaqRepository {
  _FakeInfaqRepository({
    this.listData,
    this.detailData,
    this.error,
  });

  final InfaqListData? listData;
  final InfaqDetail? detailData;
  final ApiException? error;

  @override
  Future<InfaqListData> list() async {
    final error = this.error;
    if (error != null) throw error;
    return listData ?? const InfaqListData();
  }

  @override
  Future<InfaqDetail> detail(String slug) async {
    final error = this.error;
    if (error != null) throw error;
    return detailData ?? InfaqDetail(infaq: const InfaqInfo(slug: 'test'));
  }

  @override
  Future<InfaqDonateResult> donate(
    String slug, {
    required double amount,
    required String donorName,
    required String donorPhone,
    required String donorEmail,
    String? prayerMessage,
    bool isAnonymous = false,
    bool wantsUpdates = false,
    bool isRecurring = false,
    String? frequency,
  }) async {
    return const InfaqDonateSuccess(donation: InfaqDonation());
  }
}

const _user = User(name: 'Ali', email: 'ali@test.com', phone: '0123456789');

const _listData = InfaqListData(
  infaqs: [
    Infaq(
      id: 1,
      title: 'Bina Surau Baru',
      slug: 'bina-surau',
      organizationName: 'Masjid Al-Hidayah',
      targetAmount: 10000,
      collectedAmount: 6000,
      progressPercent: 60,
      daysRunning: 12,
      imagePath: '',
    ),
    Infaq(
      id: 2,
      title: 'Kempen Buku Raya',
      slug: 'kempen-buku-raya',
      organizationName: 'Yayasan Amal',
      targetAmount: 5000,
      collectedAmount: 2500,
      progressPercent: 50,
      daysRunning: 5,
      imagePath: '',
    ),
  ],
  organizations: [
    InfaqOrganization(id: 1, name: 'Masjid Al-Hidayah'),
    InfaqOrganization(id: 2, name: 'Yayasan Amal'),
  ],
  hasGlobal: true,
);

const _detailData = InfaqDetail(
  infaq: InfaqInfo(
    slug: 'bina-surau',
    title: 'Bina Surau Baru',
    description: 'Kempen pembinaan surau komuniti.',
    imagePath: '',
    targetAmount: 10000,
    collectedAmount: 6000,
    progressPercent: 60,
    organizationName: 'Masjid Al-Hidayah',
    allowRecurring: true,
    totalDonors: 25,
    daysRunning: 12,
    publicUrl: 'https://mywap.my/sumbangan/2026/07/07/bina-surau',
  ),
  recentDonations: [
    RecentDonation(
      id: 1,
      amount: 100,
      createdAt: '2 jam lalu',
      donorName: 'Ali',
      prayerMessage: 'Semoga dipermudahkan',
    ),
  ],
  relatedInfaqs: [
    Infaq(
      id: 2,
      title: 'Kempen Buku Raya',
      imagePath: '',
      targetAmount: 5000,
      collectedAmount: 1000,
      progressPercent: 20,
    ),
  ],
);

Widget _wrap(Widget child, {required InfaqRepository repo}) {
  return ProviderScope(
    overrides: [
      authControllerProvider.overrideWith(
        () => _FakeAuthController(const AuthAuthenticated(_user)),
      ),
      infaqRepositoryProvider.overrideWithValue(repo),
    ],
    child: MaterialApp(home: child),
  );
}

void main() {
  Future<void> setViewSize(WidgetTester tester) async {
    tester.view.physicalSize = const Size(800, 1400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
  }

  testWidgets('InfaqScreen renders list with progress and org', (tester) async {
    await setViewSize(tester);
    await tester.pumpWidget(
      _wrap(
        const InfaqScreen(),
        repo: _FakeInfaqRepository(listData: _listData),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Bina Surau Baru'), findsOneWidget);
    expect(find.text('Kempen Buku Raya'), findsOneWidget);
    expect(find.text('Masjid Al-Hidayah'), findsNWidgets(2));
    expect(find.text('Yayasan Amal'), findsNWidgets(2));
    expect(find.textContaining(Formatters.currency(6000)), findsOneWidget);
    expect(find.textContaining(Formatters.currency(10000)), findsOneWidget);
    expect(find.textContaining('12 hari berjalan'), findsOneWidget);
    expect(find.byType(LinearProgressIndicator), findsNWidgets(2));
    expect(tester.takeException(), isNull);
  });

  testWidgets('InfaqScreen empty state', (tester) async {
    await setViewSize(tester);
    await tester.pumpWidget(
      _wrap(
        const InfaqScreen(),
        repo: _FakeInfaqRepository(listData: const InfaqListData()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Tiada kempen infaq buat masa ini.'), findsOneWidget);
  });

  testWidgets('InfaqScreen error state shows retry', (tester) async {
    await setViewSize(tester);
    await tester.pumpWidget(
      _wrap(
        const InfaqScreen(),
        repo: _FakeInfaqRepository(
          error: const ApiException('Ralat memuatkan infaq.'),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Ralat memuatkan infaq.'), findsOneWidget);
    expect(find.text('Cuba Semula'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('InfaqDetailScreen renders description, donations, related', (
    tester,
  ) async {
    await setViewSize(tester);
    await tester.pumpWidget(
      _wrap(
        const InfaqDetailScreen(slug: 'bina-surau'),
        repo: _FakeInfaqRepository(detailData: _detailData),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Bina Surau Baru'), findsOneWidget);
    expect(find.text('Kempen pembinaan surau komuniti.'), findsOneWidget);
    expect(find.text('Penerangan'), findsOneWidget);
    expect(find.text('Sumbang Sekarang'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.text('Sumbangan Terkini'),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    expect(find.text('Sumbangan Terkini'), findsOneWidget);
    expect(find.text('Ali'), findsOneWidget);
    expect(find.textContaining('Semoga dipermudahkan'), findsOneWidget);
    expect(find.textContaining(Formatters.currency(100)), findsOneWidget);

    await tester.scrollUntilVisible(
      find.text('Infaq Lain'),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    expect(find.text('Infaq Lain'), findsOneWidget);
    expect(find.text('Kempen Buku Raya'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('donate button opens the campaign page outside the app', (
    tester,
  ) async {
    await setViewSize(tester);
    final repo = _FakeInfaqRepository(detailData: _detailData);

    await tester.pumpWidget(
      _wrap(const InfaqDetailScreen(slug: 'bina-surau'), repo: repo),
    );
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('Sumbang Sekarang'),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Sumbang Sekarang'));
    await tester.pumpAndSettle();

    // Donations are completed outside the app (on the website). In tests the
    // url_launcher plugin is absent, so the app must fail gracefully without
    // crashing and without collecting any donation in-app.
    expect(tester.takeException(), isNull);
  });
}
