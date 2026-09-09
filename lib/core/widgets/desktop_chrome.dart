import 'package:flutter/material.dart';

import 'desktop_status_bar.dart';

/// Wraps the whole app navigator on Windows so a single persistent status bar
/// sits below **every** screen (it survives route pushes because it lives
/// above the [Navigator], not inside any one page). Applied from
/// `MaterialApp.builder`; never used on Android, where [child] is returned
/// untouched.
class DesktopChrome extends StatelessWidget {
  const DesktopChrome({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Expanded(child: child),
        const Divider(height: 1, thickness: 1),
        const SyncStatusBar(),
      ],
    );
  }
}
