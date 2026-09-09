import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:window_manager/window_manager.dart';

import '../../storage/app_preferences.dart';

/// Sets up the native desktop window on Windows: a sensible minimum size so
/// the desktop-first layout never collapses, a stable title, and
/// position/size that persist between launches (via [AppPreferences]).
///
/// `window_manager` has no mobile implementation, so every method here is a
/// no-op off Windows and the app behaves exactly as before on Android.
class DesktopWindow {
  DesktopWindow._();

  /// Matches the desktop breakpoint (`Breakpoints.desktop` = 1024) plus room
  /// for the status bar / window chrome.
  static const Size minimumSize = Size(1024, 720);
  static const String title = 'Sales & Inventory';

  static bool get isSupported => !kIsWeb && Platform.isWindows;

  static _WindowBoundsSaver? _saver;

  /// Call once, early in `main()`, before `runApp`. Safe to call
  /// unconditionally.
  static Future<void> initialize(AppPreferences preferences) async {
    if (!isSupported) return;

    try {
      await windowManager.ensureInitialized();

      final saved = await preferences.readWindowBounds();

      const options = WindowOptions(
        minimumSize: minimumSize,
        title: title,
      );

      await windowManager.waitUntilReadyToShow(options, () async {
        await windowManager.setMinimumSize(minimumSize);
        await windowManager.setTitle(title);
        if (saved != null) {
          await windowManager.setBounds(saved);
        } else {
          await windowManager.setSize(const Size(1280, 800));
          await windowManager.center();
        }
        await windowManager.show();
        await windowManager.focus();
      });

      _saver = _WindowBoundsSaver(preferences);
      windowManager.addListener(_saver!);
    } catch (e) {
      debugPrint('DesktopWindow.initialize failed: $e');
    }
  }
}

/// Debounced writer: persists the window rect a short moment after the user
/// stops dragging/resizing, so a resize doesn't hammer SharedPreferences.
class _WindowBoundsSaver with WindowListener {
  _WindowBoundsSaver(this._preferences);

  final AppPreferences _preferences;
  Timer? _debounce;

  void _schedule() {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 600), () async {
      try {
        final bounds = await windowManager.getBounds();
        await _preferences.saveWindowBounds(bounds);
      } catch (e) {
        debugPrint('DesktopWindow: could not persist bounds: $e');
      }
    });
  }

  @override
  void onWindowResized() => _schedule();

  @override
  void onWindowMoved() => _schedule();
}
