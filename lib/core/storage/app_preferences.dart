import 'package:shared_preferences/shared_preferences.dart';

import '../config/app_config.dart';

/// Non-secret, per-device settings — currently just which server this
/// terminal talks to, since store owners can self-host on a custom domain.
class AppPreferences {
  static const _baseUrlKey = 'zoom_pos.base_url';

  Future<String> readBaseUrl() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_baseUrlKey) ?? AppConfig.defaultBaseUrl;
  }

  Future<void> saveBaseUrl(String url) async {
    final prefs = await SharedPreferences.getInstance();
    final normalized = url.trim().replaceAll(RegExp(r'/+$'), '');
    await prefs.setString(_baseUrlKey, normalized);
  }
}
