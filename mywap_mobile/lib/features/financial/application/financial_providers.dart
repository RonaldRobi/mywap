import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/financial_repository.dart';
import '../data/models/financial_overview.dart';

final financialRepositoryProvider = Provider<FinancialRepository>(
  (ref) => FinancialRepository(ref.watch(apiClientProvider)),
);

final financialOverviewProvider = FutureProvider<FinancialOverviewData>(
  (ref) => ref.watch(financialRepositoryProvider).overview(),
);
