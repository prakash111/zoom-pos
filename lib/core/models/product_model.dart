class ProductModel {
  ProductModel({
    required this.id,
    required this.name,
    required this.barcode,
    required this.sku,
    required this.salePrice,
    required this.costPrice,
    required this.currentStock,
    required this.minimumStock,
    required this.unit,
    required this.categoryId,
    required this.categoryName,
    required this.brandName,
    required this.imageUrl,
    required this.taxRate,
    required this.active,
    required this.isLowStock,
    this.description,
    this.durationMinutes,
    this.categoryType,
    this.variants = const [],
    this.modifiers = const [],
    this.spiceLevels = const [],
  });

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    final rawImage = json['image_url'] ??
        json['imageUrl'] ??
        json['image'] ??
        json['image_path'] ??
        json['thumbnail'] ??
        json['photo'];
    final imageUrl = (rawImage != null && rawImage.toString().trim().isNotEmpty)
        ? rawImage.toString().trim()
        : null;

    return ProductModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      barcode: json['barcode'] as String? ?? '',
      sku: json['sku'] as String? ?? '',
      salePrice: (json['sale_price'] as num?)?.toDouble() ?? 0,
      costPrice: (json['cost_price'] as num?)?.toDouble() ?? 0,
      currentStock: (json['current_stock'] as num?)?.toDouble() ?? 0,
      minimumStock: (json['minimum_stock'] as num?)?.toDouble() ?? 0,
      unit: json['unit'] as String? ?? 'pcs',
      categoryId: json['category_id']?.toString(),
      categoryName: json['category_name'] as String? ?? 'General',
      brandName: json['brand_name'] as String? ?? '',
      imageUrl: imageUrl,
      taxRate: (json['tax_rate'] as num?)?.toDouble() ?? 0,
      active: json['active'] as bool? ?? true,
      isLowStock: json['is_low_stock'] as bool? ?? false,
      description: json['description']?.toString(),
      durationMinutes: (json['duration_minutes'] as num?)?.toInt(),
      categoryType: json['category_type'] as String?,
      variants: _parseOptions(json['variants']),
      modifiers: _parseOptions(json['modifiers']),
      spiceLevels: _parseOptions(json['spice_levels']),
    );
  }

  static List<Map<String, dynamic>> _parseOptions(Object? raw) {
    if (raw is! List) return const [];
    return raw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
  }

  final String id;
  final String name;
  final String? description;
  final String barcode;
  final String sku;
  final double salePrice;
  final double costPrice;
  final double currentStock;
  final double minimumStock;
  final String unit;
  final String? categoryId;
  final String categoryName;
  final String brandName;
  final String? imageUrl;
  final double taxRate;
  final bool active;
  final bool isLowStock;
  final int? durationMinutes;
  final String? categoryType;
  final List<Map<String, dynamic>> variants;
  final List<Map<String, dynamic>> modifiers;
  final List<Map<String, dynamic>> spiceLevels;

  bool get isOutOfStock => currentStock <= 0;

  bool get isService {
    if (unit == 'service') return true;
    if (durationMinutes != null && durationMinutes! > 0) return true;
    if (categoryType == 'salon' || categoryType == 'service') return true;
    final cat = categoryName.toLowerCase();
    if (cat.contains('hair') || cat.contains('styling') || cat.contains('facial') || cat.contains('spa') || cat.contains('massage')) {
      return true;
    }
    return false;
  }

  /// Whether this product needs the customization sheet (portion/style,
  /// add-ons, or spice level) before it can be added to a restaurant order,
  /// mirroring Pos.php's addItemDirect() gate on the web app.
  bool get hasCustomizations => variants.isNotEmpty || modifiers.isNotEmpty || spiceLevels.isNotEmpty;

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'description': description,
      'barcode': barcode,
      'sku': sku,
      'sale_price': salePrice,
      'cost_price': costPrice,
      'current_stock': currentStock,
      'minimum_stock': minimumStock,
      'unit': unit,
      'category_id': categoryId,
      'category_name': categoryName,
      'category_type': categoryType,
      'duration_minutes': durationMinutes,
      'brand_name': brandName,
      'image_url': imageUrl,
      'image': imageUrl,
      'tax_rate': taxRate,
      'active': active,
      'is_low_stock': isLowStock,
      'variants': variants,
      'modifiers': modifiers,
      'spice_levels': spiceLevels,
    };
  }
}
