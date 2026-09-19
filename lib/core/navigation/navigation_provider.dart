import 'package:flutter/foundation.dart';

/// Navigation item model hardened against Flutter Web minified JS cast exceptions.
class NavItem {
  const NavItem({
    required this.key,
    required this.title,
    this.icon,
    this.route,
    this.parentId,
    this.level = 0,
    this.indent = 0,
    this.order,
    this.visible = true,
    this.children = const [],
    this.component,
    this.permission,
    this.actionType,
    this.type,
    this.targetEndpoint,
  });

  final String key;
  final String title;
  final dynamic icon;
  final String? route;
  final String? parentId;
  final int level;
  final int indent;
  final int? order;
  final bool visible;
  final List<NavItem> children;
  final String? component;
  final String? permission;
  final String? actionType;
  final String? type;
  final String? targetEndpoint;

  factory NavItem.fromJson(Map<String, dynamic> json) {
    final safe = NavigationProvider.safeMap(json);
    final List<NavItem> parsedChildren = [];

    for (final child in NavigationProvider.safeList(safe['children'])) {
      final childMap = NavigationProvider.safeMap(child);
      if (childMap.isNotEmpty) {
        parsedChildren.add(NavItem.fromJson(childMap));
      }
    }

    final rawKey = safe['key']?.toString() ?? safe['id']?.toString() ?? '';
    final rawTitle = safe['title']?.toString() ??
        safe['label']?.toString() ??
        safe['custom_title']?.toString() ??
        '';

    return NavItem(
      key: rawKey.isNotEmpty ? rawKey : 'item_${safe['order'] ?? 0}',
      title: rawTitle.isNotEmpty ? rawTitle : rawKey,
      icon: safe['icon'],
      route: safe['route']?.toString() ?? safe['target_endpoint']?.toString(),
      parentId: safe['parent_id']?.toString() ?? safe['parent']?.toString(),
      level: NavigationProvider.safeInt(safe['level']).clamp(0, 2),
      indent: NavigationProvider.safeInt(safe['indent']).clamp(0, 2),
      order: NavigationProvider.safeNullableInt(safe['order']),
      visible: NavigationProvider.safeBool(safe['visible'], fallback: true),
      children: parsedChildren,
      component: safe['component']?.toString(),
      permission: safe['permission']?.toString(),
      actionType: safe['action_type']?.toString(),
      type: safe['type']?.toString(),
      targetEndpoint: safe['target_endpoint']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'key': key,
        'title': title,
        if (icon != null) 'icon': icon,
        if (route != null) 'route': route,
        if (parentId != null) 'parent_id': parentId,
        'level': level,
        'indent': indent,
        if (order != null) 'order': order,
        'visible': visible,
        'children': children.map((c) => c.toJson()).toList(),
        if (component != null) 'component': component,
        if (permission != null) 'permission': permission,
        if (actionType != null) 'action_type': actionType,
        if (type != null) 'type': type,
        if (targetEndpoint != null) 'target_endpoint': targetEndpoint,
      };
}

/// Navigation section model grouping items under a section header.
class NavSection {
  const NavSection({
    required this.key,
    required this.title,
    this.color,
    this.items = const [],
  });

  final String key;
  final String title;
  final String? color;
  final List<NavItem> items;

  factory NavSection.fromJson(Map<String, dynamic> json) {
    final safe = NavigationProvider.safeMap(json);
    final rawItems = safe['items'] ?? safe['children'];
    final List<NavItem> parsedItems = [];

    for (final item in NavigationProvider.safeList(rawItems)) {
      final itemMap = NavigationProvider.safeMap(item);
      if (itemMap.isNotEmpty) {
        parsedItems.add(NavItem.fromJson(itemMap));
      }
    }

    return NavSection(
      key: safe['key']?.toString() ?? '',
      title: safe['title']?.toString() ?? safe['label']?.toString() ?? '',
      color: safe['color']?.toString(),
      items: parsedItems,
    );
  }

  Map<String, dynamic> toJson() => {
        'key': key,
        'title': title,
        if (color != null) 'color': color,
        'items': items.map((i) => i.toJson()).toList(),
      };
}

/// Provider managing navigation state, hardened against minified JS type errors
/// (`TypeError: Instance of 'Minified:E<dynamic>': type 'Minified:E<dynamic>' is not a subtype of type 'Map<String, dynamic>'`).
class NavigationProvider extends ChangeNotifier {
  List<NavSection> _sections = [];
  bool _isLoading = false;
  String? _error;

  List<NavSection> get sections => _sections;
  bool get isLoading => _isLoading;
  String? get error => _error;

  /// Defensive map cloning. Guaranteed to never throw cast exceptions on minified web maps.
  static Map<String, dynamic> safeMap(dynamic value) {
    if (value is! Map) return <String, dynamic>{};
    final result = <String, dynamic>{};
    value.forEach((k, v) {
      result[k.toString()] = v;
    });
    return result;
  }

  /// Converts JSON arrays and legacy object-shaped numeric collections into
  /// a new list. PHP arrays with sparse numeric keys are encoded as objects,
  /// so accepting [Map.values] keeps old cached payloads readable.
  static List<dynamic> safeList(dynamic value) {
    if (value is Map) return List<dynamic>.from(value.values);
    if (value is Iterable) return List<dynamic>.from(value);
    return const <dynamic>[];
  }

  static int safeInt(dynamic value, {int fallback = 0}) {
    return safeNullableInt(value) ?? fallback;
  }

  static int? safeNullableInt(dynamic value) {
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  static bool safeBool(dynamic value, {required bool fallback}) {
    if (value is bool) return value;
    final normalized = value?.toString().trim().toLowerCase();
    if (normalized == 'true' || normalized == '1') return true;
    if (normalized == 'false' || normalized == '0') return false;
    return fallback;
  }

  /// Parses navigation sections from raw JSON collections using [List.from]
  /// and [safeMap] to harden against minified JS dynamic type casts.
  void parseNavigation(dynamic payload) {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final safePayload = safeMap(payload);
      final rawSections = safePayload.containsKey('sections')
          ? safePayload['sections']
          : (safePayload.containsKey('menu_structure')
              ? safePayload['menu_structure']
              : (safePayload.containsKey('navigation')
                  ? safePayload['navigation']
                  : (safePayload.containsKey('nav_v2')
                      ? safePayload['nav_v2']
                      : payload)));

      final rawSectionList = safeList(rawSections);
      if (rawSectionList.isEmpty) {
        _sections = const [];
        _isLoading = false;
        notifyListeners();
        return;
      }

      final List<NavSection> parsedSections = [];
      for (final sectionRaw in rawSectionList) {
        final secMap = safeMap(sectionRaw);
        if (secMap.isEmpty) continue;

        final rawItems = secMap['items'] ?? secMap['children'];
        final List<NavItem> parsedItems = [];
        for (final itemRaw in safeList(rawItems)) {
          final itemMap = safeMap(itemRaw);
          if (itemMap.isNotEmpty) {
            parsedItems.add(NavItem.fromJson(itemMap));
          }
        }

        parsedSections.add(NavSection(
          key: secMap['key']?.toString() ?? '',
          title:
              secMap['title']?.toString() ?? secMap['label']?.toString() ?? '',
          color: secMap['color']?.toString(),
          items: parsedItems,
        ));
      }

      _sections = parsedSections;
      _isLoading = false;
      notifyListeners();
    } catch (error, stackTrace) {
      _error = error.toString();
      _isLoading = false;
      debugPrint('Bootstrap parsing failed for nav_v2 schema: $error');
      debugPrintStack(stackTrace: stackTrace);
      notifyListeners();
    }
  }
}
