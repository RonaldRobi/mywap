import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/core/network/api_client.dart';
import 'package:mywap_mobile/core/storage/token_storage.dart';
import 'package:mywap_mobile/features/events/application/event_providers.dart';
import 'package:mywap_mobile/features/events/data/event_repository.dart';
import 'package:mywap_mobile/features/events/data/models/event_registration.dart';
import 'package:mywap_mobile/features/events/presentation/my_registrations_screen.dart';
import 'package:mywap_mobile/shared/theme/app_theme.dart';

class _FakeEventRepository extends EventRepository {
  _FakeEventRepository() : super(ApiClient(TokenStorage()));

  @override
  Future<List<EventRegistration>> myRegistrations({int page = 1}) async => const [
        EventRegistration(
          id: 1,
          registrationNo: 'REG-0001',
          status: 'confirmed',
          statusLabel: 'Disahkan',
          paymentStatus: 'paid',
          attended: true,
          event: RegistrationEvent(
            id: 10,
            title: 'Muktamar Nasional 2026',
            startFormatted: 'Jumaat, 1 Jan 2026',
          ),
        ),
      ];
}

void main() {
  testWidgets('MyRegistrationsScreen renders registrations', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [eventRepositoryProvider.overrideWithValue(_FakeEventRepository())],
        child: MaterialApp(theme: AppTheme.light, home: const MyRegistrationsScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Pendaftaran Saya'), findsOneWidget);
    expect(find.text('Muktamar Nasional 2026'), findsOneWidget);
    expect(find.text('Disahkan'), findsOneWidget);
    expect(find.text('Bayaran Selesai'), findsOneWidget);
    expect(find.text('No. Pendaftaran: REG-0001'), findsOneWidget);
  });
}
