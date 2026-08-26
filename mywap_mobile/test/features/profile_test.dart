import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:mywap_mobile/core/network/api_client.dart';
import 'package:mywap_mobile/core/network/api_exception.dart';
import 'package:mywap_mobile/core/storage/token_storage.dart';
import 'package:mywap_mobile/features/profile/application/profile_providers.dart';
import 'package:mywap_mobile/features/profile/data/models/profile_data.dart';
import 'package:mywap_mobile/features/profile/data/profile_repository.dart';
import 'package:mywap_mobile/features/profile/presentation/edit_profile_screen.dart';
import 'package:mywap_mobile/features/profile/presentation/profile_screen.dart';

class _FakeProfileRepository extends ProfileRepository {
  _FakeProfileRepository() : super(ApiClient(TokenStorage()));

  ProfileData? profileData;
  Map<String, dynamic>? submittedBody;
  Object? fetchError;
  Object? updateError;

  @override
  Future<ProfileData> fetchProfile() async {
    if (fetchError != null) throw fetchError!;
    return profileData ?? const ProfileData();
  }

  @override
  Future<EditMeta> editMeta() async {
    return const EditMeta(
      branches: [ProfileBranch(id: 1, name: 'Cawangan Kuala Lumpur')],
    );
  }

  @override
  Future<CompleteMeta> completeMeta() async {
    return const CompleteMeta(parsedDob: '1990-05-20', parsedGender: 'lelaki');
  }

  @override
  Future<ProfileUser> updateProfile(Map<String, dynamic> body) async {
    submittedBody = body;
    if (updateError != null) throw updateError!;
    return const ProfileUser(name: 'Ahmad Ali Baru');
  }
}

ProfileData _sampleData() {
  return ProfileData(
    profileUser: const ProfileUser(
      id: 1,
      member_no: 'M-1001',
      name: 'Ahmad bin Ali',
      email: 'ahmad@example.com',
      phone: '0123456789',
      ic_number: '900520-10-1234',
      roles: ['Member', 'Penolong Setiausaha'],
      branch_name: 'Cawangan Kuala Lumpur',
      locality: 'Ampang',
      current_profession: 'Jurutera',
      education_level: 'Ijazah Sarjana Muda',
      address_1: 'No 5, Jalan Melor',
      city: 'Ampang',
      postcode: '68000',
      state: 'Selangor',
      emergency_contact_name: 'Siti',
      emergency_contact_phone: '0198765432',
      organization: ProfileOrganization(id: 2, name: 'Wadah Kuala Lumpur'),
      feeStatus: ProfileFeeStatus(status: 'due', amount_due: 50),
    ),
    history: const [
      ProfileHistoryEntry(
        id: 1,
        to_organization: ProfileOrganization(id: 2, name: 'Wadah Kuala Lumpur'),
        transitioned_at_human: '20 Mei 2020',
      ),
    ],
    attendedPrograms: const [
      ProfileAttendedProgram(
        id: 1,
        event: ProfileProgramEvent(id: 9, title: 'Kursus Kepimpinan'),
        attended_at_human: '10 Jan 2024',
      ),
    ],
  );
}

Widget _pump(Widget home, _FakeProfileRepository repo) {
  return ProviderScope(
    overrides: [profileRepositoryProvider.overrideWithValue(repo)],
    child: MaterialApp(home: home),
  );
}

void _bigViewport(WidgetTester tester) {
  tester.view.physicalSize = const Size(800, 3600);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

void main() {
  group('ProfileScreen', () {
    testWidgets('renders profile data', (tester) async {
      _bigViewport(tester);
      final repo = _FakeProfileRepository()..profileData = _sampleData();
      await tester.pumpWidget(_pump(const ProfileScreen(), repo));
      await tester.pumpAndSettle();

      expect(find.text('Ahmad bin Ali'), findsOneWidget);
      expect(find.text('No. Ahli: M-1001'), findsOneWidget);
      expect(find.text('Wadah Kuala Lumpur'), findsWidgets);
      expect(find.text('Member'), findsOneWidget);
      expect(find.text('Yuran Belum Dibayar'), findsOneWidget);
      expect(find.text('Amaun tertunggak: RM50'), findsOneWidget);
      expect(find.text('ahmad@example.com'), findsOneWidget);
      expect(find.text('0123456789'), findsOneWidget);
      expect(find.text('900520-10-1234'), findsOneWidget);
      expect(find.text('Edit Profil'), findsOneWidget);
      expect(find.text('Perjalanan Ahli'), findsOneWidget);
      expect(find.textContaining('Wadah Kuala Lumpur'), findsWidgets);
    });

    testWidgets('shows error retry then recovers', (tester) async {
      _bigViewport(tester);
      final repo = _FakeProfileRepository()
        ..fetchError = const ApiException('Ralat tidak dijangka.', statusCode: 500);
      await tester.pumpWidget(_pump(const ProfileScreen(), repo));
      await tester.pumpAndSettle();

      expect(find.text('Ralat tidak dijangka.'), findsOneWidget);
      expect(find.text('Cuba Semula'), findsOneWidget);

      repo
        ..fetchError = null
        ..profileData = _sampleData();
      await tester.tap(find.text('Cuba Semula'));
      await tester.pumpAndSettle();

      expect(find.text('Ahmad bin Ali'), findsOneWidget);
      expect(find.text('Cuba Semula'), findsNothing);
    });

    testWidgets('shows complete-profile banner when profile is incomplete',
        (tester) async {
      _bigViewport(tester);
      final repo = _FakeProfileRepository()
        ..profileData = const ProfileData(
          profileUser: ProfileUser(name: 'Budi'),
        );
      await tester.pumpWidget(_pump(const ProfileScreen(), repo));
      await tester.pumpAndSettle();

      expect(find.text('Profil anda belum lengkap'), findsOneWidget);
      expect(find.text('Lengkapkan'), findsOneWidget);
    });
  });

  group('EditProfileScreen', () {
    GoRouter buildRouter() {
      return GoRouter(
        initialLocation: '/home',
        routes: [
          GoRoute(
            path: '/home',
            builder: (_, __) =>
                const Scaffold(body: Center(child: Text('Home'))),
          ),
          GoRoute(path: '/edit', builder: (_, __) => const EditProfileScreen()),
        ],
      );
    }

    Future<void> openEditScreen(
      WidgetTester tester,
      GoRouter router,
      _FakeProfileRepository repo,
    ) async {
      await tester.pumpWidget(ProviderScope(
        overrides: [profileRepositoryProvider.overrideWithValue(repo)],
        child: MaterialApp.router(routerConfig: router),
      ));
      await tester.pumpAndSettle();
      router.push('/edit');
      await tester.pumpAndSettle();
    }

    testWidgets('submits updated profile and returns with snackbar',
        (tester) async {
      _bigViewport(tester);
      final repo = _FakeProfileRepository()..profileData = _sampleData();
      final router = buildRouter();
      await openEditScreen(tester, router, repo);

      final nameField = find.widgetWithText(TextFormField, 'Nama Penuh *');
      expect(nameField, findsOneWidget);
      await tester.ensureVisible(nameField);
      await tester.enterText(nameField, 'Ahmad Ali Baru');

      await tester.tap(find.text('Simpan Perubahan'));
      await tester.pumpAndSettle();

      expect(repo.submittedBody, isNotNull);
      expect(repo.submittedBody!['name'], 'Ahmad Ali Baru');
      expect(repo.submittedBody!['email'], 'ahmad@example.com');
      expect(repo.submittedBody!['phone'], '0123456789');
      expect(repo.submittedBody!['branch_id'], isNull);
      expect(repo.submittedBody!['is_public_in_directory'], true);

      expect(find.text('Home'), findsOneWidget);
      expect(find.text('Profil berjaya dikemas kini.'), findsOneWidget);
    });

    testWidgets('surfaces 422 field errors under the form', (tester) async {
      _bigViewport(tester);
      final repo = _FakeProfileRepository()
        ..profileData = _sampleData()
        ..updateError = const ApiException(
          'Sila semak semula maklumat anda.',
          statusCode: 422,
          errors: {'name': ['Nama telah digunakan.']},
        );
      final router = buildRouter();
      await openEditScreen(tester, router, repo);

      await tester.tap(find.text('Simpan Perubahan'));
      await tester.pumpAndSettle();

      expect(find.text('Nama telah digunakan.'), findsOneWidget);
      expect(
        find.text('Sila semak semula maklumat yang bertanda merah.'),
        findsOneWidget,
      );
      expect(find.text('Edit Profil'), findsWidgets);
    });
  });
}
