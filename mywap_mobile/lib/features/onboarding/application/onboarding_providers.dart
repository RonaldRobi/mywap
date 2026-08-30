import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/onboarding_repository.dart';

final onboardingRepositoryProvider = Provider<OnboardingRepository>(
  (ref) => OnboardingRepository(ref.watch(apiClientProvider)),
);
final onboardingSlidesProvider = FutureProvider<List<OnboardingSlideData>>(
  (ref) => ref.watch(onboardingRepositoryProvider).getSlides(),
);
