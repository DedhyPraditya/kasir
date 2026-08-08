import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Penyimpanan lokal untuk mode offline: sesi login, cache menu, antrian
/// order yang belum tersinkron, dan QRIS statis default (fallback offline).
class OfflineStore {
  static const _keyApiToken = 'api_token';
  static const _keyKasirName = 'kasir_name';
  static const _keyProductsCache = 'products_cache';
  static const _keyToppingsCache = 'toppings_cache';
  static const _keyPendingOrders = 'pending_orders';
  static const _keyDefaultQrisImage = 'default_qris_image_path';

  // --- Sesi login ---

  static Future<void> saveSession(String apiToken, String kasirName) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyApiToken, apiToken);
    await prefs.setString(_keyKasirName, kasirName);
  }

  static Future<({String apiToken, String kasirName})?> getSession() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_keyApiToken);
    final name = prefs.getString(_keyKasirName);
    if (token == null || token.isEmpty) return null;
    return (apiToken: token, kasirName: name ?? 'Kasir');
  }

  static Future<void> clearSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyApiToken);
    await prefs.remove(_keyKasirName);
  }

  // --- Cache menu (produk & topping) ---

  static Future<void> cacheProducts(List<dynamic> products) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyProductsCache, jsonEncode(products));
  }

  static Future<List<dynamic>> getCachedProducts() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_keyProductsCache);
    if (raw == null) return [];
    return jsonDecode(raw) as List<dynamic>;
  }

  static Future<void> cacheToppings(List<dynamic> toppings) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyToppingsCache, jsonEncode(toppings));
  }

  static Future<List<dynamic>> getCachedToppings() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_keyToppingsCache);
    if (raw == null) return [];
    return jsonDecode(raw) as List<dynamic>;
  }

  // --- Antrian order yang belum tersinkron ---

  static Future<List<Map<String, dynamic>>> getPendingOrders() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_keyPendingOrders);
    if (raw == null) return [];
    final list = jsonDecode(raw) as List<dynamic>;
    return list.cast<Map<String, dynamic>>();
  }

  static Future<void> addPendingOrder(Map<String, dynamic> order) async {
    final orders = await getPendingOrders();
    orders.add(order);
    await _savePendingOrders(orders);
  }

  static Future<void> removePendingOrder(String invoiceNumber) async {
    final orders = await getPendingOrders();
    orders.removeWhere((o) => o['invoice_number'] == invoiceNumber);
    await _savePendingOrders(orders);
  }

  static Future<void> _savePendingOrders(
    List<Map<String, dynamic>> orders,
  ) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyPendingOrders, jsonEncode(orders));
  }

  // --- QRIS statis default (dipakai saat offline) ---

  static Future<void> saveDefaultQrisImagePath(String path) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyDefaultQrisImage, path);
  }

  static Future<String?> getDefaultQrisImagePath() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_keyDefaultQrisImage);
  }

  static Future<void> clearDefaultQrisImage() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyDefaultQrisImage);
  }
}
