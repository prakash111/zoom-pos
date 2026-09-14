import 'package:flutter/foundation.dart';

/// Represents tenant information delivered from the bootstrap engine.
class TenantSchema {
  const TenantSchema({
    required this.id,
    required this.businessName,
    required this.activeMode,
    this.availableModes = const [],
    this.businessType,
    this.planFeatures = const [],
  });

  final String id;
  final String businessName;
  final String activeMode;
  final List<String> availableModes;
  final String? businessType;
  final List<String> planFeatures;

  factory TenantSchema.fromJson(Map<String, dynamic> json) {
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

    final rawMode = json['active_mode']?.toString() ?? json['pos_mode']?.toString() ?? '';
    final resolvedType = (json['business_type'] ??
            (rawMode.isNotEmpty && rawMode.toLowerCase() != 'general'
                ? rawMode
                : 'RETAIL'))
        .toString();

    return TenantSchema(
      id: json['id']?.toString() ?? '',
      businessName: (json['business_name'] ?? json['store_name'] ?? json['name'])?.toString() ?? '',
      activeMode: rawMode,
      availableModes: (json['available_modes'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
      businessType: resolvedType,
      planFeatures: resolvedFeatures,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'business_name': businessName,
        'active_mode': activeMode,
        'available_modes': availableModes,
        if (businessType != null) 'business_type': businessType,
        'plan_features': planFeatures,
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
  final String
      layoutType; // 'standard_grid', 'table_floor_plan', 'service_booking_list'
  final String? icon;
  final Map<String, dynamic> features;
  final ModuleCartConfig cartConfiguration;

  bool featureEnabled(String key) => features[key] == true;

  factory ModuleSchema.fromJson(Map<String, dynamic> json) {
    return ModuleSchema(
      id: json['id']?.toString() ?? '',
      title: json['title']?.toString() ?? json['id']?.toString() ?? '',
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
    this.parentId,
    this.type,
    this.targetEndpoint,
    this.children = const [],
  });

  final String key;
  final String title;
  final String icon;
  final String? component;
  final String? permission;
  final String? parent;
  final String? parentId;
  final String? type;
  final String? targetEndpoint;
  final List<SduiNavItemSchema> children;

  String? get effectiveParentId => parentId ?? parent;
  bool get isAccordion => type == 'accordion' || children.isNotEmpty;
  String? get route => targetEndpoint;

  factory SduiNavItemSchema.fromJson(Map<String, dynamic> json) {
    final rawParent =
        json['parent_id']?.toString() ?? json['parent']?.toString();
    final rawChildren = json['children'];
    final List<SduiNavItemSchema> parsedChildren = [];
    if (rawChildren is List) {
      for (var index = 0; index < rawChildren.length; index++) {
        final child = rawChildren[index];
        if (child is! Map) {
          debugPrint(
              'Bootstrap navigation: ignoring non-object child at index $index for ${json['key'] ?? '(unknown)'}.');
          continue;
        }
        try {
          parsedChildren.add(SduiNavItemSchema.fromJson(
            Map<String, dynamic>.from(child),
          ));
        } catch (error, stackTrace) {
          debugPrint(
              'Bootstrap navigation: failed to parse child at index $index for ${json['key'] ?? '(unknown)'}: $error');
          debugPrintStack(stackTrace: stackTrace);
        }
      }
    } else if (rawChildren != null) {
      debugPrint(
          'Bootstrap navigation: expected children to be a list for ${json['key'] ?? '(unknown)'}, got ${rawChildren.runtimeType}.');
    }

    final rawKey = json['key']?.toString() ?? json['id']?.toString() ?? '';
    final rawTitle = json['label']?.toString() ?? json['title']?.toString() ?? '';
    final key = rawKey.isNotEmpty
        ? rawKey
        : (json['component']?.toString() ??
            rawTitle.trim().toLowerCase().replaceAll(RegExp(r'[^a-z0-9_]+'), '_'));

    return SduiNavItemSchema(
      key: key,
      title: rawTitle.isNotEmpty ? rawTitle : key,
      icon: json['icon']?.toString() ?? 'widgets',
      component: json['component']?.toString() ?? (key.isNotEmpty ? key : null),
      permission: json['permission']?.toString(),
      parent: rawParent,
      parentId: rawParent,
      type: json['type']?.toString() ??
          (parsedChildren.isNotEmpty ? 'accordion' : 'link'),
      targetEndpoint:
          json['target_endpoint']?.toString() ?? json['endpoint']?.toString() ?? json['route']?.toString(),
      children: parsedChildren,
    );
  }

  Map<String, dynamic> toJson() => {
        'key': key,
        'id': key,
        'title': title,
        'label': title,
        'icon': icon,
        if (component != null) 'component': component,
        if (permission != null) 'permission': permission,
        if (parent != null) 'parent': parent,
        if (parentId != null) 'parent_id': parentId,
        if (type != null) 'type': type,
        if (targetEndpoint != null) 'target_endpoint': targetEndpoint,
        if (children.isNotEmpty)
          'children': children.map((c) => c.toJson()).toList(),
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
    final rawItems = json['items'] ?? json['children'];
    final parsedItems = <SduiNavItemSchema>[];
    if (rawItems is List) {
      for (var index = 0; index < rawItems.length; index++) {
        final item = rawItems[index];
        if (item is! Map) {
          debugPrint(
              'Bootstrap navigation: ignoring non-object item at index $index for section ${json['key'] ?? '(unknown)'}.');
          continue;
        }
        try {
          parsedItems.add(SduiNavItemSchema.fromJson(
            Map<String, dynamic>.from(item),
          ));
        } catch (error, stackTrace) {
          debugPrint(
              'Bootstrap navigation: failed to parse item at index $index for section ${json['key'] ?? '(unknown)'}: $error');
          debugPrintStack(stackTrace: stackTrace);
        }
      }
    } else if (rawItems != null) {
      debugPrint(
          'Bootstrap navigation: expected items to be a list for section ${json['key'] ?? '(unknown)'}, got ${rawItems.runtimeType}.');
    }

    final rawKey = json['key']?.toString() ?? json['id']?.toString() ?? '';
    final rawTitle = json['label']?.toString() ?? json['title']?.toString() ?? '';
    final key = rawKey.isNotEmpty
        ? rawKey
        : rawTitle.trim().toLowerCase().replaceAll(RegExp(r'[^a-z0-9_]+'), '_');

    if (key == 'cashier_sales') {
      parsedItems.removeWhere((item) {
        final k = item.key.toLowerCase();
        final t = item.title.toLowerCase();
        final e = (item.targetEndpoint ?? '').toLowerCase();
        return k == 'lead_management' ||
            k == 'leads' ||
            k.startsWith('lead_') ||
            k.contains('lead') ||
            t.contains('lead') ||
            e.contains('lead');
      });
    }

    return SduiNavSectionSchema(
      key: key,
      title: rawTitle.isNotEmpty ? rawTitle : key,
      color: json['color']?.toString() ?? json['header_color']?.toString(),
      items: parsedItems,
    );
  }

  Map<String, dynamic> toJson() => {
        'key': key,
        'id': key,
        'title': title,
        'label': title,
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
      split: (json['split'] as num?)?.toDouble() ??
          (json['rate'] as num?)?.toDouble() ??
          0.5,
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

    final rawStatusMap =
        json['status_labels'] as Map<String, dynamic>? ?? const {};
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
