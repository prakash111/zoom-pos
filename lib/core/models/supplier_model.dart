class SupplierModel {
  SupplierModel({
    required this.id,
    required this.name,
    required this.legalName,
    required this.taxId,
    required this.email,
    required this.phone,
    required this.city,
    required this.state,
    required this.active,
  });

  factory SupplierModel.fromJson(Map<String, dynamic> json) {
    return SupplierModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      legalName: json['legal_name'] as String? ?? '',
      taxId: json['tax_id'] as String? ?? '',
      email: json['email'] as String? ?? '',
      phone: json['phone'] as String? ?? '',
      city: json['city'] as String? ?? '',
      state: json['state'] as String? ?? '',
      active: json['active'] as bool? ?? true,
    );
  }

  final String id;
  final String name;
  final String legalName;
  final String taxId;
  final String email;
  final String phone;
  final String city;
  final String state;
  final bool active;
}
