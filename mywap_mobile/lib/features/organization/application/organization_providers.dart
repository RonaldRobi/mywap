import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/models/organization_info.dart';
import '../data/organization_repository.dart';

final organizationRepositoryProvider = Provider<OrganizationRepository>(
  (ref) => OrganizationRepository(ref.watch(apiClientProvider)),
);

final organizationInfoProvider = FutureProvider<OrganizationInfoData>(
  (ref) => ref.watch(organizationRepositoryProvider).info(),
);
