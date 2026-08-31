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
  });

  factory ProductModel.fromJson(Map<String, dynamic> json) {
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
      imageUrl: json['image_url'] as String?,
      taxRate: (json['tax_rate'] as num?)?.toDouble() ?? 0,
      active: json['active'] as bool? ?? true,
      isLowStock: json['is_low_stock'] as bool? ?? false,
    );
  }

  final String id;
  final String name;
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

  bool get isOutOfStock => currentStock <= 0;

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'barcode': barcode,
      'sku': sku,
      'sale_price': salePrice,
      'cost_price': costPrice,
      'current_stock': currentStock,
      'minimum_stock': minimumStock,
      'unit': unit,
      'category_id': categoryId,
      'category_name': categoryName,
      'brand_name': brandName,
      'image_url': imageUrl,
      'tax_rate': taxRate,
      'active': active,
      'is_low_stock': isLowStock,
    };
  }
}
