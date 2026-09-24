import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/deeplink/deep_links.dart';
import 'core/push/push_providers.dart';
import 'core/router/app_router.dart';
import 'features/auth/application/auth_controller.dart';
import 'shared/l10n/app_localizations.dart';
import 'shared/theme/app_theme.dart';

class MyWapApp extends ConsumerStatefulWidget {
  const MyWapApp({super.key});

  @override
  ConsumerState<MyWapApp> createState() => _MyWapAppState();
}

class _MyWapAppState extends ConsumerState<MyWapApp> {
  StreamSubscription<Uri>? _linkSub;

  @override
  void initState() {
    super.initState();
    _linkSub = startDeepLinkListener(ref);
  }

  @override
  void dispose() {
    _linkSub?.cancel();
    super.dispose();
  }

  /// Cuba hala deep link yang menunggu. Hanya berlaku selepas auth selesai,
  /// dan tidak semasa masih di splash (splash_screen akan kendalikan sendiri
  /// supaya tempoh loading dihormati).
  void _tryRoutePending() {
    final path = ref.read(pendingDeepLinkProvider);
    if (path == null) return;
    if (ref.read(authControllerProvider) is AuthLoading) return;

    final router = ref.read(routerProvider);
    final current = router.routerDelegate.currentConfiguration.uri.path;
    if (current == '/splash') return;

    ref.read(pendingDeepLinkProvider.notifier).state = null;
    router.go(path);
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(authControllerProvider, (_, __) => _tryRoutePending());
    ref.listen(pendingDeepLinkProvider, (_, __) => _tryRoutePending());

    final router = ref.watch(routerProvider);
    final messengerKey = ref.watch(appMessengerKeyProvider);

    return MaterialApp.router(
      title: 'myWAP',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      routerConfig: router,
      scaffoldMessengerKey: messengerKey,
      locale: const Locale('ms'),
      supportedLocales: const [Locale('ms'), Locale('en')],
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
    );
  }
}
