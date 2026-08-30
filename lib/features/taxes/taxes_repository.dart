import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/tax_rule_model.dart';

/// Talks to the tax-rule endpoints on PosSyncApiController:
/// GET/POST /taxes. Creation only — the server has no update-by-id path for
/// tax rules (see taxRulesStore), so editing an existing rate isn't offered.
class TaxesRepository {
  TaxesRepository(this._client);

  final ApiClient _client;

  Future<List<TaxRuleModel>> fetchTaxes() async {
    final response = await _client.get(ApiEndpoints.taxes);
    return (response['taxes'] as List? ?? [])
        .map((e) => TaxRuleModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> createTax({
    required String name,
    required double rate,
    bool isDefault = false,
    bool active = true,
  }) {
    return _client.post(ApiEndpoints.taxes, data: {
      'name': name,
      'rate': rate,
      'is_default': isDefault,
      'active': active,
    });
  }
}
