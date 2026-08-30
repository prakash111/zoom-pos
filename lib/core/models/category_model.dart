class CategoryModel {
  CategoryModel({required this.id, required this.name});

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    return CategoryModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
    );
  }

  final String id;
  final String name;
}
