class UnitModel {
  UnitModel({required this.id, required this.name, this.abbreviation = ''});

  factory UnitModel.fromJson(Map<String, dynamic> json) {
    return UnitModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      abbreviation: json['abbreviation'] as String? ?? '',
    );
  }

  final String id;
  final String name;
  final String abbreviation;

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'abbreviation': abbreviation,
    };
  }
}
