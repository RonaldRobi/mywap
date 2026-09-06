import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// Deterministic back affordance for pushed pages and deep links.
///
/// `AppBar` only creates its automatic back button when the current navigator
/// can pop. A deep link has no previous route, so this button falls back to a
/// safe parent instead of disappearing or doing nothing.
class AppBackButton extends StatelessWidget {
  const AppBackButton({super.key, this.fallback = '/dashboard'});

  final String fallback;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: 'Kembali',
      icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 19),
      onPressed: () {
        if (context.canPop()) {
          context.pop();
        } else {
          context.go(fallback);
        }
      },
    );
  }
}
