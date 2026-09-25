import 'package:flutter/widgets.dart';
import 'package:provider/provider.dart';

import '../../features/landing/models/landing_translations.dart';
import '../api/api_client.dart';
import 'translations_cache.dart';

/// Runtime-only localization engine. No language dictionary is compiled into
/// the Flutter binary; values are read from the active tenant cache.
class DynamicStringService extends ChangeNotifier {
  DynamicStringService._();

  static final DynamicStringService instance = DynamicStringService._();

  String _locale = 'en';
  String get locale => _locale;

  Future<void> activate(String locale, {ApiClient? client}) async {
    _locale = _normalize(locale);
    await TranslationsCache.instance.loadFromDisk(_locale);
    notifyListeners();
    if (client != null &&
        await TranslationsCache.instance.refresh(_locale, client)) {
      notifyListeners();
    }
  }

  Future<void> applyFetched(
    String locale,
    Map<String, String> strings, {
    String? version,
  }) async {
    final code = _normalize(locale);
    await TranslationsCache.instance
        .applyFetched(code, strings, version: version);
    if (code == _locale) notifyListeners();
  }

  String translate(String key, [Map<String, Object?> args = const {}]) {
    final cached = TranslationsCache.instance.forLocale(_locale)[key];
    String resolved;
    if (cached != null && cached.trim().isNotEmpty) {
      resolved = cached;
    } else {
      final landing = LandingTranslations.tr(key, _locale);
      resolved = landing.trim().isNotEmpty ? landing : key;
    }
    for (final entry in args.entries) {
      final replacement = entry.value?.toString() ?? '';
      resolved = resolved
          .replaceAll('{${entry.key}}', replacement)
          .replaceAll(':${entry.key}', replacement);
    }
    return resolved;
  }

  static String _normalize(String locale) =>
      locale.trim().toLowerCase().split(RegExp('[-_]')).first;
}

String t(String key, [Map<String, Object?> args = const {}]) =>
    DynamicStringService.instance.translate(key, args);

extension DynamicStringBuildContext on BuildContext {
  String tr(String key, [Map<String, Object?> args = const {}]) {
    try {
      return watch<DynamicStringService>().translate(key, args);
    } catch (_) {
      return DynamicStringService.instance.translate(key, args);
    }
  }
}
