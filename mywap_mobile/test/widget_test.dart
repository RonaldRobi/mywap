import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/shared/theme/app_colors.dart';
import 'package:mywap_mobile/shared/widgets/skeleton_box.dart';

void main() {
  test('design tokens match blueprint', () {
    expect(AppColors.movementNavy, const Color(0xFF071525));
    expect(AppColors.movementDarkGreen, const Color(0xFF123d2a));
    expect(AppColors.movementGreen, const Color(0xFF2f6b32));
    expect(AppColors.movementSoftGreen, const Color(0xFF6fbf8a));
    expect(AppColors.movementOffWhite, const Color(0xFFf4f6f1));
  });

  testWidgets('skeleton box renders without network', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: SkeletonBox(height: 100, width: 200)),
      ),
    );
    expect(find.byType(SkeletonBox), findsOneWidget);
  });
}
