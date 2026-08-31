import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/tax_rule_model.dart';

/// Talks to the tax-rule endpoints on PosSyncApiController:
/// GET/POST /taxes, PUT/DELETE /taxes/{id}, POST /taxes/{id}/set-default.
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

  Future<void> updateTax({
    required String id,
    required String name,
    required double rate,
    bool isDefault = false,
    bool active = true,
  }) {
    return _client.put(ApiEndpoints.tax(id), data: {
      'name': name,
      'rate': rate,
      'is_default': isDefault,
      'active': active,
    });
  }

  Future<void> deleteTax(String id) {
    return _client.delete(ApiEndpoints.tax(id));
  }

  Future<void> setDefaultTax(String id) {
    return _client.post(ApiEndpoints.taxSetDefault(id));
  }

  /// The tenant's own country's suggested GST/VAT/sales-tax rules
  /// (TaxApiController::getRates) — outside the `/api/v1/pos` prefix, so it
  /// goes through [ApiClient.getAbsolute] rather than the other calls above.
  Future<TaxJurisdictionPresets> fetchJurisdictionPresets() async {
    final response = await _client.getAbsolute(ApiEndpoints.taxRatesAbsolute);
    return TaxJurisdictionPresets.fromJson(
      response['jurisdiction_presets'] as Map<String, dynamic>? ?? const {},
    );
  }
}
