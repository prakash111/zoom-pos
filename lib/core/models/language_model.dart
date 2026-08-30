/// A store language option, as returned by GET /languages (LanguageApiController).
class LanguageModel {
  LanguageModel({required this.code, required this.name, required this.nativeName, required this.flag});

  factory LanguageModel.fromJson(Map<String, dynamic> json) {
    return LanguageModel(
      code: json['code'] as String? ?? '',
      name: json['name'] as String? ?? '',
      nativeName: json['native_name'] as String? ?? '',
      flag: json['flag'] as String? ?? '',
    );
  }

  final String code;
  final String name;
  final String nativeName;
  final String flag;
}
