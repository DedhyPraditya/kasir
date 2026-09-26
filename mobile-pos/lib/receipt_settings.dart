import 'dart:convert';

import 'package:blue_thermal_printer/blue_thermal_printer.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

/// Pengaturan struk dari server (menu Printer di web): nama toko, header,
/// footer, sisa kertas, dan potong otomatis. Disimpan di HP agar tetap
/// dipakai saat offline.
class ReceiptSettings {
  static const _cacheKey = 'receipt_settings_cache';
  static const int maxFeedLines = 8;

  final String store;
  final List<String> header;
  final List<String> footer;
  final int feedLines;
  final bool autoCut;

  const ReceiptSettings({
    required this.store,
    required this.header,
    required this.footer,
    required this.feedLines,
    required this.autoCut,
  });

  /// Nilai bawaan, sama dengan bawaan server.
  static const ReceiptSettings defaults = ReceiptSettings(
    store: 'NYEMIL BEBS',
    header: ['Purnama Town House Blok H/1', 'Telp: +62 823-9943-0312'],
    footer: ['Terima Kasih atas Kunjungan Anda!', '~ Nyemil Bebs ~'],
    feedLines: 4,
    autoCut: false,
  );

  factory ReceiptSettings.fromJson(Map<String, dynamic> json) {
    List<String> lines(dynamic value) =>
        (value is List ? value : const []).map((e) => e.toString()).where((e) => e.trim().isNotEmpty).toList();

    final feed = (json['feed'] as num?)?.toInt() ?? defaults.feedLines;
    final store = (json['store'] as String?)?.trim();

    return ReceiptSettings(
      store: (store == null || store.isEmpty) ? defaults.store : store,
      header: lines(json['header']),
      footer: lines(json['footer']),
      feedLines: feed.clamp(0, maxFeedLines),
      autoCut: json['cut'] == true,
    );
  }

  Map<String, dynamic> toJson() => {
        'store': store,
        'header': header,
        'footer': footer,
        'feed': feedLines,
        'cut': autoCut,
      };

  /// Pengaturan terakhir yang tersimpan di HP (atau bawaan).
  static Future<ReceiptSettings> loadCached() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_cacheKey);
      if (raw == null) return defaults;
      return ReceiptSettings.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return defaults;
    }
  }

  /// Ambil pengaturan terbaru dari server; gagal/offline = pakai cache.
  static Future<ReceiptSettings> fetch(String backendUrl, Map<String, String> headers) async {
    try {
      final response = await http
          .get(Uri.parse('$backendUrl/receipt-settings'), headers: headers)
          .timeout(const Duration(seconds: 8));
      if (response.statusCode == 200) {
        final body = jsonDecode(response.body) as Map<String, dynamic>;
        final settings = ReceiptSettings.fromJson(body['data'] as Map<String, dynamic>);
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_cacheKey, jsonEncode(settings.toJson()));
        return settings;
      }
    } catch (_) {
      // Offline atau server lama tanpa endpoint ini: pakai cache.
    }
    return loadCached();
  }

  /// Cetak header toko (nama besar + baris header), diakhiri satu baris kosong.
  Future<void> printHeader(BlueThermalPrinter printer) async {
    await printer.printCustom(store, 3, 1);
    for (final line in header) {
      await printer.printCustom(line, 1, 1);
    }
    await printer.printNewLine();
  }

  /// Cetak footer lalu dorong kertas sesuai pengaturan. Perintah potong hanya
  /// dikirim untuk printer ber-cutter; di printer tanpa cutter perintah itu
  /// mendorong kertas jauh ke posisi pisau (sisa kertas kosong panjang).
  Future<void> printFooter(BlueThermalPrinter printer, {bool reprint = false}) async {
    if (reprint) {
      await printer.printCustom('** CETAK ULANG **', 1, 1);
    }
    for (final line in footer) {
      await printer.printCustom(line, 1, 1);
    }
    for (var i = 0; i < feedLines; i++) {
      await printer.printNewLine();
    }
    if (autoCut) {
      await printer.paperCut();
    }
  }
}
