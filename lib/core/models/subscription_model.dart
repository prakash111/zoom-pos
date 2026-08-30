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
    final sub = json['subscription'] as Map<String, dynamic>? ?? {};
    final usage = json['usage'] as Map<String, dynamic>? ?? {};
    return SubscriptionModel(
      planName: sub['plan_name'] as String? ?? 'trial',
      displayName: sub['display_name'] as String? ?? 'Trial',
      status: sub['status'] as String? ?? 'active',
      expiresAt: DateTime.tryParse(sub['expires_at'] as String? ?? ''),
      daysRemaining: (sub['days_remaining'] as num?)?.toInt(),
      isLifetime: sub['is_lifetime'] as bool? ?? false,
      productsCount: (usage['products_count'] as num?)?.toInt() ?? 0,
      productsLimit: usage['products_limit'],
      usersCount: (usage['users_count'] as num?)?.toInt() ?? 0,
      usersLimit: usage['users_limit'],
      availablePlans: (json['available_plans'] as List? ?? [])
          .map((e) => SubscriptionPlan.fromJson(e as Map<String, dynamic>))
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
    return SubscriptionPlan(
      name: json['name'] as String? ?? '',
      displayName: json['display_name'] as String? ?? '',
      price: (json['price'] as num?)?.toDouble() ?? 0,
      currency: json['currency'] as String? ?? 'USD',
      billingCycle: json['billing_cycle'] as String?,
      features: (json['features'] as List? ?? []).map((e) => e.toString()).toList(),
    );
  }

  final String name;
  final String displayName;
  final double price;
  final String currency;
  final String? billingCycle;
  final List<String> features;
}
