import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/shared/l10n/app_localizations.dart';
import 'package:mywap_mobile/shared/widgets/share_item.dart';

Future<void> _pumpShareButton(
  WidgetTester tester, {
  required String? url,
}) async {
  await tester.pumpWidget(
    MaterialApp(
      locale: const Locale('en'),
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      home: Scaffold(
        appBar: AppBar(
          actions: [
            ShareItemIconButton(
              title: 'Community update',
              url: url,
              text: 'A short summary.',
            ),
          ],
        ),
      ),
    ),
  );
  await tester.tap(find.byIcon(Icons.share_outlined));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('share sheet offers direct apps when a link exists', (
    tester,
  ) async {
    await _pumpShareButton(tester, url: 'https://mywap.example/news/1');

    expect(find.text('WhatsApp'), findsOneWidget);
    expect(find.text('Facebook'), findsOneWidget);
    expect(find.text('Telegram'), findsOneWidget);
    expect(find.text('X'), findsOneWidget);
    expect(find.text('Copy link'), findsOneWidget);
    expect(find.text('More options'), findsOneWidget);
    expect(find.textContaining('not available'), findsNothing);
  });

  testWidgets('share sheet still works without a public link', (tester) async {
    await _pumpShareButton(tester, url: null);

    expect(find.text('WhatsApp'), findsOneWidget);
    expect(find.text('Telegram'), findsOneWidget);
    expect(find.text('Copy text'), findsOneWidget);
    expect(find.text('Facebook'), findsNothing);
    expect(find.text('X'), findsNothing);
    expect(find.textContaining('not available'), findsNothing);
  });
}
