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
    this.walletBalance = 0.0,
    this.pointsBalance = 0,
    this.tierName = 'Standard',
    this.tierMultiplier = 1.0,
    this.customFields = const {},
  });

  factory CustomerModel.fromJson(Map<String, dynamic> json) {
    final points = (json['points_balance'] as num?)?.toInt() ??
        (json['loyalty_points'] as num?)?.toInt() ??
        0;
    final wallet = (json['wallet_balance'] as num?)?.toDouble() ?? 0.0;
    final tier = json['tier'];
    final tName = json['tier_name'] as String? ??
        (tier is Map ? tier['name'] as String? : null) ??
        'Standard';
    final tMultiplier = (tier is Map ? (tier['points_multiplier'] as num?)?.toDouble() : null) ?? 1.0;

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
      loyaltyPoints: points,
      walletBalance: wallet,
      pointsBalance: points,
      tierName: tName,
      tierMultiplier: tMultiplier,
      customFields: json['custom_fields'] is Map
          ? Map<String, dynamic>.from(json['custom_fields'] as Map)
          : const {},
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
  final double walletBalance;
  final int pointsBalance;
  final String tierName;
  final double tierMultiplier;
  final Map<String, dynamic> customFields;

  bool get hasBalanceDue => balanceDue > 0;
  int get effectivePoints => pointsBalance > 0 ? pointsBalance : loyaltyPoints;

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'phone': phone,
      'email': email,
      'document': document,
      'address': address,
      'city': city,
      'state': state,
      'balance_due': balanceDue,
      'loyalty_points': loyaltyPoints,
      'wallet_balance': walletBalance,
      'points_balance': pointsBalance,
      'tier_name': tierName,
      'custom_fields': customFields,
    };
  }
}
