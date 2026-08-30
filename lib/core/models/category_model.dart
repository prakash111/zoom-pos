class CategoryModel {
  CategoryModel({
    required this.id,
    required this.name,
    this.color,
    this.description = '',
    this.active = true,
  });

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    return CategoryModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      color: json['color'] as String?,
      description: json['description'] as String? ?? '',
      active: json['active'] as bool? ?? true,
    );
  }

  final String id;
  final String name;
  final String? color;
  final String description;
  final bool active;
}
