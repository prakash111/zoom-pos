/// Represents tenant information delivered from the bootstrap engine.
class TenantSchema {
  const TenantSchema({
    required this.id,
    required this.businessName,
    required this.activeMode,
    this.availableModes = const ['retail'],
  });

  final String id;
  final String businessName;
  final String activeMode;
  final List<String> availableModes;

  factory TenantSchema.fromJson(Map<String, dynamic> json) {
    return TenantSchema(
      id: json['id']?.toString() ?? '',
      businessName: json['business_name']?.toString() ?? '',
      activeMode: json['active_mode']?.toString() ?? 'retail',
      availableModes: (json['available_modes'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const ['retail'],
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'business_name': businessName,
        'active_mode': activeMode,
        'available_modes': availableModes,
      };
}

/// Module cart configuration delivered from the backend schema.
class ModuleCartConfig {
  const ModuleCartConfig({
    this.showCustomerSelector = true,
    this.allowSplitPayment = true,
    this.taxDisplay = 'country_default',
    this.allowDiscounts = true,
    this.allowHeldCarts = true,
    this.allowNotes = true,
  });

  final bool showCustomerSelector;
  final bool allowSplitPayment;
  final String taxDisplay;
  final bool allowDiscounts;
  final bool allowHeldCarts;
  final bool allowNotes;

  factory ModuleCartConfig.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const ModuleCartConfig();
    return ModuleCartConfig(
      showCustomerSelector: json['show_customer_selector'] as bool? ?? true,
      allowSplitPayment: json['allow_split_payment'] as bool? ?? true,
      taxDisplay: json['tax_display']?.toString() ?? 'country_default',
      allowDiscounts: json['allow_discounts'] as bool? ?? true,
      allowHeldCarts: json['allow_held_carts'] as bool? ?? true,
      allowNotes: json['allow_notes'] as bool? ?? true,
    );
  }

  Map<String, dynamic> toJson() => {
        'show_customer_selector': showCustomerSelector,
        'allow_split_payment': allowSplitPayment,
        'tax_display': taxDisplay,
        'allow_discounts': allowDiscounts,
        'allow_held_carts': allowHeldCarts,
        'allow_notes': allowNotes,
      };
}

/// Dynamic business module schema (e.g. retail, restaurant, pharmacy, service_booking).
class ModuleSchema {
  const ModuleSchema({
    required this.id,
    required this.title,
    this.description = '',
    required this.layoutType,
    this.icon,
    this.features = const {},
    this.cartConfiguration = const ModuleCartConfig(),
  });

  final String id;
  final String title;
  final String description;
  final String layoutType; // 'standard_grid', 'table_floor_plan', 'service_booking_list'
  final String? icon;
  final Map<String, dynamic> features;
  final ModuleCartConfig cartConfiguration;

  bool get hasTables => features['has_tables'] == true;
  bool get hasKot => features['has_kot'] == true;
  bool get hasBarcodeScanner => features['has_barcode_scanner'] == true;
  bool get hasDueReminders => features['has_due_reminders'] == true;
  bool get prepTimer => features['prep_timer'] == true;
  bool get orderAlerts => features['order_alerts'] == true;
  bool get batchTracking => features['batch_tracking'] == true;
  bool get expiryTracking => features['expiry_tracking'] == true;
  bool get prescriptionRequired => features['prescription_required'] == true;
  bool get appointmentScheduling => features['appointment_scheduling'] == true;
  bool get staffAssignment => features['staff_assignment'] == true;

  factory ModuleSchema.fromJson(Map<String, dynamic> json) {
    return ModuleSchema(
      id: json['id']?.toString() ?? 'retail',
      title: json['title']?.toString() ?? 'Retail POS',
      description: json['description']?.toString() ?? '',
      layoutType: json['layout_type']?.toString() ?? 'standard_grid',
      icon: json['icon']?.toString(),
      features: (json['features'] as Map<String, dynamic>?) ?? const {},
      cartConfiguration: ModuleCartConfig.fromJson(
        json['cart_configuration'] as Map<String, dynamic>?,
      ),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'title': title,
        'description': description,
        'layout_type': layoutType,
        if (icon != null) 'icon': icon,
        'features': features,
        'cart_configuration': cartConfiguration.toJson(),
      };
}

/// Navigation item schema from the server.
class SduiNavItemSchema {
  const SduiNavItemSchema({
    required this.key,
    required this.title,
    required this.icon,
    this.component,
    this.permission,
    this.parent,
  });

  final String key;
  final String title;
  final String icon;
  final String? component;
  final String? permission;
  final String? parent;

  factory SduiNavItemSchema.fromJson(Map<String, dynamic> json) {
    return SduiNavItemSchema(
      key: json['key']?.toString() ?? '',
      title: json['label']?.toString() ?? json['title']?.toString() ?? '',
      icon: json['icon']?.toString() ?? 'widgets',
      component: json['component']?.toString() ?? json['key']?.toString(),
      permission: json['permission']?.toString(),
      parent: json['parent']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'key': key,
        'title': title,
        'icon': icon,
        if (component != null) 'component': component,
        if (permission != null) 'permission': permission,
        if (parent != null) 'parent': parent,
      };
}

/// Navigation section schema from the server.
class SduiNavSectionSchema {
  const SduiNavSectionSchema({
    required this.key,
    required this.title,
    this.color,
    this.items = const [],
  });

  final String key;
  final String title;
  final String? color;
  final List<SduiNavItemSchema> items;

  factory SduiNavSectionSchema.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'] as List<dynamic>? ?? const [];
    return SduiNavSectionSchema(
      key: json['key']?.toString() ?? '',
      title: json['label']?.toString() ?? json['title']?.toString() ?? '',
      color: json['color']?.toString() ?? json['header_color']?.toString(),
      items: rawItems
          .whereType<Map<String, dynamic>>()
          .map(SduiNavItemSchema.fromJson)
          .toList(),
    );
  }

  Map<String, dynamic> toJson() => {
        'key': key,
        'title': title,
        if (color != null) 'color': color,
        'items': items.map((i) => i.toJson()).toList(),
      };
}

/// Dynamic payment method configuration from the server.
class SduiPaymentMethodSchema {
  const SduiPaymentMethodSchema({
    required this.id,
    required this.code,
    required this.name,
    required this.icon,
    required this.color,
    this.isCredit = false,
    this.requiresCustomer = false,
    this.metadata = const {},
  });

  final String id;
  final String code;
  final String name;
  final String icon;
  final String color;
  final bool isCredit;
  final bool requiresCustomer;
  final Map<String, dynamic> metadata;

  factory SduiPaymentMethodSchema.fromJson(Map<String, dynamic> json) {
    return SduiPaymentMethodSchema(
      id: json['id']?.toString() ?? '',
      code: json['code']?.toString() ?? json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      icon: json['icon']?.toString() ?? 'payments',
      color: json['color']?.toString() ?? '#15803d',
      isCredit: json['is_credit'] == true,
      requiresCustomer: json['requires_customer'] == true,
      metadata: (json['metadata'] as Map<String, dynamic>?) ?? const {},
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'code': code,
        'name': name,
        'icon': icon,
        'color': color,
        'is_credit': isCredit,
        'requires_customer': requiresCustomer,
        'metadata': metadata,
      };
}

/// Dynamic status label definition from the server.
class SduiStatusSchema {
  const SduiStatusSchema({
    required this.label,
    required this.color,
    required this.icon,
    this.badgeStyle = 'subtle',
  });

  final String label;
  final String color;
  final String icon;
  final String badgeStyle;

  factory SduiStatusSchema.fromJson(Map<String, dynamic> json) {
    return SduiStatusSchema(
      label: json['label']?.toString() ?? '',
      color: json['color']?.toString() ?? '#6b7280',
      icon: json['icon']?.toString() ?? 'info',
      badgeStyle: json['badge_style']?.toString() ?? 'subtle',
    );
  }

  Map<String, dynamic> toJson() => {
        'label': label,
        'color': color,
        'icon': icon,
        'badge_style': badgeStyle,
      };
}

/// Sub-component of tax rules (e.g. CGST / SGST).
class SduiTaxSubComponent {
  const SduiTaxSubComponent({
    required this.key,
    required this.label,
    required this.split,
  });

  final String key;
  final String label;
  final double split;

  String get code => key;

  factory SduiTaxSubComponent.fromJson(Map<String, dynamic> json) {
    return SduiTaxSubComponent(
      key: json['key']?.toString() ?? json['code']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      split: (json['split'] as num?)?.toDouble() ?? (json['rate'] as num?)?.toDouble() ?? 0.5,
    );
  }

  Map<String, dynamic> toJson() => {
        'key': key,
        'label': label,
        'split': split,
      };
}

/// Tax configuration from the server.
class SduiTaxConfigSchema {
  const SduiTaxConfigSchema({
    this.taxId = '',
    this.taxLabel = 'Tax',
    this.displayMode = 'country_default',
    this.isIndia = false,
    this.subComponents = const [],
  });

  final String taxId;
  final String taxLabel;
  final String displayMode;
  final bool isIndia;
  final List<SduiTaxSubComponent> subComponents;

  factory SduiTaxConfigSchema.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const SduiTaxConfigSchema();
    final rawSubs = json['sub_components'] as List<dynamic>? ?? const [];
    return SduiTaxConfigSchema(
      taxId: json['tax_id']?.toString() ?? '',
      taxLabel: json['tax_label']?.toString() ?? 'Tax',
      displayMode: json['display_mode']?.toString() ?? 'country_default',
      isIndia: json['is_india'] == true,
      subComponents: rawSubs
          .whereType<Map<String, dynamic>>()
          .map(SduiTaxSubComponent.fromJson)
          .toList(),
    );
  }

  Map<String, dynamic> toJson() => {
        'tax_id': taxId,
        'tax_label': taxLabel,
        'display_mode': displayMode,
        'is_india': isIndia,
        'sub_components': subComponents.map((s) => s.toJson()).toList(),
      };
}

/// Action pill definition.
class SduiActionPillSchema {
  const SduiActionPillSchema({
    required this.key,
    required this.label,
    required this.icon,
    this.enabled = true,
  });

  final String key;
  final String label;
  final String icon;
  final bool enabled;

  factory SduiActionPillSchema.fromJson(Map<String, dynamic> json) {
    return SduiActionPillSchema(
      key: json['key']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      icon: json['icon']?.toString() ?? 'touch_app',
      enabled: json['enabled'] != false,
    );
  }

  Map<String, dynamic> toJson() => {
        'key': key,
        'label': label,
        'icon': icon,
        'enabled': enabled,
      };
}

/// UI schema bundle containing payment methods, status labels, tax configurations, and action pills.
class SduiUiSchema {
  const SduiUiSchema({
    this.paymentMethods = const [],
    this.statusLabels = const {},
    this.taxConfiguration = const SduiTaxConfigSchema(),
    this.actionPills = const [],
  });

  final List<SduiPaymentMethodSchema> paymentMethods;
  final Map<String, Map<String, SduiStatusSchema>> statusLabels;
  final SduiTaxConfigSchema taxConfiguration;
  final List<SduiActionPillSchema> actionPills;

  SduiTaxConfigSchema get tax => taxConfiguration;

  SduiStatusSchema? statusFor(String domain, String statusKey) {
    return statusLabels[domain]?[statusKey];
  }

  factory SduiUiSchema.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const SduiUiSchema();

    final rawMethods = json['payment_methods'] as List<dynamic>? ?? const [];
    final methods = rawMethods
        .whereType<Map<String, dynamic>>()
        .map(SduiPaymentMethodSchema.fromJson)
        .toList();

    final rawStatusMap = json['status_labels'] as Map<String, dynamic>? ?? const {};
    final statusLabels = <String, Map<String, SduiStatusSchema>>{};
    rawStatusMap.forEach((domain, statuses) {
      if (statuses is Map<String, dynamic>) {
        final domainMap = <String, SduiStatusSchema>{};
        statuses.forEach((key, val) {
          if (val is Map<String, dynamic>) {
            domainMap[key] = SduiStatusSchema.fromJson(val);
          }
        });
        statusLabels[domain] = domainMap;
      }
    });

    final rawPills = json['action_pills'] as List<dynamic>? ?? const [];
    final actionPills = rawPills
        .whereType<Map<String, dynamic>>()
        .map(SduiActionPillSchema.fromJson)
        .toList();

    return SduiUiSchema(
      paymentMethods: methods,
      statusLabels: statusLabels,
      taxConfiguration: SduiTaxConfigSchema.fromJson(
        (json['tax'] ?? json['tax_configuration']) as Map<String, dynamic>?,
      ),
      actionPills: actionPills,
    );
  }

  Map<String, dynamic> toJson() => {
        'payment_methods': paymentMethods.map((m) => m.toJson()).toList(),
        'status_labels': statusLabels.map(
          (k, v) => MapEntry(k, v.map((sk, sv) => MapEntry(sk, sv.toJson()))),
        ),
        'tax_configuration': taxConfiguration.toJson(),
        'action_pills': actionPills.map((p) => p.toJson()).toList(),
      };
}
