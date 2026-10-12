/// Dynamic custom / legal page model served by Super Admin CMS.
class AppPageModel {
  const AppPageModel({
    required this.id,
    required this.title,
    required this.slug,
    required this.content,
    this.metaDescription,
    this.url,
    this.updatedAt,
  });

  factory AppPageModel.fromJson(Map<String, dynamic> json) {
    return AppPageModel(
      id: json['id'] is int
          ? json['id'] as int
          : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      title: json['title'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      content: json['content'] as String? ?? '',
      metaDescription: json['meta_description'] as String?,
      url: json['url'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }

  final int id;
  final String title;
  final String slug;
  final String content;
  final String? metaDescription;
  final String? url;
  final String? updatedAt;
}
