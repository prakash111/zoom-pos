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
    this.drawerCoverUrl,
    required this.defaultCommissionRate,
    required this.defaultCommissionType,
    this.timezone = '',
    this.resolvedTimezone = 'UTC',
    this.defaultTimezoneForCountry = 'UTC',
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
      drawerCoverUrl: json['drawer_cover_url'] as String?,
      defaultCommissionRate: (json['default_commission_rate'] as num?)?.toDouble() ?? 0,
      defaultCommissionType: json['default_commission_type'] as String? ?? 'percentage',
      timezone: json['timezone'] as String? ?? '',
      resolvedTimezone: json['resolved_timezone'] as String? ?? 'UTC',
      defaultTimezoneForCountry: json['default_timezone_for_country'] as String? ?? 'UTC',
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
  final String? drawerCoverUrl;
  final double defaultCommissionRate;
  final String defaultCommissionType;

  /// Raw manual override — empty means "none set, following the country
  /// default".
  final String timezone;

  /// What order times/prep timers/KOT logs are actually shown in — always
  /// a valid IANA identifier, never empty. See Company::resolveTimezone().
  final String resolvedTimezone;

  /// What [timezone] would default to if cleared, for the "Use country
  /// default (Xxx/Yyy)" hint next to the manual-override picker.
  final String defaultTimezoneForCountry;
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
    this.metadata,
  });

  factory PaymentMethodModel.fromJson(Map<String, dynamic> json) {
    return PaymentMethodModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      code: json['code'] as String? ?? '',
      description: json['description'] as String? ?? '',
      isActive: json['is_active'] as bool? ?? true,
      orderIndex: (json['order_index'] as num?)?.toInt() ?? 0,
      metadata: (json['metadata'] is Map) ? Map<String, dynamic>.from(json['metadata'] as Map) : null,
    );
  }

  final String id;
  final String name;
  final String code;
  final String description;
  final bool isActive;
  final int orderIndex;

  /// Bank/UPI details (bank_name, account_no, ifsc_code, upi_id,
  /// holder_name) shown at checkout when this method is selected.
  final Map<String, dynamic>? metadata;

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'code': code,
      'description': description,
      'is_active': isActive,
      'order_index': orderIndex,
      if (metadata != null) 'metadata': metadata,
    };
  }
}

class CustomNotificationChannelModel {
  CustomNotificationChannelModel({
    required this.id,
    required this.name,
    required this.url,
    required this.method,
    required this.headers,
    required this.authType,
    required this.hasAuthValue,
    required this.payloadTemplate,
    required this.eventTypes,
    required this.isActive,
    this.authValue,
  });

  factory CustomNotificationChannelModel.fromJson(Map<String, dynamic> json) {
    return CustomNotificationChannelModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      url: json['url'] as String? ?? '',
      method: json['method'] as String? ?? 'POST',
      headers: (json['headers'] is Map) ? Map<String, dynamic>.from(json['headers'] as Map) : null,
      authType: json['auth_type'] as String? ?? 'none',
      hasAuthValue: json['has_auth_value'] as bool? ?? false,
      payloadTemplate: json['payload_template'] as String? ?? '',
      eventTypes: (json['event_types'] as List? ?? []).map((e) => e.toString()).toList(),
      isActive: json['is_active'] as bool? ?? true,
    );
  }

  final String id;
  final String name;
  final String url;
  final String method;
  final Map<String, dynamic>? headers;
  final String authType;

  /// True if a secret is already stored server-side. The server never echoes
  /// the plaintext secret back, so this is the only signal the form has for
  /// showing "Secret is set" instead of a blank field.
  final bool hasAuthValue;
  final String payloadTemplate;
  final List<String> eventTypes;
  final bool isActive;

  /// Write-only: only populated when the user is actively typing a new
  /// secret in the form. Never set from [fromJson].
  final String? authValue;

  /// Request-body shape for POST/PUT. [authValue] is only included when the
  /// caller passed a non-null value, so leaving a secret untouched during an
  /// edit doesn't overwrite it with an empty string server-side.
  Map<String, dynamic> toRequestBody() {
    return {
      'name': name,
      'url': url,
      'method': method,
      if (headers != null) 'headers': headers,
      'auth_type': authType,
      if (authValue != null) 'auth_value': authValue,
      'payload_template': payloadTemplate,
      'event_types': eventTypes,
      'is_active': isActive,
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
    required this.nav,
    this.timezones = const [],
  });

  final ProfileSettings profile;
  final ReceiptSettings receipts;
  final FinancialSettings financial;
  final NotificationSettings notifications;
  final List<PaymentMethodModel> paymentMethods;
  final NavConfig nav;

  /// Full IANA identifier list for the manual-timezone-override picker —
  /// see SettingsApiController::index().
  final List<String> timezones;
}

/// One section's position override — see [NavConfig].
class NavSectionOrder {
  const NavSectionOrder({required this.key, required this.order});

  factory NavSectionOrder.fromJson(Map<String, dynamic> json) {
    return NavSectionOrder(key: json['key'] as String? ?? '', order: (json['order'] as num?)?.toInt() ?? 0);
  }

  final String key;
  final int order;

  Map<String, dynamic> toJson() => {'key': key, 'order': order};
}

/// One nav destination's placement override — see [NavConfig]. [section] and
/// [order] are null when a tenant has hidden/shown an item without ever
/// dragging it, meaning "use whatever this tile's compiled-in default
/// section/position is" (see DashboardScreen's `_sectionsFor`) — distinct
/// from an explicit override that happens to match the default. [parent] is
/// another item's key in the same section — null means this item sits at
/// its section's root level; only one level of nesting is supported (a
/// parent may not itself have a parent).
class NavItemConfig {
  const NavItemConfig({required this.key, this.section, this.parent, this.order, required this.visible});

  factory NavItemConfig.fromJson(Map<String, dynamic> json) {
    return NavItemConfig(
      key: json['key'] as String? ?? '',
      section: json['section'] as String?,
      parent: json['parent'] as String?,
      order: (json['order'] as num?)?.toInt(),
      visible: json['visible'] as bool? ?? true,
    );
  }

  final String key;
  final String? section;
  final String? parent;
  final int? order;
  final bool visible;

  /// [clearParent] un-nests this item back to its section root — plain
  /// `parent: null` in [copyWith] can't be distinguished from "leave
  /// unchanged" since null is also copyWith's own "keep current" sentinel.
  NavItemConfig copyWith({String? section, String? parent, bool clearParent = false, int? order, bool? visible}) {
    return NavItemConfig(
      key: key,
      section: section ?? this.section,
      parent: clearParent ? null : (parent ?? this.parent),
      order: order ?? this.order,
      visible: visible ?? this.visible,
    );
  }

  Map<String, dynamic> toJson() => {'key': key, 'section': section, 'parent': parent, 'order': order, 'visible': visible};
}

/// This tenant's drawer/rail/bar customization — see AppBootstrapController
/// and DashboardScreen's `_sectionsFor`. Section/item keys are opaque
/// `_FeatureTile.key`/`_NavSection.key` values the client itself defines;
/// the server only stores and echoes them back.
class NavConfig {
  const NavConfig({this.sections = const [], this.items = const []});

  factory NavConfig.fromJson(Map<String, dynamic> json) {
    return NavConfig(
      sections: (json['sections'] as List? ?? const [])
          .map((e) => NavSectionOrder.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      items: (json['items'] as List? ?? const [])
          .map((e) => NavItemConfig.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
    );
  }

  final List<NavSectionOrder> sections;
  final List<NavItemConfig> items;

  Map<String, dynamic> toJson() => {
        'sections': sections.map((s) => s.toJson()).toList(),
        'items': items.map((i) => i.toJson()).toList(),
      };
}
