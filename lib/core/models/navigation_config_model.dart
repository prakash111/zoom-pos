class BottomNavConfig {
  final List<BottomNavItemConfig> items;
  final CenterActionConfig centerAction;
  final Map<String, dynamic>? config;

  BottomNavConfig({
    required this.items,
    required this.centerAction,
    this.config,
  });

  factory BottomNavConfig.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    final itemsList = (rawItems is List ? rawItems : [])
        .map((item) => BottomNavItemConfig.fromJson(
            item is Map<String, dynamic> ? item : Map<String, dynamic>.from(item as Map)))
        .toList();

    final rawCenter = json['center_action'] ?? json['centerAction'];
    final centerAction = CenterActionConfig.fromJson(
        rawCenter is Map<String, dynamic>
            ? rawCenter
            : (rawCenter is Map ? Map<String, dynamic>.from(rawCenter) : {}));

    final rawConfig = json['config'] ?? json['store_navigation_config'];

    return BottomNavConfig(
      items: itemsList,
      centerAction: centerAction,
      config: rawConfig is Map<String, dynamic>
          ? rawConfig
          : (rawConfig is Map ? Map<String, dynamic>.from(rawConfig) : null),
    );
  }

  Map<String, dynamic> toJson() => {
        'items': items.map((e) => e.toJson()).toList(),
        'center_action': centerAction.toJson(),
        if (config != null) 'config': config,
      };
}

class BottomNavItemConfig {
  final String id;
  final String label;
  final String icon;
  final String route;
  final Map<String, dynamic>? arguments;

  BottomNavItemConfig({
    required this.id,
    required this.label,
    required this.icon,
    required this.route,
    this.arguments,
  });

  factory BottomNavItemConfig.fromJson(Map<String, dynamic> json) {
    final rawArgs = json['arguments'];
    return BottomNavItemConfig(
      id: json['id']?.toString() ?? '',
      label: json['label']?.toString() ?? json['title']?.toString() ?? '',
      icon: json['icon']?.toString() ?? 'home',
      route: json['route']?.toString() ?? json['target_route']?.toString() ?? '/home',
      arguments: rawArgs is Map<String, dynamic>
          ? rawArgs
          : (rawArgs is Map ? Map<String, dynamic>.from(rawArgs) : null),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'label': label,
        'icon': icon,
        'route': route,
        if (arguments != null) 'arguments': arguments,
      };
}

class CenterActionConfig {
  final String id;
  final String icon;
  final String targetRoute;
  final String? label;
  final Map<String, dynamic>? arguments;

  CenterActionConfig({
    required this.id,
    required this.icon,
    required this.targetRoute,
    this.label,
    this.arguments,
  });

  factory CenterActionConfig.fromJson(Map<String, dynamic> json) {
    final rawArgs = json['arguments'];
    return CenterActionConfig(
      id: json['id']?.toString() ?? 'primary_action',
      icon: json['icon']?.toString() ?? 'add',
      targetRoute: json['target_route']?.toString() ??
          json['route']?.toString() ??
          json['targetRoute']?.toString() ??
          '/pos',
      label: json['label']?.toString(),
      arguments: rawArgs is Map<String, dynamic>
          ? rawArgs
          : (rawArgs is Map ? Map<String, dynamic>.from(rawArgs) : null),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'icon': icon,
        'target_route': targetRoute,
        if (label != null) 'label': label,
        if (arguments != null) 'arguments': arguments,
      };
}
