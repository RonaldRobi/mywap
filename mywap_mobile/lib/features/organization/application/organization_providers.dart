import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/models/organization_info.dart';
import '../data/organization_repository.dart';

final organizationRepositoryProvider = Provider<OrganizationRepository>(
  (ref) => OrganizationRepository(ref.watch(apiClientProvider)),
);

/// Slug organisasi yang dipilih oleh ahli (null = organisasi semasa).
final organizationSelectionProvider = StateProvider<String?>((ref) => null);

final organizationInfoProvider = FutureProvider<OrganizationInfoData>((ref) {
  final slug = ref.watch(organizationSelectionProvider);
  return ref.watch(organizationRepositoryProvider).info(slug: slug);
});
