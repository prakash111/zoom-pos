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
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      role: json['role']?.toString() ?? '',
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

  /// Snake-case shape [fromJson] round-trips — used to cache the signed-in
  /// user for offline session restore (see SessionCache).
  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
        'role': role,
        'company_id': companyId,
        'permissions': permissions,
      };

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

  bool can(String permission) {
    final lowerRole = role.toLowerCase().trim();
    if (lowerRole == 'owner' ||
        lowerRole == 'admin' ||
        lowerRole == 'administrator' ||
        lowerRole == 'superadmin') {
      return true;
    }
    if (permissions['*'] == true) return true;
    if (permissions[permission] == true) return true;

    final dotIndex = permission.indexOf('.');
    if (dotIndex != -1) {
      final module = permission.substring(0, dotIndex);
      if (permissions[module] == true) return true;
    } else {
      if (permissions['$permission.view'] == true) return true;
    }

    if (permission == 'quotes' ||
        permission == 'quotes.view' ||
        permission == 'quotations' ||
        permission == 'quotations.view') {
      return permissions['quotes'] == true ||
          permissions['quotes.view'] == true ||
          permissions['quotations'] == true ||
          permissions['quotations.view'] == true;
    }
    if (permission == 'leads' ||
        permission == 'leads.view' ||
        permission == 'lead_management' ||
        permission == 'lead_management.view') {
      return permissions['leads'] == true ||
          permissions['leads.view'] == true ||
          permissions['lead_management'] == true ||
          permissions['lead_management.view'] == true;
    }
    if (permission == 'consignments' || permission == 'consignments.view') {
      return permissions['consignments'] == true ||
          permissions['consignments.view'] == true;
    }
    if (permission == 'customers' || permission == 'customers.view') {
      return permissions['customers'] == true ||
          permissions['customers.view'] == true;
    }
    if (permission == 'pos' || permission == 'pos.view') {
      return permissions['pos'] == true || permissions['pos.view'] == true;
    }
    if (permission == 'sales' || permission == 'sales.view') {
      return permissions['sales'] == true || permissions['sales.view'] == true;
    }

    return false;
  }
}
