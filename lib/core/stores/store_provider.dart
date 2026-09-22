import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../config/bootstrap_cache.dart';
import '../services/sync/sync_engine.dart';

int? _asInt(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value');
bool _asBool(dynamic value) =>
    value == true || value == 1 || value == '1' || value == 'true';

class StoreBranch {
  const StoreBranch(
      {required this.id,
      required this.name,
      required this.code,
      required this.isPrimary,
      this.address = '',
      this.phone = '',
      this.taxId = '',
      this.isActive = true,
      this.isCurrent = false});

  final int id;
  final String name, code, address, phone, taxId;
  final bool isPrimary, isActive, isCurrent;

  factory StoreBranch.fromJson(Map<String, dynamic> json) {
    final id = _asInt(json['id']);
    if (id == null)
      throw const FormatException('Store response has an invalid ID.');
    return StoreBranch(
        id: id,
        name: json['name']?.toString() ?? '',
        code: json['code']?.toString() ?? '',
        address: json['address']?.toString() ?? '',
        phone: json['phone']?.toString() ?? '',
        taxId: json['tax_id']?.toString() ?? '',
        isPrimary: _asBool(json['is_primary']),
        isCurrent: _asBool(json['is_current']),
        isActive: json['is_active'] == null || _asBool(json['is_active']));
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'code': code,
        'address': address,
        'phone': phone,
        'tax_id': taxId,
        'is_primary': isPrimary,
        'is_active': isActive,
        'is_current': isCurrent
      };
}

class StoreProvider extends ChangeNotifier {
  StoreProvider(this._api);
  final ApiClient _api;
  List<StoreBranch> stores = const [];
  StoreBranch? current;
  int storeLimit = 1, storeCount = 0;
  bool canCreate = false, canManage = false, loading = false, fromCache = false;
  String? error, _loadedCacheKey, defaultDialCode;
  Future<void>? _loading;

  String get _cacheKey =>
      'zoom_pos.stores.${_api.activeTenantId ?? 'unknown'}.${_api.activeUserId ?? 'unknown'}';
  bool get limitReached => storeLimit >= 0 && storeCount >= storeLimit;
  bool get canCreateMore => canCreate && !limitReached && !fromCache;
  List<StoreBranch> get activeStores =>
      stores.where((s) => s.isActive).toList();

  void _apply(Map<String, dynamic> response) {
    final data = response['data'];
    final rows = data is List
        ? data
        : data is Map
            ? data['stores']
            : response['stores'];
    if (rows is! List)
      throw const FormatException('The server returned an invalid store list.');
    final meta = response['meta'] is Map ? response['meta'] as Map : const {};
    stores = rows
        .whereType<Map>()
        .map((r) => StoreBranch.fromJson(Map<String, dynamic>.from(r)))
        .toList();
    storeLimit =
        _asInt(meta['max_allowed_stores'] ?? response['store_limit']) ?? 1;
    storeCount = _asInt(meta['total_stores'] ?? response['store_count']) ??
        stores.length;
    canCreate = _asBool(meta['can_create'] ??
        response['can_create'] ??
        meta['can_create_more']);
    canManage = _asBool(meta['can_manage'] ?? response['can_manage']);
    defaultDialCode = meta['default_dial_code']?.toString();
    final id = _asInt(meta['current_store_id'] ?? response['current_store_id']);
    current = stores.where((s) => s.id == id && s.isActive).firstOrNull ??
        stores.where((s) => s.isCurrent && s.isActive).firstOrNull ??
        activeStores.firstOrNull;
    _api.activeStoreId = current?.id;
  }

  Future<void> _remember() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
        _cacheKey,
        jsonEncode({
          'stores': stores.map((s) => s.toJson()).toList(),
          'current_store_id': current?.id,
          'store_limit': storeLimit,
          'store_count': storeCount,
          'can_create': canCreate,
          'can_manage': canManage,
        }));
  }

  Future<void> load() =>
      _loading ??= _load().whenComplete(() => _loading = null);

  Future<void> _load() async {
    final previousId = _api.activeStoreId;
    if (_loadedCacheKey != _cacheKey) {
      stores = const [];
      current = null;
      canCreate = false;
      canManage = false;
      _api.activeStoreId = null;
      _loadedCacheKey = _cacheKey;
    }
    loading = true;
    error = null;
    fromCache = false;
    notifyListeners();
    try {
      _apply(await _api.getAbsolute('/api/v1/tenant/stores',
          query: {'include_inactive': 1}));
      await _remember();
      if (current != null && previousId != current?.id) {
        unawaited(SyncEngine.instance?.syncNow() ?? Future<void>.value());
      }
    } catch (exception) {
      error = exception.toString();
      final denied = exception is ApiException &&
          (exception.statusCode == 401 || exception.statusCode == 403);
      if (denied) {
        stores = const [];
        current = null;
        canCreate = false;
        canManage = false;
        _api.activeStoreId = null;
      } else {
        final prefs = await SharedPreferences.getInstance();
        final cached = prefs.getString(_cacheKey);
        if (cached != null) {
          try {
            _apply(Map<String, dynamic>.from(jsonDecode(cached) as Map));
            fromCache = true;
            error = null;
          } catch (_) {}
        }
      }
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> _readyToSwitch() async {
    final sync = SyncEngine.instance;
    if (sync == null) return;
    if (sync.isSyncing) {
      final done = Completer<void>();
      void check() {
        if (!sync.isSyncing && !done.isCompleted) done.complete();
      }

      sync.addListener(check);
      try {
        check();
        await done.future.timeout(const Duration(seconds: 30));
      } finally {
        sync.removeListener(check);
      }
    }
    if (sync.pendingCount > 0)
      throw StateError('Sync pending offline changes before switching stores.');
  }

  bool _transitioning = false;

  Future<void> _transition(Future<void> Function() change) async {
    if (_transitioning)
      throw StateError('A store change is already in progress.');
    _transitioning = true;
    final sync = SyncEngine.instance;
    if (sync != null) sync.storeTransitionInProgress = true;
    try {
      await _readyToSwitch();
      await change();
    } finally {
      _transitioning = false;
      if (sync != null) {
        sync.storeTransitionInProgress = false;
        unawaited(sync.syncNow());
      }
    }
  }

  Future<void> _refreshWorkspace() async {
    await _remember();
    await BootstrapCache.instance.hydrate(forceRefresh: true, client: _api);
    notifyListeners();
    unawaited(SyncEngine.instance?.syncNow() ?? Future<void>.value());
  }

  Future<void> switchTo(StoreBranch store) => _transition(() async {
        await _api.postAbsolute('/api/v1/tenant/stores/${store.id}/switch');
        current = store;
        _api.activeStoreId = store.id;
        await _refreshWorkspace();
      });

  Future<void> create(
          {required String name,
          String code = '',
          String? phone,
          String? address,
          String? taxId}) =>
      _transition(() async {
        try {
          final response =
              await _api.postAbsolute('/api/v1/tenant/stores', data: {
            'name': name,
            'code': code.isEmpty ? null : code,
            'phone': phone,
            'address': address,
            'tax_id': taxId,
          });
          final raw = response['current_store'] ??
              response['store'] ??
              response['data'];
          final store =
              StoreBranch.fromJson(Map<String, dynamic>.from(raw as Map));
          current = store;
          _api.activeStoreId = store.id;
          stores = [...stores, store];
          storeCount++;
          await _refreshWorkspace();
          await load();
        } on ApiException catch (e) {
          if (e.responseData?['upgrade_required'] == true) {
            throw ApiException(
                e.responseData?['message']?.toString() ??
                    'Upgrade your plan to add another store.',
                statusCode: e.statusCode);
          }
          rethrow;
        }
      });

  Future<void> update(StoreBranch store,
      {required String name,
      String? code,
      String? phone,
      String? address,
      String? taxId,
      bool? isActive}) async {
    Future<void> save() async {
      await _api.putAbsolute('/api/v1/tenant/stores/${store.id}', data: {
        'name': name,
        'code': code,
        'phone': phone,
        'address': address,
        'tax_id': taxId,
        if (isActive != null) 'is_active': isActive,
      });
      if (isActive == false && current?.id == store.id)
        _api.activeStoreId = null;
      await load();
      await _refreshWorkspace();
    }

    if (isActive == false && current?.id == store.id) {
      await _transition(save);
    } else {
      await save();
    }
  }
}
