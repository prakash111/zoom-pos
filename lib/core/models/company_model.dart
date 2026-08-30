class CompanyModel {
  CompanyModel({
    required this.id,
    required this.name,
    required this.tradeName,
    required this.currency,
    required this.currencySymbol,
    required this.planName,
    this.country = 'IN',
    this.taxId = '',
    this.address = '',
    this.city = '',
    this.state = '',
    this.postalCode = '',
    this.phone = '',
    this.email = '',
  });

  factory CompanyModel.fromJson(Map<String, dynamic> json) {
    return CompanyModel(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      tradeName: (json['trade_name'] ?? json['name'])?.toString() ?? '',
      currency: json['currency']?.toString() ?? 'USD',
      currencySymbol: json['currency_symbol']?.toString() ?? '\$',
      planName: json['plan_name']?.toString() ?? 'trial',
      country: json['country']?.toString() ?? 'IN',
      taxId: (json['tax_id'] ?? json['tax_number'] ?? json['gstin'])?.toString() ?? '',
      address: json['address']?.toString() ?? '',
      city: json['city']?.toString() ?? '',
      state: json['state']?.toString() ?? '',
      postalCode: json['postal_code']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
    );
  }

  final String id;
  final String name;
  final String tradeName;
  final String currency;
  final String currencySymbol;
  final String planName;
  final String country;
  final String taxId;
  final String address;
  final String city;
  final String state;
  final String postalCode;
  final String phone;
  final String email;

  bool get isIndia {
    final c = country.trim().toUpperCase();
    return c == 'IN' ||
        c == 'IND' ||
        c == 'INDIA' ||
        c.contains('INDIA') ||
        currency.toUpperCase() == 'INR' ||
        currencySymbol == '₹';
  }

  String get taxLabel => isIndia ? 'GST' : 'Tax';
  String get taxIdentifierLabel => isIndia ? 'GSTIN' : 'Tax ID';
}
