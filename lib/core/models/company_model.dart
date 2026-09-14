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
    this.restaurantModeLocked = false,
    this.drawerCoverUrl,
    this.logoUrl,
    this.faviconUrl,
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
      restaurantModeLocked: json['restaurant_mode_locked'] as bool? ?? false,
      drawerCoverUrl: json['drawer_cover_url']?.toString(),
      logoUrl: (json['logo_url'] ?? json['logo'])?.toString(),
      faviconUrl: (json['favicon_url'] ?? json['favicon'])?.toString(),
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
  final bool restaurantModeLocked;
  final String? drawerCoverUrl;
  final String? logoUrl;
  final String? faviconUrl;
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
    bool? restaurantModeLocked,
    String? drawerCoverUrl,
    bool clearDrawerCoverUrl = false,
    String? logoUrl,
    bool clearLogoUrl = false,
    String? faviconUrl,
    bool clearFaviconUrl = false,
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
      restaurantModeLocked: restaurantModeLocked ?? this.restaurantModeLocked,
      drawerCoverUrl: clearDrawerCoverUrl ? null : (drawerCoverUrl ?? this.drawerCoverUrl),
      logoUrl: clearLogoUrl ? null : (logoUrl ?? this.logoUrl),
      faviconUrl: clearFaviconUrl ? null : (faviconUrl ?? this.faviconUrl),
      timezone: timezone ?? this.timezone,
    );
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
        'restaurant_mode_locked': restaurantModeLocked,
        'drawer_cover_url': drawerCoverUrl,
        'logo_url': logoUrl,
        'favicon_url': faviconUrl,
        'timezone': timezone,
      };

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
