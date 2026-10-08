import 'web_ready_stub.dart'
    if (dart.library.js_interop) 'web_ready_web.dart';

void signalFlutterAppReady() {
  notifyFlutterAppReady();
}
