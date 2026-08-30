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
  static String tax(String id) => '/taxes/$id';
  static String taxSetDefault(String id) => '/taxes/$id/set-default';

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

  static const String categories = '/categories';
  static String category(String id) => '/categories/$id';

  static const String brands = '/brands';
  static String brand(String id) => '/brands/$id';

  static const String units = '/units';
  static String unit(String id) => '/units/$id';

  static const String suppliers = '/suppliers';
  static String supplier(String id) => '/suppliers/$id';

  static const String settings = '/settings';
  static const String settingsProfile = '/settings/profile';
  static const String settingsReceipts = '/settings/receipts';
  static const String settingsFinancial = '/settings/financial';
  static const String settingsNotifications = '/settings/notifications';
  static const String settingsTestEmail = '/settings/notifications/test-email';
  static const String settingsPaymentMethods = '/settings/payment-methods';
  static String settingsPaymentMethod(String id) => '/settings/payment-methods/$id';
  static String settingsPaymentMethodToggle(String id) => '/settings/payment-methods/$id/toggle';

  static const String consignments = '/consignments';
  static String consignment(String id) => '/consignments/$id';
  static String consignmentDispatch(String id) => '/consignments/$id/dispatch';
  static String consignmentReconcile(String id) => '/consignments/$id/reconcile';
  static String consignmentFinalize(String id) => '/consignments/$id/finalize';

  static const String serviceOrders = '/service-orders';
  static String serviceOrder(String id) => '/service-orders/$id';
  static String serviceOrderStatus(String id) => '/service-orders/$id/status';

  static const String salesTargets = '/sales-targets';

  static const String users = '/users';
  static const String usersInvite = '/users/invite';
  static String userResendInvite(String id) => '/users/$id/resend-invite';
  static String userRole(String id) => '/users/$id/role';
  static String userToggleStatus(String id) => '/users/$id/toggle-status';
  static String userCommission(String id) => '/users/$id/commission';
  static String user(String id) => '/users/$id';
  static String userPermissions(String id) => '/users/$id/permissions';

  static const String catalog = '/catalog';
  static String catalogLink(String id) => '/catalog/$id';

  static const String devices = '/devices';
  static String deviceRevoke(String token) => '/devices/$token/revoke';

  static const String languages = '/languages';
  static const String languagesDefault = '/languages/default';
}
