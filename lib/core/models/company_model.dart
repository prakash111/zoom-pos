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
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      tradeName: (json['trade_name'] ?? json['name'])?.toString() ?? '',
      currency: json['currency']?.toString() ?? 'USD',
      currencySymbol: json['currency_symbol']?.toString() ?? '\$',
      planName: json['plan_name']?.toString() ?? 'trial',
    );
  }

  final String id;
  final String name;
  final String tradeName;
  final String currency;
  final String currencySymbol;
  final String planName;
}
