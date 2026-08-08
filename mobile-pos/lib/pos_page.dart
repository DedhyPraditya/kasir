import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:blue_thermal_printer/blue_thermal_printer.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'login_page.dart';
import 'history_page.dart';
import 'offline_store.dart';

import 'package:permission_handler/permission_handler.dart';

enum SnackBarType { info, success, warning, error }

const String backendUrl = 'https://kasir.madignet.site/api';

class PosHomePage extends StatefulWidget {
  final String apiToken;
  final String kasirName;

  const PosHomePage({
    super.key,
    required this.apiToken,
    required this.kasirName,
  });

  @override
  State<PosHomePage> createState() => _PosHomePageState();
}

class Product {
  final String id;
  final String name;
  final String description;
  final double price;
  final String? imageUrl;
  final List<Variant> variants;
  final bool allowTopping;

  const Product({
    required this.id,
    required this.name,
    required this.description,
    required this.price,
    this.imageUrl,
    required this.variants,
    this.allowTopping = true,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    final price = json['base_price'] ?? 0;
    return Product(
      id: json['id'] as String,
      name: json['name'] as String,
      description: json['description'] as String? ?? '',
      price: (price is int) ? price.toDouble() : (price as num).toDouble(),
      imageUrl: json['image_url'] as String?,
      allowTopping: json['allow_topping'] as bool? ?? true,
      variants:
          (json['variants'] as List<dynamic>?)
              ?.map(
                (variant) => Variant.fromJson(variant as Map<String, dynamic>),
              )
              .toList() ??
          [],
    );
  }
}

class Variant {
  final String id;
  final String name;
  final double price;

  const Variant({required this.id, required this.name, required this.price});

  factory Variant.fromJson(Map<String, dynamic> json) {
    final price = json['price'] ?? 0;
    return Variant(
      id: json['id'] as String,
      name: json['name'] as String,
      price: (price is int) ? price.toDouble() : (price as num).toDouble(),
    );
  }
}

class Topping {
  final String id;
  final String name;
  final double price;

  const Topping({required this.id, required this.name, required this.price});

  factory Topping.fromJson(Map<String, dynamic> json) {
    final price = json['price'] ?? 0;
    return Topping(
      id: json['id'] as String,
      name: json['name'] as String,
      price: (price is int) ? price.toDouble() : (price as num).toDouble(),
    );
  }
}

class CartItem {
  final String id;
  final Product product;
  final Variant? variant;
  final List<Topping> toppings;
  int quantity;

  CartItem({
    required this.id,
    required this.product,
    this.variant,
    this.toppings = const [],
    this.quantity = 1,
  });

  double get unitPrice {
    final productPrice = variant?.price ?? product.price;
    return productPrice +
        toppings.fold(0, (sum, topping) => sum + topping.price);
  }

  double get subtotal => unitPrice * quantity;

  Map<String, dynamic> toJson() => {
    'product_id': id,
    'variant_id': variant?.id,
    'product_name': product.name,
    'variant_name': variant?.name,
    'quantity': quantity,
    'price': unitPrice,
    'subtotal': subtotal,
    'toppings': toppings
        .map(
          (topping) => {
            'topping_id': topping.id,
            'topping_name': topping.name,
            'price': topping.price,
          },
        )
        .toList(),
  };
}

class _PosHomePageState extends State<PosHomePage> {
  final BlueThermalPrinter _printer = BlueThermalPrinter.instance;
  final List<BluetoothDevice> _devices = [];
  final List<CartItem> _cart = [];
  final List<Product> _products = [];
  final List<Topping> _toppings = [];

  /// Format angka menjadi format Rupiah: Rp. 1.000.000
  String _formatRp(num value) {
    final parts = value.toStringAsFixed(0).split('');
    final buffer = StringBuffer();
    final start = parts.length % 3;
    for (int i = 0; i < parts.length; i++) {
      if (i != 0 && (i - start) % 3 == 0) buffer.write('.');
      buffer.write(parts[i]);
    }
    return 'Rp. ${buffer.toString()}';
  }

  BluetoothDevice? _selectedDevice;
  bool _connected = false;
  bool _syncing = false;
  bool _loadingProducts = false;
  String _status = 'Menyiapkan printer...';
  String _customerName = '';
  String _paymentMethod = 'cash';
  String _amountPaid = '';

  bool _isOnline = true;
  int _pendingOrderCount = 0;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;

  double get _subtotal => _cart.fold(0, (value, item) => value + item.subtotal);

  static const String _currentAppVersion = '1.1.3';

  @override
  void initState() {
    super.initState();
    _refreshDevices();
    _fetchProducts();
    _fetchToppings();
    _checkAppUpdate();
    _initConnectivity();
    _refreshPendingOrderCount();
  }

  @override
  void dispose() {
    _connectivitySubscription?.cancel();
    super.dispose();
  }

  Future<void> _initConnectivity() async {
    final result = await Connectivity().checkConnectivity();
    _updateOnlineStatus(result);

    _connectivitySubscription = Connectivity()
        .onConnectivityChanged
        .listen(_updateOnlineStatus);
  }

  void _updateOnlineStatus(List<ConnectivityResult> results) {
    final wasOnline = _isOnline;
    final isOnline = results.any((r) => r != ConnectivityResult.none);

    if (!mounted) return;
    setState(() {
      _isOnline = isOnline;
    });

    if (!wasOnline && isOnline) {
      _showMessage('Koneksi kembali online.', type: SnackBarType.success);
      _fetchProducts();
      _fetchToppings();
      _syncPendingOrders();
    } else if (wasOnline && !isOnline) {
      _showMessage(
        'Koneksi terputus. Mode offline aktif.',
        type: SnackBarType.warning,
      );
    }
  }

  Future<void> _refreshPendingOrderCount() async {
    final pending = await OfflineStore.getPendingOrders();
    if (!mounted) return;
    setState(() {
      _pendingOrderCount = pending.length;
    });
  }

  Future<void> _checkAppUpdate() async {
    try {
      final response = await http.get(Uri.parse('$backendUrl/app-version'));
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        final latestVersion = data['latest_version'] ?? '1.0.0';
        final downloadUrl = data['download_url'] ?? 'https://kasir.madignet.site/download-apk';
        final changelog = data['changelog'] ?? 'Peningkatan performa dan perbaikan bug.';

        if (_isVersionNewer(_currentAppVersion, latestVersion)) {
          if (!mounted) return;
          _showUpdateDialog(latestVersion: latestVersion, downloadUrl: downloadUrl, changelog: changelog);
        }
      }
    } catch (_) {}
  }

  bool _isVersionNewer(String current, String latest) {
    try {
      final currentParts = current.split('.').map((e) => int.tryParse(e) ?? 0).toList();
      final latestParts = latest.split('.').map((e) => int.tryParse(e) ?? 0).toList();

      for (int i = 0; i < latestParts.length; i++) {
        final c = i < currentParts.length ? currentParts[i] : 0;
        final l = latestParts[i];
        if (l > c) return true;
        if (l < c) return false;
      }
    } catch (_) {}
    return false;
  }

  void _showUpdateDialog({
    required String latestVersion,
    required String downloadUrl,
    required String changelog,
  }) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Row(
            children: const [
              Icon(Icons.system_update, color: Colors.green),
              SizedBox(width: 8),
              Text('Update Tersedia'),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Versi Baru: v$latestVersion (Versi Anda: v$_currentAppVersion)',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
              const SizedBox(height: 8),
              Text(changelog, style: const TextStyle(fontSize: 12, color: Colors.black87)),
              const SizedBox(height: 12),
              const Text('Tekan "Update Sekarang" untuk mengunduh dan menginstal pembaruan.',
                  style: TextStyle(fontSize: 11, color: Colors.grey)),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Nanti Saja'),
            ),
            ElevatedButton.icon(
              icon: const Icon(Icons.download),
              label: const Text('Update Sekarang'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.green,
                foregroundColor: Colors.white,
              ),
              onPressed: () async {
                Navigator.of(context).pop();
                final uri = Uri.parse(downloadUrl);
                if (await canLaunchUrl(uri)) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                } else {
                  _showMessage('Gagal membuka link download update.');
                }
              },
            ),
          ],
        );
      },
    );
  }

  Map<String, String> get _apiHeaders => {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Api-Token': widget.apiToken,
  };

  Future<void> _fetchProducts() async {
    setState(() {
      _loadingProducts = true;
    });

    try {
      final response = await http
          .get(Uri.parse('$backendUrl/products'), headers: _apiHeaders)
          .timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final jsonBody = jsonDecode(response.body) as Map<String, dynamic>;
        final data = jsonBody['data'] as List<dynamic>;
        final products = data
            .map((item) => Product.fromJson(item as Map<String, dynamic>))
            .toList();

        await OfflineStore.cacheProducts(data);

        if (!mounted) return;
        setState(() {
          _products
            ..clear()
            ..addAll(products);
        });
      } else {
        _showMessage(
          'Gagal memuat produk: ${response.statusCode} ${response.body}',
        );
        await _loadCachedProducts();
      }
    } catch (error) {
      await _loadCachedProducts();
    }

    if (!mounted) return;
    setState(() {
      _loadingProducts = false;
    });
  }

  Future<void> _loadCachedProducts() async {
    final cached = await OfflineStore.getCachedProducts();
    if (cached.isEmpty) {
      _showMessage(
        'Tidak ada koneksi & belum ada menu tersimpan. Sambungkan internet minimal sekali.',
        type: SnackBarType.error,
      );
      return;
    }

    final products = cached
        .map((item) => Product.fromJson(item as Map<String, dynamic>))
        .toList();

    if (!mounted) return;
    setState(() {
      _products
        ..clear()
        ..addAll(products);
    });
    _showMessage(
      'Mode offline: menampilkan menu tersimpan terakhir.',
      type: SnackBarType.warning,
    );
  }

  Future<void> _fetchToppings() async {
    try {
      final response = await http
          .get(Uri.parse('$backendUrl/toppings'), headers: _apiHeaders)
          .timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final jsonBody = jsonDecode(response.body) as Map<String, dynamic>;
        final data = jsonBody['data'] as List<dynamic>;
        final toppings = data
            .map((item) => Topping.fromJson(item as Map<String, dynamic>))
            .toList();

        await OfflineStore.cacheToppings(data);

        if (!mounted) return;
        setState(() {
          _toppings
            ..clear()
            ..addAll(toppings);
        });
      } else {
        await _loadCachedToppings();
      }
    } catch (error) {
      await _loadCachedToppings();
    }
  }

  Future<void> _loadCachedToppings() async {
    final cached = await OfflineStore.getCachedToppings();
    if (cached.isEmpty) return;

    final toppings = cached
        .map((item) => Topping.fromJson(item as Map<String, dynamic>))
        .toList();

    if (!mounted) return;
    setState(() {
      _toppings
        ..clear()
        ..addAll(toppings);
    });
  }

  Future<void> _refreshDevices() async {
    setState(() {
      _status = 'Meminta izin dan memuat daftar printer...';
    });

    await _requestPermissions();

    try {
      final bool? connected = await _printer.isConnected;
      final List<BluetoothDevice> devices = await _printer.getBondedDevices();

      if (!mounted) return;

      setState(() {
        _devices
          ..clear()
          ..addAll(devices);
        _selectedDevice = _devices.isNotEmpty ? _devices.first : null;
        _connected = connected == true;
        _status = _devices.isEmpty
            ? 'Tidak ada printer terpasang. Silakan pair printer di pengaturan Bluetooth.'
            : 'Pilih printer lalu tekan Connect.';
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _status = 'Gagal memuat printer: $error';
      });
    }

    _printer.onStateChanged().listen((state) {
      if (!mounted) return;
      setState(() {
        _connected = state == BlueThermalPrinter.CONNECTED;
      });
    });
  }

  Future<void> _requestPermissions() async {
    final statuses = await [
      Permission.bluetooth,
      Permission.bluetoothScan,
      Permission.bluetoothConnect,
      Permission.locationWhenInUse,
    ].request();

    if (statuses.values.any(
      (status) => status.isDenied || status.isPermanentlyDenied,
    )) {
      _showMessage('Berikan izin Bluetooth agar printer dapat digunakan.');
    }
  }

  Future<void> _addProductToCart(Product product) async {
    Variant? selectedVariant = product.variants.isNotEmpty
        ? product.variants.first
        : null;
    final selectedToppingIds = <String>{};

    final showToppings = product.allowTopping && _toppings.isNotEmpty;

    if (product.variants.isNotEmpty || showToppings) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) {
          return StatefulBuilder(
            builder: (context, setDialogState) {
              return AlertDialog(
                title: Text('Pilih opsi untuk ${product.name}'),
                content: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (product.variants.isNotEmpty) ...[
                        const Text('Variant'),
                        const SizedBox(height: 8),
                        DropdownButton<Variant>(
                          value: selectedVariant,
                          items: product.variants
                              .map(
                                (variant) => DropdownMenuItem<Variant>(
                                  value: variant,
                                  child: Text(
                                    '${variant.name} - ${_formatRp(variant.price)}',
                                  ),
                                ),
                              )
                              .toList(),
                          onChanged: (value) {
                            if (value != null) {
                              setDialogState(() {
                                selectedVariant = value;
                              });
                            }
                          },
                        ),
                        const SizedBox(height: 16),
                      ],
                      if (showToppings) ...[
                        const Text('Toppings'),
                        const SizedBox(height: 8),
                        ..._toppings.map(
                          (topping) => CheckboxListTile(
                            title: Text(
                              '${topping.name} (+${_formatRp(topping.price)})',
                            ),
                            value: selectedToppingIds.contains(topping.id),
                            onChanged: (checked) {
                              setDialogState(() {
                                if (checked == true) {
                                  selectedToppingIds.add(topping.id);
                                } else {
                                  selectedToppingIds.remove(topping.id);
                                }
                              });
                            },
                            controlAffinity: ListTileControlAffinity.leading,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.of(context).pop(false),
                    child: const Text('Batal'),
                  ),
                  ElevatedButton(
                    onPressed: () => Navigator.of(context).pop(true),
                    child: const Text('Tambahkan'),
                  ),
                ],
              );
            },
          );
        },
      );

      if (confirmed != true) {
        return;
      }
    }

    final chosenToppings = _toppings
        .where((topping) => selectedToppingIds.contains(topping.id))
        .toList();

    final existing = _cart.firstWhere(
      (item) =>
          item.product.id == product.id &&
          item.variant?.id == selectedVariant?.id &&
          _areToppingsEqual(item.toppings, chosenToppings),
      orElse: () => CartItem(id: product.id, product: product),
    );

    if (_cart.contains(existing)) {
      setState(() {
        existing.quantity++;
      });
    } else {
      setState(() {
        _cart.add(
          CartItem(
            id: product.id,
            product: product,
            variant: selectedVariant,
            toppings: chosenToppings,
          ),
        );
      });
    }
  }

  void _removeCartItem(CartItem item) {
    setState(() {
      _cart.remove(item);
    });
  }

  void _changeQuantity(CartItem item, int delta) {
    setState(() {
      final newQty = item.quantity + delta;
      if (newQty >= 1) {
        item.quantity = newQty.clamp(1, 999);
      }
      // Jika newQty < 1, tidak lakukan apa-apa (tombol sudah di-disable dari UI)
    });
  }

  Future<void> _connectPrinter() async {
    if (_selectedDevice == null) {
      _showMessage('Pilih printer terlebih dahulu.');
      return;
    }

    try {
      await _printer.connect(_selectedDevice!);
      if (!mounted) return;
      setState(() {
        _connected = true;
        _status = 'Printer terhubung.';
      });
      _showMessage('Printer terhubung.');
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _connected = false;
        _status = 'Gagal terhubung: $error';
      });
      _showMessage('Gagal terhubung ke printer.');
    }
  }

  Future<void> _disconnectPrinter() async {
    await _printer.disconnect();
    if (!mounted) return;
    setState(() {
      _connected = false;
      _status = 'Printer terputus.';
    });
  }



  double _parseDouble(dynamic val) {
    if (val == null) return 0.0;
    if (val is num) return val.toDouble();
    if (val is String) return double.tryParse(val) ?? 0.0;
    return 0.0;
  }

  // Lebar karakter 58mm thermal = ~32 karakter
  static const int _thermalWidth = 32;

  /// Menghasilkan baris-baris teks persis seperti output printer thermal
  List<_ThermalLine> _buildThermalLines({
    required String invoiceNumber,
    required String customerName,
    required String paymentMethod,
    required double subtotal,
    required double amountPaid,
    required double change,
    required String createdAt,
    required List<Map<String, dynamic>> items,
  }) {
    final lines = <_ThermalLine>[];

    // Header
    lines.add(_ThermalLine('NYEMIL BEBS', align: TextAlign.center, bold: true, large: true));
    lines.add(_ThermalLine('Purnama Town House Blok H/1', align: TextAlign.center));
    lines.add(_ThermalLine('Telp: +62 823-9943-0312', align: TextAlign.center));
    lines.add(_ThermalLine(''));

    // Info transaksi
    lines.add(_ThermalLine('No: $invoiceNumber'));
    lines.add(_ThermalLine('Tgl: $createdAt'));
    lines.add(_ThermalLine('Kasir: ${widget.kasirName}'));
    lines.add(_ThermalLine('Pelanggan: ${customerName.isEmpty ? 'Umum' : customerName}'));
    lines.add(_ThermalLine(''));

    // Daftar item
    for (final item in items) {
      final String label = item['name'] ?? 'Produk';
      final int qty = (item['quantity'] as num?)?.toInt() ?? 1;
      final double price = _parseDouble(item['price']);
      final double itemSubtotal = _parseDouble(item['subtotal'] != null && _parseDouble(item['subtotal']) > 0 ? item['subtotal'] : null);
      final double realSubtotal = itemSubtotal > 0 ? itemSubtotal : qty * price;

      lines.add(_ThermalLine(label, bold: true));
      lines.add(_ThermalLine.leftRight(
        '$qty x ${price.toStringAsFixed(0)}',
        realSubtotal.toStringAsFixed(0),
        width: _thermalWidth,
      ));

      final List<dynamic> toppings = item['toppings'] ?? [];
      for (final top in toppings) {
        final String topName = top is String ? top : (top['topping_name'] as String? ?? '');
        if (topName.isNotEmpty) {
          lines.add(_ThermalLine('+ $topName'));
        }
      }
    }

    lines.add(_ThermalLine('-' * _thermalWidth, align: TextAlign.center));

    // Total & pembayaran
    lines.add(_ThermalLine.leftRight('TOTAL', _formatRp(subtotal), width: _thermalWidth, bold: true));
    lines.add(_ThermalLine.leftRight('Metode', paymentMethod.toLowerCase() == 'cash' ? 'CASH' : 'QRIS', width: _thermalWidth));

    if (paymentMethod.toLowerCase() == 'cash' && amountPaid > 0) {
      lines.add(_ThermalLine.leftRight('Tunai', _formatRp(amountPaid), width: _thermalWidth));
      lines.add(_ThermalLine.leftRight('Kembalian', _formatRp(change), width: _thermalWidth));
    }

    lines.add(_ThermalLine(''));
    lines.add(_ThermalLine('Terima Kasih atas Kunjungan Anda!', align: TextAlign.center));
    lines.add(_ThermalLine('~ Nyemil Bebs ~', align: TextAlign.center));

    return lines;
  }

  Future<void> _showReceiptPreviewDialog({
    required String invoiceNumber,
    required String customerName,
    required String paymentMethod,
    required double subtotal,
    required double amountPaid,
    required double change,
    required String createdAt,
    required List<Map<String, dynamic>> items,
  }) async {
    final thermalLines = _buildThermalLines(
      invoiceNumber: invoiceNumber,
      customerName: customerName,
      paymentMethod: paymentMethod,
      subtotal: subtotal,
      amountPaid: amountPaid,
      change: change,
      createdAt: createdAt,
      items: items,
    );

    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Row(
            children: const [
              Icon(Icons.receipt_long, color: Colors.green),
              SizedBox(width: 8),
              Text('Preview Struk Belanja'),
            ],
          ),
          content: SingleChildScrollView(
            child: Container(
              padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
              decoration: BoxDecoration(
                color: const Color(0xFFFAFAF8),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.grey.shade300),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                mainAxisSize: MainAxisSize.min,
                children: thermalLines.map((line) {
                  if (line.isLeftRight) {
                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 0.5),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            line.left!,
                            style: TextStyle(
                              fontFamily: 'Courier',
                              fontSize: line.bold ? 13 : 11,
                              fontWeight: line.bold ? FontWeight.bold : FontWeight.normal,
                              letterSpacing: 0.3,
                              color: Colors.black87,
                            ),
                          ),
                          Text(
                            line.right!,
                            style: TextStyle(
                              fontFamily: 'Courier',
                              fontSize: line.bold ? 13 : 11,
                              fontWeight: line.bold ? FontWeight.bold : FontWeight.normal,
                              letterSpacing: 0.3,
                              color: Colors.black87,
                            ),
                          ),
                        ],
                      ),
                    );
                  }
                  if (line.text.isEmpty) {
                    return const SizedBox(height: 6);
                  }
                  return Padding(
                    padding: const EdgeInsets.symmetric(vertical: 0.5),
                    child: Text(
                      line.text,
                      textAlign: line.align,
                      style: TextStyle(
                        fontFamily: 'Courier',
                        fontSize: line.large ? 15 : 11,
                        fontWeight: line.bold ? FontWeight.bold : FontWeight.normal,
                        letterSpacing: 0.3,
                        color: line.text.startsWith('-') ? Colors.grey.shade500 : Colors.black87,
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),
          ),
          actions: [
            OutlinedButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Batal / Tutup'),
            ),
            ElevatedButton.icon(
              icon: const Icon(Icons.print),
              label: const Text('Cetak Ke Printer'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.green,
                foregroundColor: Colors.white,
              ),
              onPressed: () async {
                Navigator.of(context).pop();
                await _printReceiptCustom(
                  invoiceNumber: invoiceNumber,
                  customerName: customerName,
                  paymentMethod: paymentMethod,
                  subtotal: subtotal,
                  amountPaid: amountPaid,
                  change: change,
                  createdAt: createdAt,
                  items: items,
                );
              },
            ),
          ],
        );
      },
    );
  }

  Future<void> _printReceiptCustom({
    required String invoiceNumber,
    required String customerName,
    required String paymentMethod,
    required double subtotal,
    required double amountPaid,
    required double change,
    required String createdAt,
    required List<Map<String, dynamic>> items,
  }) async {
    if (!_connected) {
      _showMessage('Printer belum terhubung. Silakan sambungkan di Pengaturan Printer.', type: SnackBarType.warning);
      return;
    }

    try {
      // Header toko
      await _printer.printCustom('NYEMIL BEBS', 3, 1);
      await _printer.printCustom('Purnama Town House Blok H/1', 1, 1);
      await _printer.printCustom('Telp: +62 823-9943-0312', 1, 1);
      await _printer.printNewLine();

      // Info transaksi
      await _printer.printCustom('No: $invoiceNumber', 1, 0);
      await _printer.printCustom('Tgl: $createdAt', 1, 0);
      await _printer.printCustom('Kasir: ${widget.kasirName}', 1, 0);
      await _printer.printCustom('Pelanggan: ${customerName.isEmpty ? 'Umum' : customerName}', 1, 0);
      await _printer.printNewLine();

      // Daftar item
      for (final item in items) {
        final String label = item['name'] ?? 'Produk';
        await _printer.printCustom(label, 1, 0);

        final int qty = (item['quantity'] as num?)?.toInt() ?? 1;
        final double price = (item['price'] as num?)?.toDouble() ?? 0;
        final double itemSubtotal = (item['subtotal'] as num?)?.toDouble() ?? (qty * price);

        await _printer.printLeftRight(
          '$qty x ${price.toStringAsFixed(0)}',
          itemSubtotal.toStringAsFixed(0),
          0,
        );

        final List<dynamic> toppings = item['toppings'] ?? [];
        for (final top in toppings) {
          final String topName = top is String ? top : (top['topping_name'] as String? ?? '');
          if (topName.isNotEmpty) {
            await _printer.printCustom('+ $topName', 1, 0);
          }
        }
      }

      await _printer.printCustom('--------------------------', 1, 1);

      // Total & pembayaran
      await _printer.printLeftRight('TOTAL', _formatRp(subtotal), 1);
      await _printer.printLeftRight('Metode', paymentMethod.toLowerCase() == 'cash' ? 'CASH' : 'QRIS', 0);

      if (paymentMethod.toLowerCase() == 'cash' && amountPaid > 0) {
        await _printer.printLeftRight('Tunai', _formatRp(amountPaid), 0);
        await _printer.printLeftRight('Kembalian', _formatRp(change), 0);
      }

      await _printer.printNewLine();
      await _printer.printCustom('Terima Kasih atas Kunjungan Anda!', 1, 1);
      await _printer.printCustom('~ Nyemil Bebs ~', 1, 1);
      await _printer.printNewLine();
      await _printer.printNewLine();
      await _printer.paperCut();
      _showMessage('Struk berhasil dicetak.', type: SnackBarType.success);
    } catch (error) {
      _showMessage('Gagal mencetak struk: $error', type: SnackBarType.error);
    }
  }

  Future<void> _showHistoryDialog() async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (context) => HistoryPage(
          apiHeaders: _apiHeaders,
          printer: _printer,
          kasirName: widget.kasirName,
        ),
      ),
    );
  }

  bool _areToppingsEqual(List<Topping> a, List<Topping> b) {
    if (a.length != b.length) return false;
    final aIds = a.map((t) => t.id).toSet();
    final bIds = b.map((t) => t.id).toSet();
    return aIds.length == bIds.length && aIds.containsAll(bIds);
  }

  Future<void> _syncOrder(String invoiceNumber) async {
    final payload = {
      'invoice_number': invoiceNumber,
      'customer_name': _customerName,
      'subtotal': _subtotal,
      'total': _subtotal,
      'payment_method': _paymentMethod,
      'status': 'completed',
      'items': _cart.map((item) => item.toJson()).toList(),
    };

    if (!_isOnline) {
      await _queueOrder(payload);
      return;
    }

    setState(() {
      _syncing = true;
    });

    try {
      final response = await http
          .post(
            Uri.parse('$backendUrl/orders/sync'),
            headers: _apiHeaders,
            body: jsonEncode(payload),
          )
          .timeout(const Duration(seconds: 10));

      if (response.statusCode == 201) {
        _showMessage('Order berhasil disimpan ke dashboard.');
      } else {
        await _queueOrder(payload);
      }
    } catch (error) {
      await _queueOrder(payload);
    }

    if (!mounted) return;
    setState(() {
      _syncing = false;
    });
  }

  Future<void> _queueOrder(Map<String, dynamic> payload) async {
    await OfflineStore.addPendingOrder(payload);
    await _refreshPendingOrderCount();
    _showMessage(
      'Tidak ada koneksi. Order disimpan & akan otomatis disinkronkan.',
      type: SnackBarType.warning,
    );
  }

  Future<void> _syncPendingOrders() async {
    if (_syncing) return;

    final pending = await OfflineStore.getPendingOrders();
    if (pending.isEmpty) return;

    setState(() {
      _syncing = true;
    });

    var successCount = 0;
    var failCount = 0;

    for (final order in pending) {
      try {
        final response = await http
            .post(
              Uri.parse('$backendUrl/orders/sync'),
              headers: _apiHeaders,
              body: jsonEncode(order),
            )
            .timeout(const Duration(seconds: 10));

        if (response.statusCode == 201) {
          await OfflineStore.removePendingOrder(
            order['invoice_number'] as String,
          );
          successCount++;
        } else {
          failCount++;
        }
      } catch (_) {
        failCount++;
        break; // kemungkinan koneksi putus lagi, hentikan biar tidak spam retry
      }
    }

    await _refreshPendingOrderCount();

    if (!mounted) return;
    setState(() {
      _syncing = false;
    });

    if (successCount > 0) {
      _showMessage(
        '$successCount order berhasil disinkronkan.'
        '${failCount > 0 ? ' $failCount masih tertunda.' : ''}',
        type: SnackBarType.success,
      );
    } else if (failCount > 0) {
      _showMessage(
        'Sinkronisasi belum berhasil, akan dicoba lagi nanti.',
        type: SnackBarType.warning,
      );
    }
  }

  Future<void> _checkout() async {
    if (_cart.isEmpty) {
      _showMessage('Keranjang kosong. Tambahkan produk terlebih dahulu.');
      return;
    }

    _customerName = '';
    _paymentMethod = 'cash';
    _amountPaid = '';

    String? qrisBase64;
    bool qrisLoading = false;
    String? qrisError;
    String? qrisOfflineImagePath;

    Future<void> loadQrisImage(
      int amount,
      void Function(void Function()) setDialogState,
    ) async {
      if (!_isOnline) {
        final path = await OfflineStore.getDefaultQrisImagePath();
        setDialogState(() {
          qrisOfflineImagePath = path;
          qrisBase64 = null;
          qrisError = null;
          qrisLoading = false;
        });
        return;
      }

      setDialogState(() {
        qrisLoading = true;
        qrisError = null;
        qrisOfflineImagePath = null;
      });

      try {
        final response = await http
            .get(
              Uri.parse('$backendUrl/qris/dynamic?amount=$amount'),
              headers: _apiHeaders,
            )
            .timeout(const Duration(seconds: 10));

        if (response.statusCode == 200) {
          final body = jsonDecode(response.body) as Map<String, dynamic>;
          setDialogState(() {
            qrisBase64 = body['qr_base64'] as String?;
            qrisLoading = false;
          });
        } else {
          setDialogState(() {
            qrisError = 'Gagal memuat QRIS (${response.statusCode})';
            qrisLoading = false;
          });
        }
      } catch (error) {
        setDialogState(() {
          qrisError = 'Gagal memuat QRIS: $error';
          qrisLoading = false;
        });
      }
    }

    final result = await showDialog<bool>(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            final total = _subtotal;
            final amountPaidValue = int.tryParse(_amountPaid) ?? 0;
            final change = amountPaidValue > total
                ? amountPaidValue - total
                : 0;

            return AlertDialog(
              title: const Text('Selesaikan Pembayaran'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'Total: ${_formatRp(_subtotal)}',
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      decoration: const InputDecoration(
                        labelText: 'Nama pelanggan',
                      ),
                      onChanged: (value) => setDialogState(() {
                        _customerName = value;
                      }),
                    ),
                    const SizedBox(height: 16),
                    InputDecorator(
                      decoration: const InputDecoration(
                        labelText: 'Metode pembayaran',
                        border: OutlineInputBorder(),
                      ),
                      child: Column(
                        children: [
                          RadioListTile<String>(
                            title: const Text('Tunai (Cash)'),
                            value: 'cash',
                            groupValue: _paymentMethod,
                            onChanged: (value) {
                              if (value != null) {
                                setDialogState(() {
                                  _paymentMethod = value;
                                });
                              }
                            },
                          ),
                          RadioListTile<String>(
                            title: const Text('QRIS'),
                            value: 'qris',
                            groupValue: _paymentMethod,
                            onChanged: (value) {
                              if (value != null) {
                                setDialogState(() {
                                  _paymentMethod = value;
                                });
                                loadQrisImage(total.round(), setDialogState);
                              }
                            },
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (_paymentMethod == 'cash') ...[
                      TextField(
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                          labelText: 'Nominal Uang (Tunai)',
                        ),
                        onChanged: (value) => setDialogState(() {
                          _amountPaid = value;
                        }),
                      ),
                      const SizedBox(height: 12),
                      if (amountPaidValue > 0)
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Kembalian:'),
                            Text(
                              '${_formatRp(change)}',
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                    ],
                    if (_paymentMethod == 'qris') ...[
                      Container(
                        margin: const EdgeInsets.only(top: 12),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.grey.shade100,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Column(
                          children: [
                            if (qrisLoading)
                              const Padding(
                                padding: EdgeInsets.symmetric(vertical: 32),
                                child: CircularProgressIndicator(),
                              )
                            else if (!_isOnline)
                              Column(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 10,
                                      vertical: 4,
                                    ),
                                    decoration: BoxDecoration(
                                      color: Colors.orange.shade100,
                                      borderRadius: BorderRadius.circular(20),
                                    ),
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Icon(Icons.cloud_off, size: 14, color: Colors.orange.shade800),
                                        const SizedBox(width: 4),
                                        Text(
                                          'Mode Offline',
                                          style: TextStyle(fontSize: 11, color: Colors.orange.shade800, fontWeight: FontWeight.bold),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(height: 10),
                                  if (qrisOfflineImagePath != null)
                                    Image.file(
                                      File(qrisOfflineImagePath!),
                                      width: 220,
                                      height: 220,
                                      fit: BoxFit.contain,
                                    )
                                  else ...[
                                    Icon(Icons.qr_code_2, size: 64, color: Colors.grey.shade400),
                                    const SizedBox(height: 8),
                                    const Text(
                                      'QRIS offline belum diatur. Buka menu Setting untuk mengunggah QRIS default, atau gunakan Tunai.',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(fontSize: 12, color: Colors.black54),
                                    ),
                                  ],
                                  if (qrisOfflineImagePath != null) ...[
                                    const SizedBox(height: 8),
                                    const Text(
                                      'QRIS statis (nominal tidak otomatis) - konfirmasi manual nominal ke pelanggan.',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(fontSize: 11, color: Colors.black54),
                                    ),
                                  ],
                                ],
                              )
                            else if (qrisError != null)
                              Column(
                                children: [
                                  Icon(
                                    Icons.error_outline,
                                    size: 48,
                                    color: Colors.red.shade400,
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    qrisError!,
                                    textAlign: TextAlign.center,
                                    style: const TextStyle(color: Colors.red),
                                  ),
                                  const SizedBox(height: 8),
                                  TextButton(
                                    onPressed: () => loadQrisImage(
                                      total.round(),
                                      setDialogState,
                                    ),
                                    child: const Text('Coba lagi'),
                                  ),
                                ],
                              )
                            else if (qrisBase64 != null)
                              Image.memory(
                                base64Decode(qrisBase64!),
                                width: 220,
                                height: 220,
                              )
                            else
                              const Icon(
                                Icons.qr_code,
                                size: 64,
                                color: Colors.black54,
                              ),
                            const SizedBox(height: 12),
                            Text(
                              'Silakan scan QRIS untuk membayar ${_formatRp(total)}.',
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(context).pop(false),
                  child: const Text('Batal'),
                ),
                ElevatedButton(
                  onPressed: () {
                    if (_customerName.trim().isEmpty) {
                      _showMessage('Nama pelanggan wajib diisi.');
                      return;
                    }
                    if (_paymentMethod == 'cash' && amountPaidValue < total) {
                      _showMessage('Nominal uang belum cukup.');
                      return;
                    }
                    Navigator.of(context).pop(true);
                  },
                  child: const Text('Bayar'),
                ),
              ],
            );
          },
        );
      },
    );

    if (result != true) {
      return;
    }

    final invoiceNumber = _generateInvoiceNumber();
    await _syncOrder(invoiceNumber);

    final now = DateTime.now();
    final tanggalStr = '${now.day.toString().padLeft(2, '0')}/${now.month.toString().padLeft(2, '0')}/${now.year} ${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}';

    final currentCart = List<CartItem>.from(_cart);
    final currentCustomer = _customerName;
    final currentPayment = _paymentMethod;
    final currentAmountPaidStr = _amountPaid;
    final currentSubtotal = _subtotal;
    final amountPaidVal = (int.tryParse(currentAmountPaidStr) ?? 0).toDouble();
    final changeVal = amountPaidVal > currentSubtotal ? amountPaidVal - currentSubtotal : 0.0;

    final formattedItems = currentCart.map<Map<String, dynamic>>((item) {
      final label = item.variant != null
          ? '${item.product.name} - ${item.variant!.name}'
          : item.product.name;

      final baseUnitPrice = (item.variant?.price ?? item.product.price);
      return {
        'name': label,
        'quantity': item.quantity,
        'price': baseUnitPrice,
        'subtotal': item.subtotal,
        'toppings': item.toppings.map((t) => {'topping_name': t.name, 'price': t.price}).toList(),
      };
    }).toList();

    setState(() {
      _cart.clear();
      _customerName = '';
      _paymentMethod = 'cash';
      _amountPaid = '';
    });

    if (!mounted) return;

    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Row(
            children: const [
              Icon(Icons.check_circle, color: Colors.green, size: 28),
              SizedBox(width: 8),
              Text('Transaksi Berhasil'),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('No. Invoice: $invoiceNumber', style: const TextStyle(fontWeight: FontWeight.bold)),
              const SizedBox(height: 6),
              Text('Total: ${_formatRp(currentSubtotal)}', style: const TextStyle(color: Colors.green, fontWeight: FontWeight.bold, fontSize: 16)),
              Text('Metode: ${currentPayment == 'cash' ? 'Tunai' : 'QRIS'}'),
              if (currentPayment == 'cash') ...[
                Text('Uang Diterima: ${_formatRp(amountPaidVal)}'),
                Text('Kembalian: ${_formatRp(changeVal)}', style: const TextStyle(fontWeight: FontWeight.bold)),
              ],
              const Divider(height: 24),
              const Text('Apakah Anda ingin mencetak struk belanja?', style: TextStyle(fontWeight: FontWeight.w500)),
            ],
          ),
          actions: [
            OutlinedButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Tanpa Cetak'),
            ),
            ElevatedButton.icon(
              icon: const Icon(Icons.print),
              label: const Text('Cetak Struk'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.green,
                foregroundColor: Colors.white,
              ),
              onPressed: () async {
                Navigator.of(context).pop();
                await _showReceiptPreviewDialog(
                  invoiceNumber: invoiceNumber,
                  customerName: currentCustomer,
                  paymentMethod: currentPayment,
                  subtotal: currentSubtotal,
                  amountPaid: amountPaidVal,
                  change: changeVal,
                  createdAt: tanggalStr,
                  items: formattedItems,
                );
              },
            ),
          ],
        );
      },
    );
  }

  String _generateInvoiceNumber() {
    final now = DateTime.now();
    final datePart =
        '${now.year}${now.month.toString().padLeft(2, '0')}${now.day.toString().padLeft(2, '0')}';

    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    final random = Random();
    final randomPart = List.generate(
      4,
      (_) => chars[random.nextInt(chars.length)],
    ).join();

    return 'INV-$datePart-$randomPart';
  }

  void _showMessage(String message, {SnackBarType type = SnackBarType.info}) {
    if (!mounted) return;

    final Color backgroundColor = switch (type) {
      SnackBarType.success => Colors.green.shade700,
      SnackBarType.error   => Colors.red.shade700,
      SnackBarType.warning => Colors.orange.shade800,
      SnackBarType.info    => Colors.blueGrey.shade700,
    };

    final IconData icon = switch (type) {
      SnackBarType.success => Icons.check_circle,
      SnackBarType.error   => Icons.error,
      SnackBarType.warning => Icons.warning_amber_rounded,
      SnackBarType.info    => Icons.info,
    };

    // Insert directly into the Navigator Overlay — always above dialogs.
    final overlay = Navigator.of(context, rootNavigator: true).overlay;
    if (overlay == null) return;

    late OverlayEntry entry;
    entry = OverlayEntry(
      builder: (ctx) => _ToastOverlay(
        message: message,
        backgroundColor: backgroundColor,
        icon: icon,
        onDismissed: () => entry.remove(),
      ),
    );

    overlay.insert(entry);
  }

  Future<void> _pickDefaultQrisImage(
    void Function(void Function()) setDialogState,
  ) async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
    );
    if (picked == null) return;

    final appDir = await getApplicationDocumentsDirectory();
    final savedPath = '${appDir.path}/default_qris_offline.jpg';
    await File(picked.path).copy(savedPath);
    await OfflineStore.saveDefaultQrisImagePath(savedPath);

    setDialogState(() {});
    _showMessage('QRIS offline default berhasil disimpan.', type: SnackBarType.success);
  }

  Future<void> _removeDefaultQrisImage(
    void Function(void Function()) setDialogState,
  ) async {
    await OfflineStore.clearDefaultQrisImage();
    setDialogState(() {});
    _showMessage('QRIS offline default dihapus.');
  }

  void _openPrinterSettings() async {
    String? qrisOfflinePath = await OfflineStore.getDefaultQrisImagePath();

    if (!mounted) return;

    showDialog<void>(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              title: const Text('Pengaturan'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                  const Text('Pilih printer Bluetooth dari daftar berikut:'),
                  const SizedBox(height: 12),
                  if (_devices.isEmpty)
                    const Text(
                      'Belum ada printer terdeteksi. Tekan Refresh untuk mencoba lagi.',
                    )
                  else
                    DropdownButtonFormField<BluetoothDevice>(
                      value: _selectedDevice,
                      items: _devices
                          .map(
                            (device) => DropdownMenuItem<BluetoothDevice>(
                              value: device,
                              child: Text(
                                device.name ?? device.address ?? 'Unknown',
                              ),
                            ),
                          )
                          .toList(),
                      onChanged: (device) {
                        setDialogState(() {
                          _selectedDevice = device;
                        });
                      },
                      decoration: const InputDecoration(
                        border: OutlineInputBorder(),
                      ),
                    ),
                  const SizedBox(height: 16),
                  Text(
                    'Status printer: ${_connected ? 'Terkoneksi' : 'Tidak terhubung'}',
                    style: TextStyle(
                      fontWeight: FontWeight.w600,
                      color: _connected
                          ? Colors.green.shade700
                          : Colors.orange.shade700,
                    ),
                  ),
                  const Divider(height: 32),
                  const Text(
                    'QRIS Offline (Default)',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Gambar QRIS ini ditampilkan saat kasir memilih QRIS dalam kondisi tanpa internet. Nominal tidak otomatis, jadi konfirmasi manual ke pelanggan.',
                    style: TextStyle(fontSize: 12, color: Colors.black54),
                  ),
                  const SizedBox(height: 12),
                  if (qrisOfflinePath != null)
                    Column(
                      children: [
                        Image.file(
                          File(qrisOfflinePath!),
                          width: 140,
                          height: 140,
                          fit: BoxFit.contain,
                        ),
                        const SizedBox(height: 8),
                      ],
                    ),
                  Row(
                    children: [
                      OutlinedButton.icon(
                        icon: const Icon(Icons.image_outlined, size: 18),
                        label: Text(qrisOfflinePath == null ? 'Pilih Gambar' : 'Ganti Gambar'),
                        onPressed: () async {
                          await _pickDefaultQrisImage(setDialogState);
                          qrisOfflinePath = await OfflineStore.getDefaultQrisImagePath();
                          setDialogState(() {});
                        },
                      ),
                      if (qrisOfflinePath != null) ...[
                        const SizedBox(width: 8),
                        TextButton.icon(
                          icon: const Icon(Icons.delete_outline, size: 18, color: Colors.red),
                          label: const Text('Hapus', style: TextStyle(color: Colors.red)),
                          onPressed: () async {
                            await _removeDefaultQrisImage(setDialogState);
                            qrisOfflinePath = null;
                            setDialogState(() {});
                          },
                        ),
                      ],
                    ],
                  ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () {
                    Navigator.of(context).pop();
                  },
                  child: const Text('Tutup'),
                ),
                TextButton(
                  onPressed: () {
                    _refreshDevices();
                    setDialogState(() {});
                  },
                  child: const Text('Refresh'),
                ),
                ElevatedButton(
                  onPressed: _selectedDevice == null
                      ? null
                      : () {
                          Navigator.of(context).pop();
                          if (_connected) {
                            _disconnectPrinter();
                          } else {
                            _connectPrinter();
                          }
                        },
                  child: Text(_connected ? 'Disconnect' : 'Connect'),
                ),
              ],
            );
          },
        );
      },
    );
  }
  void _logout() {
    showDialog<bool>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text('Konfirmasi Keluar'),
          content: const Text('Apakah Anda yakin ingin keluar dari akun kasir ini?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Batal'),
            ),
            ElevatedButton(
              onPressed: () => Navigator.of(context).pop(true),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.red.shade700,
                foregroundColor: Colors.white,
              ),
              child: const Text('Keluar'),
            ),
          ],
        );
      },
    ).then((confirmed) async {
      if (confirmed == true) {
        await OfflineStore.clearSession();
        if (!mounted) return;
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (context) => const LoginPage(),
          ),
        );
      }
    });
  }

  Widget _buildBottomNavItem({
    required IconData icon,
    required String label,
    required Color color,
    required VoidCallback onTap,
  }) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, color: color, size: 24),
              const SizedBox(height: 2),
              Text(
                label,
                style: TextStyle(fontSize: 10, color: color, fontWeight: FontWeight.w500),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('NYEMIL BEBS'),
        actions: [
          // Indikator online/offline + antrian order belum sinkron
          Tooltip(
            message: _isOnline
                ? (_pendingOrderCount > 0
                    ? '$_pendingOrderCount order menunggu sinkronisasi'
                    : 'Online')
                : 'Offline${_pendingOrderCount > 0 ? ' - $_pendingOrderCount order tertunda' : ''}',
            child: InkWell(
              onTap: _isOnline && _pendingOrderCount > 0
                  ? _syncPendingOrders
                  : null,
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      _isOnline ? Icons.cloud_done : Icons.cloud_off,
                      size: 18,
                      color: _isOnline ? Colors.greenAccent : Colors.orangeAccent,
                    ),
                    if (_pendingOrderCount > 0) ...[
                      const SizedBox(width: 4),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                        decoration: BoxDecoration(
                          color: Colors.redAccent,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          '$_pendingOrderCount',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
          // Indikator status printer
          Tooltip(
            message: _connected ? 'Printer terhubung' : 'Printer belum terhubung',
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 18, horizontal: 4),
              child: Container(
                width: 12,
                height: 12,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: _connected ? Colors.greenAccent : Colors.orangeAccent,
                  boxShadow: [
                    BoxShadow(
                      color: (_connected ? Colors.green : Colors.orange).withAlpha(160),
                      blurRadius: 6,
                      spreadRadius: 1,
                    ),
                  ],
                ),
              ),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Keluar',
            onPressed: _logout,
          ),
        ],
      ),
      bottomNavigationBar: BottomAppBar(
        color: Colors.white,
        elevation: 8,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            _buildBottomNavItem(
              icon: Icons.home_rounded,
              label: 'Home',
              color: Colors.green,
              onTap: () {},
            ),
            _buildBottomNavItem(
              icon: Icons.history,
              label: 'Riwayat',
              color: Colors.blueGrey,
              onTap: _showHistoryDialog,
            ),
            _buildBottomNavItem(
              icon: Icons.settings,
              label: 'Setting',
              color: Colors.blueGrey,
              onTap: _openPrinterSettings,
            ),
          ],
        ),
      ),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            const SizedBox(height: 8),
            Expanded(
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final isWide = constraints.maxWidth >= 840;
                  final productColumns = isWide ? 3 : 2;
                  final aspectRatio = isWide ? 1.35 : 1.15;

                  Widget buildProductCard() {
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(10),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize
                              .min, // <-- penting: card tinggi sesuai isi
                          children: [
                            const Text(
                              'Produk',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 8),
                            _loadingProducts
                                ? const Padding(
                                    padding: EdgeInsets.all(24),
                                    child: Center(
                                      child: CircularProgressIndicator(),
                                    ),
                                  )
                                : _products.isEmpty
                                ? const Padding(
                                    padding: EdgeInsets.all(24),
                                    child: Center(
                                      child: Text('Tidak ada produk tersedia'),
                                    ),
                                  )
                                : GridView.builder(
                                    padding: EdgeInsets.zero,
                                    shrinkWrap:
                                        true, // <-- grid tinggi sesuai isi, bukan maksa penuh
                                    physics:
                                        const NeverScrollableScrollPhysics(), // grid ini gak perlu scroll sendiri
                                    gridDelegate:
                                        SliverGridDelegateWithFixedCrossAxisCount(
                                          crossAxisCount: productColumns,
                                          mainAxisSpacing: 12,
                                          crossAxisSpacing: 12,
                                          childAspectRatio: aspectRatio,
                                        ),
                                    itemCount: _products.length,
                                    itemBuilder: (context, index) {
                                      final product = _products[index];
                                      return Card(
                                        elevation: 2,
                                        clipBehavior: Clip.antiAlias,
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(
                                            16,
                                          ),
                                        ),
                                        child: InkWell(
                                          borderRadius: BorderRadius.circular(
                                            16,
                                          ),
                                          onTap: () =>
                                              _addProductToCart(product),
                                          child: Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Expanded(
                                                child: Container(
                                                  width: double.infinity,
                                                  padding: const EdgeInsets.all(6),
                                                  color: Colors.green.shade50,
                                                  child: product.imageUrl != null &&
                                                          product.imageUrl!.isNotEmpty
                                                      ? Image.network(
                                                          product.imageUrl!,
                                                          fit: BoxFit.contain,
                                                          errorBuilder: (context, error, stackTrace) =>
                                                              const Icon(
                                                                Icons.fastfood,
                                                                color: Colors.green,
                                                                size: 32,
                                                              ),
                                                        )
                                                      : const Icon(
                                                          Icons.fastfood,
                                                          color: Colors.green,
                                                          size: 32,
                                                        ),
                                                ),
                                              ),
                                              Padding(
                                                padding: const EdgeInsets.all(10),
                                                child: Column(
                                                  crossAxisAlignment: CrossAxisAlignment.start,
                                                  children: [
                                                    Text(
                                                      product.name,
                                                      maxLines: 1,
                                                      overflow: TextOverflow.ellipsis,
                                                      style: const TextStyle(
                                                        fontWeight: FontWeight.bold,
                                                        fontSize: 14,
                                                      ),
                                                    ),
                                                    const SizedBox(height: 4),
                                                    Text(
                                                      '${_formatRp(product.price)}',
                                                      style: const TextStyle(
                                                        color: Colors.green,
                                                        fontWeight: FontWeight.bold,
                                                        fontSize: 13,
                                                      ),
                                                    ),
                                                  ],
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      );
                                    },
                                  ),
                          ],
                        ),
                      ),
                    );
                  }

                  Widget buildCartCard() {
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Keranjang',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 10),
                            Expanded(
                              child: _cart.isEmpty
                                  ? const Center(
                                      child: Text('Keranjang kosong'),
                                    )
                                  : ListView.builder(
                                      itemCount: _cart.length,
                                      itemBuilder: (context, index) {
                                        final item = _cart[index];
                                        return Card(
                                          elevation: 1,
                                          margin: const EdgeInsets.symmetric(
                                            vertical: 6,
                                          ),
                                          shape: RoundedRectangleBorder(
                                            borderRadius: BorderRadius.circular(
                                              12,
                                            ),
                                          ),
                                          child: Padding(
                                            padding: const EdgeInsets.symmetric(
                                              horizontal: 12,
                                              vertical: 10,
                                            ),
                                            child: Row(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.center,
                                              children: [
                                                // Kontrol qty: ➖ angka ➕
                                                Column(
                                                  mainAxisSize: MainAxisSize.min,
                                                  mainAxisAlignment:
                                                      MainAxisAlignment.center,
                                                  children: [
                                                    SizedBox(
                                                      width: 32,
                                                      height: 32,
                                                      child: IconButton(
                                                        padding: EdgeInsets.zero,
                                                        constraints:
                                                            const BoxConstraints(),
                                                        icon: Icon(
                                                          Icons
                                                              .remove_circle_outline,
                                                          size: 22,
                                                          color: item.quantity <= 1
                                                              ? Colors.grey.shade300
                                                              : null,
                                                        ),
                                                        onPressed:
                                                            item.quantity <= 1
                                                                ? null
                                                                : () =>
                                                                    _changeQuantity(
                                                                      item,
                                                                      -1,
                                                                    ),
                                                      ),
                                                    ),
                                                    Padding(
                                                      padding:
                                                          const EdgeInsets.symmetric(
                                                            vertical: 2,
                                                          ),
                                                      child: Text(
                                                        '${item.quantity}',
                                                        style: const TextStyle(
                                                          fontWeight:
                                                              FontWeight.bold,
                                                        ),
                                                      ),
                                                    ),
                                                    SizedBox(
                                                      width: 32,
                                                      height: 32,
                                                      child: IconButton(
                                                        padding: EdgeInsets.zero,
                                                        constraints:
                                                            const BoxConstraints(),
                                                        icon: const Icon(
                                                          Icons.add_circle_outline,
                                                          size: 22,
                                                        ),
                                                        onPressed: () =>
                                                            _changeQuantity(
                                                              item,
                                                              1,
                                                            ),
                                                      ),
                                                    ),
                                                  ],
                                                ),
                                                const SizedBox(width: 12),
                                                // Info produk
                                                Expanded(
                                                  child: Column(
                                                    crossAxisAlignment:
                                                        CrossAxisAlignment.start,
                                                    mainAxisSize: MainAxisSize.min,
                                                    children: [
                                                      Text(
                                                        item.product.name,
                                                        style: const TextStyle(
                                                          fontWeight:
                                                              FontWeight.bold,
                                                        ),
                                                      ),
                                                      if (item.variant != null)
                                                        Text(
                                                          'Varian: ${item.variant!.name}',
                                                        ),
                                                      if (item.toppings.isNotEmpty)
                                                        Text(
                                                          'Topping: ${item.toppings.map((t) => t.name).join(', ')}',
                                                        ),
                                                      Text(
                                                        '${_formatRp(item.subtotal)}',
                                                      ),
                                                    ],
                                                  ),
                                                ),
                                                // Tombol hapus
                                                IconButton(
                                                  padding: EdgeInsets.zero,
                                                  constraints:
                                                      const BoxConstraints(),
                                                  icon: const Icon(
                                                    Icons.delete_outline,
                                                  ),
                                                  onPressed: () =>
                                                      _removeCartItem(item),
                                                ),
                                              ],
                                            ),
                                          ),
                                        );
                                      },
                                    ),
                            ),
                            const Divider(),
                            Text(
                              'Subtotal: ${_formatRp(_subtotal)}',
                            ),
                          ],
                        ),
                      ),
                    );
                  }

                  if (isWide) {
                    return Row(
                      children: [
                        Expanded(child: buildProductCard()),
                        const SizedBox(width: 12),
                        Expanded(child: buildCartCard()),
                      ],
                    );
                  }

                  return Column(
                    children: [
                      buildProductCard(), // tanpa Expanded, tinggi menyesuaikan isi
                      const SizedBox(height: 12),
                      Expanded(
                        child: buildCartCard(),
                      ), // ini yang ambil sisa ruang
                    ],
                  );
                },
              ),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: _syncing ? null : _checkout,
                icon: const Icon(Icons.payment),
                label: _syncing
                    ? const SizedBox(
                        height: 18,
                        width: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Bayar & Sync'),
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Toast widget yang muncul di atas semua lapisan (termasuk Dialog),
/// karena dimasukkan langsung ke Navigator Overlay.
class _ToastOverlay extends StatefulWidget {
  final String message;
  final Color backgroundColor;
  final IconData icon;
  final VoidCallback onDismissed;

  const _ToastOverlay({
    required this.message,
    required this.backgroundColor,
    required this.icon,
    required this.onDismissed,
  });

  @override
  State<_ToastOverlay> createState() => _ToastOverlayState();
}

class _ToastOverlayState extends State<_ToastOverlay> {
  double _opacity = 0.0;

  @override
  void initState() {
    super.initState();
    // Fade in
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) setState(() => _opacity = 1.0);
    });
    // Auto-dismiss setelah 3 detik
    Future.delayed(const Duration(seconds: 3), () {
      if (mounted) {
        setState(() => _opacity = 0.0);
        Future.delayed(const Duration(milliseconds: 400), widget.onDismissed);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final safeTop = MediaQuery.of(context).padding.top;
    return Positioned(
      top: safeTop + 12,
      left: 16,
      right: 16,
      child: Material(
        color: Colors.transparent,
        child: AnimatedOpacity(
          opacity: _opacity,
          duration: const Duration(milliseconds: 350),
          child: GestureDetector(
            onTap: () {
              setState(() => _opacity = 0.0);
              Future.delayed(
                const Duration(milliseconds: 350),
                widget.onDismissed,
              );
            },
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(
                color: widget.backgroundColor,
                borderRadius: BorderRadius.circular(12),
                boxShadow: const [
                  BoxShadow(
                    color: Colors.black26,
                    blurRadius: 10,
                    offset: Offset(0, 4),
                  ),
                ],
              ),
              child: Row(
                children: [
                  Icon(widget.icon, color: Colors.white, size: 22),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      widget.message,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                  const Icon(Icons.close, color: Colors.white70, size: 18),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Model baris teks untuk simulasi printer thermal
class _ThermalLine {
  final String text;
  final TextAlign align;
  final bool bold;
  final bool large;

  // Untuk baris left-right (printLeftRight)
  final String? left;
  final String? right;
  bool get isLeftRight => left != null && right != null;

  const _ThermalLine(
    this.text, {
    this.align = TextAlign.left,
    this.bold = false,
    this.large = false,
  })  : left = null,
        right = null;

  _ThermalLine.leftRight(
    String leftText,
    String rightText, {
    int width = 32,
    this.bold = false,
  })  : left = leftText,
        right = rightText,
        text = '',
        align = TextAlign.left,
        large = false;
}
