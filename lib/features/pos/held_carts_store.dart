import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'pos_provider.dart';

/// Persists held (parked) carts independently of any single [PosProvider]
/// instance, so they survive leaving the POS screen, re-entering it, and app
/// restarts. Held carts are removed only by explicit user action — resuming
/// a cart (which folds it back into the active cart for completion) or
/// deleting it — never as a side effect of starting a new sale.
class HeldCartsStore extends ChangeNotifier {
  static const _storageKey = 'zoom_pos.held_carts';

  List<HeldCart> _carts = [];
  List<HeldCart> get carts => List.unmodifiable(_carts);

  Future<void> load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_storageKey);
      if (raw == null || raw.isEmpty) return;

      final decoded = jsonDecode(raw) as List<dynamic>;
      _carts = decoded.map((item) => HeldCart.fromJson(item as Map<String, dynamic>)).toList();
      notifyListeners();
    } catch (e) {
      debugPrint('HeldCartsStore.load error: $e');
    }
  }

  void add(HeldCart cart) {
    _carts.add(cart);
    notifyListeners();
    _persist();
  }

  void remove(String id) {
    _carts.removeWhere((c) => c.id == id);
    notifyListeners();
    _persist();
  }

  Future<void> _persist() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_storageKey, jsonEncode(_carts.map((c) => c.toJson()).toList()));
    } catch (e) {
      debugPrint('HeldCartsStore.persist error: $e');
    }
  }
}
