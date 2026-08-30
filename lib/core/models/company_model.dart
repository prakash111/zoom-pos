class CompanyModel {
  CompanyModel({
    required this.id,
    required this.name,
    required this.tradeName,
    required this.currency,
    required this.currencySymbol,
    required this.planName,
  });

  factory CompanyModel.fromJson(Map<String, dynamic> json) {
    return CompanyModel(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      tradeName: json['trade_name'] as String? ?? json['name'] as String? ?? '',
      currency: json['currency'] as String? ?? 'USD',
      currencySymbol: json['currency_symbol'] as String? ?? '\$',
      planName: json['plan_name'] as String? ?? 'trial',
    );
  }

  final int id;
  final String name;
  final String tradeName;
  final String currency;
  final String currencySymbol;
  final String planName;
}
