import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/facility_repository.dart';
import '../data/models/facility.dart';

final facilityRepositoryProvider = Provider<FacilityRepository>(
  (ref) => FacilityRepository(ref.watch(apiClientProvider)),
);

final facilitiesProvider = FutureProvider<FacilityListData>(
  (ref) => ref.watch(facilityRepositoryProvider).list(),
);

final facilityDetailProvider = FutureProvider.family<FacilityDetailData, int>(
  (ref, id) => ref.watch(facilityRepositoryProvider).detail(id),
);
