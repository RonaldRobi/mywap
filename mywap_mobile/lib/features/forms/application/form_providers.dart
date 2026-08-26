import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/form_repository.dart';
import '../data/models/form_model.dart';

final formRepositoryProvider = Provider<FormRepository>(
  (ref) => FormRepository(ref.watch(apiClientProvider)),
);

final formDetailProvider = FutureProvider.family<FormModel, String>(
  (ref, token) => ref.watch(formRepositoryProvider).detail(token),
);
