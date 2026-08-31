import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:window_manager/window_manager.dart';

import '../../../features/auth/auth_provider.dart';

/// Intercepts the native window close button on Windows so a signed-in
/// session never survives the app being closed: the stored auth token and
/// in-memory user/company state are cleared before the process exits, so the
/// next launch always lands back on the login screen instead of resuming
/// into an already-unlocked app.
///
/// `window_manager` has no Android/iOS implementation, so every entry point
/// here is a no-op off Windows.
class WindowCloseGuard with WindowListener {
  WindowCloseGuard(this._authProvider);

  final AuthProvider _authProvider;

  static bool get isSupported => !kIsWeb && Platform.isWindows;

  /// Wires up the close interception. Safe to call unconditionally — it
  /// does nothing on platforms other than Windows.
  Future<void> install() async {
    if (!isSupported) return;
    await windowManager.ensureInitialized();
    // Turns the OS close button from "close immediately" into "fire
    // onWindowClose and wait for us to call destroy() ourselves".
    await windowManager.setPreventClose(true);
    windowManager.addListener(this);
  }

  @override
  void onWindowClose() async {
    final stillPrevented = await windowManager.isPreventClose();
    if (!stillPrevented) return;

    try {
      await _authProvider.logout();
    } catch (e) {
      debugPrint('WindowCloseGuard: failed to clear session on close: $e');
    } finally {
      await windowManager.destroy();
    }
  }
}
