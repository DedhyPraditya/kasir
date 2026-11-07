# Audit Keamanan & Integritas Data — Sistem Kasir deo'ku

Tanggal audit: 8 November 2026  
Status Terkini: **Mayoritas celah kritis & menengah telah diperbaiki (Rilis v1.6.4+20)**  
Cakupan: Backend Laravel (web + API), komponen Livewire, aplikasi Mobile POS (Flutter).

---

## Ringkasan Status Perbaikan

| Kode | Temuan | Tingkat | Status |
|:---|:---|:---|:---|
| **H1** | Tabrakan nomor invoice format baru (`INV-YYYYMMDD-HHmmss`) antar kasir/toko | 🔴 Tinggi | **SELESAI DIPERBAIKI** |
| **H2** | Manipulasi harga/qty keranjang Web POS dari sisi browser | 🔴 Tinggi | **SELESAI DIPERBAIKI** |
| **H3** | Timestamp `created_at` sync offline tanpa batas rentang waktu | 🔴 Tinggi | **SELESAI DIPERBAIKI** |
| **M1** | Otorisasi aksi Livewire (`Produk`, `QrisSettings`) hanya di middleware route | 🟠 Sedang | **SELESAI DIPERBAIKI** |
| **M2** | Kasir dapat mengedit/menghapus pengeluaran milik user lain | 🟠 Sedang | **SELESAI DIPERBAIKI** |
| **M3** | Kasir dapat melihat daftar riwayat transaksi toko di menu Laporan | 🟠 Sedang | **MENUNGGU KEPUTUSAN OWNER** |
| **M4** | Potensi kebocoran detail pengeluaran lintas toko via idempotensi UUID | 🟠 Sedang | **SELESAI DIPERBAIKI** |
| **M5** | Belum ada rate limit pada grup endpoint API terautentikasi (`api.token`) | 🟠 Sedang | **SELESAI DIPERBAIKI** |
| **L6** | Pembatasan ekstensi MIME upload gambar (`jpg,jpeg,png,webp`) | 🟡 Rendah | **SELESAI DIPERBAIKI** |
| **L7** | Penyimpanan transaksi Web POS belum memakai `DB::transaction()` | 🟡 Rendah | **SELESAI DIPERBAIKI** |
| **L3/L4** | Konfigurasi `.env` production (`APP_DEBUG=false`) & `trustProxies` | 🟡 Rendah | **PANDUAN SERVER** |

---

## 🔴 Prioritas Tinggi (Telah Diperbaiki)

### H1. Tabrakan nomor invoice format baru dapat menghilangkan transaksi — ✅ SELESAI
**Lokasi:** `mobile-pos/lib/pos_page.dart`, `app/Livewire/Pos.php`, `app/Http/Controllers/Api/OrderSyncController.php`, migrasi `2026_11_08_000000_add_client_uuid_and_store_unique_invoice_to_orders.php`.

**Perbaikan yang diterapkan:**
1. Menambahkan kolom `client_uuid` (unique per `store_id`) pada tabel `orders` dan menyertakan UUID v4 dari aplikasi Mobile POS sebagai kunci idempotensi utama saat sync.
2. Mengubah unique index `invoice_number` dari global menjadi per toko: `unique(['store_id', 'invoice_number'])`.
3. Menambahkan sufiks 2 karakter acak pada nomor invoice (`INV-YYYYMMDD-HHmmss-XX`) di Web POS dan Mobile POS agar dua perangkat di detik yang sama tidak bentrok, serta fallback penambahan sufiks di `OrderSyncController` bila terjadi tabrakan.

### H2 & L7. Harga keranjang Web POS bisa dimanipulasi dari browser — ✅ SELESAI
**Lokasi:** `app/Livewire/Pos.php` (`processPayment`).

**Perbaikan yang diterapkan:**
- Menghitung ulang harga satuan, varian, topping, dan subtotal langsung dari database (`Product::with('variants')`, `Topping`) milik toko aktif, mengabaikan harga/subtotal dari state klien.
- Membatasi `quantity` antara `1` s/d `1000`.
- Membungkus pembuatan `Order`, `OrderItem`, dan `OrderItemTopping` di dalam `DB::transaction()`.

### H3. Timestamp transaksi dari aplikasi tidak dibatasi (backdating) — ✅ SELESAI
**Lokasi:** `app/Http/Controllers/Api/OrderSyncController.php` (`sync`).

**Perbaikan yang diterapkan:**
- Membatasi `created_at` dari aplikasi agar hanya diterima bila berada dalam rentang maksimal **14 hari ke belakang** hingga **5 menit ke depan** (toleransi selisih jam perangkat). Di luar rentang tersebut otomatis menggunakan waktu server (`now()`).

---

## 🟠 Prioritas Sedang

### M1. Otorisasi aksi Livewire hanya bergantung pada middleware route — ✅ SELESAI
**Lokasi:** `app/Livewire/Produk.php`, `app/Livewire/QrisSettings.php`.

**Perbaikan yang diterapkan:**
- Menambahkan method `boot()` dengan `abort_unless(auth()->user()?->hasRole('admin'), 403);` sehingga setiap request update Livewire tetap memverifikasi role admin.

### M2. Kasir dapat mengubah/menghapus pengeluaran siapa pun di tokonya — ✅ SELESAI
**Lokasi:** `app/Livewire/Pengeluaran.php`, `resources/views/livewire/pengeluaran.blade.php`, `app/Http/Controllers/Api/ExpenseController.php`.

**Perbaikan yang diterapkan:**
- Membatasi aksi `edit`, `save` (update), dan `delete`/`destroy` hanya untuk pembuat catatan (`expense->user_id === user->id`) atau pengguna ber-role `admin` / `developer`. Tombol edit/hapus di tabel web juga disembunyikan untuk catatan milik pengguna lain.

### M3. Kasir melihat seluruh transaksi & detail toko di Laporan — ⏳ OPSIONAL (Sesuai Kebijakan Toko)
**Lokasi:** `app/Livewire/Laporan.php`, route `/laporan`.

**Catatan:** Kartu ringkasan omzet sudah disembunyikan untuk non-admin, namun daftar transaksi masih tampil. Jika kasir hanya boleh melihat transaksi hari ini, dapat ditambahkan pembatasan tanggal khusus role `kasir`.

### M4. Bocoran data lintas toko lewat idempotensi pengeluaran — ✅ SELESAI
**Lokasi:** `app/Http/Controllers/Api/ExpenseController.php` (`store`).

**Perbaikan yang diterapkan:**
- Menambahkan filter `->where('store_id', $storeId)` pada pengecekan idempotensi `Expense::withoutGlobalScopes()`.

### M5. Tidak ada rate limit pada endpoint API terautentikasi — ✅ SELESAI
**Lokasi:** `routes/api.php`.

**Perbaikan yang diterapkan:**
- Menambahkan middleware `throttle:60,1` pada grup route `api.token`.

---

## 🟡 Prioritas Rendah / Hardening

### L6. Validasi upload gambar — ✅ SELESAI
**Lokasi:** `app/Livewire/Produk.php`, `app/Livewire/QrisSettings.php`.
- Menambahkan `mimes:jpg,jpeg,png,webp` pada aturan validasi unggah gambar produk dan QRIS.

### L1. Token API disimpan di `shared_preferences` HP
**Lokasi:** `mobile-pos/lib/offline_store.dart`.
- **Catatan:** Saat ini tersimpan di penyimpanan internal privat aplikasi Android. Di masa mendatang dapat ditingkatkan ke `flutter_secure_storage` (Android Keystore).

### L3 & L4. Checklist Konfigurasi Server Production (VPS)
Pastikan di file `.env` pada server VPS (`/www/wwwroot/kasir/.env`):
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_TIMEZONE=Asia/Makassar`
