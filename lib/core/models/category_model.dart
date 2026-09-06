class CategoryModel {
  CategoryModel({
    required this.id,
    required this.name,
    this.type,
    this.color,
    this.description = '',
    this.active = true,
  });

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    return CategoryModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      type: json['type'] as String?,
      color: json['color'] as String?,
      description: json['description'] as String? ?? '',
      active: json['active'] as bool? ?? true,
    );
  }

  final String id;
  final String name;
  final String? type;
  final String? color;
  final String description;
  final bool active;

  bool get isSalon => type == 'salon' || type == 'service';

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'type': type,
      'color': color,
      'description': description,
      'active': active,
    };
  }
}
