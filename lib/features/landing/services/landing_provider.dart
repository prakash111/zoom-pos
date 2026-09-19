import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/api/api_client.dart';
import '../models/landing_data.dart';

class LandingProvider extends ChangeNotifier {
  LandingProvider({ApiClient? apiClient}) : _apiClient = apiClient {
    _data = LandingData.fallback();
    loadFromDisk();
  }

  static const _cacheKey = 'zoom_pos.landing_cache';
  final ApiClient? _apiClient;

  LandingData _data = LandingData.fallback();
  bool _isLoading = false;
  String? _error;

  LandingData get data => _data;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> loadFromDisk() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final cachedJson = prefs.getString(_cacheKey);
      if (cachedJson != null && cachedJson.isNotEmpty) {
        final map = jsonDecode(cachedJson) as Map<String, dynamic>;
        _data = LandingData.fromJson(map);
        notifyListeners();
      }
    } catch (e) {
      debugPrint('LandingProvider.loadFromDisk error: $e');
    }
  }

  Future<void> refresh([ApiClient? client]) async {
    final effectiveClient = client ?? _apiClient;
    if (effectiveClient == null) return;

    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final res = await effectiveClient.get('/public/landing');
      if (res.isNotEmpty) {
        _data = LandingData.fromJson(res);
        try {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString(_cacheKey, jsonEncode(res));
        } catch (_) {}
      }
    } catch (e) {
      _error = e.toString();
      debugPrint('LandingProvider.refresh error: $e');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
