/// One action within a permission module (e.g. `view` on `products`).
class PermissionAction {
  PermissionAction({required this.slug, required this.label});

  factory PermissionAction.fromJson(Map<String, dynamic> json) {
    return PermissionAction(slug: json['slug'] as String? ?? '', label: json['label'] as String? ?? '');
  }

  final String slug;
  final String label;
}

/// One permission module (e.g. `products`) and the actions it exposes.
class PermissionModule {
  PermissionModule({required this.slug, required this.label, required this.actions});

  factory PermissionModule.fromJson(Map<String, dynamic> json) {
    return PermissionModule(
      slug: json['slug'] as String? ?? '',
      label: json['label'] as String? ?? '',
      actions: (json['actions'] as List? ?? []).map((e) => PermissionAction.fromJson(e as Map<String, dynamic>)).toList(),
    );
  }

  final String slug;
  final String label;
  final List<PermissionAction> actions;
}

/// The full GET /users/{id}/permissions response: the module/action
/// vocabulary, the current allowed grid, and role-default presets — all
/// computed server-side from PermissionChecker so mobile never guesses at
/// the permission matrix shape.
class PermissionGridBundle {
  PermissionGridBundle({
    required this.userName,
    required this.userRole,
    required this.isPrivileged,
    required this.modules,
    required this.grid,
    required this.roleDefaults,
  });

  factory PermissionGridBundle.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>? ?? {};
    final rawGrid = json['grid'] as Map<String, dynamic>? ?? {};
    final grid = <String, Map<String, bool>>{};
    rawGrid.forEach((module, actions) {
      grid[module] = (actions as Map<String, dynamic>).map((k, v) => MapEntry(k, v as bool));
    });

    final rawDefaults = json['role_defaults'] as Map<String, dynamic>? ?? {};
    final roleDefaults = <String, Map<String, List<String>>>{};
    rawDefaults.forEach((role, moduleMap) {
      roleDefaults[role] = (moduleMap as Map<String, dynamic>).map(
        (module, actions) => MapEntry(module, (actions as List).cast<String>()),
      );
    });

    return PermissionGridBundle(
      userName: user['name'] as String? ?? '',
      userRole: user['role'] as String? ?? '',
      isPrivileged: user['is_privileged'] as bool? ?? false,
      modules: (json['modules'] as List? ?? []).map((e) => PermissionModule.fromJson(e as Map<String, dynamic>)).toList(),
      grid: grid,
      roleDefaults: roleDefaults,
    );
  }

  final String userName;
  final String userRole;
  final bool isPrivileged;
  final List<PermissionModule> modules;
  final Map<String, Map<String, bool>> grid;
  final Map<String, Map<String, List<String>>> roleDefaults;
}
