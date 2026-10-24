import 'dart:convert';
import 'package:blue_thermal_printer/blue_thermal_printer.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'brand_colors.dart';
import 'receipt_settings.dart';

const String _reconBackendUrl = 'https://kasir.madignet.site/api';

class ReconciliationPage extends StatefulWidget {
  final Map<String, String> apiHeaders;
  final BlueThermalPrinter printer;
  final String kasirName;
  final ReceiptSettings receiptSettings;

  const ReconciliationPage({
    super.key,
    required this.apiHeaders,
    required this.printer,
    required this.kasirName,
    this.receiptSettings = ReceiptSettings.defaults,
  });

  @override
  State<ReconciliationPage> createState() => _ReconciliationPageState();
}

class _ReconciliationPageState extends State<ReconciliationPage> {
  bool _loading = false;
  double _cashSales = 0;
  double _qrisSales = 0;
  double _cashExpenses = 0;
  int _totalTransactions = 0;

  final TextEditingController _startingCashController = TextEditingController(text: '0');
  final TextEditingController _actualCashController = TextEditingController();
  final TextEditingController _notesController = TextEditingController();
  String _shiftName = 'Harian';

  final List<String> _shifts = ['Harian', 'Shift 1 (Pagi)', 'Shift 2 (Sore/Malam)'];

  @override
  void initState() {
    super.initState();
    _fetchSummary();
  }

  Future<void> _fetchSummary() async {
    setState(() => _loading = true);
    try {
      final res = await http
          .get(
            Uri.parse('$_reconBackendUrl/reconciliation/summary'),
            headers: widget.apiHeaders,
          )
          .timeout(const Duration(seconds: 8));

      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (mounted) {
          setState(() {
            _cashSales = (data['cash_sales'] as num?)?.toDouble() ?? 0.0;
            _qrisSales = (data['qris_sales'] as num?)?.toDouble() ?? 0.0;
            _cashExpenses = (data['cash_expenses'] as num?)?.toDouble() ?? 0.0;
            _totalTransactions = (data['total_transactions'] as num?)?.toInt() ?? 0;
            _loading = false;
          });
        }
        return;
      }
    } catch (_) {
      // offline
    }

    if (mounted) {
      setState(() => _loading = false);
    }
  }

  double get _startingCash {
    final raw = _startingCashController.text.replaceAll(RegExp(r'[^0-9]'), '');
    return double.tryParse(raw) ?? 0.0;
  }

  double? get _actualCash {
    if (_actualCashController.text.trim().isEmpty) return null;
    final raw = _actualCashController.text.replaceAll(RegExp(r'[^0-9]'), '');
    return double.tryParse(raw);
  }

  double get _expectedCash {
    return _startingCash + _cashSales - _cashExpenses;
  }

  double? get _difference {
    if (_actualCash == null) return null;
    return _actualCash! - _expectedCash;
  }

  String _formatRp(dynamic val) {
    double v = 0;
    if (val is num) v = val.toDouble();
    if (val is String) v = double.tryParse(val) ?? 0;
    final s = v.toInt().toString();
    final parts = <String>[];
    for (int i = s.length; i > 0; i -= 3) {
      final start = i - 3 < 0 ? 0 : i - 3;
      parts.insert(0, s.substring(start, i));
    }
    return 'Rp ${parts.join('.')}';
  }

  Future<void> _submitAndPrint() async {
    if (_actualCash == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Uang fisik di laci wajib dihitung dan diisi')),
      );
      return;
    }

    final payload = {
      'shift_name': _shiftName,
      'starting_cash': _startingCash,
      'actual_cash': _actualCash,
      'notes': _notesController.text.trim(),
    };

    setState(() => _loading = true);

    Map<String, dynamic>? resultData;

    try {
      final res = await http
          .post(
            Uri.parse('$_reconBackendUrl/reconciliation'),
            headers: widget.apiHeaders,
            body: jsonEncode(payload),
          )
          .timeout(const Duration(seconds: 10));

      if (res.statusCode == 200 || res.statusCode == 201) {
        final body = jsonDecode(res.body);
        resultData = body['data'];
      }
    } catch (_) {
      // offline save if needed
    }

    setState(() => _loading = false);

    // Cetak struk tutup kas jika printer terhubung
    await _printReconciliationReceipt(resultData);

    if (!mounted) return;

    await showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Row(
          children: const [
            Icon(Icons.check_circle, color: Colors.green),
            SizedBox(width: 8),
            Text('Tutup Kas Berhasil'),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Kas Seharusnya: ${_formatRp(_expectedCash)}'),
            Text('Uang Fisik: ${_formatRp(_actualCash!)}'),
            const SizedBox(height: 8),
            Text(
              'Selisih: ${_difference == 0 ? "PAS" : (_difference! > 0 ? "+${_formatRp(_difference!)} (LEBIH)" : "-${_formatRp(_difference!.abs())} (KURANG)")}',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: _difference == 0 ? Colors.green : (_difference! > 0 ? Colors.blue : Colors.red),
              ),
            ),
          ],
        ),
        actions: [
          ElevatedButton(
            onPressed: () {
              Navigator.pop(ctx);
              Navigator.pop(context); // kembali ke POS
            },
            style: ElevatedButton.styleFrom(backgroundColor: kBrandBlue, foregroundColor: Colors.white),
            child: const Text('Selesai'),
          ),
        ],
      ),
    );
  }

  Future<void> _printReconciliationReceipt(Map<String, dynamic>? data) async {
    final isConnected = await widget.printer.isConnected;
    if (isConnected != true) return;

    final storeName = data?['store_name'] ?? widget.receiptSettings.store;
    final now = DateTime.now();
    final timeStr = '${now.day.toString().padLeft(2, '0')}/${now.month.toString().padLeft(2, '0')}/${now.year} ${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}';

    widget.printer.printCustom(storeName.toUpperCase(), 2, 1);
    widget.printer.printCustom('LAPORAN TUTUP KASIR', 1, 1);
    widget.printer.printCustom('--------------------------------', 1, 1);
    widget.printer.printLeftRight('Waktu:', timeStr, 1);
    widget.printer.printLeftRight('Kasir:', widget.kasirName, 1);
    widget.printer.printLeftRight('Shift:', _shiftName, 1);
    widget.printer.printCustom('--------------------------------', 1, 1);

    widget.printer.printLeftRight('Modal Awal:', _formatRp(_startingCash), 1);
    widget.printer.printLeftRight('Penjualan Tunai (+):', _formatRp(_cashSales), 1);
    widget.printer.printLeftRight('Pengeluaran (-):', _formatRp(_cashExpenses), 1);
    widget.printer.printCustom('--------------------------------', 1, 1);
    widget.printer.printLeftRight('Kas Seharusnya:', _formatRp(_expectedCash), 1);
    widget.printer.printLeftRight('Uang Fisik Laci:', _formatRp(_actualCash!), 1);
    widget.printer.printCustom('--------------------------------', 1, 1);

    final diff = _difference ?? 0.0;
    final diffLabel = diff == 0 ? 'PAS (Rp 0)' : (diff > 0 ? '+${_formatRp(diff)} (LEBIH)' : '-${_formatRp(diff.abs())} (KURANG)');
    widget.printer.printLeftRight('SELISIH:', diffLabel, 1);
    widget.printer.printCustom('--------------------------------', 1, 1);
    widget.printer.printLeftRight('Non-Tunai (QRIS):', _formatRp(_qrisSales), 1);
    widget.printer.printLeftRight('Total Transaksi:', '$_totalTransactions Struk', 1);

    if (_notesController.text.trim().isNotEmpty) {
      widget.printer.printCustom('Catatan: ${_notesController.text.trim()}', 1, 0);
    }

    widget.printer.printNewLine();
    widget.printer.printLeftRight('Kasir,', 'Supervisor,', 1);
    widget.printer.printNewLine();
    widget.printer.printNewLine();
    widget.printer.printLeftRight('(${widget.kasirName})', '(...............)', 1);

    for (int i = 0; i < widget.receiptSettings.feedLines; i++) {
      widget.printer.printNewLine();
    }
    if (widget.receiptSettings.autoCut) {
      widget.printer.paperCut();
    }
  }

  @override
  Widget build(BuildContext context) {
    final diff = _difference;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Rekonsiliasi (Tutup Kas)'),
        backgroundColor: kBrandBlue,
        foregroundColor: Colors.white,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                // Info Otomatis Penjualan Shift
                Card(
                  elevation: 0,
                  color: Colors.grey.shade50,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                    side: BorderSide(color: Colors.grey.shade200),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Transaksi Hari Ini (Sistem)',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                            ),
                            Text(
                              '$_totalTransactions Transaksi',
                              style: const TextStyle(fontSize: 12, color: Colors.grey),
                            ),
                          ],
                        ),
                        const Divider(height: 18),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Penjualan Tunai (+):', style: TextStyle(fontSize: 13)),
                            Text(
                              _formatRp(_cashSales),
                              style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.green),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Pengeluaran Kasir (-):', style: TextStyle(fontSize: 13)),
                            Text(
                              _formatRp(_cashExpenses),
                              style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.red),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Penjualan QRIS (Bank):', style: TextStyle(fontSize: 13, color: Colors.blueGrey)),
                            Text(
                              _formatRp(_qrisSales),
                              style: const TextStyle(color: Colors.blue),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 14),

                // Pilihan Shift
                DropdownButtonFormField<String>(
                  initialValue: _shiftName,
                  decoration: InputDecoration(
                    labelText: 'Pilih Shift',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  items: _shifts.map((s) => DropdownMenuItem(value: s, child: Text(s))).toList(),
                  onChanged: (v) {
                    if (v != null) setState(() => _shiftName = v);
                  },
                ),
                const SizedBox(height: 14),

                // Modal Awal
                TextField(
                  controller: _startingCashController,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    prefixText: 'Rp ',
                    labelText: 'Modal Awal Kasir (Uang Kembalian Laci)',
                    helperText: 'Uang receh pecahan di laci saat buka shift',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onChanged: (_) => setState(() {}),
                ),
                const SizedBox(height: 14),

                // Kas Seharusnya Card
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.green.shade50,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: Colors.green.shade200),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: const [
                          Text('Kas Seharusnya di Laci', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Colors.green)),
                          Text('(Modal + Tunai - Pengeluaran)', style: TextStyle(fontSize: 10, color: Colors.black54)),
                        ],
                      ),
                      Text(
                        _formatRp(_expectedCash),
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Colors.green.shade900),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),

                // Uang Fisik Di Laci
                TextField(
                  controller: _actualCashController,
                  keyboardType: TextInputType.number,
                  autofocus: true,
                  style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                  decoration: InputDecoration(
                    prefixText: 'Rp ',
                    labelText: 'Uang Fisik Dihitung di Laci *',
                    helperText: 'Hitung lembar & koin uang nyata di laci kasir',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onChanged: (_) => setState(() {}),
                ),
                const SizedBox(height: 14),

                // Selisih Banner
                if (diff != null) ...[
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    decoration: BoxDecoration(
                      color: diff == 0 ? Colors.green.shade50 : (diff > 0 ? Colors.blue.shade50 : Colors.red.shade50),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: diff == 0 ? Colors.green.shade300 : (diff > 0 ? Colors.blue.shade300 : Colors.red.shade300),
                      ),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Status Selisih:',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: diff == 0 ? Colors.green.shade900 : (diff > 0 ? Colors.blue.shade900 : Colors.red.shade900),
                          ),
                        ),
                        Text(
                          diff == 0
                              ? 'PAS (Rp 0)'
                              : (diff > 0 ? '+${_formatRp(diff)} (LEBIH)' : '-${_formatRp(diff.abs())} (KURANG)'),
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                            color: diff == 0 ? Colors.green.shade900 : (diff > 0 ? Colors.blue.shade900 : Colors.red.shade900),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                ],

                // Catatan
                TextField(
                  controller: _notesController,
                  decoration: InputDecoration(
                    labelText: 'Catatan Kasir (Opsional)',
                    hintText: 'Alasan jika ada selisih uang...',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
                const SizedBox(height: 24),

                // Tombol Simpan
                ElevatedButton.icon(
                  onPressed: _submitAndPrint,
                  icon: const Icon(Icons.print),
                  label: const Text('Simpan & Cetak Struk Tutup Kas'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: kBrandBlue,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ],
            ),
    );
  }
}
