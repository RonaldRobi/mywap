import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/event_repository.dart';
import '../data/models/event.dart';
import '../data/models/event_registration.dart';

final eventRepositoryProvider = Provider<EventRepository>(
  (ref) => EventRepository(ref.watch(apiClientProvider)),
);

final eventsProvider = FutureProvider<List<Event>>((ref) async {
  return ref.watch(eventRepositoryProvider).list();
});

final eventDetailProvider = FutureProvider.family<EventDetail, int>(
  (ref, id) => ref.watch(eventRepositoryProvider).detail(id),
);

final myRegistrationsProvider = FutureProvider<List<EventRegistration>>(
  (ref) async => ref.watch(eventRepositoryProvider).myRegistrations(),
);
