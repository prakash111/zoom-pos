import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/config/bootstrap_cache.dart';
import '../../core/models/settings_models.dart';

/// Talks to SettingsApiController: GET /settings, PUT /settings/{section},
/// POST /settings/notifications/test-email, and the payment-methods CRUD.
class SettingsRepository {
  SettingsRepository(this._client);

  final ApiClient _client;

  Future<TenantSettingsBundle> fetchAll() async {
    final response = await _client.get(ApiEndpoints.settings);
    return TenantSettingsBundle(
      profile: ProfileSettings.fromJson(response['profile'] as Map<String, dynamic>),
      receipts: ReceiptSettings.fromJson(response['receipts'] as Map<String, dynamic>),
      financial: FinancialSettings.fromJson(response['financial'] as Map<String, dynamic>),
      notifications: NotificationSettings.fromJson(response['notifications'] as Map<String, dynamic>),
      paymentMethods: (response['payment_methods'] as List? ?? [])
          .map((e) => PaymentMethodModel.fromJson(e as Map<String, dynamic>))
          .toList(),
      nav: NavConfig.fromJson(response['nav'] as Map<String, dynamic>? ?? const {}),
    );
  }

  /// Persists this tenant's nav customization (AppBootstrapController) and
  /// refreshes [BootstrapCache] so the drawer/rail/bars pick it up without
  /// waiting for the next locale switch or app restart.
  Future<NavConfig> updateNavConfig(NavConfig nav) async {
    final response = await _client.post(ApiEndpoints.settingsNavConfig, data: nav.toJson());
    final updated = NavConfig.fromJson(response['nav'] as Map<String, dynamic>? ?? nav.toJson());
    await BootstrapCache.instance.applyNav(updated);
    return updated;
  }

  Future<ProfileSettings> updateProfile({
    required String name,
    String? tradeName,
    String? taxId,
    String? email,
    String? phone,
    String? website,
    String? address,
    String? city,
    String? state,
    String? postalCode,
    String? country,
    String? primaryColor,
    double? defaultCommissionRate,
    String? defaultCommissionType,
  }) async {
    final response = await _client.put(ApiEndpoints.settingsProfile, data: {
      'name': name,
      if (tradeName != null) 'trade_name': tradeName,
      if (taxId != null) 'tax_id': taxId,
      if (email != null && email.isNotEmpty) 'email': email,
      if (phone != null) 'phone': phone,
      if (website != null) 'website': website,
      if (address != null) 'address': address,
      if (city != null) 'city': city,
      if (state != null) 'state': state,
      if (postalCode != null) 'postal_code': postalCode,
      if (country != null && country.isNotEmpty) 'country': country,
      if (primaryColor != null) 'primary_color': primaryColor,
      if (defaultCommissionRate != null) 'default_commission_rate': defaultCommissionRate,
      if (defaultCommissionType != null) 'default_commission_type': defaultCommissionType,
    });
    return ProfileSettings.fromJson(response['profile'] as Map<String, dynamic>);
  }

  Future<String?> uploadLogo(List<int> bytes, String filename) async {
    final response = await _client.postMultipart(
      ApiEndpoints.settingsProfileLogo,
      fieldName: 'logo',
      bytes: bytes,
      filename: filename,
    );
    return response['logo_url'] as String?;
  }

  Future<void> removeLogo() => _client.delete(ApiEndpoints.settingsProfileLogo);

  Future<String?> uploadFavicon(List<int> bytes, String filename) async {
    final response = await _client.postMultipart(
      ApiEndpoints.settingsProfileFavicon,
      fieldName: 'favicon',
      bytes: bytes,
      filename: filename,
    );
    return response['favicon_url'] as String?;
  }

  Future<void> removeFavicon() => _client.delete(ApiEndpoints.settingsProfileFavicon);

  Future<String?> uploadDrawerCover(List<int> bytes, String filename) async {
    final response = await _client.postMultipart(
      ApiEndpoints.settingsProfileDrawerCover,
      fieldName: 'drawer_cover',
      bytes: bytes,
      filename: filename,
    );
    return response['drawer_cover_url'] as String?;
  }

  Future<void> removeDrawerCover() => _client.delete(ApiEndpoints.settingsProfileDrawerCover);

  Future<ReceiptSettings> updateReceipts({
    String? invoicePrefix,
    String? quotationPrefix,
    String? invoiceTerms,
    String? quoteTerms,
    String? bankDetails,
  }) async {
    final response = await _client.put(ApiEndpoints.settingsReceipts, data: {
      if (invoicePrefix != null) 'invoice_prefix': invoicePrefix,
      if (quotationPrefix != null) 'quotation_prefix': quotationPrefix,
      if (invoiceTerms != null) 'invoice_terms': invoiceTerms,
      if (quoteTerms != null) 'quote_terms': quoteTerms,
      if (bankDetails != null) 'bank_details': bankDetails,
    });
    return ReceiptSettings.fromJson(response['receipts'] as Map<String, dynamic>);
  }

  Future<FinancialSettings> updateFinancial({
    required String currency,
    String? currencySymbol,
    int? currencyDecimals,
    String? currencySymbolPosition,
    List<OtherCurrency>? otherCurrencies,
  }) async {
    final response = await _client.put(ApiEndpoints.settingsFinancial, data: {
      'currency': currency,
      if (currencySymbol != null) 'currency_symbol': currencySymbol,
      if (currencyDecimals != null) 'currency_decimals': currencyDecimals,
      if (currencySymbolPosition != null) 'currency_symbol_position': currencySymbolPosition,
      if (otherCurrencies != null) 'other_currencies': otherCurrencies.map((c) => c.toJson()).toList(),
    });
    return FinancialSettings.fromJson(response['financial'] as Map<String, dynamic>);
  }

  Future<NotificationSettings> updateNotifications({
    String? smtpHost,
    int? smtpPort,
    String? smtpUsername,
    String? smtpPassword,
    String? smtpEncryption,
    String? smtpFromAddress,
    String? smtpFromName,
    String? whatsappPhonePrefix,
    String? whatsappCustomNote,
    String? whatsappPhoneNumberId,
    String? whatsappApiToken,
  }) async {
    final response = await _client.put(ApiEndpoints.settingsNotifications, data: {
      if (smtpHost != null) 'smtp_host': smtpHost,
      if (smtpPort != null) 'smtp_port': smtpPort,
      if (smtpUsername != null) 'smtp_username': smtpUsername,
      if (smtpPassword != null && smtpPassword.isNotEmpty) 'smtp_password': smtpPassword,
      if (smtpEncryption != null) 'smtp_encryption': smtpEncryption,
      if (smtpFromAddress != null) 'smtp_from_address': smtpFromAddress,
      if (smtpFromName != null) 'smtp_from_name': smtpFromName,
      if (whatsappPhonePrefix != null) 'whatsapp_phone_prefix': whatsappPhonePrefix,
      if (whatsappCustomNote != null) 'whatsapp_custom_note': whatsappCustomNote,
      if (whatsappPhoneNumberId != null) 'whatsapp_phone_number_id': whatsappPhoneNumberId,
      if (whatsappApiToken != null && whatsappApiToken.isNotEmpty) 'whatsapp_api_token': whatsappApiToken,
    });
    return NotificationSettings.fromJson(response['notifications'] as Map<String, dynamic>);
  }

  Future<void> sendTestEmail(String recipient) {
    return _client.post(ApiEndpoints.settingsTestEmail, data: {'recipient': recipient});
  }

  Future<List<PaymentMethodModel>> fetchPaymentMethods() async {
    final response = await _client.get(ApiEndpoints.settingsPaymentMethods);
    return (response['payment_methods'] as List? ?? [])
        .map((e) => PaymentMethodModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> savePaymentMethod({String? id, required String name, String? code, String? description}) {
    final data = {
      'name': name,
      if (code != null && code.isNotEmpty) 'code': code,
      if (description != null && description.isNotEmpty) 'description': description,
    };
    return id == null
        ? _client.post(ApiEndpoints.settingsPaymentMethods, data: data)
        : _client.put(ApiEndpoints.settingsPaymentMethod(id), data: data);
  }

  Future<void> togglePaymentMethod(String id) {
    return _client.post(ApiEndpoints.settingsPaymentMethodToggle(id));
  }

  Future<void> deletePaymentMethod(String id) {
    return _client.delete(ApiEndpoints.settingsPaymentMethod(id));
  }

  Future<List<CustomNotificationChannelModel>> fetchNotificationChannels() async {
    final response = await _client.get(ApiEndpoints.settingsNotificationChannels);
    return (response['channels'] as List? ?? [])
        .map((e) => CustomNotificationChannelModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> saveNotificationChannel({
    String? id,
    required String name,
    required String url,
    required String method,
    Map<String, dynamic>? headers,
    required String authType,
    String? authValue,
    String? payloadTemplate,
    required List<String> eventTypes,
    required bool isActive,
  }) {
    final data = {
      'name': name,
      'url': url,
      'method': method,
      if (headers != null) 'headers': headers,
      'auth_type': authType,
      if (authValue != null && authValue.isNotEmpty) 'auth_value': authValue,
      if (payloadTemplate != null) 'payload_template': payloadTemplate,
      'event_types': eventTypes,
      'is_active': isActive,
    };
    return id == null
        ? _client.post(ApiEndpoints.settingsNotificationChannels, data: data)
        : _client.put(ApiEndpoints.settingsNotificationChannel(id), data: data);
  }

  Future<void> deleteNotificationChannel(String id) {
    return _client.delete(ApiEndpoints.settingsNotificationChannel(id));
  }
}
