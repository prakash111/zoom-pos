import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:window_manager/window_manager.dart';

import '../../../features/auth/auth_provider.dart';
import '../sync/sync_engine.dart';

/// Intercepts the native window close button on Windows.
///
/// A shared POS terminal should not resume into an already-unlocked app after
/// being closed, so historically this cleared the auth token on every close.
/// With offline-first sync that is no longer safe unconditionally: closing the
/// app while there are unsynced local changes (or no connectivity) would
/// strand that queue behind a login the user can't complete offline. So the
/// session is now only cleared on close when it is safe to do so — the device
/// is online **and** the outbox is empty. Otherwise the session is kept and
/// the next launch resumes (still gated by the OS keystore token + the
/// re-validation that runs the moment the device is back online).
///
/// `window_manager` has no Android/iOS implementation, so every entry point
/// here is a no-op off Windows.
class WindowCloseGuard with WindowListener {
  WindowCloseGuard(this._authProvider, {SyncEngine? syncEngine})
      : _syncEngine = syncEngine;

  final AuthProvider _authProvider;
  final SyncEngine? _syncEngine;

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

    final engine = _syncEngine;
    final hasPending = (engine?.pendingCount ?? 0) > 0;
    final offline = engine != null && !engine.isOnline;
    final keepSession = hasPending || offline;

    try {
      if (!keepSession) {
        await _authProvider.logout();
      }
    } catch (e) {
      debugPrint('WindowCloseGuard: failed to clear session on close: $e');
    } finally {
      await windowManager.destroy();
    }
  }
}
