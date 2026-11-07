# Audit Keamanan & Integritas Data — Sistem Kasir deo'ku

Tanggal audit: 8 November 2026
Cakupan: Backend Laravel (web + API), komponen Livewire, aplikasi Mobile POS (Flutter).
Metode: review kode statis (belum ada uji penetrasi langsung ke production).

Prioritas:
- 🔴 **Tinggi** — bisa menyebabkan kehilangan data atau manipulasi uang. Sebaiknya segera.
- 🟠 **Sedang** — butuh akses login, tapi bisa disalahgunakan orang dalam.
- 🟡 **Rendah** — hardening / praktik terbaik.

---

## 🔴 Prioritas Tinggi

### H1. Tabrakan nomor invoice format baru dapat menghilangkan transaksi
**Lokasi:** `mobile-pos/lib/pos_page.dart` (`_generateInvoiceNumber`), `app/Livewire/Pos.php`, `app/Http/Controllers/Api/OrderSyncController.php` (baris ~39–50), migrasi `orders.invoice_number` (`unique` global).

**Masalah:** Format `INV-YYYYMMDD-HHmmss` (dipasang pada rilis 1.6.3) hanya unik per detik. Kolom `invoice_number` unik secara global (lintas toko).
- Dua HP kasir **di toko yang sama** membuat transaksi pada detik yang sama → API menganggap order kedua sebagai "kirim ulang" (idempotensi) dan membalas sukses → **order kedua hilang tanpa jejak**, padahal uang sudah diterima.
- Dua **toko berbeda** pada detik yang sama → API menolak dengan 422 → order tertahan di antrean offline HP selamanya.
- Web POS punya fallback `rand(1, 9)` — hanya 9 kemungkinan, masih bisa bentrok.

**Perbaikan yang disarankan:**
1. Tambah sufiks pendek unik per perangkat/transaksi, mis. `INV-YYYYMMDD-HHmmss-XX` (2–3 karakter acak atau ID perangkat), **atau** tambah kolom `client_uuid` (UUID dari HP) sebagai kunci idempotensi, bukan `invoice_number`.
2. Ubah unique index menjadi `unique(['store_id', 'invoice_number'])`.
3. Idempotensi di `OrderSyncController` sebaiknya membandingkan `client_uuid`, bukan sekadar nomor invoice.

### H2. Harga keranjang Web POS bisa dimanipulasi dari browser
**Lokasi:** `app/Livewire/Pos.php` — properti `public $cart`, `processPayment()`.

**Masalah:** Properti publik Livewire dapat diubah dari sisi klien (DevTools / request `/livewire/update`). `processPayment()` langsung memakai `price`, `subtotal`, `quantity`, `product_id`, dan data topping dari `$cart` tanpa menghitung ulang dari database. Akibatnya user login bisa mencatat transaksi dengan harga Rp 1, quantity negatif (mengurangi omzet), atau `product_id` milik toko lain.

**Perbaikan yang disarankan:**
- Di `processPayment()`, hitung ulang harga dari tabel `products`/`variants`/`toppings` (meniru logika di `OrderSyncController::sync`), validasi `quantity` (`integer|min:1|max:1000`), dan pastikan produk milik toko aktif.
- Atau tandai properti dengan atribut `#[Locked]` dan simpan cart yang aman di server.
- Bungkus penyimpanan order + item dalam `DB::transaction()` (saat ini belum).

### H3. Timestamp transaksi dari aplikasi tidak dibatasi (backdating)
**Lokasi:** `OrderSyncController::sync` — field `created_at` (ditambahkan rilis 1.6.2).

**Masalah:** Validasi hanya `nullable|date`. Pengguna dengan token API bisa mengirim tanggal bebas (tahun lalu atau masa depan), sehingga bisa menyisipkan/menggeser transaksi ke hari yang sudah ditutup kas (rekonsiliasi) atau mengacaukan laporan.

**Perbaikan yang disarankan:**
- Batasi rentang: `before_or_equal:now` (+ toleransi beberapa menit) dan `after:` misal maksimal 7–14 hari ke belakang (masa antrean offline yang wajar).
- Simpan juga `synced_at` (waktu server) terpisah untuk audit.

---

## 🟠 Prioritas Sedang

### M1. Otorisasi aksi Livewire hanya bergantung pada middleware route
**Lokasi:** `app/Livewire/Produk.php`, `app/Livewire/QrisSettings.php`.

**Masalah:** Pembatasan admin hanya lewat middleware `role:admin` di route. Request aksi Livewire berikutnya (`/livewire/update`) tidak otomatis menjalankan ulang middleware Spatie kecuali didaftarkan sebagai persistent middleware. Komponen lain (`StoreManagement`, `UserManagement`) sudah benar memakai `boot()` + `abort_unless`.

**Perbaikan:** Tambahkan `boot()` dengan `abort_unless(auth()->user()?->hasRole('admin'), 403);` pada `Produk` dan `QrisSettings`, atau daftarkan `RoleMiddleware` via `Livewire::addPersistentMiddleware()`.

### M2. Kasir dapat mengubah/menghapus pengeluaran siapa pun di tokonya
**Lokasi:** `app/Livewire/Pengeluaran.php` (`edit`, `save`, `delete`), `app/Http/Controllers/Api/ExpenseController.php` (`destroy`).

**Masalah:** Tidak ada cek kepemilikan atau peran. Kasir bisa menghapus pengeluaran yang dicatat admin/kasir lain, atau mengedit nominal setelah kas ditutup.

**Perbaikan:** Batasi edit/hapus ke pembuat (`user_id`) atau admin; kunci data yang tanggalnya sudah direkonsiliasi. Pertimbangkan soft delete + log audit.

### M3. Kasir melihat seluruh transaksi & detail toko di Laporan
**Lokasi:** `app/Livewire/Laporan.php`, route `/laporan`.

**Masalah:** Kartu ringkasan disembunyikan di view untuk non-admin, tetapi daftar dan detail transaksi lengkap tetap dapat diakses kasir. Jika ini disengaja, abaikan; jika tidak, batasi ke transaksi hari ini/miliknya.

### M4. Bocoran data lintas toko lewat idempotensi pengeluaran
**Lokasi:** `ExpenseController::store` — `Expense::withoutGlobalScopes()->find($data['id'])`.

**Masalah:** Jika UUID pengeluaran toko lain diketahui, API mengembalikan detailnya. Risiko kecil (UUID sulit ditebak) tetapi sebaiknya ditutup.

**Perbaikan:** Tambahkan `->where('store_id', $storeId)` pada pengecekan.

### M5. Tidak ada rate limit pada endpoint API terautentikasi
**Lokasi:** `routes/api.php` — `orders/sync`, `expenses`, `reconciliation`.

**Perbaikan:** Tambahkan `throttle:60,1` (atau sesuai kebutuhan) pada grup `api.token`.

---

## 🟡 Prioritas Rendah / Hardening

### L1. Token API disimpan plaintext di HP
**Lokasi:** `mobile-pos/lib/offline_store.dart` (`shared_preferences`).
**Perbaikan:** Gunakan `flutter_secure_storage` (Keystore Android). Token di server sudah di-hash — bagus.

### L2. Token API tidak pernah benar-benar kedaluwarsa selama dipakai
**Lokasi:** `ApiTokenMiddleware` — `expires_at` diperpanjang tiap pemakaian.
**Catatan:** Wajar untuk POS, tetapi pastikan ada tombol revoke (sudah ada `revokeApk` di UserManagement) dan token dicabut saat user dihapus/ganti password.

### L3. Verifikasi pengaturan production
Pastikan di `.env` server:
- `APP_ENV=production`, `APP_DEBUG=false` (debug aktif membocorkan stack trace & konfigurasi).
- `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true` opsional.
- `APP_TIMEZONE=Asia/Makassar` bila ingin eksplisit (default config kini sudah WITA).

### L4. `trustProxies(at: '*')`
**Lokasi:** `bootstrap/app.php`.
**Catatan:** Membuat header `X-Forwarded-For` dipercaya dari mana saja → IP untuk rate limit login bisa dipalsukan sehingga throttle login per-IP bisa diakali. Batasi ke IP reverse proxy (Nginx/Cloudflare) bila memungkinkan.

### L5. Endpoint `/download-apk` publik
**Catatan:** Disengaja untuk distribusi. Risiko: APK dapat di-reverse engineer (URL backend sudah publik, tidak ada secret di dalamnya — aman selama tidak menyimpan kunci rahasia di kode Flutter).

### L6. Validasi upload gambar
**Lokasi:** `Produk.php`, `QrisSettings.php` — `image|max:2048/4096`.
**Catatan:** Sudah cukup baik. Tambahan opsional: `mimes:jpg,jpeg,png,webp` untuk menolak SVG (potensi XSS bila disajikan langsung).

### L7. Pemrosesan pembayaran Web POS tanpa transaksi database
Lihat H2 — bila gagal di tengah, order bisa tersimpan tanpa item.

---

## Hal yang Sudah Baik ✅
- Token API di-hash di database (`ApiToken::hash`).
- Login API memiliki rate limit per username+IP dan proteksi timing attack.
- Multi-tenant scope global (`BelongsToTenant`) diterapkan konsisten di model utama.
- `OrderSyncController` menghitung ulang harga dari database (tidak percaya nominal dari aplikasi).
- Aksi hapus transaksi dibatasi peran `developer`.
- `.env` dan file APK tidak masuk repository.

---

## Urutan Pengerjaan yang Disarankan
1. **H1** — tabrakan invoice (risiko kehilangan transaksi nyata, terkait rilis 1.6.3).
2. **H2** — hitung ulang harga di Web POS + `DB::transaction`.
3. **H3** — batasi rentang `created_at`.
4. **M1, M2, M5** — otorisasi & rate limit.
5. Sisanya saat ada waktu luang.
