import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/app.dart';
import 'package:mywap_mobile/core/network/providers.dart';
import 'package:mywap_mobile/core/storage/token_storage.dart';
import 'package:mywap_mobile/features/auth/presentation/login_screen.dart';

class _FakeTokenStorage extends TokenStorage {
  _FakeTokenStorage() : super();
  String? _value;

  @override
  Future<String?> read() async => _value;

  @override
  Future<void> write(String token) async => _value = token;

  @override
  Future<void> delete() async => _value = null;
}

void main() {
  testWidgets('app boots to login screen when no token', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [tokenStorageProvider.overrideWithValue(_FakeTokenStorage())],
        child: const MyWapApp(),
      ),
    );
    await tester.pumpAndSettle(const Duration(milliseconds: 200));

    expect(find.byType(LoginScreen), findsOneWidget);
    expect(find.text('Log Masuk'), findsWidgets);
  });
}
