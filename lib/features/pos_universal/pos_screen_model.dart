import 'package:flutter/material.dart';

/// Parsed representation of the `pos_screen` JSON contract (see backend
/// `App\Services\Sdui\PosScreenBuilder`). Deliberately independent of
/// `ProductModel`/`SduiCatalogLayout`, which are rigid to Retail's fixed
/// field set (no subtitle/badge/on_tap slot) — see the design note in
/// `screens/universal_pos_screen.dart`.
class PosScreenBanner {
  PosScreenBanner({required this.message, this.icon, this.action});

  final String message;
  final String? icon;
  final Map<String, dynamic>? action;

  static PosScreenBanner? fromJson(Map<String, dynamic>? json) {
    if (json == null) return null;
    final message = json['message']?.toString();
    if (message == null || message.isEmpty) return null;

    return PosScreenBanner(
      message: message,
      icon: json['icon']?.toString(),
      action: json['action'] is Map
          ? Map<String, dynamic>.from(json['action'] as Map)
          : null,
    );
  }
}

class PosCatalogBadge {
  PosCatalogBadge(this.text, this.color);

  final String text;
  final Color color;

  static PosCatalogBadge? fromJson(Map<String, dynamic>? json) {
    if (json == null) return null;
    final text = json['text']?.toString();
    if (text == null || text.isEmpty) return null;

    return PosCatalogBadge(text, _parseColor(json['color']?.toString()));
  }

  static Color _parseColor(String? hex) {
    if (hex == null || hex.isEmpty) return Colors.grey;
    var value = hex.replaceFirst('#', '');
    if (value.length == 6) value = 'FF$value';
    return Color(int.tryParse(value, radix: 16) ?? 0xFF9E9E9E);
  }
}

class PosCatalogItem {
  PosCatalogItem({
    required this.id,
    this.categoryId,
    required this.title,
    this.subtitle,
    required this.price,
    this.imageUrl,
    this.stock,
    this.badge,
    required this.onTap,
  });

  final dynamic id;
  final dynamic categoryId;
  final String title;
  final String? subtitle;
  final double price;
  final String? imageUrl;
  final int? stock;
  final PosCatalogBadge? badge;
  final Map<String, dynamic> onTap;

  bool get isOutOfStock => stock != null && stock! <= 0;
  bool get isLowStock => stock != null && stock! > 0 && stock! <= 5;

  static PosCatalogItem fromJson(Map<String, dynamic> json) {
    return PosCatalogItem(
      id: json['id'],
      categoryId: json['category_id'],
      title: json['title']?.toString() ?? '',
      subtitle: json['subtitle']?.toString(),
      price: (json['price'] as num?)?.toDouble() ?? 0,
      imageUrl: json['image_url']?.toString(),
      stock: (json['stock'] as num?)?.toInt(),
      badge: json['badge'] is Map
          ? PosCatalogBadge.fromJson(Map<String, dynamic>.from(json['badge'] as Map))
          : null,
      onTap: json['on_tap'] is Map
          ? Map<String, dynamic>.from(json['on_tap'] as Map)
          : const {'type': ''},
    );
  }
}

class PosCategory {
  PosCategory(this.id, this.label);

  final dynamic id;
  final String label;

  static PosCategory fromJson(Map<String, dynamic> json) =>
      PosCategory(json['id'], json['label']?.toString() ?? '');
}

class PosScreenModel {
  PosScreenModel({
    required this.title,
    this.banner,
    required this.searchPlaceholder,
    required this.scannerEnabled,
    required this.categories,
    required this.items,
    required this.checkoutSheetEndpoint,
    required this.cartLabelTemplate,
  });

  final String title;
  final PosScreenBanner? banner;
  final String searchPlaceholder;
  final bool scannerEnabled;
  final List<PosCategory> categories;
  final List<PosCatalogItem> items;
  final String checkoutSheetEndpoint;
  final String cartLabelTemplate;

  static PosScreenModel fromJson(Map<String, dynamic> schema) {
    final search = schema['search'] is Map
        ? Map<String, dynamic>.from(schema['search'] as Map)
        : const <String, dynamic>{};
    final catalog = schema['catalog'] is Map
        ? Map<String, dynamic>.from(schema['catalog'] as Map)
        : const <String, dynamic>{};
    final cartBar = schema['cart_bar'] is Map
        ? Map<String, dynamic>.from(schema['cart_bar'] as Map)
        : const <String, dynamic>{};
    final rawItems = catalog['items'] as List<dynamic>? ?? const [];
    final rawCategories = schema['categories'] as List<dynamic>? ?? const [];

    return PosScreenModel(
      title: schema['title']?.toString() ?? 'Point of Sale',
      banner: PosScreenBanner.fromJson(
        schema['banner'] is Map ? Map<String, dynamic>.from(schema['banner'] as Map) : null,
      ),
      searchPlaceholder: search['placeholder']?.toString() ?? 'Search...',
      scannerEnabled: search['scanner_enabled'] != false,
      categories: rawCategories
          .whereType<Map>()
          .map((c) => PosCategory.fromJson(Map<String, dynamic>.from(c)))
          .toList(),
      items: rawItems
          .whereType<Map>()
          .map((i) => PosCatalogItem.fromJson(Map<String, dynamic>.from(i)))
          .toList(),
      checkoutSheetEndpoint: cartBar['checkout_sheet_endpoint']?.toString() ?? '',
      cartLabelTemplate:
          cartBar['label_template']?.toString() ?? 'View Cart · {count} items · {total}',
    );
  }
}
