import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../auth/application/auth_controller.dart';

/// Shared [GlobalKey] for the [MainShell]'s outer [Scaffold] so that tab
/// screens (which each render their own nested Scaffold/AppBar) can open
/// the collapsible sidebar via [AppMenuButton] without prop-drilling a
/// callback through every route.
final mainShellScaffoldKeyProvider = Provider<GlobalKey<ScaffoldState>>(
  (ref) => GlobalKey<ScaffoldState>(),
);

/// [GlobalKey] for the public (guest) shell's [Scaffold] — used by the same
/// tab screens when rendered for a logged-out visitor so the hamburger opens
/// the public menu instead of the member sidebar.
final publicShellScaffoldKeyProvider = Provider<GlobalKey<ScaffoldState>>(
  (ref) => GlobalKey<ScaffoldState>(),
);

/// Hamburger icon button — place as the `leading` widget of a tab screen's
/// [AppBar] to open the collapsible left sidebar ([AppSidebar]) for members,
/// or the public drawer for guests.
class AppMenuButton extends ConsumerWidget {
  const AppMenuButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isAuthenticated = ref.watch(currentUserProvider) != null;
    final key =
        isAuthenticated
            ? ref.watch(mainShellScaffoldKeyProvider)
            : ref.watch(publicShellScaffoldKeyProvider);
    return IconButton(
      tooltip: 'Menu',
      icon: const Icon(Icons.menu_rounded),
      onPressed: () => key.currentState?.openDrawer(),
    );
  }
}
