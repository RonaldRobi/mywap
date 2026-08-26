import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:mywap_mobile/app.dart';
import 'package:mywap_mobile/core/network/providers.dart';
import 'package:mywap_mobile/core/storage/token_storage.dart';
import 'package:mywap_mobile/features/admin/application/admin_providers.dart';
import 'package:mywap_mobile/features/admin/data/admin_repository.dart';
import 'package:mywap_mobile/features/admin/data/models/admin_models.dart';
import 'package:mywap_mobile/features/admin/presentation/admin_attendance_detail_screen.dart';
import 'package:mywap_mobile/features/admin/presentation/admin_broadcast_screen.dart';
import 'package:mywap_mobile/features/admin/presentation/admin_dashboard_screen.dart';
import 'package:mywap_mobile/features/admin/presentation/admin_fees_screen.dart';
import 'package:mywap_mobile/features/admin/presentation/admin_members_screen.dart';
import 'package:mywap_mobile/features/auth/application/auth_controller.dart';
import 'package:mywap_mobile/features/auth/data/models/user.dart';
import 'package:mywap_mobile/features/events/data/models/event.dart';
import 'package:mywap_mobile/features/member/application/member_providers.dart';
import 'package:mywap_mobile/features/member/data/models/dashboard_data.dart';

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

/// In-memory admin repository: returns canned data and records mutation calls.
class FakeAdminRepository implements AdminRepository {
  AdminDashboard dashboardResult = const AdminDashboard(stats: AdminStats());
  FeesData feesResult = const FeesData(summary: FeesSummary());
  AttendanceData attendanceResult = const AttendanceData();
  BroadcastResult broadcastResult = const BroadcastResult();
  List<AdminMember> membersSeed = const [];
  List<Event> upcomingEventsResult = const [];

  final List<({int eventId, String identifier})> scanCalls = [];
  final List<Map<String, dynamic>> broadcastCalls = [];
  final List<String> feesStatusCalls = [];

  @override
  Future<AdminDashboard> dashboard() async => dashboardResult;

  @override
  Future<PaginatedMembers> members({
    String search = '',
    String status = '',
    int page = 1,
  }) async {
    var items = membersSeed;
    if (search.isNotEmpty) {
      items = items.where((m) => m.name.toLowerCase().contains(search.toLowerCase())).toList();
    }
    if (status.isNotEmpty) {
      items = items.where((m) => m.status == status).toList();
    }
    return PaginatedMembers(
      items: items,
      currentPage: page,
      lastPage: 1,
      perPage: 25,
      total: items.length,
    );
  }

  @override
  Future<FeesData> fees({String status = '', String search = ''}) async {
    feesStatusCalls.add(status);
    return feesResult;
  }

  @override
  Future<AttendanceData> attendanceRegistrations(int eventId) async => attendanceResult;

  @override
  Future<ScanResult> scan({required int eventId, required String identifier}) async {
    scanCalls.add((eventId: eventId, identifier: identifier));
    return ScanResult(
      status: 'ok',
      message: 'Kehadiran direkodkan.',
      registration: Registration(id: 1, name: 'Ahmad Ali', memberNo: 'M001'),
    );
  }

  @override
  Future<BroadcastResult> broadcast({
    required String title,
    required String message,
    required String audience,
    int? organizationId,
  }) async {
    broadcastCalls.add({
      'title': title,
      'message': message,
      'audience': audience,
      'organization_id': organizationId,
    });
    return broadcastResult;
  }

  @override
  Future<List<Event>> upcomingEvents() async => upcomingEventsResult;
}

void main() {
  final fakeRepo = FakeAdminRepository();

  Widget wrap({required Widget home, GoRouter? router}) {
    return ProviderScope(
      overrides: [adminRepositoryProvider.overrideWithValue(fakeRepo)],
      child: MaterialApp(theme: ThemeData(useMaterial3: true), home: home),
    );
  }

  setUp(() {
    fakeRepo
      ..scanCalls.clear()
      ..broadcastCalls.clear()
      ..feesStatusCalls.clear();
  });

  group('AdminDashboardScreen', () {
    testWidgets('renders stats, revenue chart and recent activities', (tester) async {
      tester.view.physicalSize = const Size(800, 1400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.reset);

      fakeRepo.dashboardResult = const AdminDashboard(
        stats: AdminStats(
          totalMembers: 150,
          activeMembers: 120,
          pendingMembers: 30,
          totalEvents: 40,
          upcomingEvents: 3,
          totalRevenue: 5000,
          pendingPayments: 4,
        ),
        revenueLabels: ['Jan', 'Feb', 'Mac'],
        revenueValues: [1000, 2500, 3000],
        activities: [
          AdminActivity(
            id: 1,
            type: 'member',
            title: 'Ahli baru',
            description: 'Ahmad mendaftar sebagai ahli.',
            createdAt: null,
          ),
        ],
      );

      await tester.pumpWidget(wrap(home: const AdminDashboardScreen()));
      await tester.pumpAndSettle();

      expect(find.text('Panel Admin'), findsOneWidget);
      expect(find.text('Jumlah Ahli'), findsOneWidget);
      expect(find.text('150'), findsOneWidget);
      expect(find.text('120'), findsOneWidget);
      expect(find.textContaining('RM5'), findsOneWidget);
      expect(find.byType(BarChart), findsOneWidget);
      expect(find.text('Ahli baru'), findsOneWidget);
      expect(find.text('Ahmad mendaftar sebagai ahli.'), findsOneWidget);
    });
  });

  group('AdminMembersScreen', () {
    const members = [
      AdminMember(id: 1, name: 'Ahmad Ali', memberNo: 'M001', branchName: 'Kuala Lumpur', status: 'active'),
      AdminMember(id: 2, name: 'Siti Aminah', memberNo: 'M002', branchName: 'Shah Alam', status: 'pending'),
      AdminMember(id: 3, name: 'Ahmad Faiz', memberNo: 'M003', branchName: 'Petaling Jaya', status: 'active'),
    ];

    setUp(() => fakeRepo.membersSeed = members);

    testWidgets('renders paginated member list', (tester) async {
      await tester.pumpWidget(wrap(home: const AdminMembersScreen()));
      await tester.pumpAndSettle();

      expect(find.text('Ahmad Ali'), findsOneWidget);
      expect(find.text('Siti Aminah'), findsOneWidget);
      expect(find.text('No. M001 · Kuala Lumpur'), findsOneWidget);
    });

    testWidgets('filters list by search query', (tester) async {
      await tester.pumpWidget(wrap(home: const AdminMembersScreen()));
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextField), 'Faiz');
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();

      expect(find.text('Ahmad Faiz'), findsOneWidget);
      expect(find.text('Ahmad Ali'), findsNothing);
      expect(find.text('Siti Aminah'), findsNothing);
    });

    testWidgets('filters list by status chip', (tester) async {
      await tester.pumpWidget(wrap(home: const AdminMembersScreen()));
      await tester.pumpAndSettle();

      await tester.tap(find.widgetWithText(ChoiceChip, 'Aktif'));
      await tester.pumpAndSettle();

      expect(find.text('Ahmad Ali'), findsOneWidget);
      expect(find.text('Siti Aminah'), findsNothing);
    });

    testWidgets('shows member detail bottom sheet on tap', (tester) async {
      await tester.pumpWidget(wrap(home: const AdminMembersScreen()));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Ahmad Ali'));
      await tester.pumpAndSettle();

      expect(find.text('No. Ahli'), findsOneWidget);
      expect(find.text('M001'), findsWidgets);
      expect(find.text('Kuala Lumpur'), findsWidgets);
    });
  });

  group('AdminFeesScreen', () {
    testWidgets('renders summary cards and fee rows', (tester) async {
      fakeRepo.feesResult = const FeesData(
        summary: FeesSummary(totalMembers: 100, paidCount: 42, pendingCount: 58, revenue: 50000),
        fees: [
          AdminFee(id: 1, name: 'Ahmad Ali', memberNo: 'M001', year: '2025', amount: 50, status: 'paid'),
          AdminFee(id: 2, name: 'Siti Aminah', memberNo: 'M002', year: '2025', amount: 50, status: 'pending'),
        ],
      );

      await tester.pumpWidget(wrap(home: const AdminFeesScreen()));
      await tester.pumpAndSettle();

      expect(find.text('42'), findsOneWidget);
      expect(find.text('58'), findsOneWidget);
      expect(find.textContaining('50,000'), findsOneWidget);
      expect(find.text('Ahmad Ali'), findsOneWidget);
      expect(find.text('RM50'), findsNWidgets(2));
      expect(find.text('No. M001 · Yuran 2025'), findsOneWidget);
    });

    testWidgets('refetches with status filter', (tester) async {
      fakeRepo.feesResult = const FeesData(summary: FeesSummary(), fees: []);

      await tester.pumpWidget(wrap(home: const AdminFeesScreen()));
      await tester.pumpAndSettle();

      await tester.tap(find.widgetWithText(ChoiceChip, 'Belum Bayar'));
      await tester.pumpAndSettle();

      expect(fakeRepo.feesStatusCalls, contains('pending'));
    });
  });

  group('AdminAttendanceDetailScreen', () {
    testWidgets('renders registrations with attended status', (tester) async {
      fakeRepo.attendanceResult = const AttendanceData(
        event: AttendanceEvent(id: 1, title: 'Konvensyen Tahunan', startTime: null),
        totalRegistered: 3,
        attendedCount: 1,
        registrations: [
          Registration(id: 1, name: 'Ahmad Ali', memberNo: 'M001', status: 'confirmed', attended: true, attendedAt: null),
          Registration(id: 2, name: 'Siti Aminah', memberNo: 'M002', status: 'confirmed', attended: false, attendedAt: null),
        ],
      );

      await tester.pumpWidget(wrap(home: const AdminAttendanceDetailScreen(eventId: 1)));
      await tester.pumpAndSettle();

      expect(find.text('Konvensyen Tahunan'), findsOneWidget);
      expect(find.text('3'), findsOneWidget);
      expect(find.text('Ahmad Ali'), findsOneWidget);
      expect(find.text('Siti Aminah'), findsOneWidget);
      expect(find.text('Imbas QR'), findsOneWidget);
    });
  });

  group('AdminRepository scan', () {
    test('scan posts the scanned identifier for the event', () async {
      final result = await fakeRepo.scan(eventId: 9, identifier: 'M001');

      expect(fakeRepo.scanCalls, [(eventId: 9, identifier: 'M001')]);
      expect(result.isOk, isTrue);
      expect(result.registration?.name, 'Ahmad Ali');
    });
  });

  group('AdminBroadcastScreen', () {
    testWidgets('submits broadcast form and shows success snackbar', (tester) async {
      fakeRepo.broadcastResult = const BroadcastResult(success: true, message: 'Siaran berjaya dihantar.');

      final router = GoRouter(
        initialLocation: '/',
        routes: [
          GoRoute(path: '/', builder: (_, __) => const Scaffold(body: Text('Home'))),
          GoRoute(path: '/admin/broadcast', builder: (_, __) => const AdminBroadcastScreen()),
        ],
      );

      await tester.pumpWidget(
        ProviderScope(
          overrides: [adminRepositoryProvider.overrideWithValue(fakeRepo)],
          child: MaterialApp.router(routerConfig: router),
        ),
      );
      await tester.pumpAndSettle();
      router.push('/admin/broadcast');
      await tester.pumpAndSettle();

      await tester.enterText(
        find.widgetWithText(TextFormField, 'Tajuk'),
        'Mesyuarat Agung',
      );
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Mesej'),
        'Sila hadir tepat pada masa.',
      );
      await tester.tap(find.text('Hantar Siaran'));
      await tester.pumpAndSettle();

      expect(fakeRepo.broadcastCalls, [
        {
          'title': 'Mesyuarat Agung',
          'message': 'Sila hadir tepat pada masa.',
          'audience': 'all',
          'organization_id': null,
        },
      ]);
      expect(find.text('Siaran berjaya dihantar.'), findsOneWidget);
    });
  });

  group('app router wiring', () {
    testWidgets('/admin and admin sub-routes resolve without errors', (tester) async {
      fakeRepo.dashboardResult = const AdminDashboard(stats: AdminStats());

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tokenStorageProvider.overrideWithValue(_FakeTokenStorage()),
            authControllerProvider.overrideWith(
              () => _FakeAuthController(AuthAuthenticated(User(roles: ['Admin']))),
            ),
            memberDashboardProvider.overrideWith((ref) async => const DashboardData()),
            adminRepositoryProvider.overrideWithValue(fakeRepo),
          ],
          child: const MyWapApp(),
        ),
      );
      await tester.pumpAndSettle();

      final router = GoRouter.of(tester.element(find.text('Utama').first));

      router.go('/admin');
      await tester.pumpAndSettle();
      expect(find.text('Panel Admin'), findsWidgets);
      expect(tester.takeException(), isNull);

      router.go('/admin/members');
      await tester.pumpAndSettle();
      expect(find.text('Ahli'), findsWidgets);
      expect(tester.takeException(), isNull);

      router.go('/admin/fees');
      await tester.pumpAndSettle();
      expect(find.text('Yuran'), findsWidgets);
      expect(tester.takeException(), isNull);

      router.go('/admin/attendance');
      await tester.pumpAndSettle();
      expect(find.text('Kehadiran'), findsWidgets);
      expect(tester.takeException(), isNull);

      router.go('/admin/broadcast');
      await tester.pumpAndSettle();
      expect(find.text('Siaran'), findsWidgets);
      expect(tester.takeException(), isNull);
    });
  });
}
