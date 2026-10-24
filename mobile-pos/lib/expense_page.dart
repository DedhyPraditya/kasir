import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'brand_colors.dart';
import 'offline_store.dart';

const String _expenseBackendUrl = 'https://kasir.madignet.site/api';

class ExpensePage extends StatefulWidget {
  final Map<String, String> apiHeaders;
  final String kasirName;

  const ExpensePage({
    super.key,
    required this.apiHeaders,
    required this.kasirName,
  });

  @override
  State<ExpensePage> createState() => _ExpensePageState();
}

class _ExpensePageState extends State<ExpensePage> {
  bool _loading = false;
  List<Map<String, dynamic>> _expenses = [];
  double _totalAmount = 0;
  String _selectedCategory = 'Semua';

  final List<String> _categories = [
    'Semua',
    'Operasional',
    'Bahan Baku',
    'Utilitas',
    'Gaji/Uang Makan',
    'Lainnya',
  ];

  @override
  void initState() {
    super.initState();
    _loadExpenses();
  }

  Future<void> _loadExpenses() async {
    setState(() => _loading = true);

    // Sync pending offline expenses first
    await _syncPendingExpenses();

    try {
      final response = await http
          .get(
            Uri.parse('$_expenseBackendUrl/expenses'),
            headers: widget.apiHeaders,
          )
          .timeout(const Duration(seconds: 8));

      if (response.statusCode == 200) {
        final body = jsonDecode(response.body);
        final list = List<Map<String, dynamic>>.from(body['data'] ?? []);
        final total = (body['total_amount'] as num?)?.toDouble() ?? 0.0;

        await OfflineStore.cacheTodayExpenses(list);

        if (mounted) {
          setState(() {
            _expenses = list;
            _totalAmount = total;
            _loading = false;
          });
        }
        return;
      }
    } catch (_) {
      // Offline fallback
    }

    // Load from cache + pending offline
    final cached = await OfflineStore.getCachedTodayExpenses();
    final pending = await OfflineStore.getPendingExpenses();

    final all = <Map<String, dynamic>>[
      ...pending.map((e) => {...e, 'is_pending': true}),
      ...cached.map((e) => Map<String, dynamic>.from(e as Map)),
    ];

    double sum = 0;
    for (final item in all) {
      sum += (item['amount'] as num?)?.toDouble() ?? 0.0;
    }

    if (mounted) {
      setState(() {
        _expenses = all;
        _totalAmount = sum;
        _loading = false;
      });
    }
  }

  Future<void> _syncPendingExpenses() async {
    final pending = await OfflineStore.getPendingExpenses();
    if (pending.isEmpty) return;

    for (final exp in pending) {
      try {
        final res = await http
            .post(
              Uri.parse('$_expenseBackendUrl/expenses'),
              headers: widget.apiHeaders,
              body: jsonEncode(exp),
            )
            .timeout(const Duration(seconds: 5));

        if (res.statusCode == 200 || res.statusCode == 201) {
          await OfflineStore.removePendingExpense(exp['id'] as String);
        }
      } catch (_) {
        break; // Stop retry on network failure
      }
    }
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

  void _showAddExpenseDialog() {
    final amountController = TextEditingController();
    final descController = TextEditingController();
    String category = 'Operasional';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(context).viewInsets.bottom + 20,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Catat Pengeluaran',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () => Navigator.pop(context),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: amountController,
                    keyboardType: TextInputType.number,
                    autofocus: true,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: Colors.red,
                    ),
                    decoration: InputDecoration(
                      prefixText: 'Rp ',
                      labelText: 'Nominal Pengeluaran *',
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  DropdownButtonFormField<String>(
                    initialValue: category,
                    decoration: InputDecoration(
                      labelText: 'Kategori',
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    items: _categories
                        .where((c) => c != 'Semua')
                        .map((c) => DropdownMenuItem(value: c, child: Text(c)))
                        .toList(),
                    onChanged: (v) {
                      if (v != null) setModalState(() => category = v);
                    },
                  ),
                  const SizedBox(height: 14),
                  TextField(
                    controller: descController,
                    textCapitalization: TextCapitalization.sentences,
                    decoration: InputDecoration(
                      labelText: 'Keterangan *',
                      hintText: 'Misal: Beli es batu, isi ulang gas 3kg',
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: () async {
                      final raw = amountController.text.replaceAll(RegExp(r'[^0-9]'), '');
                      final amount = double.tryParse(raw);
                      final desc = descController.text.trim();

                      if (amount == null || amount <= 0) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Nominal harus diisi dengan benar')),
                        );
                        return;
                      }

                      if (desc.isEmpty) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Keterangan tidak boleh kosong')),
                        );
                        return;
                      }

                      Navigator.pop(context);
                      await _submitExpense(amount, category, desc);
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: kBrandBlue,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text(
                      'Simpan Pengeluaran',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _submitExpense(double amount, String category, String description) async {
    final id = DateTime.now().millisecondsSinceEpoch.toString();
    final now = DateTime.now();
    final dateStr = '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';

    final payload = {
      'amount': amount,
      'category': category,
      'description': description,
      'expense_date': dateStr,
    };

    try {
      final res = await http
          .post(
            Uri.parse('$_expenseBackendUrl/expenses'),
            headers: widget.apiHeaders,
            body: jsonEncode(payload),
          )
          .timeout(const Duration(seconds: 8));

      if (res.statusCode == 200 || res.statusCode == 201) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Pengeluaran berhasil dicatat.'),
            backgroundColor: Colors.green,
          ),
        );
        _loadExpenses();
        return;
      }
    } catch (_) {
      // Offline fallback: simpan di local storage
    }

    final localItem = {
      'id': id,
      ...payload,
      'created_by': widget.kasirName,
      'is_pending': true,
    };
    await OfflineStore.addPendingExpense(localItem);

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Offline: Pengeluaran tersimpan lokal & akan otomatis disinkronkan.'),
        backgroundColor: Colors.orange,
      ),
    );
    _loadExpenses();
  }

  Future<void> _deleteExpense(Map<String, dynamic> item) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Hapus Pengeluaran?'),
        content: Text('Yakin ingin menghapus catatan "${item['description']}"?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
            child: const Text('Hapus'),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    if (item['is_pending'] == true) {
      await OfflineStore.removePendingExpense(item['id'] as String);
      _loadExpenses();
      return;
    }

    try {
      final res = await http.delete(
        Uri.parse('$_expenseBackendUrl/expenses/${item['id']}'),
        headers: widget.apiHeaders,
      );
      if (res.statusCode == 200) {
        _loadExpenses();
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Gagal menghapus pengeluaran (koneksi terputus)')),
        );
      }
    }
  }

  List<Map<String, dynamic>> get _filteredExpenses {
    if (_selectedCategory == 'Semua') return _expenses;
    return _expenses.where((e) => e['category'] == _selectedCategory).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pengeluaran Toko'),
        backgroundColor: kBrandBlue,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Segarkan',
            onPressed: _loadExpenses,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadExpenses,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Card Total Hari Ini
            Card(
              elevation: 0,
              color: Colors.red.shade50,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
                side: BorderSide(color: Colors.red.shade200),
              ),
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Total Pengeluaran Hari Ini',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: Colors.red.shade800,
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: Colors.red.shade100,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            '${_expenses.length} Catatan',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              color: Colors.red.shade900,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _formatRp(_totalAmount),
                      style: TextStyle(
                        fontSize: 26,
                        fontWeight: FontWeight.bold,
                        color: Colors.red.shade900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Otomatis mengurangi kas fisik saat rekonsiliasi kasir.',
                      style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 14),

            // Pills Kategori
            SizedBox(
              height: 38,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: _categories.length,
                separatorBuilder: (_, _) => const SizedBox(width: 8),
                itemBuilder: (context, i) {
                  final cat = _categories[i];
                  final isSelected = cat == _selectedCategory;
                  return ChoiceChip(
                    label: Text(cat),
                    selected: isSelected,
                    selectedColor: kBrandBlue,
                    labelStyle: TextStyle(
                      color: isSelected ? Colors.white : Colors.black87,
                      fontSize: 12,
                      fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                    ),
                    onSelected: (_) {
                      setState(() => _selectedCategory = cat);
                    },
                  );
                },
              ),
            ),
            const SizedBox(height: 16),

            // Daftar Pengeluaran
            if (_loading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(32),
                  child: CircularProgressIndicator(),
                ),
              )
            else if (_filteredExpenses.isEmpty)
              Card(
                elevation: 0,
                color: Colors.grey.shade50,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                  side: BorderSide(color: Colors.grey.shade200),
                ),
                child: const Padding(
                  padding: EdgeInsets.symmetric(vertical: 40, horizontal: 20),
                  child: Column(
                    children: [
                      Icon(Icons.receipt_long_outlined, size: 48, color: Colors.black26),
                      SizedBox(height: 10),
                      Text(
                        'Belum ada catatan pengeluaran hari ini.',
                        style: TextStyle(fontSize: 13, color: Colors.black54),
                      ),
                    ],
                  ),
                ),
              )
            else
              ..._filteredExpenses.map((exp) {
                final isPending = exp['is_pending'] == true;
                return Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(color: Colors.grey.shade200),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: Colors.red.shade50,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Icon(Icons.outbox, color: Colors.red.shade700, size: 20),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: Colors.grey.shade100,
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      exp['category'] ?? 'Operasional',
                                      style: TextStyle(fontSize: 10, color: Colors.grey.shade800),
                                    ),
                                  ),
                                  if (isPending) ...[
                                    const SizedBox(width: 6),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: Colors.orange.shade100,
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Text(
                                        'Offline',
                                        style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: Colors.orange.shade900),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                              const SizedBox(height: 4),
                              Text(
                                exp['description'] ?? '-',
                                style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                'Oleh: ${exp['created_by'] ?? 'Kasir'}',
                                style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
                              ),
                            ],
                          ),
                        ),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              _formatRp(exp['amount']),
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.bold,
                                color: Colors.red,
                              ),
                            ),
                            IconButton(
                              icon: const Icon(Icons.delete_outline, size: 18, color: Colors.black38),
                              onPressed: () => _deleteExpense(exp),
                              padding: EdgeInsets.zero,
                              constraints: const BoxConstraints(),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              }),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _showAddExpenseDialog,
        backgroundColor: kBrandBlue,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: const Text('Catat Pengeluaran', style: TextStyle(fontWeight: FontWeight.bold)),
      ),
    );
  }
}
