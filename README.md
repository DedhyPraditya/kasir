# Point of Sale (POS) & Management System + Mobile Apps

Aplikasi POS (Kasir) & Manajemen Toko modern berbasis web yang dirancang khusus untuk mengelola transaksi penjualan produk kuliner (seperti Gabin Fla dan Banana Roll) lengkap dengan pilihan topping, sistem struk belanja, dan laporan keuangan. Dilengkapi juga dengan **aplikasi mobile Android** berbasis Flutter.

## 🚀 Fitur Utama

### 🌐 Aplikasi Web (Laravel + Livewire)
- **Interactive POS (Kasir):**
  - Pemilihan menu instan secara visual — hanya menampilkan produk/varian/topping yang berstatus **Aktif**.
  - Kustomisasi varian rasa produk dan topping tambahan (dengan penambahan harga otomatis).
  - Metode pembayaran ganda: **Tunai (Cash)** dan **QRIS Dynamic** (nominal otomatis tersisip di kode QR, sesuai standar EMVCo).
  - Cetak struk belanja thermal/kasir langsung menggunakan fitur cetak bawaan browser (`window.print()`).
- **Multi-Role Authentication (Spatie Permission):**
  - **Admin:** Memiliki akses penuh ke Dashboard ringkasan pendapatan, manajemen produk/topping, dan ekspor laporan.
  - **Kasir:** Hanya diarahkan ke halaman POS untuk transaksi kasir (akses dashboard & laporan dibatasi).
- **Laporan Transaksi & Ekspor PDF:**
  - Pemantauan total pendapatan, jumlah transaksi, pendapatan tunai, dan QRIS.
  - Filter pencarian berdasarkan rentang tanggal dan kata kunci invoice/nama pelanggan.
  - Fitur **Ekspor PDF** untuk mencetak laporan transaksi periode terpilih.
- **Kelola Produk, Varian Rasa & Topping (Centralized CRUD):**
  - Tab manajemen produk: ubah harga dasar, status menu aktif/nonaktif.
  - **Manajemen Varian Rasa per Produk:** Tambah, edit, hapus varian rasa (misal: Coklat, Keju, Stroberi) dengan harga khusus dan status aktif/nonaktif per varian.
  - Tab manajemen topping: tambah/ubah harga topping tambahan beserta status aktifnya.
  - Status **Aktif/Nonaktif** pada produk, varian, dan topping langsung mempengaruhi tampilan di halaman kasir.
- **Notifikasi Floating Terdepan:**
  - Notifikasi sukses/error menggunakan `position: fixed` dengan `z-index: 9999`, selalu tampil di atas semua elemen termasuk modal, pada posisi tengah-atas layar.
- **Pengaturan QRIS (Admin):**
  - Ganti kode QRIS toko langsung dari dashboard — upload foto QRIS (otomatis dibaca) atau tempel string EMV manual.
  - Riwayat QRIS tersimpan otomatis, bisa diaktifkan kembali (rollback) kapan saja tanpa upload ulang.

### 📱 Aplikasi Mobile (Flutter — `mobile-pos/`)
- Kasir via smartphone Android dengan antarmuka yang ringan dan cepat.
- Koneksi langsung ke backend Laravel via REST API (token-based authentication).
- Pemilihan varian rasa dan topping, keranjang belanja, dan proses pembayaran (Cash/QRIS Dynamic — nominal otomatis).
- Sinkronisasi otomatis order ke dashboard web setelah transaksi selesai.
- Cetak struk langsung ke **printer thermal Bluetooth** (menggunakan `blue_thermal_printer`).
- **Notifikasi Overlay:** Notifikasi selalu tampil di lapisan terdepan (di atas dialog, modal, atau layar manapun) menggunakan Flutter `Navigator Overlay` — dilengkapi animasi fade-in/out, auto-dismiss 3 detik, dan bisa ditutup manual.

---

## 🛠️ Tech Stack

### Web
| Komponen | Teknologi |
|---|---|
| Framework | Laravel 13 |
| Reaktivitas | Livewire v4 + Alpine.js |
| Styling | Bootstrap 5 + Bootstrap Icons |
| Authorization | Spatie Laravel-Permission |
| PDF Generator | Barryvdh Laravel DomPDF |
| Database | MySQL / SQLite |

### Mobile
| Komponen | Teknologi |
|---|---|
| Framework | Flutter (Dart) |
| HTTP Client | `package:http` |
| Cetak Struk | `blue_thermal_printer` |
| Permissions | `permission_handler` |

---

## 💻 Cara Install & Menjalankan Project

### Web
1. **Clone repository:**
   ```bash
   git clone https://github.com/DedhyPraditya/kasir.git
   cd kasir
   ```

2. **Install dependensi PHP & Assets:**
   ```bash
   composer install
   npm install
   ```

3. **Duplikat file environment dan generate key:**
   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

4. **Konfigurasi Database (.env):**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nyemilbebs
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. **Jalankan Migrasi & Database Seeder:**
   ```bash
   php artisan migrate --seed
   ```

6. **Build assets & jalankan server lokal:**
   ```bash
   npm run build
   php artisan serve
   ```
   Aplikasi dapat diakses melalui `http://127.0.0.1:8000`.

### Mobile (Flutter)
1. Masuk ke folder mobile:
   ```bash
   cd mobile-pos
   ```
2. Install dependensi Flutter:
   ```bash
   flutter pub get
   ```
3. Sesuaikan `backendUrl` di `lib/pos_page.dart` dengan URL server Anda.
4. Build & jalankan:
   ```bash
   flutter run
   # atau untuk build APK release:
   flutter build apk --release
   ```

---

## 🔑 Akun Uji Coba (Testing Credentials)

| Role | Username | Password |
| :--- | :--- | :--- |
| **Admin** | `admin` | `admin123` |
| **Kasir** | `kasir1` | `kasir123` |

---

## 📋 Changelog

### [Unreleased / Planned v1.2.0] — Rencana Pembaruan Mendatang
- **[Planned] Opsi Topping Berdasarkan Kategori (Makanan vs Minuman):**
  Opsi saklar `allow_topping` pada Kategori / Produk di Dashboard Admin. Kategori Makanan menampilkan opsi topping, sedangkan Kategori Minuman menyembunyikan opsi topping secara otomatis di tampilan kasir Web & Mobile POS.

### [1.1.1] — 2026-08-04 (Mobile POS Update)
- **[Feature] Icon Aplikasi Logo Nyemil Bebs:** App icon Flutter resmi menggunakan logo Nyemil Bebs (lengkap dengan adaptive icon Android).
- **[Feature] Halaman Penuh Riwayat Transaksi (`HistoryPage`):** Tampilan riwayat berdiri sendiri dengan Card ringkas, badge metode pembayaran (CASH/QRIS), dan filter transaksi khusus hari ini.
- **[Feature] Print Ulang & Share Struk (JPG):** Tombol aksi per transaksi untuk cetak ulang ke printer Bluetooth atau share gambar struk fisik ke WhatsApp/media lain.
- **[Feature] Preview Struk Thermal Presisi 100%:** Preview struk baris-per-baris (Courier, 32 kolom) yang presisi dan identik dengan hasil cetakan printer 58mm.
- **[Feature] In-App Auto Update & Direct APK Download:** Pengecekan versi aplikasi otomatis dari server VPS dan link download langsung `/download-apk`.
- **[Feature] Presisi Multi-Timezone (WIB/WITA/WIT):** Menyesuaikan jam transaksi secara otomatis dengan waktu lokal smartphone pengguna tanpa pergeseran offset ganda.
- **[UI/UX] Bottom Navigation Bar & Indikator Printer:** Menata ulang navigasi ke bagian bawah (Home, Riwayat, Setting) dan indikator status printer (🟢 Hijau / 🟠 Oranye) di AppBar.

### [2026-07-31] — Session Update
- **[Fix] Filter status produk di kasir web & mobile:**
  Halaman kasir (POS) web kini hanya menampilkan produk, varian rasa, dan topping yang berstatus **Aktif** (`is_active = true`). Produk/varian/topping yang dinonaktifkan otomatis tersembunyi dari tampilan kasir tanpa perlu dihapus dari database.

- **[Feature] Manajemen Varian Rasa Produk:**
  Formulir tambah/edit produk di dasbor admin kini dilengkapi section **Varian Rasa (Opsional)**. Admin dapat menambah, mengedit, dan menghapus varian rasa (contoh: Coklat, Keju, Stroberi) dengan harga khusus dan status aktif/nonaktif per varian secara dinamis tanpa reload halaman. Jumlah varian juga ditampilkan sebagai badge di daftar produk.

- **[Fix] Notifikasi Floating Terdepan (Web):**
  Notifikasi sukses/error di halaman kasir dan kelola produk diubah menjadi `position: fixed` dengan `z-index: 9999`, memastikan notifikasi selalu tampil di atas semua elemen termasuk modal dan overlay.

- **[Fix] Notifikasi Overlay Terdepan (Mobile Flutter):**
  Sistem notifikasi di aplikasi Flutter diubah dari `ScaffoldMessenger.showSnackBar()` menjadi widget `_ToastOverlay` kustom yang dimasukkan langsung ke `Navigator Overlay`. Notifikasi kini selalu tampil di atas semua lapisan termasuk `AlertDialog`, dilengkapi animasi fade, auto-dismiss 3 detik, ikon sesuai tipe pesan, dan dapat ditutup manual.

---

*Dibuat untuk kebutuhan operasional kasir dan pencatatan keuangan toko.*
