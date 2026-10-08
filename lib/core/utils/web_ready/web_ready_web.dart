import 'package:web/web.dart' as web;

void notifyFlutterAppReady() {
  try {
    final event = web.Event('flutter-app-ready');
    web.window.dispatchEvent(event);
  } catch (_) {}
}
