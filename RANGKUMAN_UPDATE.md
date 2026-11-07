# Rangkuman Perkembangan & Pembaruan Sistem (8 November 2026)

Dokumen ini merangkum seluruh perubahan, perbaikan bug, modernisasi UI, serta status rilis yang telah diselesaikan pada sistem kasir (Web Dashboard & Mobile POS).

---

## 1. Masalah Utama yang Ditemukan & Diinvestigasi

### Kendala Tanggal/Jam Sama pada Laporan Penjualan (31/10/2026 01:13)
- **Penyebab:** Transaksi kasir pada 23 dan 29 Oktober 2026 tersimpan di antrean offline HP kasir (`OfflineStore`) karena ketiadaan koneksi internet. Ketika HP kasir online kembali pada 31/10/2026 pukul 01:13, antrean disinkronkan secara massal ke server.
- **Celah Sistem:** Endpoint API backend (`/api/orders/sync`) belum menerima timestamp asli kasir dan hanya menggunakan `created_at = now()`, sehingga seluruh transaksi offline terstempel waktu saat sinkronisasi tiba.
- **Status:** Bukan transaksi dobel, melainkan transaksi riil dari hari sebelumnya yang baru terunggah serentak.

---

## 2. Rincian Perbaikan yang Telah Diselesaikan

### A. Akurasi Timestamp Transaksi Offline Kasir
1. **Mobile POS (`mobile-pos/lib/pos_page.dart`):**
   - Payload sinkronisasi ditambahkan `'created_at': DateTime.now().toIso8601String()`, menjaga waktu riil saat kasir menekan tombol bayar.
2. **Backend API (`app/Http/Controllers/Api/OrderSyncController.php`):**
   - Menerima validasi `'created_at' => ['nullable', 'date']`.
   - Menggunakan `$data['created_at']` jika tersedia saat pembuatan order (fallback `now()`).
3. **Model Database (`app/Models/Order.php`):**
   - Menambahkan `'created_at'` ke dalam properti `$fillable`.

### B. Format Nomor Invoice Baru (Tanpa Karakter Acak)
1. **Web POS (`app/Livewire/Pos.php`):**
   - Nomor invoice diubah menjadi format presisi waktu: `INV-YYYYMMDD-HHmmss` (contoh: `INV-20261108-011425`).
2. **Mobile POS (`mobile-pos/lib/pos_page.dart`):**
   - Fungsi `_generateInvoiceNumber()` diubah menjadi `INV-YYYYMMDD-HHmmss`.
   - Peniadaan 4 karakter acak agar seluruh nomor struk mencerminkan tanggal & waktu pembuatan transaksi secara presisi.

### C. Normalisasi & Reset Otomatis Bulanan di Web
1. **Dashboard Utama (`app/Livewire/Dashboard.php`):**
   - **Best Seller Produk & Topping:** Difilter `whereMonth` dan `whereYear` bulan berjalan. Begitu berganti bulan baru, peringkat produk terlaris otomatis mulai *fresh* dari 0.
   - **Riwayat Transaksi:** Ditambahkan filter bulan berjalan agar transaksi bulan-bulan sebelumnya tidak menumpuk di dashboard.
2. **Laporan Penjualan (`app/Livewire/Laporan.php` & `LaporanController.php`):**
   - Default filter rentang tanggal disetel ke awal bulan (`startOfMonth`) sampai akhir bulan (`endOfMonth`).
   - Tombol **Reset** difungsikan penuh (`resetFilter()`) untuk mengembalikan rentang tanggal ke bulan berjalan dan membersihkan kata kunci pencarian.
   - Export PDF otomatis mengikuti default bulan berjalan.

### D. Modernisasi Tampilan Dashboard
1. **Smooth Gradient Area Chart (`resources/views/livewire/dashboard.blade.php`):**
   - Mengganti grafik bar hijau kaku menjadi kurva lengkung modern (*emerald line* dengan arsiran gradien vertikal memudar).
   - Default rentang hari disetel ke **7 Hari** (bisa diganti ke 14 Hari / 30 Hari).
   - Interaksi hover cerdas dengan dark tooltip dan format mata uang ringkas (`jt` / `rb`).
2. **Kartu Ringkasan Finansial Lengkap (4 Kartu Stat):**
   - **Pendapatan Hari Ini** (lengkap dengan badge total pesanan).
   - **Pendapatan Bulan Ini** (omzet kotor).
   - **Pengeluaran Bulan Ini** (biaya operasional toko).
   - **Laba Bersih Bulan Ini** (otomatis berwarna hijau surplus atau merah defisit).
3. **Pills Pilihan Periode Tren Dinamis:**
   - Tombol pilihan `[ 7 Hari ] [ 14 Hari ] [ 30 Hari ]` di atas grafik (default: 7 Hari).
   - Transisi instan via JavaScript (0 ms) beserta pembaruan otomatis metrik *Rata-rata/hari* dan *Puncak*.

### E. Sinkronisasi Timezone & Pengurutan Kronologis Ketat
1. **Timezone Toko (`config/app.php`):**
   - Default timezone disinkronkan ke `Asia/Makassar` (WITA, UTC+8) agar jam kasir lokal dan jam server tepat sama tanpa selisih 1 jam.
2. **Pengurutan Kronologis Mutlak:**
   - Seluruh query daftar order (`Laporan.php`, `Dashboard.php`, `LaporanController.php`, `OrderSyncController.php`) dikunci dengan:
     `->orderByDesc('created_at')->orderByDesc('invoice_number')`.
   - Transaksi paling baru mutlak di paling atas tanpa dipengaruhi abjad pelanggan/invoice.

### E. Command Artisan Pemulihan Data Transaksi Lama
- **File:** `app/Console/Commands/FixOrderTimestamps.php`
- **Fungsi:** Mendeteksi transaksi berpola `INV-YYYYMMDD-...` yang tanggal `created_at`-nya tidak sesuai (misal invoice 23 & 29 Oktober yang tertumpuk di 31 Oktober 01:13) dan memulihkannya ke tanggal riil.
- **Perintah:**
  - Simulasi: `php artisan orders:fix-timestamps --dry-run`
  - Eksekusi: `php artisan orders:fix-timestamps`

### F. Rilis Versi Aplikasi Mobile (v1.6.3 Build 19)
- **Versi:** Dinaikkan ke `1.6.3+19` mengikuti aturan `.agents/rules/mobile_release.md`:
  - `config/app.php`
  - `mobile-pos/pubspec.yaml`
  - `mobile-pos/lib/about_page.dart`
  - `mobile-pos/CHANGELOG.md`
- **File APK:** Telah dikompilasi ke `storage/app/public/mobile-pos-latest.apk` (55.5 MB) dan siap diunduh via `/download-apk` atau deteksi otomatis via `/api/app-version`.
- **Git:** Seluruh commit telah ter-push ke cabang utama `origin/main`.

---

## 3. Hal yang Perlu Dilakukan di Sisi Server / Kasir (Action Items)

| No | Tugas / Tindakan | Lokasi | Catatan / Perintah |
|:---|:---|:---|:---|
| 1 | **Koreksi Data Lama di Production** | Terminal VPS / Server | Jalankan `php artisan orders:fix-timestamps` di VPS agar lonjakan 31/10 di grafik normal kembali ke 23 & 29 Okt. |
| 2 | **Update Aplikasi Kasir di HP Toko** | HP / Tablet Kasir | Buka aplikasi kasir atau buka menu *Tentang Aplikasi* / download APK `1.6.3` dari web agar format invoice detik aktif di toko. |

---
*Status Kode: Bersih (Clean Tree), Teruji, dan Tersinkronisasi Penuh dengan GitHub.*

