# NYEMIL BEBS - Point of Sale (POS) & Management System

Aplikasi POS (Kasir) & Manajemen Toko modern berbasis web yang dirancang khusus untuk mengelola transaksi penjualan produk kuliner (seperti Gabin Fla dan Banana Roll) lengkap dengan pilihan topping, sistem struk belanja, dan laporan keuangan.

## 🚀 Fitur Utama

- **Interactive POS (Kasir):**
  - Pemilihan menu instan secara visual.
  - Kustomisasi varian produk dan topping tambahan (dengan penambahan harga otomatis).
  - Metode pembayaran ganda: **Tunai (Cash)** dan **QRIS**.
  - Cetak struk belanja thermal/kasir langsung menggunakan fitur cetak bawaan browser (`window.print()`).
- **Multi-Role Authentication (Spatie Permission):**
  - **Admin:** Memiliki akses penuh ke Dashboard ringkasan pendapatan, manajemen produk/topping, dan ekspor laporan.
  - **Kasir:** Hanya diarahkan ke halaman POS untuk transaksi kasir (akses dashboard & laporan dibatasi).
- **Laporan Transaksi & Ekspor PDF:**
  - Pemantauan total pendapatan, jumlah transaksi, pendapatan tunai, dan QRIS.
  - Filter pencarian berdasarkan rentang tanggal dan kata kunci invoice/nama pelanggan.
  - Fitur **Ekspor PDF** untuk mencetak laporan transaksi periode terpilih.
- **Kelola Produk & Topping (Centralized CRUD):**
  - Tab manajemen produk untuk mengubah harga dasar dan status menu.
  - Tab manajemen topping untuk menambah/mengubah harga topping tambahan.

---

## 🛠️ Tech Stack

- **Framework:** Laravel 13
- **Front-End:** Livewire v4 (Single Page Application feel tanpa reload) & Alpine.js
- **Styling:** Bootstrap 5 & Bootstrap Icons
- **Authorization:** Spatie Laravel-Permission
- **PDF Generator:** Barryvdh Laravel DomPDF
- **Database:** SQLite / MySQL

---

## 💻 Cara Install & Menjalankan Project

1. **Clone repository:**
   ```bash
   git clone https://github.com/username/nyemilbebs.git
   cd nyemilbebs
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
   *Secara default menggunakan SQLite, pastikan file database tersedia:*
   ```env
   DB_CONNECTION=sqlite
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
   Aplikasi dapat diakses melalui link `http://127.0.0.1:8000`.

---

## 🔑 Akun Uji Coba (Testing Credentials)

| Role | Username | Password |
| :--- | :--- | :--- |
| **Admin** | `admin` | `admin123` |
| **Kasir** | `kasir1` | `kasir123` |

---
*Dibuat untuk kebutuhan operasional kasir dan pencatatan keuangan toko Nyemil Bebs.*
