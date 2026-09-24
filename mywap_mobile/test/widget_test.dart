import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/shared/theme/app_colors.dart';
import 'package:mywap_mobile/shared/widgets/skeleton_box.dart';

void main() {
  test('design tokens match blueprint', () {
    expect(AppColors.movementNavy, const Color(0xFF12241C));
    expect(AppColors.movementDarkGreen, const Color(0xFF0E5C2E));
    expect(AppColors.movementGreen, const Color(0xFF147A3D));
    expect(AppColors.movementSoftGreen, const Color(0xFF4FAE73));
    expect(AppColors.movementOffWhite, const Color(0xFFF3F6EF));
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
