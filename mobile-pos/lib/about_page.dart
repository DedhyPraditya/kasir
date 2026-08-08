import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

class _ChangelogEntry {
  final String version;
  final String date;
  final List<String> points;

  const _ChangelogEntry({
    required this.version,
    required this.date,
    required this.points,
  });
}

const List<_ChangelogEntry> _changelog = [
  _ChangelogEntry(
    version: '1.4.0',
    date: '9 Agustus 2026',
    points: [
      'Mode Offline: aplikasi tetap bisa dipakai transaksi tanpa internet — login tidak perlu diulang, menu produk & topping tersimpan otomatis.',
      'Antrian order offline otomatis tersinkron ke server begitu koneksi internet kembali, tanpa perlu aksi manual.',
      'Indikator status online/offline di layar utama, lengkap jumlah order yang masih menunggu sinkronisasi.',
      'QRIS tetap bisa dipakai saat offline memakai gambar QRIS default yang bisa diatur di menu Setting.',
    ],
  ),
  _ChangelogEntry(
    version: '1.3.0',
    date: '8 Agustus 2026',
    points: [
      'Opsi topping kini mengikuti kategori produk — kategori seperti Minuman bisa menyembunyikan pilihan topping secara otomatis.',
    ],
  ),
  _ChangelogEntry(
    version: '1.2.0',
    date: '8 Agustus 2026',
    points: [
      'QRIS Dynamic: nominal tagihan otomatis tersisip ke kode QR, pelanggan tinggal scan tanpa isi nominal manual.',
      'Admin bisa mengganti QRIS toko sendiri dari Dashboard Web (upload foto atau tempel string QRIS).',
    ],
  ),
];

class AboutPage extends StatelessWidget {
  const AboutPage({super.key});

  @override
  Widget build(BuildContext context) {
    const themeColor = Color(0xFF1B6A6B);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Tentang Aplikasi'),
        backgroundColor: themeColor,
        foregroundColor: Colors.white,
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Center(
            child: Column(
              children: [
                Image.asset('assets/images/logo.png', height: 96),
                const SizedBox(height: 12),
                const Text(
                  'NYEMIL BEBS POS',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: themeColor,
                    letterSpacing: 1.2,
                  ),
                ),
                const SizedBox(height: 4),
                FutureBuilder<PackageInfo>(
                  future: PackageInfo.fromPlatform(),
                  builder: (context, snapshot) {
                    final info = snapshot.data;
                    final label = info != null
                        ? 'Versi ${info.version} (build ${info.buildNumber})'
                        : 'Memuat versi...';
                    return Text(
                      label,
                      style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
                    );
                  },
                ),
              ],
            ),
          ),
          const SizedBox(height: 28),
          const Text(
            'Pembaruan Terbaru',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 12),
          ...List.generate(_changelog.length, (index) {
            final entry = _changelog[index];
            final isLatest = index == 0;
            return Card(
              margin: const EdgeInsets.only(bottom: 12),
              elevation: isLatest ? 2 : 0,
              color: isLatest ? themeColor.withOpacity(0.06) : Colors.grey.shade50,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
                side: BorderSide(
                  color: isLatest ? themeColor.withOpacity(0.3) : Colors.grey.shade200,
                ),
              ),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          'v${entry.version}',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 15,
                            color: isLatest ? themeColor : Colors.black87,
                          ),
                        ),
                        if (isLatest) ...[
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: themeColor,
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: const Text(
                              'Terbaru',
                              style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                            ),
                          ),
                        ],
                        const Spacer(),
                        Text(
                          entry.date,
                          style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    ...entry.points.map(
                      (point) => Padding(
                        padding: const EdgeInsets.only(bottom: 6),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Padding(
                              padding: EdgeInsets.only(top: 5),
                              child: Icon(Icons.circle, size: 5, color: Colors.black45),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                point,
                                style: const TextStyle(fontSize: 13, height: 1.4),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          }),
          const SizedBox(height: 8),
          Center(
            child: Text(
              '© Nyemil Bebs',
              style: TextStyle(color: Colors.grey.shade400, fontSize: 11),
            ),
          ),
        ],
      ),
    );
  }
}
