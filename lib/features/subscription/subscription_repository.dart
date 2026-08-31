import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/subscription_model.dart';

/// Result of a Razorpay order-creation call — everything the razorpay_flutter
/// SDK's checkout options need, plus the total for display before payment.
class RazorpayOrder {
  RazorpayOrder({
    required this.orderId,
    required this.keyId,
    required this.amount,
    required this.currency,
    required this.companyName,
    required this.planName,
    required this.description,
    required this.userName,
    required this.userEmail,
    required this.userPhone,
    required this.color,
    required this.totalAmount,
  });

  factory RazorpayOrder.fromJson(Map<String, dynamic> json) {
    return RazorpayOrder(
      orderId: json['order_id']?.toString() ?? '',
      keyId: json['key_id']?.toString() ?? '',
      amount: (json['amount'] as num?)?.toInt() ?? 0,
      currency: json['currency']?.toString() ?? 'USD',
      companyName: json['company_name']?.toString() ?? '',
      planName: json['plan_name']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      userName: json['user_name']?.toString() ?? '',
      userEmail: json['user_email']?.toString() ?? '',
      userPhone: json['user_phone']?.toString() ?? '',
      color: json['color']?.toString() ?? '#2563EB',
      totalAmount: (json['total_amount'] as num?)?.toDouble() ?? 0,
    );
  }

  final String orderId;
  final String keyId;
  final int amount; // subunits (e.g. paise), as Razorpay expects
  final String currency;
  final String companyName;
  final String planName;
  final String description;
  final String userName;
  final String userEmail;
  final String userPhone;
  final String color;
  final double totalAmount; // major units, for display
}

/// Talks to the subscription endpoints on PosSyncApiController: GET
/// /subscription, POST /subscription/redeem, and the plan
/// activation/purchase endpoints (free-plan activation, and Razorpay /
/// Mercado Pago order-create + verify, mirroring the web Billing page's
/// SubscriptionPaymentGatewayService-backed flow).
class SubscriptionRepository {
  SubscriptionRepository(this._client);

  final ApiClient _client;

  Future<SubscriptionModel> fetchSubscription() async {
    final response = await _client.get(ApiEndpoints.subscription);
    return SubscriptionModel.fromJson(response);
  }

  Future<String> redeemCode(String code) async {
    final response = await _client.post(ApiEndpoints.subscriptionRedeem, data: {'code': code});
    return response['message'] as String? ?? 'Activation code redeemed successfully.';
  }

  /// Activates a $0 plan directly — no gateway involved.
  Future<DateTime?> activateFreePlan(String planName) async {
    final response = await _client.post(ApiEndpoints.subscriptionActivateFree(planName));
    final expiresAt = response['expires_at'] as String?;
    return expiresAt != null ? DateTime.tryParse(expiresAt) : null;
  }

  Future<RazorpayOrder> createRazorpayOrder(String planName) async {
    final response = await _client.post(ApiEndpoints.subscriptionRazorpayOrder(planName));
    return RazorpayOrder.fromJson(response);
  }

  Future<DateTime?> verifyRazorpayPayment({
    required String planName,
    required String paymentId,
    required String orderId,
    required String signature,
  }) async {
    final response = await _client.post(ApiEndpoints.subscriptionRazorpayVerify(planName), data: {
      'payment_id': paymentId,
      'order_id': orderId,
      'signature': signature,
    });
    final expiresAt = response['expires_at'] as String?;
    return expiresAt != null ? DateTime.tryParse(expiresAt) : null;
  }

  /// Returns the Mercado Pago hosted checkout URL to open in a WebView.
  Future<String> createMercadoPagoPreference(String planName) async {
    final response = await _client.post(ApiEndpoints.subscriptionMercadoPagoPreference(planName));
    return response['checkout_url']?.toString() ?? '';
  }

  Future<DateTime?> verifyMercadoPagoPayment({required String planName, required String paymentId}) async {
    final response = await _client.post(ApiEndpoints.subscriptionMercadoPagoVerify(planName), data: {
      'payment_id': paymentId,
    });
    final expiresAt = response['expires_at'] as String?;
    return expiresAt != null ? DateTime.tryParse(expiresAt) : null;
  }
}
