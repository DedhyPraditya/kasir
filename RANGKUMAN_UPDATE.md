# Rangkuman Perkembangan & Pembaruan Sistem (8 November 2026)

Dokumen ini merangkum seluruh perubahan, perbaikan bug, modernisasi UI, perbaikan celah keamanan, serta status rilis yang telah diselesaikan pada sistem kasir (Web Dashboard & Mobile POS).

---

## 1. Masalah Utama yang Ditemukan & Diinvestigasi

### Kendala Tanggal/Jam Sama pada Laporan Penjualan (31/10/2026 01:13)
- **Penyebab:** Transaksi kasir pada 23 dan 29 Oktober 2026 tersimpan di antrean offline HP kasir (`OfflineStore`) karena ketiadaan koneksi internet. Ketika HP kasir online kembali pada 31/10/2026 pukul 01:13, antrean disinkronkan secara massal ke server.
- **Celah Sistem:** Endpoint API backend (`/api/orders/sync`) belum menerima timestamp asli kasir dan hanya menggunakan `created_at = now()`, sehingga seluruh transaksi offline terstempel waktu saat sinkronisasi tiba.
- **Status:** Bukan transaksi dobel, melainkan transaksi riil dari hari sebelumnya yang baru terunggah serentak.

---

## 2. Rincian Perbaikan yang Telah Diselesaikan

### A. Akurasi Timestamp Transaksi Offline Kasir & Pembatasan Backdating (H3)
1. **Mobile POS (`mobile-pos/lib/pos_page.dart`):**
   - Payload sinkronisasi menyertakan `'created_at': DateTime.now().toIso8601String()`, menjaga waktu riil saat kasir menekan tombol bayar.
2. **Backend API (`app/Http/Controllers/Api/OrderSyncController.php`):**
   - Menerima validasi `'created_at' => ['nullable', 'date']`.
   - Membatasi `created_at` hanya diterima jika dalam rentang **14 hari ke belakang s/d 5 menit ke depan** (mencegah manipulasi tanggal masa lalu/masa depan).
3. **Model Database (`app/Models/Order.php`):**
   - Menambahkan `'created_at'` dan `'client_uuid'` ke dalam properti `$fillable`.

### B. Format Nomor Invoice Presisi & Anti-Bentrok Multi-Kasir (H1)
1. **Format Invoice (`INV-YYYYMMDD-HHmmss-XX`):**
   - Mencerminkan tanggal dan jam-menit-detik transaksi secara presisi, ditambah 2 karakter kode unik di ujung untuk mencegah tabrakan jika dua HP kasir membuat transaksi pada detik yang sama.
2. **Idempotensi Berbasis `client_uuid`:**
   - Migrasi `2026_11_08_000000_add_client_uuid_and_store_unique_invoice_to_orders.php` menambahkan kolom `client_uuid` dan mengubah unique index `invoice_number` menjadi per toko (`['store_id', 'invoice_number']`).
   - Menjamin tidak ada transaksi yang hilang/tertimpa saat sinkronisasi ulang maupun saat banyak perangkat kasir aktif bersamaan.

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

### F. Perbaikan Keamanan Backend & Livewire (H2, M1, M2, M4, M5, L6, L7)
1. **Hitung Ulang Harga Keranjang Web POS (`Pos.php`):** Harga produk, varian, dan topping dihitung ulang dari database di dalam `DB::transaction()`, mencegah manipulasi harga/qty dari browser.
2. **Proteksi Role Admin di Livewire (`Produk.php`, `QrisSettings.php`):** Penambahan `boot()` `abort_unless` untuk memastikan hanya admin yang dapat memodifikasi produk dan QRIS.
3. **Otorisasi Pengeluaran (`Pengeluaran.php`, `ExpenseController.php`):** Kasir hanya dapat mengedit/menghapus pengeluaran yang dicatatnya sendiri; admin/developer dapat mengelola seluruhnya.
4. **Isolasi Idempotensi Pengeluaran (`ExpenseController.php`):** Pengecekan duplikasi UUID pengeluaran dibatasi per `store_id`.
5. **Rate Limiting API (`routes/api.php`):** Penambahan `throttle:60,1` pada grup endpoint `api.token`.
6. **Validasi MIME Upload Gambar:** Pembatasan `mimes:jpg,jpeg,png,webp` pada upload gambar produk dan QRIS.

### G. Command Artisan Pemulihan Data Transaksi Lama
- **File:** `app/Console/Commands/FixOrderTimestamps.php`
- **Perintah:** `php artisan orders:fix-timestamps [--dry-run]`

### H. Rilis Versi Aplikasi Mobile (v1.6.4 Build 20)
- **Versi:** Dinaikkan ke `1.6.4+20` mengikuti aturan `.agents/rules/mobile_release.md`:
  - `config/app.php`
  - `mobile-pos/pubspec.yaml`
  - `mobile-pos/lib/about_page.dart`
  - `mobile-pos/CHANGELOG.md`
- **File APK:** `storage/app/public/mobile-pos-latest.apk` siap diunduh via `/download-apk` atau deteksi otomatis via `/api/app-version`.

---

## 3. Status Pelaksanaan di Server / Kasir (Action Items)

| No | Tindakan | Lokasi | Status |
|:---|:---|:---|:---|
| 1 | **Koreksi Data Lama di Production** | VPS (`kasir.madignet.site`) | **SELESAI** - 22 transaksi tertumpuk berhasil dinormalkan. |
| 2 | **Jalankan Migrasi Baru (v1.6.4)** | VPS (`kasir.madignet.site`) | Jalankan `git pull origin main && php artisan migrate --force && php artisan optimize:clear`. |
| 3 | **Update Aplikasi Kasir di HP Toko** | HP / Tablet Kasir Toko | Update ke versi `1.6.4 (build 20)`. |

---
*Status Sistem: Seluruh perbaikan fungsional, tampilan, dan keamanan telah diterapkan.*
