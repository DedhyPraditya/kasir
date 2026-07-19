# 📌 Pengingat: Implementasi Pengelolaan Varian Produk

Dokumen ini berfungsi sebagai pengingat (TODO) untuk menambahkan fitur pengelolaan **Varian Produk** pada dasbor admin. Saat ini, sistem POS sudah mendukung varian harga produk di sisi database, namun antarmuka admin belum memiliki formulir untuk mengelolanya.

---

## 🛠️ Rencana Pengembangan (Next Steps)

### 1. Database & Model Status
- Tabel `product_variants` sudah memiliki relasi dengan `products`.
- Setiap varian memiliki atribut: `product_id`, `name` (misalnya: *Original, Premium, Extra Cheese*), dan `price` (harga khusus varian).

### 2. Livewire Backend (`App\Livewire\Produk.php`)
- **State Varian:** Tambahkan properti array atau collection untuk menampung varian saat modal produk dibuka.
- **Relasi Form:** Pada modal Tambah/Edit Produk, sediakan opsi dinamis untuk menambahkan input nama varian dan harganya.
- **Validasi:** Validasi input dinamis varian (misal: `variants.*.name` dan `variants.*.price`).
- **Penyimpanan:** 
  - Simpan/sinkronkan data varian baru ke tabel `product_variants`.
  - Hapus varian lama yang tidak disertakan lagi saat proses edit produk.

### 3. Blade Frontend View (`resources/views/livewire/produk.blade.php`)
- **Tampilan Form Produk:**
  - Tambahkan tombol **"+ Tambah Varian"** di bagian bawah form produk.
  - Tampilkan list input varian dengan tombol hapus (icon tempat sampah) di setiap baris input varian dinamis.
- **Tabel Produk:**
  - Tampilkan jumlah varian yang dimiliki produk tersebut (misal: `3 Varian`).

---

> [!TIP]
> Saat mengimplementasikan form dinamis varian di Livewire, gunakan array `public $variants = [];` dan gunakan `wire:model` berindeks (seperti `wire:model="variants.0.name"`) untuk kemudahan pengelolaan *state* input dinamis.
