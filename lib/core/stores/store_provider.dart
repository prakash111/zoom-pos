import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api_client.dart';
import '../services/sync/sync_engine.dart';

class StoreBranch {
  const StoreBranch({
    required this.id,
    required this.name,
    required this.code,
    required this.isPrimary,
  });

  final int id;
  final String name;
  final String code;
  final bool isPrimary;

  factory StoreBranch.fromJson(Map<String, dynamic> json) => StoreBranch(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? '',
        code: json['code']?.toString() ?? '',
        isPrimary: json['is_primary'] == true || json['is_primary'] == 1,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'code': code,
        'is_primary': isPrimary,
      };
}

class StoreProvider extends ChangeNotifier {
  StoreProvider(this._api);

  final ApiClient _api;
  List<StoreBranch> stores = const [];
  StoreBranch? current;
  int storeLimit = 1;
  int storeCount = 0;
  bool canCreate = false;
  bool loading = false;
  String? error;
  String? _loadedCacheKey;

  String get _cacheKey =>
      'zoom_pos.stores.${_api.activeTenantId ?? 'unknown'}.${_api.activeUserId ?? 'unknown'}';

  Future<void> _remember() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
        _cacheKey,
        jsonEncode({
          'stores': stores.map((store) => store.toJson()).toList(),
          'current_store_id': current?.id,
          'store_limit': storeLimit,
          'store_count': storeCount,
          'can_create': canCreate,
        }));
  }

  bool get limitReached => storeLimit >= 0 && storeCount >= storeLimit;

  Future<void> load() async {
    if (_loadedCacheKey != _cacheKey) {
      stores = const [];
      current = null;
      _api.activeStoreId = null;
      _loadedCacheKey = _cacheKey;
    }
    loading = true;
    error = null;
    notifyListeners();
    try {
      final response = await _api.getAbsolute('/api/v1/tenant/stores');
      final parsed = (response['stores'] as List? ?? const [])
          .whereType<Map>()
          .map((raw) => StoreBranch.fromJson(Map<String, dynamic>.from(raw)))
          .toList();
      stores = parsed;
      storeLimit = (response['store_limit'] as num?)?.toInt() ?? 1;
      storeCount = (response['store_count'] as num?)?.toInt() ?? parsed.length;
      canCreate = response['can_create'] == true;
      final currentId = (response['current_store_id'] as num?)?.toInt();
      current = parsed.where((store) => store.id == currentId).firstOrNull;
      current ??= parsed.isNotEmpty ? parsed.first : null;
      _api.activeStoreId = current?.id;
      await _remember();
      if (current != null) {
        unawaited(SyncEngine.instance?.syncNow() ?? Future<void>.value());
      }
    } catch (exception) {
      error = exception.toString();
      final prefs = await SharedPreferences.getInstance();
      final cached = prefs.getString(_cacheKey);
      if (cached != null) {
        try {
          final data = Map<String, dynamic>.from(jsonDecode(cached) as Map);
          stores = (data['stores'] as List? ?? const [])
              .whereType<Map>()
              .map(
                  (raw) => StoreBranch.fromJson(Map<String, dynamic>.from(raw)))
              .toList();
          final id = (data['current_store_id'] as num?)?.toInt();
          current = stores.where((store) => store.id == id).firstOrNull;
          _api.activeStoreId = current?.id;
          storeLimit = (data['store_limit'] as num?)?.toInt() ?? 1;
          storeCount = (data['store_count'] as num?)?.toInt() ?? stores.length;
          canCreate = data['can_create'] == true;
          error = null;
        } catch (_) {}
      }
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> switchTo(StoreBranch store) async {
    _requireSyncedOutbox();
    await _api.postAbsolute('/api/v1/tenant/stores/switch',
        data: {'store_id': store.id});
    current = store;
    _api.activeStoreId = store.id;
    await _remember();
    notifyListeners();
    unawaited(SyncEngine.instance?.syncNow() ?? Future<void>.value());
  }

  Future<void> create({
    required String name,
    required String code,
    String? phone,
    String? address,
    String? taxId,
  }) async {
    _requireSyncedOutbox();
    final response = await _api.postAbsolute('/api/v1/tenant/stores', data: {
      'name': name,
      'code': code,
      'phone': phone,
      'address': address,
      'tax_id': taxId,
    });
    final store = StoreBranch.fromJson(
        Map<String, dynamic>.from(response['store'] as Map));
    _api.activeStoreId = store.id;
    current = store;
    await load();
  }

  void _requireSyncedOutbox() {
    final sync = SyncEngine.instance;
    if (sync != null && (sync.isSyncing || sync.pendingCount > 0)) {
      throw StateError('Sync pending offline changes before switching stores.');
    }
  }
}
