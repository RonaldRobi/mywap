import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:mywap_mobile/app.dart';
import 'package:mywap_mobile/core/constants/prefs_keys.dart';
import 'package:mywap_mobile/core/network/providers.dart';
import 'package:mywap_mobile/core/storage/token_storage.dart';
import 'package:mywap_mobile/features/auth/application/auth_controller.dart';
import 'package:mywap_mobile/features/auth/data/models/user.dart';
import 'package:mywap_mobile/features/member/application/member_providers.dart';
import 'package:mywap_mobile/features/member/data/models/dashboard_data.dart';
import 'package:mywap_mobile/features/member/presentation/main_shell.dart';
import 'package:mywap_mobile/features/menu/presentation/menu_screen.dart';
import 'package:mywap_mobile/features/profile/application/profile_providers.dart';
import 'package:mywap_mobile/features/profile/data/models/profile_data.dart';

class _FakeTokenStorage extends TokenStorage {
  _FakeTokenStorage() : super();
  String? _value;

  @override
  Future<String?> read() async => _value;

  @override
  Future<void> write(String token) async => _value = token;

  @override
  Future<void> delete() async => _value = null;
}

class _FakeAuthController extends AuthController {
  _FakeAuthController(this._initial);

  final AuthState _initial;

  @override
  AuthState build() => _initial;
}

/// Membina [MenuScreen] di dalam skop yang diautentikasi, dengan viewport
/// yang cukup tinggi supaya setiap seksyen dirender tanpa perlu menatal.
Future<void> _pumpMenu(WidgetTester tester, {required User user}) async {
  tester.view.physicalSize = const Size(800, 4000);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        authControllerProvider.overrideWith(
          () => _FakeAuthController(AuthAuthenticated(user)),
        ),
        profileProvider.overrideWith((ref) async => const ProfileData()),
      ],
      child: const MaterialApp(home: MenuScreen()),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('MenuScreen renders every member destination', (tester) async {
    await _pumpMenu(tester, user: User(roles: ['Ahli']));

    // Setiap pautan yang dahulunya dalam sidebar mesti kekal boleh dicapai.
    const expected = [
      'Kad Ahli',
      'Profil Saya',
      'Jemput Ahli',
      'Yuran & Bayaran',
      'Infaq',
      'Program',
      'Pendaftaran Saya',
      'Usrah',
      'Undian',
      'Tempah Fasiliti',
      'Info Terkini',
      'Artikel',
      'Video',
      'Pustaka',
      'Produk',
      'Pesanan Saya',
      'Info Organisasi',
      'Pengumuman',
      'Notifikasi',
      'Manual Pengguna',
    ];
    for (final label in expected) {
      expect(
        find.text(label),
        findsOneWidget,
        reason: 'missing menu item: $label',
      );
    }
  });

  testWidgets('MenuScreen hides Panel Admin from ordinary members', (
    tester,
  ) async {
    await _pumpMenu(tester, user: User(roles: ['Ahli']));
    expect(find.text('Panel Admin'), findsNothing);
  });

  testWidgets('MenuScreen surfaces Panel Admin for admin roles', (
    tester,
  ) async {
    await _pumpMenu(tester, user: User(roles: ['Admin']));
    expect(find.text('Panel Admin'), findsOneWidget);
  });

  /// Bar tab mesti sama untuk setiap peranan — admin tidak lagi mendapat tab
  /// keenam yang sempit; Panel Admin berada dalam hab Menu.
  for (final role in ['Admin', 'Ahli']) {
    testWidgets('MainShell renders the same five tabs for $role', (
      tester,
    ) async {
      final router = GoRouter(
        initialLocation: '/dashboard',
        routes: [
          ShellRoute(
            builder: (_, __, child) => MainShell(child: child),
            routes: [
              GoRoute(
                path: '/dashboard',
                builder: (_, __) => const Scaffold(body: Text('Dashboard')),
              ),
              GoRoute(
                path: '/infaq',
                builder: (_, __) => const Scaffold(body: Text('Infaq')),
              ),
            ],
          ),
        ],
      );

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            authControllerProvider.overrideWith(
              () => _FakeAuthController(AuthAuthenticated(User(roles: [role]))),
            ),
          ],
          child: MaterialApp.router(routerConfig: router),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Utama'), findsOneWidget);
      expect(find.text('Program'), findsOneWidget);
      expect(find.text('Infaq'), findsOneWidget);
      expect(find.text('Menu'), findsOneWidget);
      // Profil dan Admin kini hidup di dalam hab Menu, bukan bar tab.
      expect(find.text('Profil'), findsNothing);
      expect(find.text('Admin'), findsNothing);

      await tester.tap(find.text('Infaq'));
      await tester.pumpAndSettle();
      expect(find.text('Infaq'), findsWidgets);
    });
  }

  testWidgets('MainShell mounts no drawer so back-swipe stays with the OS', (
    tester,
  ) async {
    final router = GoRouter(
      initialLocation: '/dashboard',
      routes: [
        ShellRoute(
          builder: (_, __, child) => MainShell(child: child),
          routes: [
            GoRoute(
              path: '/dashboard',
              builder: (_, __) => const Scaffold(body: Text('Dashboard')),
            ),
          ],
        ),
      ],
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authControllerProvider.overrideWith(
            () => _FakeAuthController(AuthAuthenticated(User(roles: ['Ahli']))),
          ),
        ],
        child: MaterialApp.router(routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    // Seretan tepi drawer berebut zon yang sama dengan gesture kembali iOS
    // dan predictive-back Android, jadi tiada Scaffold shell boleh memasang
    // drawer.
    final shellScaffolds = tester.widgetList<Scaffold>(find.byType(Scaffold));
    expect(shellScaffolds, isNotEmpty);
    for (final scaffold in shellScaffolds) {
      expect(scaffold.drawer, isNull);
      expect(scaffold.endDrawer, isNull);
    }
  });

  testWidgets('app router: static event route beats /events/:id', (
    tester,
  ) async {
    final adminUser = User(roles: ['Admin']);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          tokenStorageProvider.overrideWithValue(_FakeTokenStorage()),
          authControllerProvider.overrideWith(
            () => _FakeAuthController(AuthAuthenticated(adminUser)),
          ),
          memberDashboardProvider.overrideWith(
            (ref) async => const DashboardData(),
          ),
        ],
        child: const MyWapApp(),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Utama'), findsWidgets);

    final router = GoRouter.of(tester.element(find.text('Utama').first));

    router.go('/events/my-registrations');
    await tester.pumpAndSettle();
    expect(find.text('Pendaftaran Saya'), findsWidgets);
    expect(tester.takeException(), isNull);

    router.go('/events/999');
    await tester.pumpAndSettle();
    expect(find.text('Butiran Program'), findsOneWidget);
    expect(tester.takeException(), isNull);

    router.go('/infaq');
    await tester.pumpAndSettle();
    expect(find.text('Infaq'), findsWidgets);

    router.go('/menu');
    await tester.pumpAndSettle();
    expect(find.text('Kad Ahli'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('app router: /menu stays public for logged-out visitors', (
    tester,
  ) async {
    // Tanpa tanda ini, tetamu baru mendarat di onboarding dan bukan shell.
    SharedPreferences.setMockInitialValues({onboardingCompletedKey: true});
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          tokenStorageProvider.overrideWithValue(_FakeTokenStorage()),
          authControllerProvider.overrideWith(
            () => _FakeAuthController(const AuthUnauthenticated()),
          ),
        ],
        child: const MyWapApp(),
      ),
    );
    // Beranda awam menjalankan karusel auto-main, jadi `pumpAndSettle` tidak
    // akan pernah reda di sini — pam bingkai berjangka masa sebaliknya
    // sehingga splash selesai dan shell tetamu dipasang.
    for (var i = 0; i < 12; i++) {
      await tester.pump(const Duration(milliseconds: 250));
      if (find.text('Menu').evaluate().isNotEmpty) break;
    }

    expect(find.text('Menu'), findsWidgets);
    final router = GoRouter.of(tester.element(find.text('Menu').first));

    // `/menu` ialah tab kelima pada shell tetamu. Jika pengubah hala tidak
    // menganggapnya awam, menekan tab itu melemparkan tetamu kembali ke
    // /home dan tab tersebut nampak rosak.
    router.go('/menu');
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 400));

    expect(router.state.matchedLocation, '/menu');
    expect(tester.takeException(), isNull);
  });

  testWidgets('MenuScreen shows guest links instead of member destinations', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(800, 2400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authControllerProvider.overrideWith(
            () => _FakeAuthController(const AuthUnauthenticated()),
          ),
        ],
        child: const MaterialApp(home: MenuScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Log Masuk'), findsOneWidget);
    expect(find.text('Daftar Ahli'), findsOneWidget);
    expect(find.text('Info Organisasi'), findsOneWidget);
    expect(find.text('Artikel'), findsOneWidget);
    // Destinasi khusus ahli tidak boleh bocor kepada tetamu.
    expect(find.text('Kad Ahli'), findsNothing);
    expect(find.text('Yuran & Bayaran'), findsNothing);
    expect(find.text('Log Keluar'), findsNothing);
  });
}
