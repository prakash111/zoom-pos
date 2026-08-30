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
