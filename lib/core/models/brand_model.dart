class BrandModel {
  BrandModel({required this.id, required this.name, this.active = true});

  factory BrandModel.fromJson(Map<String, dynamic> json) {
    return BrandModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      active: json['active'] as bool? ?? true,
    );
  }

  final String id;
  final String name;
  final bool active;

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'active': active,
    };
  }
}
