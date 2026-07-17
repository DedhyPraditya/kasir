import 'dart:convert';

import 'package:blue_thermal_printer/blue_thermal_printer.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:permission_handler/permission_handler.dart';

const String backendUrl = 'https://kasir.madignet.site/api/orders/sync';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'NYEMIL BEBS POS',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.deepPurple),
        useMaterial3: true,
      ),
      home: const PosHomePage(),
    );
  }
}

class Product {
  final String id;
  final String name;
  final double price;

  const Product({required this.id, required this.name, required this.price});
}

class CartItem {
  final String id;
  final Product product;
  int quantity;

  CartItem({required this.id, required this.product, this.quantity = 1});

  double get subtotal => product.price * quantity;

  Map<String, dynamic> toJson() => {
    'product_id': id,
    'variant_id': null,
    'product_name': product.name,
    'variant_name': null,
    'quantity': quantity,
    'price': product.price,
    'subtotal': subtotal,
    'toppings': [],
  };
}

class PosHomePage extends StatefulWidget {
  const PosHomePage({super.key});

  @override
  State<PosHomePage> createState() => _PosHomePageState();
}

class _PosHomePageState extends State<PosHomePage> {
  final BlueThermalPrinter _printer = BlueThermalPrinter.instance;
  final List<BluetoothDevice> _devices = [];
  final List<CartItem> _cart = [];
  final List<Product> _products = const [
    Product(id: 'p1', name: 'Nasi Goreng', price: 25000),
    Product(id: 'p2', name: 'Es Teh', price: 5000),
    Product(id: 'p3', name: 'Mie Ayam', price: 20000),
    Product(id: 'p4', name: 'Kopi Hitam', price: 12000),
  ];

  BluetoothDevice? _selectedDevice;
  bool _connected = false;
  bool _loading = false;
  bool _syncing = false;
  String _status = 'Menyiapkan printer...';
  String _customerName = '';
  String _paymentMethod = 'cash';

  double get _subtotal => _cart.fold(0, (value, item) => value + item.subtotal);

  @override
  void initState() {
    super.initState();
    _refreshDevices();
  }

  Future<void> _refreshDevices() async {
    setState(() {
      _loading = true;
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
    } finally {
      if (!mounted) return;
      setState(() {
        _loading = false;
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

  void _addProductToCart(Product product) {
    final existing = _cart
        .where((item) => item.product.id == product.id)
        .toList();
    if (existing.isNotEmpty) {
      setState(() {
        existing.first.quantity++;
      });
    } else {
      setState(() {
        _cart.add(CartItem(id: product.id, product: product));
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
      item.quantity = (item.quantity + delta).clamp(1, 999);
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

  Future<void> _printReceipt(String invoiceNumber) async {
    if (!_connected) {
      _showMessage('Printer belum terhubung.');
      return;
    }

    try {
      await _printer.printCustom('NYEMIL BEBS', 3, 1);
      await _printer.printNewLine();
      await _printer.printCustom('Invoice: $invoiceNumber', 1, 1);
      await _printer.printCustom('--------------------------', 1, 1);

      for (final item in _cart) {
        await _printer.printLeftRight(
          item.product.name,
          item.subtotal.toStringAsFixed(0),
          1,
        );
        if (item.quantity > 1) {
          await _printer.printCustom(
            '  x${item.quantity} @ ${item.product.price.toStringAsFixed(0)}',
            1,
            0,
          );
        }
      }

      await _printer.printCustom('--------------------------', 1, 1);
      await _printer.printLeftRight('TOTAL', _subtotal.toStringAsFixed(0), 2);
      await _printer.printNewLine();
      await _printer.printCustom('Terima kasih atas pembelian Anda!', 1, 1);
      await _printer.printNewLine();
      await _printer.printNewLine();
      await _printer.paperCut();
      _showMessage('Cetak berhasil.');
    } catch (error) {
      _showMessage('Gagal mencetak: $error');
    }
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

    setState(() {
      _syncing = true;
    });

    try {
      final response = await http.post(
        Uri.parse(backendUrl),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode(payload),
      );

      if (response.statusCode == 201) {
        _showMessage('Order berhasil disimpan ke dashboard.');
      } else {
        _showMessage('Sinkron gagal: ${response.statusCode}.');
      }
    } catch (error) {
      _showMessage('Sinkron gagal: $error');
    } finally {
      if (!mounted) return;
      setState(() {
        _syncing = false;
      });
    }
  }

  Future<void> _checkout() async {
    if (_cart.isEmpty) {
      _showMessage('Keranjang kosong. Tambahkan produk terlebih dahulu.');
      return;
    }

    final result = await showDialog<bool>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text('Checkout'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                decoration: const InputDecoration(labelText: 'Nama pelanggan'),
                onChanged: (value) => _customerName = value,
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: _paymentMethod,
                items: const [
                  DropdownMenuItem(value: 'cash', child: Text('Cash')),
                  DropdownMenuItem(value: 'qris', child: Text('QRIS')),
                ],
                onChanged: (value) {
                  if (value != null) {
                    setState(() {
                      _paymentMethod = value;
                    });
                  }
                },
                decoration: const InputDecoration(
                  labelText: 'Metode pembayaran',
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Batal'),
            ),
            ElevatedButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Bayar'),
            ),
          ],
        );
      },
    );

    if (result != true) {
      return;
    }

    final invoiceNumber = 'INV-${DateTime.now().millisecondsSinceEpoch}';
    await _syncOrder(invoiceNumber);

    if (_connected) {
      await _printReceipt(invoiceNumber);
    }

    setState(() {
      _cart.clear();
      _customerName = '';
      _paymentMethod = 'cash';
    });
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('NYEMIL BEBS POS')),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(_status),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<BluetoothDevice>(
                      value: _selectedDevice,
                      items: _devices.isEmpty
                          ? const [
                              DropdownMenuItem<BluetoothDevice>(
                                value: null,
                                child: Text('Tidak ada printer terpasang'),
                              ),
                            ]
                          : _devices
                                .map(
                                  (device) => DropdownMenuItem<BluetoothDevice>(
                                    value: device,
                                    child: Text(
                                      device.name ??
                                          device.address ??
                                          'Unknown',
                                    ),
                                  ),
                                )
                                .toList(),
                      onChanged: (device) {
                        setState(() {
                          _selectedDevice = device;
                        });
                      },
                      decoration: const InputDecoration(
                        labelText: 'Printer Bluetooth',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: ElevatedButton(
                            onPressed: _refreshDevices,
                            child: const Text('Refresh'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: ElevatedButton(
                            onPressed: _connected
                                ? _disconnectPrinter
                                : _connectPrinter,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: _connected
                                  ? Colors.red
                                  : Colors.green,
                            ),
                            child: Text(_connected ? 'Disconnect' : 'Connect'),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    ElevatedButton(
                      onPressed: _syncing ? null : _checkout,
                      child: _syncing
                          ? const SizedBox(
                              height: 18,
                              width: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Bayar & Sync'),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Expanded(
              child: Row(
                children: [
                  Expanded(
                    child: Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Produk',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 10),
                            Expanded(
                              child: ListView.builder(
                                itemCount: _products.length,
                                itemBuilder: (context, index) {
                                  final product = _products[index];
                                  return ListTile(
                                    title: Text(product.name),
                                    subtitle: Text(
                                      'Rp ${product.price.toStringAsFixed(0)}',
                                    ),
                                    trailing: IconButton(
                                      icon: const Icon(Icons.add_shopping_cart),
                                      onPressed: () =>
                                          _addProductToCart(product),
                                    ),
                                  );
                                },
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Card(
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
                                        return ListTile(
                                          title: Text(item.product.name),
                                          subtitle: Text(
                                            'Rp ${item.subtotal.toStringAsFixed(0)}',
                                          ),
                                          leading: IconButton(
                                            icon: const Icon(
                                              Icons.remove_circle_outline,
                                            ),
                                            onPressed: () =>
                                                _changeQuantity(item, -1),
                                          ),
                                          trailing: Row(
                                            mainAxisSize: MainAxisSize.min,
                                            children: [
                                              Text('x${item.quantity}'),
                                              IconButton(
                                                icon: const Icon(
                                                  Icons.add_circle_outline,
                                                ),
                                                onPressed: () =>
                                                    _changeQuantity(item, 1),
                                              ),
                                              IconButton(
                                                icon: const Icon(
                                                  Icons.delete_outline,
                                                ),
                                                onPressed: () =>
                                                    _removeCartItem(item),
                                              ),
                                            ],
                                          ),
                                        );
                                      },
                                    ),
                            ),
                            const Divider(),
                            Text(
                              'Subtotal: Rp ${_subtotal.toStringAsFixed(0)}',
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
