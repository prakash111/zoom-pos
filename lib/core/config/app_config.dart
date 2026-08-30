/// Endpoint layout for the Dual-Mode POS Sync API (routes/api.php, `v1/pos` group).
///
/// The API resolves the tenant purely from the bearer token (see
/// AuthenticateTenantApi), not from the host, so a single [baseUrl] serves
/// every tenant — it only needs to point at whichever server the store owner
/// deployed the SaaS platform on, which is why it's user-editable rather than
/// hardcoded.
class AppConfig {
  AppConfig._();

  static const String defaultBaseUrl = 'https://saas.zoomnearby.com';
  static const String apiPrefix = '/api/v1/pos';

  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 30);
}

class ApiEndpoints {
  ApiEndpoints._();

  static const String login = '/auth/login';
  static const String register = '/auth/register';
  static const String session = '/auth/session';
  static const String status = '/status';

  static const String syncPull = '/sync-pull';
  static const String syncPush = '/sync-push';
  static const String syncBatch = '/sync-batch';

  static const String inventory = '/inventory';
  static const String inventoryStoreProduct = '/inventory/product';
  static const String inventoryAdjustStock = '/inventory/adjust';

  static const String customers = '/customers';
  static String customerLedger(String id) => '/customers/$id/ledger';
  static String customerPayment(String id) => '/customers/$id/payment';

  static const String analytics = '/analytics';
  static const String sendDelivery = '/send-delivery';

  static const String taxes = '/taxes';

  static const String subscription = '/subscription';
  static const String subscriptionRedeem = '/subscription/redeem';

  static const String quotations = '/quotations';
  static String quotation(String id) => '/quotations/$id';
  static String quotationConvert(String id) => '/quotations/$id/convert';

  static const String cashRegisterCurrent = '/cash-register/current';
  static const String cashRegisterOpen = '/cash-register/open';
  static const String cashRegisterHistory = '/cash-register/history';
  static String cashRegister(String id) => '/cash-register/$id';
  static String cashRegisterTransaction(String id) => '/cash-register/$id/transaction';
  static String cashRegisterClose(String id) => '/cash-register/$id/close';

  static const String payables = '/payables';
  static String payable(String id) => '/payables/$id';
  static String payablePay(String id) => '/payables/$id/pay';

  static const String reportsSummary = '/reports/summary';
  static const String reportsProfitLoss = '/reports/profit-loss';
  static const String reportsPaymentMethods = '/reports/payment-methods';
  static const String reportsTillClosings = '/reports/till-closings';
  static const String reportsCommissions = '/reports/commissions';
  static const String reportsAging = '/reports/aging';
  static const String reportsExport = '/reports/export';
}
