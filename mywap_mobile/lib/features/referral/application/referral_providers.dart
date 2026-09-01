import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/models/referral_data.dart';
import '../data/referral_repository.dart';

final referralRepositoryProvider = Provider<ReferralRepository>(
  (ref) => ReferralRepository(ref.watch(apiClientProvider)),
);

final referralDataProvider = FutureProvider<ReferralData>(
  (ref) => ref.watch(referralRepositoryProvider).get(),
);
