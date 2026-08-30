class UserModel {
  UserModel({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.companyId,
    required this.permissions,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      role: json['role'] as String? ?? '',
      companyId: json['company_id']?.toString() ?? '',
      permissions: _parsePermissions(json['permissions']),
    );
  }

  final String id;
  final String name;
  final String email;
  final String role;
  final String companyId;
  final Map<String, bool> permissions;

  /// The API sends permissions as `{"pos.create": true, ...}` (see
  /// PosSyncApiController::desktopPermissions), except a raw TenantApiKey
  /// with `permissions: ['*']` grants everything — handle both shapes.
  static Map<String, bool> _parsePermissions(Object? raw) {
    if (raw is Map) {
      return raw.map((key, value) => MapEntry(key.toString(), value == true));
    }
    if (raw is List && raw.contains('*')) {
      return const {'*': true};
    }
    return const {};
  }

  bool can(String permission) => permissions['*'] == true || permissions[permission] == true;
}
