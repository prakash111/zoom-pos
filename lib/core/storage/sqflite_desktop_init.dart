import 'dart:io' show Platform;

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:sqflite/sqflite.dart';
import 'package:sqflite_common_ffi/sqflite_common_ffi.dart';

/// `sqflite` only ships a native implementation for Android/iOS. On Windows
/// (and Linux) there is no platform-channel implementation at all, so any
/// `openDatabase()` call throws `MissingPluginException` unless the
/// FFI-backed factory below is installed first.
///
/// Call this once, before any code path can reach `openDatabase()` — the
/// top of `main()`, right after `WidgetsFlutterBinding.ensureInitialized()`.
/// It's a no-op on Android/iOS/web, where the platform-channel
/// implementation already works.
void initializeSqfliteForDesktop() {
  if (kIsWeb) return;
  if (Platform.isWindows || Platform.isLinux) {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
  }
}
