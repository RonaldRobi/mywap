import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../biometric/biometric_service.dart';
import '../storage/token_storage.dart';
import 'api_client.dart';

final tokenStorageProvider = Provider<TokenStorage>((ref) => TokenStorage());

final apiClientProvider = Provider<ApiClient>(
  (ref) => ApiClient(ref.watch(tokenStorageProvider)),
);

final biometricServiceProvider = Provider<BiometricService>(
  (ref) => BiometricService(),
);
