import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/manual_file_service.dart';
import '../data/manual_repository.dart';
import '../data/models/manual.dart';

final manualRepositoryProvider = Provider<ManualRepository>(
  (ref) => ManualRepository(ref.watch(apiClientProvider)),
);

final manualFileServiceProvider = Provider<ManualFileService>(
  (ref) => ManualFileService(tokenStorage: ref.watch(tokenStorageProvider)),
);

final manualsProvider = FutureProvider<List<Manual>>(
  (ref) async => ref.watch(manualRepositoryProvider).list(),
);
