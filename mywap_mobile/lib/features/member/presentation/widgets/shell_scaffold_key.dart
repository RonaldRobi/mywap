import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Shared [GlobalKey] for the [MainShell]'s outer [Scaffold] so that tab
/// screens (which each render their own nested Scaffold/AppBar) can open
/// the collapsible sidebar via [AppMenuButton] without prop-drilling a
/// callback through every route.
final mainShellScaffoldKeyProvider = Provider<GlobalKey<ScaffoldState>>(
  (ref) => GlobalKey<ScaffoldState>(),
);

/// Hamburger icon button — place as the `leading` widget of a tab screen's
/// [AppBar] to open the collapsible left sidebar ([AppSidebar]).
class AppMenuButton extends ConsumerWidget {
  const AppMenuButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final key = ref.watch(mainShellScaffoldKeyProvider);
    return IconButton(
      tooltip: 'Menu',
      icon: const Icon(Icons.menu_rounded),
      onPressed: () => key.currentState?.openDrawer(),
    );
  }
}
