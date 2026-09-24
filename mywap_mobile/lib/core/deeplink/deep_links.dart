import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Laluan deep link yang menunggu untuk diarahkan selepas auth selesai.
///
/// Deep link boleh tiba lebih awal daripada auth restore (cold start), jadi
/// kita simpan dulu dan hantar selepas session siap — lihat `_tryRoute` dalam
/// `app.dart` dan pengendalian dalam `splash_screen.dart`.
final pendingDeepLinkProvider = StateProvider<String?>((ref) => null);

/// Ekstrak laluan (path + query) daripada mana-mana URI deep link.
/// `https://mywap.my/events/5/attend/token` → `/events/5/attend/token`.
String deepLinkPathOf(Uri uri) {
  var path = uri.path;
  if (path.isEmpty) path = '/';
  return uri.hasQuery ? '$path?${uri.query}' : path;
}

/// Mulakan pendengar deep link (Universal Links iOS, App Links Android dan
/// skim tersuai `mywap://`). Simpan laluan ke [pendingDeepLinkProvider].
///
/// Pulangkan [StreamSubscription] supaya pemanggil boleh membatalkan semasa
/// dispose.
StreamSubscription<Uri> startDeepLinkListener(WidgetRef ref) {
  final appLinks = AppLinks();

  final sub = appLinks.uriLinkStream.listen((uri) {
    ref.read(pendingDeepLinkProvider.notifier).state = deepLinkPathOf(uri);
  });

  // Cold start — pautan tiba sebelum app berjalan.
  appLinks.getInitialLink().then((uri) {
    if (uri != null) {
      ref.read(pendingDeepLinkProvider.notifier).state = deepLinkPathOf(uri);
    }
  });

  return sub;
}
