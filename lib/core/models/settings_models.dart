/// Model shapes returned by SettingsApiController — tenant-owned Profile,
/// Receipts and Financial sections plus the Payment Methods manager.
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
    this.subdomain = '',
    this.storeWebsite = '',
    this.storefrontUrl = '',
    this.customDomain = '',
    this.cnameTarget = 'cname.saas.zoomnearby.com',
    this.sslStatus = 'not_configured',
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
      defaultCommissionRate:
          (json['default_commission_rate'] as num?)?.toDouble() ?? 0,
      defaultCommissionType:
          json['default_commission_type'] as String? ?? 'percentage',
      timezone: json['timezone'] as String? ?? '',
      resolvedTimezone: json['resolved_timezone'] as String? ?? 'UTC',
      defaultTimezoneForCountry:
          json['default_timezone_for_country'] as String? ?? 'UTC',
      subdomain: json['subdomain'] as String? ?? '',
      storeWebsite: json['store_website'] as String? ?? '',
      storefrontUrl: json['storefront_url'] as String? ?? '',
      customDomain: json['custom_domain'] as String? ?? '',
      cnameTarget: json['cname_target'] as String? ?? 'cname.saas.zoomnearby.com',
      sslStatus: json['ssl_status'] as String? ?? 'not_configured',
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

  /// Subdomain of the tenant storefront (e.g. 'mystore')
  final String subdomain;

  /// Live storefront URL (e.g. 'https://mystore.saas.zoomnearby.com')
  final String storeWebsite;

  /// Storefront URL alias
  final String storefrontUrl;

  /// Tenant custom domain (e.g. 'store.mybrand.com')
  final String customDomain;

  /// CNAME target host for custom domain setup
  final String cnameTarget;

  /// SSL certificate status ('active' or 'not_configured')
  final String sslStatus;
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
  OtherCurrency(
      {required this.code,
      required this.name,
      required this.symbol,
      required this.exchangeRate});

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

  Map<String, dynamic> toJson() => {
        'code': code,
        'name': name,
        'symbol': symbol,
        'exchange_rate': exchangeRate
      };
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
      currencySymbolPosition:
          json['currency_symbol_position'] as String? ?? 'prefix',
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
      metadata: (json['metadata'] is Map)
          ? Map<String, dynamic>.from(json['metadata'] as Map)
          : null,
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

class TenantSettingsBundle {
  TenantSettingsBundle({
    required this.profile,
    required this.receipts,
    required this.financial,
    required this.paymentMethods,
    required this.nav,
    this.timezones = const [],
  });

  final ProfileSettings profile;
  final ReceiptSettings receipts;
  final FinancialSettings financial;
  final List<PaymentMethodModel> paymentMethods;
  final NavConfig nav;

  /// Full IANA identifier list for the manual-timezone-override picker —
  /// see SettingsApiController::index().
  final List<String> timezones;
}

/// One section's position override — see [NavConfig].
class NavSectionOrder {
  const NavSectionOrder({
    required this.key,
    required this.order,
    this.customTitle,
  });

  factory NavSectionOrder.fromJson(Map<String, dynamic> json) {
    return NavSectionOrder(
        key: json['key']?.toString() ?? '',
        order: _safeNavInt(json['order']) ?? 0,
        customTitle: json['custom_title']?.toString().trim().isNotEmpty == true
            ? json['custom_title'].toString().trim()
            : null);
  }

  final String key;
  final int order;
  final String? customTitle;

  Map<String, dynamic> toJson() => {
        'key': key,
        'order': order,
        if (customTitle?.isNotEmpty == true) 'custom_title': customTitle,
      };
}

/// One nav destination's canonical placement. [parent] / [parentId] names
/// the nearest ancestor one level above it; [level] is 0 (Main Menu), 1
/// (Sub-Menu), or 2 (Sub-Sub-Menu). [children] is populated when reading a
/// recursive tree and is emitted when this object is serialized as a node.
class NavItemConfig {
  const NavItemConfig({
    required this.key,
    this.section,
    this.parent,
    this.level = 0,
    this.order,
    required this.visible,
    this.children = const [],
  });

  factory NavItemConfig.fromJson(Map<String, dynamic> json) {
    return NavItemConfig(
      key: json['key']?.toString() ?? '',
      section: _safeNullableNavString(json['section']),
      parent: _safeNullableNavString(json['parent_id'] ?? json['parent']),
      level: (_safeNavInt(json['level']) ?? 0).clamp(0, 2),
      order: _safeNavInt(json['order']),
      visible: _safeNavBool(json['visible'], fallback: true),
      children: _safeNavList(json['children'])
          .map(_safeNavMap)
          .where((item) => item.isNotEmpty)
          .map(NavItemConfig.fromJson)
          .toList(),
    );
  }

  final String key;
  final String? section;
  final String? parent;
  String? get parentId => parent;
  final int level;
  final int? order;
  final bool visible;
  final List<NavItemConfig> children;

  /// [clearParent] un-nests this item back to its section root — plain
  /// `parent: null` in [copyWith] can't be distinguished from "leave
  /// unchanged" since null is also copyWith's own "keep current" sentinel.
  NavItemConfig copyWith({
    String? section,
    String? parent,
    bool clearParent = false,
    int? level,
    int? order,
    bool? visible,
    List<NavItemConfig>? children,
  }) {
    return NavItemConfig(
      key: key,
      section: section ?? this.section,
      parent: clearParent ? null : (parent ?? this.parent),
      level: level ?? this.level,
      order: order ?? this.order,
      visible: visible ?? this.visible,
      children: children ?? this.children,
    );
  }

  Map<String, dynamic> toJson() => {
        'key': key,
        'section': section,
        'parent': parent,
        'parent_id': parent,
        'level': level.clamp(0, 2),
        'order': order,
        'visible': visible,
        'children': children.map((item) => item.toJson()).toList(),
      };
}

/// This tenant's drawer/rail/bar customization. The flat [items] index keeps
/// older clients compatible; [toJson] also builds the lossless recursive
/// `tree` consumed by the web editor and Laravel normalizer.
class NavConfig {
  const NavConfig({this.sections = const [], this.items = const []});

  factory NavConfig.fromJson(Map<String, dynamic> json) {
    final flatItems = _safeNavList(json['items'])
        .map(_safeNavMap)
        .where((item) => item.isNotEmpty)
        .map(NavItemConfig.fromJson)
        .toList();

    final rawItems =
        flatItems.isNotEmpty ? flatItems : _flattenTree(json['tree']);
    final sanitizedItems = rawItems.map((item) {
      // Consignments is always a root-level sibling of Quotations. Older
      // saved trees may still carry a quotation parent; normalize it here so
      // the Flutter drawer and navigation editor share the same SSOT.
      if (item.key == 'consignments') {
        return item.copyWith(clearParent: true, level: 0, children: const []);
      }
      if (item.section == 'cashier_sales' &&
          (item.key == 'lead_management' ||
              item.key == 'leads' ||
              item.key.startsWith('lead_') ||
              item.key.contains('lead'))) {
        return item.copyWith(section: 'lead_ops');
      }
      return item;
    }).toList();

    final configuredSections = _safeNavList(json['sections']);
    final rawSections = configuredSections.isNotEmpty
        ? configuredSections
        : _safeNavList(json['tree']);

    return NavConfig(
      sections: rawSections
          .map(_safeNavMap)
          .where((section) => section.isNotEmpty)
          .map(NavSectionOrder.fromJson)
          .toList(),
      items: sanitizedItems,
    );
  }

  final List<NavSectionOrder> sections;
  final List<NavItemConfig> items;

  static List<NavItemConfig> _flattenTree(dynamic source) {
    final flattened = <NavItemConfig>[];

    void visit(dynamic rawNodes, String section, String? parent, int level) {
      for (final raw in _safeNavList(rawNodes)) {
        final json = _safeNavMap(raw);
        if (json.isEmpty) continue;
        final node = NavItemConfig.fromJson({
          ...json,
          'section': section,
          'parent_id': parent,
          'level': level.clamp(0, 2),
          'children': const [],
        });
        flattened.add(node);
        visit(json['children'], section, node.key, level + 1);
      }
    }

    for (final rawSection in _safeNavList(source)) {
      final section = _safeNavMap(rawSection);
      final key = section['key']?.toString() ?? '';
      if (key.isNotEmpty) visit(section['items'], key, null, 0);
    }

    return flattened;
  }

  List<Map<String, dynamic>> _treeJson() {
    if (items.any((item) => item.section == null || item.section!.isEmpty))
      return const [];

    final itemByKey = {for (final item in items) item.key: item};
    final sourceIndex = {
      for (var index = 0; index < items.length; index++) items[index].key: index
    };
    final safeParent = <String, String?>{};

    for (final item in items) {
      final rawParent = item.parent;
      final candidate =
          rawParent == null || rawParent.isEmpty ? null : rawParent;
      final seen = <String>{item.key};
      var depth = 0;
      var valid = true;
      var cursor = candidate;

      while (cursor != null) {
        final ancestor = itemByKey[cursor];
        if (ancestor == null ||
            ancestor.section != item.section ||
            !seen.add(cursor) ||
            ++depth > 2) {
          valid = false;
          break;
        }
        cursor = ancestor.parent;
      }
      safeParent[item.key] = valid ? candidate : null;
    }

    final childrenByBucket = <String, List<NavItemConfig>>{};
    for (final item in items) {
      final bucket = '${item.section}|${safeParent[item.key] ?? ''}';
      (childrenByBucket[bucket] ??= []).add(item);
    }

    int compareItems(NavItemConfig first, NavItemConfig second) {
      final byOrder =
          (first.order ?? 1 << 30).compareTo(second.order ?? 1 << 30);
      return byOrder != 0
          ? byOrder
          : (sourceIndex[first.key] ?? 0)
              .compareTo(sourceIndex[second.key] ?? 0);
    }

    List<Map<String, dynamic>> buildNodes(
        String section, String? parent, int level) {
      final rows = [...?childrenByBucket['$section|${parent ?? ''}']]
        ..sort(compareItems);
      return [
        for (var order = 0; order < rows.length; order++)
          {
            'key': rows[order].key,
            'section': section,
            'parent': parent,
            'parent_id': parent,
            'level': level.clamp(0, 2),
            'order': order,
            'visible': rows[order].visible,
            'children': buildNodes(section, rows[order].key, level + 1),
          },
      ];
    }

    final orderedSections = [...sections]
      ..sort((a, b) => a.order.compareTo(b.order));
    final knownSections = {for (final section in orderedSections) section.key};
    for (final item in items) {
      if (knownSections.add(item.section!)) {
        orderedSections.add(
            NavSectionOrder(key: item.section!, order: orderedSections.length));
      }
    }

    return [
      for (var order = 0; order < orderedSections.length; order++)
        {
          'key': orderedSections[order].key,
          'order': order,
          if (orderedSections[order].customTitle?.isNotEmpty == true)
            'custom_title': orderedSections[order].customTitle,
          'items': buildNodes(orderedSections[order].key, null, 0),
        },
    ];
  }

  Map<String, dynamic> toJson() {
    final tree = _treeJson();
    return {
      'sections': sections.map((section) => section.toJson()).toList(),
      'items': items.map((item) {
        final json = item.toJson();
        json.remove('children');
        return json;
      }).toList(),
      if (tree.isNotEmpty) 'tree': tree,
    };
  }
}

Map<String, dynamic> _safeNavMap(dynamic value) {
  if (value is Map) {
    final result = <String, dynamic>{};
    value.forEach((k, v) {
      result[k.toString()] = v;
    });
    return result;
  }
  return <String, dynamic>{};
}

List<dynamic> _safeNavList(dynamic value) {
  if (value is Map) return List<dynamic>.from(value.values);
  if (value is Iterable) return List<dynamic>.from(value);
  return const <dynamic>[];
}

String? _safeNullableNavString(dynamic value) {
  if (value == null) return null;
  final result = value.toString();
  return result.isEmpty ? null : result;
}

int? _safeNavInt(dynamic value) {
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '');
}

bool _safeNavBool(dynamic value, {required bool fallback}) {
  if (value is bool) return value;
  final normalized = value?.toString().trim().toLowerCase();
  if (normalized == 'true' || normalized == '1') return true;
  if (normalized == 'false' || normalized == '0') return false;
  return fallback;
}
