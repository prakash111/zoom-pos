import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/subscription_model.dart';

/// Talks to the subscription endpoints on PosSyncApiController:
/// GET /subscription and POST /subscription/redeem.
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
}
