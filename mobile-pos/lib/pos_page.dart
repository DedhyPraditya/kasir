import 'dart:convert';

import 'package:blue_thermal_printer/blue_thermal_printer.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:permission_handler/permission_handler.dart';

const String backendUrl = 'https://kasir.madignet.site/api';

class PosHomePage extends StatefulWidget {
  final String apiToken;

  const PosHomePage({super.key, required this.apiToken});

  @override
  State<PosHomePage> createState() => _PosHomePageState();
}

class Product {
  final String id;
  final String name;
  final String description;
  final double price;
  final List<Variant> variants;

  const Product({
    required this.id,
    required this.name,
    required this.description,
    required this.price,
    required this.variants,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    final price = json['base_price'] ?? 0;
    return Product(
      id: json['id'] as String,
      name: json['name'] as String,
      description: json['description'] as String? ?? '',
      price: (price is int) ? price.toDouble() : (price as num).toDouble(),
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

  BluetoothDevice? _selectedDevice;
  bool _connected = false;
  bool _syncing = false;
  bool _loadingProducts = false;
  String _status = 'Menyiapkan printer...';
  String _customerName = '';
  String _paymentMethod = 'cash';
  String _amountPaid = '';

  double get _subtotal => _cart.fold(0, (value, item) => value + item.subtotal);

  @override
  void initState() {
    super.initState();
    _refreshDevices();
    _fetchProducts();
    _fetchToppings();
  }

  Map<String, String> get _apiHeaders => {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Api-Token': widget.apiToken,
    'Authorization': 'Bearer ${widget.apiToken}',
  };

  Future<void> _fetchProducts() async {
    setState(() {
      _loadingProducts = true;
    });

    try {
      final response = await http.get(
        Uri.parse('$backendUrl/products'),
        headers: _apiHeaders,
      );

      if (response.statusCode == 200) {
        final jsonBody = jsonDecode(response.body) as Map<String, dynamic>;
        final data = jsonBody['data'] as List<dynamic>;
        final products = data
            .map((item) => Product.fromJson(item as Map<String, dynamic>))
            .toList();

        if (!mounted) return;
        setState(() {
          _products
            ..clear()
            ..addAll(products);
        });
      } else {
        _showMessage('Gagal memuat produk: ${response.statusCode}');
      }
    } catch (error) {
      _showMessage('Gagal memuat produk: $error');
    }

    if (!mounted) return;
    setState(() {
      _loadingProducts = false;
    });
  }

  Future<void> _fetchToppings() async {
    try {
      final response = await http.get(
        Uri.parse('$backendUrl/toppings'),
        headers: _apiHeaders,
      );

      if (response.statusCode == 200) {
        final jsonBody = jsonDecode(response.body) as Map<String, dynamic>;
        final data = jsonBody['data'] as List<dynamic>;
        final toppings = data
            .map((item) => Topping.fromJson(item as Map<String, dynamic>))
            .toList();

        if (!mounted) return;
        setState(() {
          _toppings
            ..clear()
            ..addAll(toppings);
        });
      } else {
        _showMessage('Gagal memuat topping: ${response.statusCode}');
      }
    } catch (error) {
      _showMessage('Gagal memuat topping: $error');
    }
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

    if (product.variants.isNotEmpty || _toppings.isNotEmpty) {
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
                                    '${variant.name} - Rp ${variant.price.toStringAsFixed(0)}',
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
                      if (_toppings.isNotEmpty) ...[
                        const Text('Toppings'),
                        const SizedBox(height: 8),
                        ..._toppings.map(
                          (topping) => CheckboxListTile(
                            title: Text(
                              '${topping.name} (+Rp ${topping.price.toStringAsFixed(0)})',
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
        final label = item.variant != null
            ? '${item.product.name} (${item.variant!.name})'
            : item.product.name;

        await _printer.printLeftRight(
          label,
          item.subtotal.toStringAsFixed(0),
          1,
        );

        await _printer.printCustom(
          '  x${item.quantity} @ Rp ${item.unitPrice.toStringAsFixed(0)}',
          1,
          0,
        );

        for (final topping in item.toppings) {
          await _printer.printCustom(
            '    + ${topping.name} Rp ${topping.price.toStringAsFixed(0)}',
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

    setState(() {
      _syncing = true;
    });

    try {
      final response = await http.post(
        Uri.parse('$backendUrl/orders/sync'),
        headers: _apiHeaders,
        body: jsonEncode(payload),
      );

      if (response.statusCode == 201) {
        _showMessage('Order berhasil disimpan ke dashboard.');
      } else {
        _showMessage('Sinkron gagal: ${response.statusCode}.');
      }
    } catch (error) {
      _showMessage('Sinkron gagal: $error');
    }

    if (!mounted) return;
    setState(() {
      _syncing = false;
    });
  }

  Future<void> _checkout() async {
    if (_cart.isEmpty) {
      _showMessage('Keranjang kosong. Tambahkan produk terlebih dahulu.');
      return;
    }

    _customerName = '';
    _paymentMethod = 'cash';
    _amountPaid = '';

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
                      'Total: Rp ${_subtotal.toStringAsFixed(0)}',
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
                              'Rp ${change.toStringAsFixed(0)}',
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
                          children: const [
                            Icon(
                              Icons.qr_code,
                              size: 64,
                              color: Colors.black54,
                            ),
                            SizedBox(height: 12),
                            Text(
                              'Silakan scan QRIS untuk menyelesaikan pembayaran.',
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

    final invoiceNumber = 'INV-${DateTime.now().millisecondsSinceEpoch}';
    await _syncOrder(invoiceNumber);

    if (_connected) {
      await _printReceipt(invoiceNumber);
    }

    setState(() {
      _cart.clear();
      _customerName = '';
      _paymentMethod = 'cash';
      _amountPaid = '';
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
                      initialValue: _selectedDevice,
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
                              child: _loadingProducts
                                  ? const Center(
                                      child: CircularProgressIndicator(),
                                    )
                                  : _products.isEmpty
                                  ? const Center(
                                      child: Text('Tidak ada produk tersedia'),
                                    )
                                  : GridView.builder(
                                      gridDelegate:
                                          const SliverGridDelegateWithFixedCrossAxisCount(
                                            crossAxisCount: 2,
                                            mainAxisSpacing: 12,
                                            crossAxisSpacing: 12,
                                            childAspectRatio: 1.1,
                                          ),
                                      itemCount: _products.length,
                                      itemBuilder: (context, index) {
                                        final product = _products[index];
                                        return Card(
                                          elevation: 2,
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
                                            child: Padding(
                                              padding: const EdgeInsets.all(12),
                                              child: Column(
                                                crossAxisAlignment:
                                                    CrossAxisAlignment.start,
                                                children: [
                                                  Expanded(
                                                    child: Text(
                                                      product.name,
                                                      style: const TextStyle(
                                                        fontWeight:
                                                            FontWeight.bold,
                                                        fontSize: 16,
                                                      ),
                                                    ),
                                                  ),
                                                  Text(
                                                    'Rp ${product.price.toStringAsFixed(0)}',
                                                    style: const TextStyle(
                                                      color: Colors.green,
                                                      fontWeight:
                                                          FontWeight.bold,
                                                    ),
                                                  ),
                                                  const SizedBox(height: 8),
                                                  Align(
                                                    alignment:
                                                        Alignment.bottomRight,
                                                    child: ElevatedButton.icon(
                                                      icon: const Icon(
                                                        Icons.add_shopping_cart,
                                                        size: 18,
                                                      ),
                                                      label: const Text(
                                                        'Pilih',
                                                      ),
                                                      style:
                                                          ElevatedButton.styleFrom(
                                                            minimumSize:
                                                                const Size(
                                                                  100,
                                                                  36,
                                                                ),
                                                          ),
                                                      onPressed: () =>
                                                          _addProductToCart(
                                                            product,
                                                          ),
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ),
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
                                          child: ListTile(
                                            contentPadding:
                                                const EdgeInsets.symmetric(
                                                  horizontal: 12,
                                                  vertical: 10,
                                                ),
                                            title: Text(
                                              item.product.name,
                                              style: const TextStyle(
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                            subtitle: Column(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.start,
                                              children: [
                                                if (item.variant != null)
                                                  Text(
                                                    'Varian: ${item.variant!.name}',
                                                  ),
                                                if (item.toppings.isNotEmpty)
                                                  Text(
                                                    'Topping: ${item.toppings.map((t) => t.name).join(', ')}',
                                                  ),
                                                Text(
                                                  'Rp ${item.subtotal.toStringAsFixed(0)}',
                                                ),
                                              ],
                                            ),
                                            leading: Column(
                                              mainAxisSize: MainAxisSize.min,
                                              children: [
                                                IconButton(
                                                  icon: const Icon(
                                                    Icons.remove_circle_outline,
                                                  ),
                                                  onPressed: () =>
                                                      _changeQuantity(item, -1),
                                                ),
                                                Text('${item.quantity}'),
                                                IconButton(
                                                  icon: const Icon(
                                                    Icons.add_circle_outline,
                                                  ),
                                                  onPressed: () =>
                                                      _changeQuantity(item, 1),
                                                ),
                                              ],
                                            ),
                                            trailing: IconButton(
                                              icon: const Icon(
                                                Icons.delete_outline,
                                              ),
                                              onPressed: () =>
                                                  _removeCartItem(item),
                                            ),
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
