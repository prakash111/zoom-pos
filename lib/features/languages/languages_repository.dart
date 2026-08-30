import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/language_model.dart';

/// Talks to LanguageApiController: GET /languages, PUT /languages/default.
class LanguagesRepository {
  LanguagesRepository(this._client);

  final ApiClient _client;

  Future<({String defaultLanguage, List<LanguageModel> languages})> fetchLanguages() async {
    final response = await _client.get(ApiEndpoints.languages);
    return (
      defaultLanguage: response['default_language'] as String? ?? 'en',
      languages: (response['languages'] as List? ?? [])
          .map((e) => LanguageModel.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  Future<void> setDefault(String locale) {
    return _client.put(ApiEndpoints.languagesDefault, data: {'locale': locale});
  }
}
