import 'dart:convert';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:blue_thermal_printer/blue_thermal_printer.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';
import 'dart:io';

const String _historyBackendUrl = 'https://kasir.madignet.site/api';

class HistoryPage extends StatefulWidget {
  final Map<String, String> apiHeaders;
  final BlueThermalPrinter printer;
  final String kasirName;

  const HistoryPage({
    super.key,
    required this.apiHeaders,
    required this.printer,
    required this.kasirName,
  });

  @override
  State<HistoryPage> createState() => _HistoryPageState();
}

class _HistoryPageState extends State<HistoryPage> {
  late Future<List<Map<String, dynamic>>> _ordersFuture;

  @override
  void initState() {
    super.initState();
    _ordersFuture = _fetchOrders();
  }

  Future<List<Map<String, dynamic>>> _fetchOrders() async {
    final response = await http.get(
      Uri.parse('$_historyBackendUrl/orders'),
      headers: widget.apiHeaders,
    );
    if (response.statusCode != 200) {
      throw Exception('Gagal memuat riwayat transaksi (${response.statusCode})');
    }
    final data = jsonDecode(response.body);
    return List<Map<String, dynamic>>.from(data['data'] ?? []);
  }

  String _formatRp(dynamic val) {
    double v = 0;
    if (val is num) v = val.toDouble();
    if (val is String) v = double.tryParse(val) ?? 0;
    return 'Rp ${v.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d)(?=(\d{3})+$)'), (m) => '${m[1]}.')}';
  }

  double _parseDouble(dynamic val) {
    if (val == null) return 0.0;
    if (val is num) return val.toDouble();
    if (val is String) return double.tryParse(val) ?? 0.0;
    return 0.0;
  }

  String _formatDate(String raw) {
    try {
      final dt = DateTime.parse(raw).toLocal();
      return '${dt.day.toString().padLeft(2, '0')}/${dt.month.toString().padLeft(2, '0')}/${dt.year} ${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return raw;
    }
  }

  List<Map<String, dynamic>> _buildItems(List rawItems) {
    return rawItems.map<Map<String, dynamic>>((i) {
      final pName = i['product_name'] ?? 'Produk';
      final vName = i['variant_name'];
      final fullName = (vName != null && vName.toString().isNotEmpty)
          ? '$pName - $vName'
          : pName;
      return {
        'name': fullName,
        'quantity': (i['quantity'] is num)
            ? (i['quantity'] as num).toInt()
            : (int.tryParse(i['quantity']?.toString() ?? '1') ?? 1),
        'price': _parseDouble(i['price']),
        'subtotal': _parseDouble(i['subtotal']),
        'toppings': i['toppings'] ?? [],
      };
    }).toList();
  }

  Future<void> _printReceipt({
    required String invoice,
    required String customer,
    required String method,
    required double total,
    required String createdAt,
    required List<Map<String, dynamic>> items,
  }) async {
    try {
      await widget.printer.printCustom('NYEMIL BEBS', 3, 1);
      await widget.printer.printCustom('Purnama Town House Blok H/1', 1, 1);
      await widget.printer.printCustom('Telp: +62 823-9943-0312', 1, 1);
      await widget.printer.printNewLine();
      await widget.printer.printCustom('No: $invoice', 1, 0);
      await widget.printer.printCustom('Tgl: $createdAt', 1, 0);
      await widget.printer.printCustom('Kasir: ${widget.kasirName}', 1, 0);
      await widget.printer.printCustom('Pelanggan: ${customer.isEmpty ? 'Umum' : customer}', 1, 0);
      await widget.printer.printNewLine();

      for (final item in items) {
        await widget.printer.printCustom(item['name'] ?? '', 1, 0);
        final int qty = item['quantity'] as int;
        final double price = item['price'] as double;
        final double sub = item['subtotal'] as double;
        await widget.printer.printLeftRight('$qty x ${price.toStringAsFixed(0)}', sub.toStringAsFixed(0), 0);
        final List tops = item['toppings'] ?? [];
        for (final top in tops) {
          final String tn = top is String ? top : (top['topping_name'] as String? ?? '');
          if (tn.isNotEmpty) await widget.printer.printCustom('+ $tn', 1, 0);
        }
      }

      await widget.printer.printCustom('--------------------------', 1, 1);
      await widget.printer.printLeftRight('TOTAL', _formatRp(total), 1);
      await widget.printer.printLeftRight('Metode', method.toUpperCase(), 0);
      await widget.printer.printNewLine();
      await widget.printer.printCustom('Terima Kasih atas Kunjungan Anda!', 1, 1);
      await widget.printer.printCustom('~ Nyemil Bebs ~', 1, 1);
      await widget.printer.printNewLine();
      await widget.printer.printNewLine();
      await widget.printer.paperCut();

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Struk berhasil dicetak.'), backgroundColor: Colors.green));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Gagal mencetak: $e'), backgroundColor: Colors.red));
      }
    }
  }

  Future<void> _shareAsImage({
    required String invoice,
    required String customer,
    required String method,
    required double total,
    required String createdAt,
    required List<Map<String, dynamic>> items,
  }) async {
    final repaintKey = GlobalKey();
    bool popped = false;

    if (!mounted) return;

    showDialog(
      context: context,
      barrierDismissible: false,
      barrierColor: Colors.black38,
      builder: (_) => Center(
        child: Material(
          color: Colors.transparent,
          child: RepaintBoundary(
            key: repaintKey,
            child: _ReceiptImageWidget(
              invoice: invoice,
              customer: customer,
              method: method,
              total: total,
              createdAt: createdAt,
              items: items,
              kasirName: widget.kasirName,
              formatRp: _formatRp,
            ),
          ),
        ),
      ),
    ).then((_) => popped = true);

    await Future.delayed(const Duration(milliseconds: 400));

    try {
      final boundary = repaintKey.currentContext?.findRenderObject() as RenderRepaintBoundary?;
      if (boundary == null) throw Exception('Gagal merender struk.');

      final image = await boundary.toImage(pixelRatio: 3.0);
      final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
      final Uint8List pngBytes = byteData!.buffer.asUint8List();

      final tempDir = await getTemporaryDirectory();
      final safe = invoice.replaceAll(RegExp(r'[^\w]'), '_');
      final file = File('${tempDir.path}/struk_$safe.png');
      await file.writeAsBytes(pngBytes);

      if (mounted && !popped) Navigator.of(context).pop();

      await Share.shareXFiles(
        [XFile(file.path, mimeType: 'image/png')],
        subject: 'Struk NYEMIL BEBS',
        text: 'Struk NYEMIL BEBS - $invoice\n$createdAt',
      );
    } catch (e) {
      if (mounted && !popped) Navigator.of(context).pop();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Gagal share: $e'), backgroundColor: Colors.red));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey.shade100,
      appBar: AppBar(
        title: const Text('Riwayat Transaksi'),
        backgroundColor: Colors.green,
        foregroundColor: Colors.white,
        leading: const BackButton(),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Muat ulang',
            onPressed: () => setState(() { _ordersFuture = _fetchOrders(); }),
          ),
        ],
      ),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _ordersFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [CircularProgressIndicator(color: Colors.green), SizedBox(height: 12), Text('Memuat riwayat...')]));
          }
          if (snapshot.hasError) {
            return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.cloud_off, size: 64, color: Colors.grey),
              const SizedBox(height: 12),
              Text('${snapshot.error}', textAlign: TextAlign.center, style: const TextStyle(color: Colors.grey)),
              const SizedBox(height: 16),
              ElevatedButton.icon(
                style: ElevatedButton.styleFrom(backgroundColor: Colors.green, foregroundColor: Colors.white),
                onPressed: () => setState(() { _ordersFuture = _fetchOrders(); }),
                icon: const Icon(Icons.refresh),
                label: const Text('Coba Lagi'),
              ),
            ]));
          }

          final orders = snapshot.data ?? [];
          if (orders.isEmpty) {
            return const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.receipt_long, size: 64, color: Colors.grey),
              SizedBox(height: 12),
              Text('Belum ada riwayat transaksi.', style: TextStyle(color: Colors.grey)),
            ]));
          }

          return ListView.builder(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            itemCount: orders.length,
            itemBuilder: (context, index) {
              final order = orders[index];
              final invoice = order['invoice_number'] ?? '';
              final customer = (order['customer_name'] ?? '').toString();
              final total = _parseDouble(order['total']);
              final method = (order['payment_method'] ?? 'cash').toString();
              final createdAt = _formatDate(order['created_at'] ?? '');
              final items = _buildItems(order['items'] ?? []);

              return Card(
                margin: const EdgeInsets.only(bottom: 10),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                elevation: 2,
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(children: [
                        const Icon(Icons.receipt_long, size: 18, color: Colors.green),
                        const SizedBox(width: 6),
                        Expanded(child: Text(invoice, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14))),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: method.toLowerCase() == 'cash' ? Colors.blue.shade50 : Colors.purple.shade50,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(method.toUpperCase(), style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: method.toLowerCase() == 'cash' ? Colors.blue.shade700 : Colors.purple.shade700)),
                        ),
                      ]),
                      const SizedBox(height: 4),
                      Text(customer.isEmpty ? 'Pelanggan: Umum' : 'Pelanggan: $customer', style: const TextStyle(fontSize: 12, color: Colors.grey)),
                      Text(createdAt, style: const TextStyle(fontSize: 11, color: Colors.grey)),
                      const SizedBox(height: 6),
                      ...items.take(3).map((item) => Padding(
                        padding: const EdgeInsets.only(bottom: 2),
                        child: Row(children: [
                          const SizedBox(width: 4),
                          Text('• ${item['quantity']}x ', style: const TextStyle(fontSize: 12)),
                          Expanded(child: Text(item['name'], style: const TextStyle(fontSize: 12), overflow: TextOverflow.ellipsis)),
                          Text(_formatRp(item['subtotal']), style: const TextStyle(fontSize: 12)),
                        ]),
                      )),
                      if (items.length > 3)
                        Text('+ ${items.length - 3} item lainnya', style: const TextStyle(fontSize: 11, color: Colors.grey)),
                      const Divider(height: 16),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(_formatRp(total), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Colors.green)),
                          Row(children: [
                            OutlinedButton.icon(
                              icon: const Icon(Icons.share, size: 15),
                              label: const Text('Share', style: TextStyle(fontSize: 12)),
                              style: OutlinedButton.styleFrom(foregroundColor: Colors.blue, side: const BorderSide(color: Colors.blue), padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4), tapTargetSize: MaterialTapTargetSize.shrinkWrap),
                              onPressed: () => _shareAsImage(invoice: invoice, customer: customer, method: method, total: total, createdAt: createdAt, items: items),
                            ),
                            const SizedBox(width: 8),
                            ElevatedButton.icon(
                              icon: const Icon(Icons.print, size: 15),
                              label: const Text('Print Ulang', style: TextStyle(fontSize: 12)),
                              style: ElevatedButton.styleFrom(backgroundColor: Colors.green, foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4), tapTargetSize: MaterialTapTargetSize.shrinkWrap),
                              onPressed: () => _printReceipt(invoice: invoice, customer: customer, method: method, total: total, createdAt: createdAt, items: items),
                            ),
                          ]),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _ReceiptImageWidget extends StatelessWidget {
  final String invoice;
  final String customer;
  final String method;
  final double total;
  final String createdAt;
  final List<Map<String, dynamic>> items;
  final String kasirName;
  final String Function(dynamic) formatRp;

  const _ReceiptImageWidget({required this.invoice, required this.customer, required this.method, required this.total, required this.createdAt, required this.items, required this.kasirName, required this.formatRp});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 300,
      color: Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text('NYEMIL BEBS', style: TextStyle(fontFamily: 'Courier', fontSize: 20, fontWeight: FontWeight.bold)),
          const Text('Purnama Town House Blok H/1', style: TextStyle(fontFamily: 'Courier', fontSize: 11)),
          const Text('Telp: +62 823-9943-0312', style: TextStyle(fontFamily: 'Courier', fontSize: 11)),
          const SizedBox(height: 8),
          const Divider(thickness: 1),
          _row('No', invoice),
          _row('Tgl', createdAt),
          _row('Kasir', kasirName),
          _row('Pelanggan', customer.isEmpty ? 'Umum' : customer),
          const Divider(thickness: 1),
          ...items.map((item) {
            final int qty = item['quantity'] as int;
            final double price = item['price'] as double;
            final double sub = item['subtotal'] as double;
            final List tops = item['toppings'] ?? [];
            return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(item['name'] ?? '', style: const TextStyle(fontFamily: 'Courier', fontSize: 12, fontWeight: FontWeight.bold)),
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text('$qty x ${price.toStringAsFixed(0)}', style: const TextStyle(fontFamily: 'Courier', fontSize: 11)),
                Text(sub.toStringAsFixed(0), style: const TextStyle(fontFamily: 'Courier', fontSize: 11)),
              ]),
              ...tops.map((top) {
                final tn = top is String ? top : (top['topping_name'] as String? ?? '');
                return Text('+ $tn', style: const TextStyle(fontFamily: 'Courier', fontSize: 10, color: Colors.grey));
              }),
              const SizedBox(height: 4),
            ]);
          }),
          const Divider(thickness: 1),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            const Text('TOTAL', style: TextStyle(fontFamily: 'Courier', fontSize: 13, fontWeight: FontWeight.bold)),
            Text(formatRp(total), style: const TextStyle(fontFamily: 'Courier', fontSize: 13, fontWeight: FontWeight.bold)),
          ]),
          _row('Metode', method.toUpperCase()),
          const SizedBox(height: 12),
          const Text('Terima Kasih atas Kunjungan Anda!', textAlign: TextAlign.center, style: TextStyle(fontFamily: 'Courier', fontSize: 11)),
          const Text('~ Nyemil Bebs ~', textAlign: TextAlign.center, style: TextStyle(fontFamily: 'Courier', fontSize: 11)),
          const SizedBox(height: 8),
        ],
      ),
    );
  }

  Widget _row(String label, String value) {
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('$label: ', style: const TextStyle(fontFamily: 'Courier', fontSize: 11)),
      Expanded(child: Text(value, style: const TextStyle(fontFamily: 'Courier', fontSize: 11))),
    ]);
  }
}