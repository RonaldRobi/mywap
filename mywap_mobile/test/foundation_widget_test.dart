import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:mywap_mobile/app.dart';
import 'package:mywap_mobile/core/network/providers.dart';
import 'package:mywap_mobile/core/storage/token_storage.dart';
import 'package:mywap_mobile/features/auth/application/auth_controller.dart';
import 'package:mywap_mobile/features/auth/data/models/user.dart';
import 'package:mywap_mobile/features/member/application/member_providers.dart';
import 'package:mywap_mobile/features/member/data/models/dashboard_data.dart';
import 'package:mywap_mobile/features/member/presentation/main_shell.dart';
import 'package:mywap_mobile/features/menu/presentation/menu_screen.dart';

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

void main() {
  testWidgets('MenuScreen renders all menu items', (tester) async {
    tester.view.physicalSize = const Size(800, 1500);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      const ProviderScope(
        child: MaterialApp(home: MenuScreen()),
      ),
    );
    await tester.pumpAndSettle();

    const expected = [
      'Profil',
      'Kad Ahli',
      'Pengumuman',
      'Pustaka',
      'Berita',
      'Artikel',
      'Video',
      'Infaq',
      'Pasar',
      'Pesanan',
      'Kemudahan',
      'Usrah',
      'Undian',
      'Direktori',
      'Chat',
      'Notifikasi',
    ];
    for (final label in expected) {
      expect(find.text(label), findsOneWidget, reason: 'missing menu item: $label');
    }
  });

  testWidgets('MainShell shows Admin tab only for admin role', (tester) async {
    final adminUser = User(roles: ['Admin']);
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
            () => _FakeAuthController(AuthAuthenticated(adminUser)),
          ),
        ],
        child: MaterialApp.router(routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Utama'), findsOneWidget);
    expect(find.text('Acara'), findsOneWidget);
    expect(find.text('Infaq'), findsOneWidget);
    expect(find.text('Menu'), findsOneWidget);
    expect(find.text('Admin'), findsOneWidget);

    await tester.tap(find.text('Infaq'));
    await tester.pumpAndSettle();
    expect(find.text('Infaq'), findsWidgets);
  });

  testWidgets('MainShell hides Admin tab for non-admin role', (tester) async {
    final regularUser = User(roles: ['Ahli']);
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
            () => _FakeAuthController(AuthAuthenticated(regularUser)),
          ),
        ],
        child: MaterialApp.router(routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Admin'), findsNothing);
    expect(find.text('Menu'), findsOneWidget);
  });

  testWidgets('app router: static event route beats /events/:id', (tester) async {
    final adminUser = User(roles: ['Admin']);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          tokenStorageProvider.overrideWithValue(_FakeTokenStorage()),
          authControllerProvider.overrideWith(
            () => _FakeAuthController(AuthAuthenticated(adminUser)),
          ),
          memberDashboardProvider.overrideWith((ref) async => const DashboardData()),
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
    expect(find.text('Butiran Acara'), findsOneWidget);
    expect(tester.takeException(), isNull);

    router.go('/infaq');
    await tester.pumpAndSettle();
    expect(find.text('Infaq'), findsWidgets);

    router.go('/menu');
    await tester.pumpAndSettle();
    expect(find.text('Profil'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
