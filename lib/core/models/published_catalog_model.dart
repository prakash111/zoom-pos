/// A published online catalog link, as returned by CatalogApiController.
class PublishedCatalogModel {
  PublishedCatalogModel({
    required this.id,
    required this.title,
    required this.productCount,
    required this.expiresAt,
    required this.isExpired,
    required this.url,
  });

  factory PublishedCatalogModel.fromJson(Map<String, dynamic> json) {
    return PublishedCatalogModel(
      id: json['id'].toString(),
      title: json['title'] as String? ?? '',
      productCount: (json['product_count'] as num?)?.toInt() ?? 0,
      expiresAt: DateTime.tryParse(json['expires_at'] as String? ?? ''),
      isExpired: json['is_expired'] as bool? ?? false,
      url: json['url'] as String? ?? '',
    );
  }

  final String id;
  final String title;
  final int productCount;
  final DateTime? expiresAt;
  final bool isExpired;
  final String url;
}

class CatalogProductOption {
  CatalogProductOption({required this.id, required this.name, required this.salePrice});

  factory CatalogProductOption.fromJson(Map<String, dynamic> json) {
    return CatalogProductOption(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      salePrice: (json['sale_price'] as num?)?.toDouble() ?? 0,
    );
  }

  final String id;
  final String name;
  final double salePrice;
}
