class CustomerModel {
  CustomerModel({
    required this.id,
    required this.name,
    required this.phone,
    required this.email,
    required this.document,
    required this.address,
    required this.city,
    required this.state,
    required this.balanceDue,
    required this.loyaltyPoints,
  });

  factory CustomerModel.fromJson(Map<String, dynamic> json) {
    return CustomerModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      phone: json['phone'] as String? ?? '',
      email: json['email'] as String? ?? '',
      document: json['document'] as String? ?? '',
      address: json['address'] as String? ?? '',
      city: json['city'] as String? ?? '',
      state: json['state'] as String? ?? '',
      balanceDue: (json['balance_due'] as num?)?.toDouble() ?? 0,
      loyaltyPoints: (json['loyalty_points'] as num?)?.toInt() ?? 0,
    );
  }

  final String id;
  final String name;
  final String phone;
  final String email;
  final String document;
  final String address;
  final String city;
  final String state;
  final double balanceDue;
  final int loyaltyPoints;

  bool get hasBalanceDue => balanceDue > 0;
}
