/// Model shapes returned by SettingsApiController — one class per section of
/// the web tenant Settings page (Profile, Receipts, Financial, Notifications)
/// plus the Payment Methods manager.
class ProfileSettings {
  ProfileSettings({
    required this.name,
    required this.tradeName,
    required this.taxId,
    required this.email,
    required this.phone,
    required this.website,
    required this.address,
    required this.city,
    required this.state,
    required this.postalCode,
    required this.country,
    required this.primaryColor,
    this.logoUrl,
    this.faviconUrl,
    required this.defaultCommissionRate,
    required this.defaultCommissionType,
  });

  factory ProfileSettings.fromJson(Map<String, dynamic> json) {
    return ProfileSettings(
      name: json['name'] as String? ?? '',
      tradeName: json['trade_name'] as String? ?? '',
      taxId: json['tax_id'] as String? ?? '',
      email: json['email'] as String? ?? '',
      phone: json['phone'] as String? ?? '',
      website: json['website'] as String? ?? '',
      address: json['address'] as String? ?? '',
      city: json['city'] as String? ?? '',
      state: json['state'] as String? ?? '',
      postalCode: json['postal_code'] as String? ?? '',
      country: json['country'] as String? ?? 'US',
      primaryColor: json['primary_color'] as String? ?? '#2563eb',
      logoUrl: json['logo_url'] as String?,
      faviconUrl: json['favicon_url'] as String?,
      defaultCommissionRate: (json['default_commission_rate'] as num?)?.toDouble() ?? 0,
      defaultCommissionType: json['default_commission_type'] as String? ?? 'percentage',
    );
  }

  final String name;
  final String tradeName;
  final String taxId;
  final String email;
  final String phone;
  final String website;
  final String address;
  final String city;
  final String state;
  final String postalCode;
  final String country;
  final String primaryColor;
  final String? logoUrl;
  final String? faviconUrl;
  final double defaultCommissionRate;
  final String defaultCommissionType;
}

class ReceiptSettings {
  ReceiptSettings({
    required this.invoicePrefix,
    required this.quotationPrefix,
    required this.invoiceTerms,
    required this.quoteTerms,
    required this.bankDetails,
  });

  factory ReceiptSettings.fromJson(Map<String, dynamic> json) {
    return ReceiptSettings(
      invoicePrefix: json['invoice_prefix'] as String? ?? '',
      quotationPrefix: json['quotation_prefix'] as String? ?? '',
      invoiceTerms: json['invoice_terms'] as String? ?? '',
      quoteTerms: json['quote_terms'] as String? ?? '',
      bankDetails: json['bank_details'] as String? ?? '',
    );
  }

  final String invoicePrefix;
  final String quotationPrefix;
  final String invoiceTerms;
  final String quoteTerms;
  final String bankDetails;
}

class OtherCurrency {
  OtherCurrency({required this.code, required this.name, required this.symbol, required this.exchangeRate});

  factory OtherCurrency.fromJson(Map<String, dynamic> json) {
    return OtherCurrency(
      code: json['code'] as String? ?? '',
      name: json['name'] as String? ?? '',
      symbol: json['symbol'] as String? ?? '',
      exchangeRate: (json['exchange_rate'] as num?)?.toDouble() ?? 1,
    );
  }

  final String code;
  final String name;
  final String symbol;
  final double exchangeRate;

  Map<String, dynamic> toJson() => {'code': code, 'name': name, 'symbol': symbol, 'exchange_rate': exchangeRate};
}

class FinancialSettings {
  FinancialSettings({
    required this.currency,
    required this.currencySymbol,
    required this.currencyDecimals,
    required this.currencySymbolPosition,
    required this.otherCurrencies,
  });

  factory FinancialSettings.fromJson(Map<String, dynamic> json) {
    return FinancialSettings(
      currency: json['currency'] as String? ?? 'USD',
      currencySymbol: json['currency_symbol'] as String? ?? '\$',
      currencyDecimals: (json['currency_decimals'] as num?)?.toInt() ?? 2,
      currencySymbolPosition: json['currency_symbol_position'] as String? ?? 'prefix',
      otherCurrencies: (json['other_currencies'] as List? ?? [])
          .map((e) => OtherCurrency.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  final String currency;
  final String currencySymbol;
  final int currencyDecimals;
  final String currencySymbolPosition;
  final List<OtherCurrency> otherCurrencies;
}

class SmtpSettings {
  SmtpSettings({
    required this.host,
    required this.port,
    required this.username,
    required this.encryption,
    required this.fromAddress,
    required this.fromName,
    required this.hasPassword,
  });

  factory SmtpSettings.fromJson(Map<String, dynamic> json) {
    return SmtpSettings(
      host: json['host'] as String? ?? '',
      port: (json['port'] as num?)?.toInt() ?? 587,
      username: json['username'] as String? ?? '',
      encryption: json['encryption'] as String? ?? 'tls',
      fromAddress: json['from_address'] as String? ?? '',
      fromName: json['from_name'] as String? ?? '',
      hasPassword: json['has_password'] as bool? ?? false,
    );
  }

  final String host;
  final int port;
  final String username;
  final String encryption;
  final String fromAddress;
  final String fromName;
  final bool hasPassword;
}

class WhatsappSettings {
  WhatsappSettings({
    required this.phonePrefix,
    required this.customNote,
    required this.phoneNumberId,
    required this.hasApiToken,
  });

  factory WhatsappSettings.fromJson(Map<String, dynamic> json) {
    return WhatsappSettings(
      phonePrefix: json['phone_prefix'] as String? ?? '',
      customNote: json['custom_note'] as String? ?? '',
      phoneNumberId: json['phone_number_id'] as String? ?? '',
      hasApiToken: json['has_api_token'] as bool? ?? false,
    );
  }

  final String phonePrefix;
  final String customNote;
  final String phoneNumberId;
  final bool hasApiToken;
}

class NotificationSettings {
  NotificationSettings({required this.smtp, required this.whatsapp});

  factory NotificationSettings.fromJson(Map<String, dynamic> json) {
    return NotificationSettings(
      smtp: SmtpSettings.fromJson(json['smtp'] as Map<String, dynamic>? ?? {}),
      whatsapp: WhatsappSettings.fromJson(json['whatsapp'] as Map<String, dynamic>? ?? {}),
    );
  }

  final SmtpSettings smtp;
  final WhatsappSettings whatsapp;
}

class PaymentMethodModel {
  PaymentMethodModel({
    required this.id,
    required this.name,
    required this.code,
    required this.description,
    required this.isActive,
    required this.orderIndex,
  });

  factory PaymentMethodModel.fromJson(Map<String, dynamic> json) {
    return PaymentMethodModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      code: json['code'] as String? ?? '',
      description: json['description'] as String? ?? '',
      isActive: json['is_active'] as bool? ?? true,
      orderIndex: (json['order_index'] as num?)?.toInt() ?? 0,
    );
  }

  final String id;
  final String name;
  final String code;
  final String description;
  final bool isActive;
  final int orderIndex;

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'code': code,
      'description': description,
      'is_active': isActive,
      'order_index': orderIndex,
    };
  }
}

class TenantSettingsBundle {
  TenantSettingsBundle({
    required this.profile,
    required this.receipts,
    required this.financial,
    required this.notifications,
    required this.paymentMethods,
  });

  final ProfileSettings profile;
  final ReceiptSettings receipts;
  final FinancialSettings financial;
  final NotificationSettings notifications;
  final List<PaymentMethodModel> paymentMethods;
}
