/// GET /subscription response (PosSyncApiController::subscription) — the
/// tenant's current plan/expiry, module usage against plan limits, and the
/// catalog of plans available to redeem an activation code into.
class SubscriptionModel {
  SubscriptionModel({
    required this.planName,
    required this.displayName,
    required this.status,
    required this.expiresAt,
    required this.daysRemaining,
    required this.isLifetime,
    required this.productsCount,
    required this.productsLimit,
    required this.usersCount,
    required this.usersLimit,
    required this.availablePlans,
  });

  factory SubscriptionModel.fromJson(Map<String, dynamic> json) {
    final sub = json['subscription'] is Map
        ? Map<String, dynamic>.from(json['subscription'] as Map)
        : <String, dynamic>{};
    final usage = json['usage'] is Map
        ? Map<String, dynamic>.from(json['usage'] as Map)
        : <String, dynamic>{};

    return SubscriptionModel(
      planName: sub['plan_name']?.toString() ?? 'trial',
      displayName: sub['display_name']?.toString() ?? 'Trial',
      status: sub['status']?.toString() ?? 'active',
      expiresAt: sub['expires_at'] != null ? DateTime.tryParse(sub['expires_at'].toString()) : null,
      daysRemaining: sub['days_remaining'] != null ? (sub['days_remaining'] as num).toInt() : null,
      isLifetime: sub['is_lifetime'] == true || sub['expires_at'] == null,
      productsCount: (usage['products_count'] as num?)?.toInt() ?? 0,
      productsLimit: usage['products_limit'] ?? 'Unlimited',
      usersCount: (usage['users_count'] as num?)?.toInt() ?? 0,
      usersLimit: usage['users_limit'] ?? 'Unlimited',
      availablePlans: (json['available_plans'] as List? ?? [])
          .whereType<Map>()
          .map((e) => SubscriptionPlan.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }

  final String planName;
  final String displayName;
  final String status;
  final DateTime? expiresAt;
  final int? daysRemaining;
  final bool isLifetime;
  final int productsCount;
  final dynamic productsLimit;
  final int usersCount;
  final dynamic usersLimit;
  final List<SubscriptionPlan> availablePlans;

  bool get isExpired => status == 'expired';
}

class SubscriptionPlan {
  SubscriptionPlan({
    required this.name,
    required this.displayName,
    required this.price,
    required this.currency,
    required this.billingCycle,
    required this.features,
  });

  factory SubscriptionPlan.fromJson(Map<String, dynamic> json) {
    final feats = json['features'];
    final featuresList = feats is List
        ? feats.map((e) => e.toString()).toList()
        : <String>[];

    return SubscriptionPlan(
      name: json['name']?.toString() ?? '',
      displayName: json['display_name']?.toString() ?? '',
      price: (json['price'] as num?)?.toDouble() ?? 0.0,
      currency: json['currency']?.toString() ?? 'USD',
      billingCycle: json['billing_cycle']?.toString(),
      features: featuresList,
    );
  }

  final String name;
  final String displayName;
  final double price;
  final String currency;
  final String? billingCycle;
  final List<String> features;
}
