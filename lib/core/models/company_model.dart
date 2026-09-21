class CompanyModel {
  CompanyModel({
    required this.id,
    required this.name,
    required this.tradeName,
    required this.currency,
    required this.currencySymbol,
    required this.planName,
    this.country = 'IN',
    this.taxId = '',
    this.address = '',
    this.city = '',
    this.state = '',
    this.postalCode = '',
    this.phone = '',
    this.email = '',
    this.posMode = 'general',
    this.businessType,
    this.planFeatures = const [],
    this.licensedModules = const [],
    this.restaurantModeLocked = false,
    this.requireCustomerVerification = false,
    this.enableOrderNotifications = false,
    this.enableProductReviews = true,
    this.drawerCoverUrl,
    this.logoUrl,
    this.faviconUrl,
    this.slug,
    this.subdomain,
    this.customDomain,
    this.storefrontUrl,
    this.timezone = 'UTC',
  });

  factory CompanyModel.fromJson(Map<String, dynamic> json) {
    final rawPosMode = json['pos_mode']?.toString() ?? 'general';
    final resolvedBusinessType = (json['business_type'] ??
            json['active_mode'] ??
            (rawPosMode.toLowerCase() == 'restaurant' ? 'RESTAURANT' : 'RETAIL'))
        ?.toString();

    final rawFeatures = json['plan_features'] ?? json['features'];
    final List<String> resolvedFeatures;
    if (rawFeatures is List) {
      resolvedFeatures = rawFeatures.map((e) => e.toString()).toList();
    } else if (rawFeatures is Map) {
      resolvedFeatures = rawFeatures.entries
          .where((e) => e.value == true)
          .map((e) => e.key.toString())
          .toList();
    } else {
      resolvedFeatures = const [];
    }

    final rawModules = json['licensed_modules'] ?? json['modules'] ?? json['enabled_modules'];
    final List<String> resolvedModules;
    if (rawModules is List) {
      resolvedModules = rawModules.map((e) => e.toString().toLowerCase().trim()).toList();
    } else {
      resolvedModules = const [];
    }

    final reqVer = json['require_customer_verification'] == true ||
        json['require_customer_verification'] == 1 ||
        json['require_customer_verification'] == '1';
    final notifOn = json['enable_order_notifications'] == true ||
        json['enable_order_notifications'] == 1 ||
        json['enable_order_notifications'] == '1';
    final revOn = json['enable_product_reviews'] != false &&
        json['enable_product_reviews'] != 0 &&
        json['enable_product_reviews'] != '0';

    return CompanyModel(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      tradeName: (json['trade_name'] ?? json['name'])?.toString() ?? '',
      currency: json['currency']?.toString() ?? 'USD',
      currencySymbol: json['currency_symbol']?.toString() ?? '\$',
      planName: json['plan_name']?.toString() ?? 'trial',
      country: json['country']?.toString() ?? 'IN',
      taxId: (json['tax_id'] ?? json['tax_number'] ?? json['gstin'])?.toString() ?? '',
      address: json['address']?.toString() ?? '',
      city: json['city']?.toString() ?? '',
      state: json['state']?.toString() ?? '',
      postalCode: json['postal_code']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      posMode: rawPosMode,
      businessType: resolvedBusinessType,
      planFeatures: resolvedFeatures,
      licensedModules: resolvedModules,
      restaurantModeLocked: json['restaurant_mode_locked'] as bool? ?? false,
      requireCustomerVerification: reqVer,
      enableOrderNotifications: notifOn,
      enableProductReviews: revOn,
      drawerCoverUrl: json['drawer_cover_url']?.toString(),
      logoUrl: (json['logo_url'] ?? json['logo'])?.toString(),
      faviconUrl: (json['favicon_url'] ?? json['favicon'])?.toString(),
      slug: json['slug']?.toString(),
      subdomain: (json['subdomain'] ?? json['slug'])?.toString(),
      customDomain: (json['custom_domain'] ?? json['customDomain'])?.toString(),
      storefrontUrl: (json['storefront_url'] ?? json['storefrontUrl'])?.toString(),
      timezone: json['timezone']?.toString().isNotEmpty == true ? json['timezone'].toString() : 'UTC',
    );
  }

  final String id;
  final String name;
  final String tradeName;
  final String currency;
  final String currencySymbol;
  final String planName;
  final String country;
  final String taxId;
  final String address;
  final String city;
  final String state;
  final String postalCode;
  final String phone;
  final String email;
  final String posMode;
  final String? businessType;
  final List<String> planFeatures;
  final List<String> licensedModules;
  final bool restaurantModeLocked;
  final bool requireCustomerVerification;
  final bool enableOrderNotifications;
  final bool enableProductReviews;
  final String? drawerCoverUrl;
  final String? logoUrl;
  final String? faviconUrl;
  final String? slug;
  final String? subdomain;
  final String? customDomain;
  final String? storefrontUrl;
  final String timezone;

  CompanyModel copyWith({
    String? id,
    String? name,
    String? tradeName,
    String? currency,
    String? currencySymbol,
    String? planName,
    String? country,
    String? taxId,
    String? address,
    String? city,
    String? state,
    String? postalCode,
    String? phone,
    String? email,
    String? posMode,
    String? businessType,
    List<String>? planFeatures,
    List<String>? licensedModules,
    bool? restaurantModeLocked,
    bool? requireCustomerVerification,
    bool? enableOrderNotifications,
    bool? enableProductReviews,
    String? drawerCoverUrl,
    bool clearDrawerCoverUrl = false,
    String? logoUrl,
    bool clearLogoUrl = false,
    String? faviconUrl,
    bool clearFaviconUrl = false,
    String? slug,
    bool clearSlug = false,
    String? subdomain,
    bool clearSubdomain = false,
    String? customDomain,
    bool clearCustomDomain = false,
    String? storefrontUrl,
    bool clearStorefrontUrl = false,
    String? timezone,
  }) {
    return CompanyModel(
      id: id ?? this.id,
      name: name ?? this.name,
      tradeName: tradeName ?? this.tradeName,
      currency: currency ?? this.currency,
      currencySymbol: currencySymbol ?? this.currencySymbol,
      planName: planName ?? this.planName,
      country: country ?? this.country,
      taxId: taxId ?? this.taxId,
      address: address ?? this.address,
      city: city ?? this.city,
      state: state ?? this.state,
      postalCode: postalCode ?? this.postalCode,
      phone: phone ?? this.phone,
      email: email ?? this.email,
      posMode: posMode ?? this.posMode,
      businessType: businessType ?? this.businessType,
      planFeatures: planFeatures ?? this.planFeatures,
      licensedModules: licensedModules ?? this.licensedModules,
      restaurantModeLocked: restaurantModeLocked ?? this.restaurantModeLocked,
      requireCustomerVerification: requireCustomerVerification ?? this.requireCustomerVerification,
      enableOrderNotifications: enableOrderNotifications ?? this.enableOrderNotifications,
      enableProductReviews: enableProductReviews ?? this.enableProductReviews,
      drawerCoverUrl: clearDrawerCoverUrl ? null : (drawerCoverUrl ?? this.drawerCoverUrl),
      logoUrl: clearLogoUrl ? null : (logoUrl ?? this.logoUrl),
      faviconUrl: clearFaviconUrl ? null : (faviconUrl ?? this.faviconUrl),
      slug: clearSlug ? null : (slug ?? this.slug),
      subdomain: clearSubdomain ? null : (subdomain ?? this.subdomain),
      customDomain: clearCustomDomain ? null : (customDomain ?? this.customDomain),
      storefrontUrl: clearStorefrontUrl ? null : (storefrontUrl ?? this.storefrontUrl),
      timezone: timezone ?? this.timezone,
    );
  }

  /// Resolve the live storefront URL for this tenant, prioritizing custom domain,
  /// then storefront_url, and falling back to {subdomain}.saas.zoomnearby.com.
  String resolveLiveStoreUrl({String fallbackHost = 'saas.zoomnearby.com'}) {
    if (customDomain != null && customDomain!.trim().isNotEmpty) {
      final cd = customDomain!.trim();
      return cd.startsWith('http') ? cd : 'https://$cd';
    }
    if (storefrontUrl != null && storefrontUrl!.trim().isNotEmpty) {
      final su = storefrontUrl!.trim();
      if (!su.contains('://saas.zoomnearby.com') && !su.endsWith('://saas.zoomnearby.com/')) {
        return su;
      }
    }
    final sub = (subdomain != null && subdomain!.trim().isNotEmpty)
        ? subdomain!.trim()
        : ((slug != null && slug!.trim().isNotEmpty) ? slug!.trim() : 'store');
    return 'https://$sub.$fallbackHost';
  }

  /// Snake-case shape [fromJson] round-trips — used to cache the signed-in
  /// company for offline session restore (see SessionCache).
  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'trade_name': tradeName,
        'currency': currency,
        'currency_symbol': currencySymbol,
        'plan_name': planName,
        'country': country,
        'tax_id': taxId,
        'address': address,
        'city': city,
        'state': state,
        'postal_code': postalCode,
        'phone': phone,
        'email': email,
        'pos_mode': posMode,
        if (businessType != null) 'business_type': businessType,
        'plan_features': planFeatures,
        'licensed_modules': licensedModules,
        'restaurant_mode_locked': restaurantModeLocked,
        'require_customer_verification': requireCustomerVerification,
        'enable_order_notifications': enableOrderNotifications,
        'enable_product_reviews': enableProductReviews,
        'drawer_cover_url': drawerCoverUrl,
        'logo_url': logoUrl,
        'favicon_url': faviconUrl,
        if (slug != null) 'slug': slug,
        if (subdomain != null) 'subdomain': subdomain,
        if (customDomain != null) 'custom_domain': customDomain,
        if (storefrontUrl != null) 'storefront_url': storefrontUrl,
        'timezone': timezone,
      };

  bool isModuleEnabled(String module) {
    if (licensedModules.isEmpty) {
      return true;
    }
    final mod = module.toLowerCase().trim();
    return licensedModules.contains(mod);
  }

  /// True when this tenant should see the restaurant POS (table
  /// management + KOT) instead of the standard retail POS. Mirrors
  /// `Company::isRestaurantMode()` on the backend: the superadmin lock
  /// always wins over the tenant's own registered mode.
  bool get isRestaurantMode => !restaurantModeLocked && (posMode == 'restaurant' || posMode == 'food_restaurant');

  bool get isIndia {
    final c = country.trim().toUpperCase();
    return c == 'IN' ||
        c == 'IND' ||
        c == 'INDIA' ||
        c.contains('INDIA') ||
        currency.toUpperCase() == 'INR' ||
        currencySymbol == '₹';
  }

  String get taxLabel => isIndia ? 'GST' : 'Tax';
  String get taxIdentifierLabel => isIndia ? 'GSTIN' : 'Tax ID';
}
