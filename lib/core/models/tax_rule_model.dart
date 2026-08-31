/// A tax rule, as returned by GET /taxes (PosSyncApiController::taxRulesIndex).
class TaxRuleModel {
  TaxRuleModel({
    required this.id,
    required this.name,
    required this.rate,
    required this.isDefault,
    required this.active,
  });

  factory TaxRuleModel.fromJson(Map<String, dynamic> json) {
    return TaxRuleModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      rate: (json['rate'] as num?)?.toDouble() ?? 0,
      isDefault: json['is_default'] as bool? ?? false,
      active: json['active'] as bool? ?? true,
    );
  }

  final String id;
  final String name;
  final double rate;
  final bool isDefault;
  final bool active;
}

/// One CGST/SGST/IGST-style component of a [TaxRulePreset].
class TaxSubComponent {
  TaxSubComponent({required this.name, required this.rate});

  factory TaxSubComponent.fromJson(Map<String, dynamic> json) {
    return TaxSubComponent(
      name: json['name'] as String? ?? '',
      rate: (json['rate'] as num?)?.toDouble() ?? 0,
    );
  }

  final String name;
  final double rate;
}

/// A single suggested tax rule from the server's country jurisdiction
/// presets (e.g. "GST 18% (Intra-State)"), as returned by
/// `jurisdiction_presets.rules` on GET /api/v1/tax/rates. Distinct from
/// [TaxRuleModel], which is a tax rule the tenant has actually saved.
class TaxRulePreset {
  TaxRulePreset({
    required this.name,
    required this.code,
    required this.rate,
    required this.isInclusive,
    required this.isDefault,
    required this.subComponents,
    required this.description,
  });

  factory TaxRulePreset.fromJson(Map<String, dynamic> json) {
    return TaxRulePreset(
      name: json['name'] as String? ?? '',
      code: json['code'] as String? ?? '',
      rate: (json['rate'] as num?)?.toDouble() ?? 0,
      isInclusive: json['is_inclusive'] as bool? ?? false,
      isDefault: json['is_default'] as bool? ?? false,
      subComponents: (json['sub_components'] as List? ?? [])
          .map((e) => TaxSubComponent.fromJson(e as Map<String, dynamic>))
          .toList(),
      description: json['description'] as String?,
    );
  }

  final String name;
  final String code;
  final double rate;
  final bool isInclusive;
  final bool isDefault;
  final List<TaxSubComponent> subComponents;
  final String? description;
}

/// The tenant's country jurisdiction preset bundle, as returned by the
/// `jurisdiction_presets` field of GET /api/v1/tax/rates
/// (TaxCalculationService::getJurisdictionPresets, scoped to the tenant's
/// own saved country — there's no way to preview another country's presets
/// without first saving it).
class TaxJurisdictionPresets {
  TaxJurisdictionPresets({required this.country, required this.system, required this.standardRate, required this.rules});

  factory TaxJurisdictionPresets.fromJson(Map<String, dynamic> json) {
    return TaxJurisdictionPresets(
      country: json['country'] as String? ?? '',
      system: json['system'] as String? ?? '',
      standardRate: (json['standard_rate'] as num?)?.toDouble() ?? 0,
      rules: (json['rules'] as List? ?? []).map((e) => TaxRulePreset.fromJson(e as Map<String, dynamic>)).toList(),
    );
  }

  final String country;
  final String system;
  final double standardRate;
  final List<TaxRulePreset> rules;
}
